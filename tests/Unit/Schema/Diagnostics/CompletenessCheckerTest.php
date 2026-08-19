<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Diagnostics;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\CompletenessChecker;

class CompletenessCheckerTest extends TestCase
{
    public function testReportsMissingRequiredAndRecommendedForVideoObject(): void
    {
        $document = ['@graph' => [[
            '@type' => 'VideoObject',
            '@id' => 'https://shop.example/fittings#video-1',
            'name' => 'Titel',
            'thumbnailUrl' => 'https://img.youtube.com/vi/x/maxresdefault.jpg',
            'embedUrl' => 'https://www.youtube.com/embed/x',
            // description + uploadDate fehlen (Pflicht), duration + contentUrl fehlen (Empfehlung)
        ]]];

        $findings = (new CompletenessChecker())->check($document);

        static::assertCount(1, $findings);
        static::assertSame('VideoObject', $findings[0]['type']);
        static::assertSame(['description', 'uploadDate'], $findings[0]['missingRequired']);
        static::assertContains('duration', $findings[0]['missingRecommended']);
        static::assertContains('contentUrl', $findings[0]['missingRecommended']);
    }

    public function testNoFindingWhenAllFieldsPresent(): void
    {
        $document = ['@graph' => [[
            '@type' => 'VideoObject',
            '@id' => 'x',
            'name' => 'Titel',
            'description' => 'Text',
            'thumbnailUrl' => 'url',
            'uploadDate' => '2024-05-01',
            'duration' => 'PT1M',
            'contentUrl' => 'url',
            'embedUrl' => 'url',
        ]]];

        static::assertSame([], (new CompletenessChecker())->check($document));
    }

    public function testEmptyStringCountsAsMissing(): void
    {
        $document = ['@graph' => [[
            '@type' => 'Organization',
            '@id' => 'org',
            'name' => '   ',
            'url' => 'https://shop.example/',
        ]]];

        $findings = (new CompletenessChecker())->check($document);

        static::assertSame(['name'], $findings[0]['missingRequired']);
    }

    public function testUnknownTypesAreIgnored(): void
    {
        $document = ['@graph' => [['@type' => 'BreadcrumbList', '@id' => 'bc']]];

        static::assertSame([], (new CompletenessChecker())->check($document));
    }

    public function testDescribeProducesReadableLines(): void
    {
        $document = ['@graph' => [[
            '@type' => 'VideoObject',
            '@id' => 'https://shop.example/fittings#video-1',
            'name' => 'Titel',
            'thumbnailUrl' => 'url',
            'embedUrl' => 'url',
            'contentUrl' => 'url',
            'duration' => 'PT1M',
        ]]];

        $lines = (new CompletenessChecker())->describe($document);

        static::assertCount(1, $lines);
        static::assertStringContainsString('VideoObject', $lines[0]);
        static::assertStringContainsString('Pflicht: description, uploadDate', $lines[0]);
    }

    public function testHandlesMissingGraphKey(): void
    {
        static::assertSame([], (new CompletenessChecker())->check([]));
        static::assertSame([], (new CompletenessChecker())->describe([]));
    }
}
