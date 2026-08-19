<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Cms;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;

class CmsSlotIteratorTest extends TestCase
{
    use CmsFixtureTrait;

    public function testReturnsEmptyForNullPage(): void
    {
        static::assertSame([], (new CmsSlotIterator())->slots(null));
    }

    public function testFlattensAllSlots(): void
    {
        $page = $this->makeCmsPage(
            $this->makeSlot('text'),
            $this->makeSlot('rc-faq', []),
            $this->makeSlot('youtube-video'),
        );

        $slots = (new CmsSlotIterator())->slots($page);

        static::assertCount(3, $slots);
        $types = array_map(static fn ($slot) => $slot->getType(), $slots);
        static::assertSame(['text', 'rc-faq', 'youtube-video'], $types);
    }
}
