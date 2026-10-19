<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Telefon und Rückgaberichtlinie der Organisation, für den eigenen Graphen und für den Organization-Knoten, den der Kern
 * auf Produktseiten ausgibt. Beide nennen damit dieselben Angaben.
 */
final class OrganizationDetails
{
    private const CONFIG_PREFIX = 'RcStructuredData.config.';

    /**
     * Rücksendekosten, wie sie in der Einstellung stehen, als Wert von Schema.org.
     */
    private const RETURN_FEES = [
        'free' => 'https://schema.org/FreeReturn',
        'customer' => 'https://schema.org/ReturnFeesCustomerResponsibility',
    ];

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    /**
     * `telephone` und `hasMerchantReturnPolicy`, soweit eingestellt.
     *
     * @return array<string, mixed>
     */
    public function forSalesChannel(?string $salesChannelId): array
    {
        $details = [];

        $telephone = $this->telephone($salesChannelId);
        if ($telephone !== '') {
            $details['telephone'] = $telephone;
        }

        $returnPolicy = $this->returnPolicy($salesChannelId);
        if ($returnPolicy !== null) {
            $details['hasMerchantReturnPolicy'] = $returnPolicy;
        }

        return $details;
    }

    public function telephone(?string $salesChannelId): string
    {
        return trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'localBusinessPhone', $salesChannelId));
    }

    /**
     * Die Rückgaberichtlinie, `null` ohne Frist oder ohne Land. Google verlangt Land, Art und bei einer Frist die Tage.
     *
     * @return array<string, mixed>|null
     */
    public function returnPolicy(?string $salesChannelId): ?array
    {
        $days = $this->systemConfig->getInt(self::CONFIG_PREFIX . 'returnPolicyDays', $salesChannelId);
        $countries = self::countries($this->systemConfig->getString(self::CONFIG_PREFIX . 'returnPolicyCountries', $salesChannelId));
        if ($days <= 0 || $countries === []) {
            return null;
        }

        $policy = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => \count($countries) === 1 ? $countries[0] : $countries,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => $days,
            'returnMethod' => 'https://schema.org/ReturnByMail',
        ];

        $fees = self::RETURN_FEES[$this->systemConfig->getString(self::CONFIG_PREFIX . 'returnPolicyFees', $salesChannelId)] ?? null;
        if ($fees !== null) {
            $policy['returnFees'] = $fees;
        }

        return $policy;
    }

    /**
     * Ländercodes aus einer Liste mit Kommas, Leerzeichen oder Zeilen; nur zweistellige Codes, groß geschrieben.
     *
     * @return list<string>
     */
    public static function countries(string $raw): array
    {
        $codes = array_map(static fn (string $code): string => strtoupper(trim($code)), preg_split('/[\s,;]+/', $raw) ?: []);

        return array_values(array_unique(array_filter($codes, static fn (string $code): bool => preg_match('/^[A-Z]{2}$/', $code) === 1)));
    }
}
