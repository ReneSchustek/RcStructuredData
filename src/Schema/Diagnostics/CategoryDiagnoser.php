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
    public function diagnose(string $identifier): DiagnoseResult;
}
