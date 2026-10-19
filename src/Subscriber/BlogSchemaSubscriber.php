<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Subscriber;

use Ruhrcoder\RcStructuredData\Blog\BlogArticleResolver;
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
 * Plugin::build()). Die Felder des Beitrags liest der BlogArticleResolver, Kontext und Graph bauen
 * Fabrik und GraphBuilder; der Subscriber verbindet nur.
 */
final class BlogSchemaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SchemaContextFactory $contextFactory,
        private readonly GraphBuilder $graphBuilder,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly BlogArticleResolver $articleResolver,
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

        $name = $this->articleResolver->headline($entry);
        if ($name === '') {
            return;
        }

        $article = $this->articleResolver->article($entry);

        // Werkl setzt `meta author` aus Vor- und Nachnamen, die Seite zeigt den Anzeigenamen. Prüfwerkzeuge vergleichen
        // das Markup mit `meta author`; beide nennen deshalb den Namen, der sichtbar ist.
        if ($article['author'] !== '') {
            $event->getPage()->getMetaInformation()?->setAuthor($article['author']);
        }

        $context = $this->contextFactory->forBlog(
            $entry->getId(),
            $name,
            $this->articleResolver->summary($entry),
            $event->getSalesChannelContext()->getLanguageInfo()->localeCode,
            $event->getSalesChannelContext(),
            $article,
            $this->loadArticleCmsPage($event),
        );

        $graph = $this->graphBuilder->build($context);
        if ($graph === null) {
            return;
        }

        $event->getPage()->addExtension(SchemaGraphStruct::EXTENSION_NAME, new SchemaGraphStruct($graph, []));
    }

    /**
     * Lädt die dem Blog-Eintrag zugeordnete CMS-Seite (der Artikel-Inhalt mit etwaigem rc-faq-Block),
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
}
