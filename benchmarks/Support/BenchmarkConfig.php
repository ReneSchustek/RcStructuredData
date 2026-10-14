<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Benchmarks\Support;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Die Einstellungen der Messung, ohne Datenbank: ein ungültiger YouTube-Schlüssel.
 *
 * Der Konstruktor der Elternklasse wird absichtlich nicht aufgerufen; der Abgleich liest nur
 * `getString`, und alles andere bräuchte einen gestarteten Kern.
 */
final class BenchmarkConfig extends SystemConfigService
{
    public function __construct()
    {
    }

    public function getString(string $key, ?string $salesChannelId = null): string
    {
        return 'invalid-key-for-benchmark';
    }
}
