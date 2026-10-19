<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\OrganizationDetails;
use Ruhrcoder\RcStructuredData\Schema\PluginConfigReader;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Liefert den Organization-Singleton aus der Plugin-Konfiguration.
 *
 * Ist kein Name konfiguriert, wird auf den Shop-Namen zurückgegriffen, damit der Knoten
 * (und die daran hängenden Referenzen wie publisher) auch in einem normal eingerichteten Shop
 * ohne Zusatzkonfiguration funktioniert.
 */
final class OrganizationNodeProvider implements SchemaNodeProvider
{
    private const SHOP_EMAIL_KEY = 'core.basicInformation.email';

    /**
     * Liefergebiet des Kundendienstes. Fest, weil der Shop genau dorthin liefert und berät; eine Einstellung dafür
     * lohnte die Pflege nicht.
     */
    private const AREA_SERVED = ['DE', 'AT', 'CH'];

    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly IdFactory $idFactory,
        private readonly OrganizationDetails $details,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return $this->config()->organizationName($context->getSalesChannelContext()->getSalesChannelId()) !== '';
    }

    public function provide(SchemaContext $context): array
    {
        $node = [
            '@type' => 'Organization',
            '@id' => $this->idFactory->organization($context),
            'name' => $this->config()->organizationName($context->getSalesChannelContext()->getSalesChannelId()),
            'url' => $context->getBaseUrl(),
        ];

        $logo = $this->config()->string('organizationLogo', $context->getSalesChannelContext()->getSalesChannelId());
        if ($logo !== '') {
            $node['logo'] = $logo;
        }

        $contactPoint = $this->resolveContactPoint($context);
        if ($contactPoint !== null) {
            $node['contactPoint'] = $contactPoint;
        }

        $sameAs = $this->config()->lines('organizationSameAs', $context->getSalesChannelContext()->getSalesChannelId());
        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        return [[...$node, ...$this->details->forSalesChannel($context->getSalesChannelContext()->getSalesChannelId())]];
    }

    /**
     * Kundendienst mit Telefon (Einstellung LocalBusiness) und der Shop-E-Mail aus den Grundeinstellungen. Ohne beides
     * gibt es keinen Kontaktpunkt; ein leerer wäre für Suchmaschinen wertlos.
     *
     * @return array<string, mixed>|null
     */
    private function resolveContactPoint(SchemaContext $context): ?array
    {
        $salesChannelId = $context->getSalesChannelContext()->getSalesChannelId();
        $telephone = $this->details->telephone($salesChannelId);
        $email = trim($this->systemConfig->getString(self::SHOP_EMAIL_KEY, $salesChannelId));
        if ($telephone === '' && $email === '') {
            return null;
        }

        $contactPoint = ['@type' => 'ContactPoint', 'contactType' => 'customer service'];
        if ($telephone !== '') {
            $contactPoint['telephone'] = $telephone;
        }
        if ($email !== '') {
            $contactPoint['email'] = $email;
        }
        $contactPoint['availableLanguage'] = 'de';
        $contactPoint['areaServed'] = self::AREA_SERVED;

        return $contactPoint;
    }

    private function config(): PluginConfigReader
    {
        return new PluginConfigReader($this->systemConfig);
    }
}
