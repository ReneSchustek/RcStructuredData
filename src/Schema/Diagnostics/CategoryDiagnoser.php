<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

/**
 * Vertrag für die Kategorie-Diagnose.
 *
 * Entkoppelt den Konsolenbefehl von der DAL-Beschaffung im SchemaDiagnoser (DIP) und macht die
 * Befehl-Ein-/Ausgabe ohne Kernel-Boot testbar.
 */
interface CategoryDiagnoser
{
    /**
     * @param string|null $salesChannelId Kanal, in dessen Sicht geprüft wird; ohne Angabe der erste aktive Storefront-Kanal
     */
    public function diagnose(string $identifier, ?string $salesChannelId = null): DiagnoseResult;
}
