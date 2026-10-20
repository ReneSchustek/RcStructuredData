<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Product;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ruhrcoder\RcProductFeedShippingExtension\Service\ShippingCostCalculatorService;
use Ruhrcoder\RcProductFeedShippingExtension\Struct\ShippingCalculationResult;
use Ruhrcoder\RcStructuredData\Schema\Product\RcProductFeedShippingRate;
use RuntimeException;

// Die Nachbildung der Fremdklassen liegt bewusst nicht im PSR-4-Namensraum dieses Plugins --
// sie trägt fremde Namen und lässt sich deshalb nur von Hand einbinden.
require_once __DIR__ . '/ForeignPluginStubs.php';

/**
 * Die Anbindung an den Warenstrom, und was passiert, wenn es ihn nicht gibt.
 *
 * `RcProductFeedShippingExtension` ist keine
 * Abhängigkeit dieses Plugins und liegt beim Testlauf nicht im Ladepfad. Die Nachbildung trägt
 * genau die Berührungsfläche, die der Adapter benutzt. Mehr würde eine Kopplung vortäuschen,
 * die es nicht gibt. Dieselben Symbole stehen als Stub für die statische Analyse bereit.
 */
final class RcProductFeedShippingRateTest extends TestCase
{
    /**
     * Was: Der Warenstrom ist nicht installiert.
     * Warum: Dieses Plugin muss ohne das andere unverändert laufen.
     */
    public function testWithoutTheFeedPluginThereIsNoRate(): void
    {
        $rate = new RcProductFeedShippingRate();

        self::assertNull($rate->rateFor('produkt-1', 'DE', 'kanal-1'));
    }

    public function testItPassesThroughACalculatedRate(): void
    {
        $rate = new RcProductFeedShippingRate($this->calculator(
            new ShippingCalculationResult('produkt-1', 'DE', 8.93, 'EUR', false)
        ));

        self::assertSame(['amount' => 8.93, 'currency' => 'EUR'], $rate->rateFor('produkt-1', 'DE', 'kanal-1'));
    }

    /**
     * Was: Der Warenstrom liefert einen Ersatzwert.
     * Warum: Ein Ersatzwert ist geraten. Er stammt aus der Voreinstellung des Kanals, nicht
     *        aus der Berechnung für dieses Produkt. Als Angabe an Google wäre er eine Zusage über
     *        einen Preis, den der Shop so nicht ermittelt hat.
     */
    public function testAFallbackRateIsNotHandedToGoogle(): void
    {
        $rate = new RcProductFeedShippingRate($this->calculator(
            new ShippingCalculationResult('produkt-1', 'DE', 7.95, 'EUR', true)
        ));

        self::assertNull($rate->rateFor('produkt-1', 'DE', 'kanal-1'));
    }

    public function testNothingCachedMeansNoRate(): void
    {
        $rate = new RcProductFeedShippingRate($this->calculator(null));

        self::assertNull($rate->rateFor('produkt-1', 'DE', 'kanal-1'));
    }

    /**
     * Was: Der fremde Dienst wirft.
     * Warum: Die strukturierten Daten sind ein Zusatz. Ein Fehler im anderen Plugin darf die
     *        Produktseite nicht mit einem 500 abreißen.
     * Erwartet: kein Preis, eine Warnung im Protokoll.
     */
    public function testAFailingLookupDoesNotBreakThePage(): void
    {
        $calculator = new class () extends ShippingCostCalculatorService {
            public function lookupCached(
                string $productId,
                string $countryIso,
                string $salesChannelId,
            ): ?ShippingCalculationResult {
                throw new RuntimeException('Warenstrom kaputt');
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::anything(),
            self::callback(static fn (array $context): bool =>
    $context['productId'] === 'produkt-1' &&
    $context['exception'] === RuntimeException::class),
        );

        // Kein Serverfehler, aber protokolliert, damit ein dauerhafter Ausfall auffällt.
        self::assertNull((new RcProductFeedShippingRate($calculator, $logger))->rateFor('produkt-1', 'DE', 'kanal-1'));
    }

    private function calculator(?ShippingCalculationResult $result): ShippingCostCalculatorService
    {
        return new class ($result) extends ShippingCostCalculatorService {
            public function __construct(private readonly ?ShippingCalculationResult $result)
            {
            }

            public function lookupCached(
                string $productId,
                string $countryIso,
                string $salesChannelId,
            ): ?ShippingCalculationResult {
                return $this->result;
            }
        };
    }
}
