<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Subscriber;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Blog\BlogArticleResolver;
use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaGraphStruct;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Ruhrcoder\RcStructuredData\Subscriber\BlogSchemaSubscriber;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Werkl\OpenBlogware\Content\Blog\BlogEntryEntity;
use Werkl\OpenBlogware\Content\BlogAuthor\BlogAuthorEntity;
use Werkl\OpenBlogware\Page\Blog\BlogPage;
use Werkl\OpenBlogware\Page\Blog\BlogPageLoadedEvent;

// Das Blog-Plugin ist keine Abhängigkeit; ohne es stehen seine Klassen nur als Stub für die statische Analyse bereit.
if (!class_exists(BlogPageLoadedEvent::class)) {
    require_once __DIR__ . '/../../../phpstan-stubs/werkl-openblogware.php';
}

/**
 * Prüft, welche Angaben ein Blogbeitrag an den Graphen gibt: Titel mit Rückfall, Autor, Änderungsdatum, Text ohne HTML
 * und die Erlebniswelt des Beitrags.
 */
#[CoversClass(BlogSchemaSubscriber::class)]
#[CoversClass(BlogArticleResolver::class)]
final class BlogSchemaSubscriberTest extends TestCase
{
    private const ENTRY_ID = '0190aa000000000000000000000000e1';
    private const CMS_PAGE_ID = '0190aa000000000000000000000000e2';

    private ?SchemaContext $captured = null;

    /**
     * Was: ein vollständiger Beitrag mit Anzeigename des Autors, Änderungsdatum, Bild, HTML-Text und Erlebniswelt.
     * Warum: Das sind die Angaben, die Google für einen BlogPosting erwartet.
     * Erwartet: Erweiterung an der Seite, im Kontext Anzeigename, Änderungsdatum, Bild, Text ohne Auszeichnung und die
     *           geladene Erlebniswelt.
     */
    public function testAFullEntryPassesAllArticleDetails(): void
    {
        $author = $this->createMock(BlogAuthorEntity::class);
        $author->method('getDisplayName')->willReturn(' Anna Muster ');
        $media = new MediaEntity();
        $media->setUrl('https://shop.example/media/titel.jpg');
        $entry = $this->entry(title: ' Geländer planen ', author: $author, updatedAt: new DateTimeImmutable('2026-10-02T08:00:00+00:00'), media: $media, cmsPageId: self::CMS_PAGE_ID);
        $cmsPage = new CmsPageEntity();
        $cmsPage->setId(self::CMS_PAGE_ID);
        $page = $this->page($entry);
        $meta = new MetaInformation();
        $meta->setAuthor('Anna-Maria Muster');
        $page->setMetaInformation($meta);

        $this->subscriber($cmsPage)->onBlogPageLoaded($this->event($page));

        self::assertSame('Anna Muster', $meta->getAuthor(), '`meta author` nennt den sichtbaren Namen wie das Markup.');
        self::assertInstanceOf(SchemaGraphStruct::class, $page->getExtension(SchemaGraphStruct::EXTENSION_NAME));
        self::assertNotNull($this->captured);
        self::assertSame('Geländer planen', $this->captured->getName());
        self::assertSame($cmsPage, $this->captured->getCmsPage());
        self::assertSame([
            'datePublished' => '2026-10-01T08:00:00+00:00',
            'dateModified' => '2026-10-02T08:00:00+00:00',
            'author' => 'Anna Muster',
            'image' => 'https://shop.example/media/titel.jpg',
            'articleBody' => 'Erst messen, dann bestellen.',
        ], $this->captured->getArticle());
    }

    /**
     * Was: ein Beitrag ohne Titel (nur Meta-Titel), ohne Änderungsdatum, mit Autor ohne Anzeigenamen, ohne Erlebniswelt.
     * Warum: Beiträge sind oft lückenhaft gepflegt; die Rückfälle füllen die Pflichtfelder.
     * Erwartet: Meta-Titel als Name, Veröffentlichung als Änderungsdatum, voller Name als Autor, keine Erlebniswelt.
     */
    public function testFallbacksForTitleDateAndAuthor(): void
    {
        $author = $this->createMock(BlogAuthorEntity::class);
        $author->method('getDisplayName')->willReturn('  ');
        $author->method('getFullName')->willReturn('Anna Muster');
        $page = $this->page($this->entry(title: '', metaTitle: 'Meta-Titel', author: $author));

        $this->subscriber(null)->onBlogPageLoaded($this->event($page));

        self::assertNotNull($this->captured);
        self::assertSame('Meta-Titel', $this->captured->getName());
        self::assertNull($this->captured->getCmsPage());
        $article = $this->captured->getArticle();
        self::assertSame('2026-10-01T08:00:00+00:00', $article['dateModified'] ?? null);
        self::assertSame('Anna Muster', $article['author'] ?? null);
        self::assertSame('', $article['image'] ?? null);
    }

