<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\BlogPostingNodeProvider;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;

class BlogPostingNodeProviderTest extends TestCase
{
    use SchemaContextTrait;

    public function testSupportsOnlyBlogPages(): void
    {
        $provider = new BlogPostingNodeProvider(new IdFactory());

        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_BLOG)));
        static::assertFalse($provider->supports($this->makeContext(SchemaContext::TYPE_CATEGORY)));
    }

    public function testBuildsBlogPostingNode(): void
    {
        $provider = new BlogPostingNodeProvider(new IdFactory());
        $context = $this->makeContext(
            SchemaContext::TYPE_BLOG,
            'Edelstahl richtig montieren',
            'Kurzer Teaser',
            'https://shop.example/blog/montage',
            'https://shop.example/',
            'de-DE',
            article: [
                'datePublished' => '2026-05-01T10:00:00+02:00',
                'author' => 'René Schustek',
                'image' => 'https://shop.example/media/blog.png',
                'articleBody' => 'Der volle Text.',
            ],
        );

        $node = $provider->provide($context)[0];
        static::assertSame('BlogPosting', $node['@type']);
        static::assertSame('https://shop.example/blog/montage#article', $node['@id']);
        static::assertSame('Edelstahl richtig montieren', $node['headline']);
        static::assertSame('Kurzer Teaser', $node['description']);
        static::assertSame('2026-05-01T10:00:00+02:00', $node['datePublished']);
        static::assertSame(['@type' => 'Person', 'name' => 'René Schustek'], $node['author']);
        static::assertSame('https://shop.example/media/blog.png', $node['image']);
        static::assertSame('Der volle Text.', $node['articleBody']);
        static::assertSame(['@id' => 'https://shop.example/blog/montage'], $node['mainEntityOfPage']);
        static::assertSame(['@id' => 'https://shop.example/#/schema/organization/1'], $node['publisher']);
    }

    public function testOmitsMissingArticleFields(): void
    {
        $provider = new BlogPostingNodeProvider(new IdFactory());
        $context = $this->makeContext(SchemaContext::TYPE_BLOG, 'Nur Titel', '', 'https://shop.example/blog/x');

        $node = $provider->provide($context)[0];
        static::assertArrayNotHasKey('datePublished', $node);
        static::assertArrayNotHasKey('author', $node);
        static::assertArrayNotHasKey('image', $node);
        static::assertArrayNotHasKey('description', $node);
    }

    public function testReturnsNothingWithoutName(): void
    {
        $provider = new BlogPostingNodeProvider(new IdFactory());

        static::assertSame([], $provider->provide($this->makeContext(SchemaContext::TYPE_BLOG, '')));
    }
}
