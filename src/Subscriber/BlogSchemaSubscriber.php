<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Subscriber;

use DateTimeInterface;
use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaGraphStruct;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Werkl\OpenBlogware\Content\Blog\BlogEntryEntity;
use Werkl\OpenBlogware\Page\Blog\BlogPageLoadedEvent;

/**
 * Hängt den BlogPosting-@graph an WerklOpenBlogware-Blog-Detailseiten.
 *
 * Wird nur registriert, wenn WerklOpenBlogware installiert ist (bedingtes blog.xml-Load in
 * Plugin::build()). Die Werkl-spezifische Feldauflösung ist hier gekapselt; Kontext-Aufbau und
 * Graph-Bau sind delegiert (Subscriber = reine Verdrahtung, keine Schema-Logik).
 */
final class BlogSchemaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SchemaContextFactory $contextFactory,
        private readonly GraphBuilder $graphBuilder,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [BlogPageLoadedEvent::class => 'onBlogPageLoaded'];
    }

    public function onBlogPageLoaded(BlogPageLoadedEvent $event): void
    {
        $entry = $event->getPage()->getBlogEntry();
        if (!$entry instanceof BlogEntryEntity) {
            return;
        }

        $name = $this->firstNonEmpty($entry->getTitle(), $entry->getMetaTitle());
        if ($name === '') {
            return;
        }

        $context = $this->contextFactory->forBlog(
            $entry->getId(),
            $name,
            $this->firstNonEmpty($entry->getTeaser(), $entry->getMetaDescription()),
            $event->getSalesChannelContext()->getLanguageInfo()->localeCode,
            $event->getSalesChannelContext(),
            $this->resolveArticle($entry),
            $this->loadArticleCmsPage($event),
        );

        $graph = $this->graphBuilder->build($context);
        if ($graph === null) {
            return;
        }

        $event->getPage()->addExtension(SchemaGraphStruct::EXTENSION_NAME, new SchemaGraphStruct($graph, []));
    }

    /**
     * Lädt die dem Blog-Eintrag zugeordnete CMS-Seite (der Artikel-Inhalt mit etwaigem rc-faq-Block) —
     * nicht das globale Blog-Detail-Layout aus getPage()->getCmsPage(). Nur so wird ein FAQ-Block je
     * Artikel erkannt. Ohne zugeordnete Seite bleibt es beim reinen BlogPosting.
     */
    private function loadArticleCmsPage(BlogPageLoadedEvent $event): ?CmsPageEntity
    {
        $cmsPageId = $event->getPage()->getBlogEntry()?->getCmsPageId();
        if ($cmsPageId === null || $cmsPageId === '') {
            return null;
        }

        $cmsPage = $this->cmsPageLoader
            ->load($event->getRequest(), new Criteria([$cmsPageId]), $event->getSalesChannelContext())
            ->first();

        return $cmsPage instanceof CmsPageEntity ? $cmsPage : null;
    }

    /**
     * @return array{datePublished: string, author: string, image: string, articleBody: string}
     */
    private function resolveArticle(BlogEntryEntity $entry): array
    {
        return [
            'datePublished' => $entry->getPublishedAt()->format(DateTimeInterface::ATOM),
            'author' => $this->resolveAuthor($entry),
            'image' => $entry->getMedia()?->getUrl() ?? '',
            'articleBody' => $this->toPlainText($entry->getContent()),
        ];
    }

    private function resolveAuthor(BlogEntryEntity $entry): string
    {
        $author = $entry->getBlogAuthor();
        if ($author === null) {
            return '';
        }

        $displayName = $author->getDisplayName();
        if (\is_string($displayName) && trim($displayName) !== '') {
            return trim($displayName);
        }

        return trim($author->getFullName());
    }

    private function firstNonEmpty(?string ...$values): string
    {
        foreach ($values as $value) {
            if (\is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function toPlainText(?string $value): string
    {
        if (!\is_string($value) || $value === '') {
            return '';
        }

        $stripped = strip_tags($value);
        $collapsed = preg_replace('/\s+/u', ' ', $stripped) ?? $stripped;

        return trim($collapsed);
    }
}
