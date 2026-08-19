<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Command\DiagnoseCommand;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\CategoryDiagnoser;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\DiagnoseResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DiagnoseCommandTest extends TestCase
{
    public function testReportsCategoryNotFound(): void
    {
        $tester = $this->tester(DiagnoseResult::categoryNotFound());

        $exitCode = $tester->execute(['category' => 'Unbekannt']);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Keine Kategorie zu "Unbekannt"', $tester->getDisplay());
    }

    public function testReportsCompleteGraph(): void
    {
        $graph = ['@graph' => [['@type' => 'CollectionPage'], ['@type' => 'Organization']]];
        $tester = $this->tester(DiagnoseResult::analysed('Fittings', $graph, []));

        $exitCode = $tester->execute(['category' => 'Fittings']);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('2 Knoten im @graph', $tester->getDisplay());
        static::assertStringContainsString('Keine fehlenden Felder', $tester->getDisplay());
    }

    public function testListsMissingFields(): void
    {
        $graph = ['@graph' => [['@type' => 'VideoObject']]];
        $findings = [[
            'type' => 'VideoObject',
            'id' => 'https://shop.example/fittings#video-1',
            'missingRequired' => ['uploadDate'],
            'missingRecommended' => ['duration'],
        ]];
        $tester = $this->tester(DiagnoseResult::analysed('Fittings', $graph, $findings));

        $exitCode = $tester->execute(['category' => 'Fittings']);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('VideoObject', $tester->getDisplay());
        static::assertStringContainsString('uploadDate', $tester->getDisplay());
        static::assertStringContainsString('1 Knoten mit Lücken', $tester->getDisplay());
    }

    public function testWarnsWhenNoGraph(): void
    {
        $tester = $this->tester(DiagnoseResult::analysed('Startseite', null, []));

        $exitCode = $tester->execute(['category' => 'Startseite']);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('kein Graph', $tester->getDisplay());
    }

    private function tester(DiagnoseResult $result): CommandTester
    {
        $diagnoser = new class ($result) implements CategoryDiagnoser {
            public function __construct(private readonly DiagnoseResult $result)
            {
            }

            public function diagnose(string $identifier): DiagnoseResult
            {
                return $this->result;
            }
        };

        return new CommandTester(new DiagnoseCommand($diagnoser));
    }
}