    /**
     * Was: eine Seite ohne Beitrag, ein Beitrag ohne jeden Titel und ein Graph ohne Knoten.
     * Warum: Ein Artikel ohne Überschrift ist kein gültiger BlogPosting; ein leerer Graph erzeugte ein leeres Skript.
     * Erwartet: in allen drei Fällen keine Erweiterung.
     */
    public function testNoExtensionWithoutEntryTitleOrNodes(): void
    {
        $withoutEntry = $this->page(null);
        $withoutTitle = $this->page($this->entry(title: null));
        $withoutNodes = $this->page($this->entry());

        $this->subscriber(null)->onBlogPageLoaded($this->event($withoutEntry));
        $this->subscriber(null)->onBlogPageLoaded($this->event($withoutTitle));
        $this->subscriber(null, nodes: [])->onBlogPageLoaded($this->event($withoutNodes));

        self::assertFalse($withoutEntry->hasExtension(SchemaGraphStruct::EXTENSION_NAME));
        self::assertFalse($withoutTitle->hasExtension(SchemaGraphStruct::EXTENSION_NAME));
        self::assertFalse($withoutNodes->hasExtension(SchemaGraphStruct::EXTENSION_NAME));
        self::assertSame([BlogPageLoadedEvent::class => 'onBlogPageLoaded'], BlogSchemaSubscriber::getSubscribedEvents());
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function subscriber(?CmsPageEntity $cmsPage, array $nodes = [['@type' => 'BlogPosting', '@id' => 'https://shop.example/blog#article']]): BlogSchemaSubscriber
    {
        $seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->method('generate')->willReturn('https://shop.example/');

        $test = $this;
        $provider = new class ($nodes, static function (SchemaContext $context) use ($test): void {
            $test->captured = $context;
        }) implements SchemaNodeProvider {
            /**
             * @param list<array<string, mixed>>     $nodes
             * @param Closure(SchemaContext): void $capture
             */
            public function __construct(private readonly array $nodes, private readonly Closure $capture)
            {
            }

            public function supports(SchemaContext $context): bool
            {
                ($this->capture)($context);

                return true;
            }

            public function provide(SchemaContext $context): array
            {
                return $this->nodes;
            }
        };

        $cmsPageLoader = $this->createMock(SalesChannelCmsPageLoaderInterface::class);
        $cmsPageLoader->method('load')->willReturn(new EntitySearchResult(
            'cms_page',
            $cmsPage === null ? 0 : 1,
            new CmsPageCollection($cmsPage === null ? [] : [$cmsPage]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        return new BlogSchemaSubscriber(new SchemaContextFactory($seoUrlReplacer), new GraphBuilder([$provider]), $cmsPageLoader, new BlogArticleResolver());
    }

    private function entry(
        ?string $title = 'Geländer planen',
        ?string $metaTitle = null,
        ?BlogAuthorEntity $author = null,
        ?DateTimeImmutable $updatedAt = null,
        ?MediaEntity $media = null,
        ?string $cmsPageId = null,
    ): BlogEntryEntity {
        $entry = $this->createMock(BlogEntryEntity::class);
        $entry->method('getId')->willReturn(self::ENTRY_ID);
        $entry->method('getTitle')->willReturn($title);
        $entry->method('getMetaTitle')->willReturn($metaTitle);
        $entry->method('getTeaser')->willReturn('Kurz erklärt.');
        $entry->method('getContent')->willReturn("<p>Erst messen,\n  <strong>dann</strong> bestellen.</p>");
        $entry->method('getPublishedAt')->willReturn(new DateTimeImmutable('2026-10-01T08:00:00+00:00'));
        $entry->method('getUpdatedAt')->willReturn($updatedAt);
        $entry->method('getBlogAuthor')->willReturn($author);
        $entry->method('getMedia')->willReturn($media);
        $entry->method('getCmsPageId')->willReturn($cmsPageId);

        return $entry;
    }

    private function page(?BlogEntryEntity $entry): BlogPage
    {
        $page = $this->getMockBuilder(BlogPage::class)->onlyMethods(['getBlogEntry'])->getMock();
        $page->method('getBlogEntry')->willReturn($entry);

        return $page;
    }

    private function event(BlogPage $page): BlogPageLoadedEvent
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Deutsch', 'de-DE'));

        $event = $this->createMock(BlogPageLoadedEvent::class);
        $event->method('getPage')->willReturn($page);
        $event->method('getSalesChannelContext')->willReturn($context);
        $event->method('getRequest')->willReturn(new Request());

        return $event;
    }
}
