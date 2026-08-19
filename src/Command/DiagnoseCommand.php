<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Command;

use Ruhrcoder\RcStructuredData\Schema\Diagnostics\CategoryDiagnoser;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\DiagnoseResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Baut den Schema-Graphen einer Kategorie und listet fehlende Google-Felder je Knoten.
 *
 * Macht die Lücken vor dem Rich-Results-Test sichtbar, ohne die Storefront aufzurufen.
 * Die eigentliche Analyse liegt im SchemaDiagnoser — der Befehl ist reine Ein-/Ausgabe (SoC).
 */
#[AsCommand(
    name: 'rc-schema:diagnose',
    description: 'Zeigt fehlende Schema.org-Felder einer Kategorie (Name oder ID).',
)]
final class DiagnoseCommand extends Command
{
    public function __construct(
        private readonly CategoryDiagnoser $diagnoser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('category', InputArgument::REQUIRED, 'Kategorie-Name oder -ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = (string) $input->getArgument('category');

        $result = $this->diagnoser->diagnose($identifier);
        if (!$result->categoryFound) {
            $io->error(sprintf('Keine Kategorie zu "%s" gefunden.', $identifier));

            return Command::FAILURE;
        }

        $io->title(sprintf('Schema-Diagnose: %s', $result->categoryName));

        if ($result->graph === null) {
            $io->warning('Für diese Kategorie wird kein Graph erzeugt (z. B. Startseite/Root).');

            return Command::SUCCESS;
        }

        $io->text(sprintf('%d Knoten im @graph.', $result->nodeCount()));
        $this->renderWarnings($io, $result);
        $this->renderFindings($io, $result);

        return Command::SUCCESS;
    }

    /**
     * Die Hinweise stehen vor den Feld-Lücken: Eine Seite mit zwei Auszeichnungen ist das
     * schwerere Problem als ein fehlendes Empfehlungsfeld.
     */
    private function renderWarnings(SymfonyStyle $io, DiagnoseResult $result): void
    {
        foreach ($result->warnings as $warning) {
            $io->warning($warning['message']);
        }
    }

    private function renderFindings(SymfonyStyle $io, DiagnoseResult $result): void
    {
        if ($result->findings === []) {
            $io->success('Keine fehlenden Felder — alle bekannten Knoten sind vollständig.');

            return;
        }

        $rows = [];
        foreach ($result->findings as $finding) {
            $rows[] = [
                $finding['type'],
                $finding['id'],
                implode(', ', $finding['missingRequired']) ?: '–',
                implode(', ', $finding['missingRecommended']) ?: '–',
            ];
        }

        $io->table(['Typ', '@id', 'Fehlende Pflichtfelder', 'Fehlende Empfehlungen'], $rows);
        $io->warning(sprintf('%d Knoten mit Lücken.', \count($result->findings)));
    }
}
