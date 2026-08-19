<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\Navigation\NavigationPage;

/**
 * Baut aus einer geladenen Storefront-Seite den SchemaContext.
 *
 * Hier passiert die gesamte Auflösung (übersetzte Texte, Custom-Field-Overrides, SEO-URL-
 * Platzhalter, Locale) — die Subscriber bleiben dadurch reine Delegation, die Provider reine
 * Berechnung.
 */
final class SchemaContextFactory
{
    private const NAVIGATION_ROUTE = 'frontend.navigation.page';
    private const LANDING_ROUTE = 'frontend.landing.page';
    private const HOME_ROUTE = 'frontend.home.page';
    private const BLOG_DETAIL_ROUTE = 'werkl.frontend.blog.detail';

    private const FIELD_DESCRIPTION = 'rc_schema_description';
    private const FIELD_ADDITIONAL_PROPERTIES = 'rc_schema_additional_properties';

    private const FIELD_VIDEO_NAME = 'rc_schema_video_name';
    private const FIELD_VIDEO_DESCRIPTION = 'rc_schema_video_description';
    private const FIELD_VIDEO_UPLOAD_DATE = 'rc_schema_video_upload_date';
    private const FIELD_VIDEO_DURATION = 'rc_schema_video_duration';

    public function __construct(
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
    ) {
    }

    public function fromNavigationPage(NavigationPage $page, SalesChannelContext $context): ?SchemaContext
    {
        return $this->fromCategory($page->getCategory(), $page->getCmsPage(), $context);
    }

    /**
     * Baut den Kontext direkt aus einer Kategorie — genutzt vom Page-Subscriber und vom
     * Diagnose-Befehl (der keine Storefront-Seite lädt).
     */
    public function fromCategory(?CategoryEntity $category, ?CmsPageEntity $cmsPage, SalesChannelContext $context): ?SchemaContext
    {
        if ($category === null) {
            return null;
        }

        // Die Startseite ist technisch ebenfalls eine Navigation-Seite (Root-Kategorie) — sie
        // bekommt keine CollectionPage/Breadcrumb, sondern den Home-Kontext (Organization + WebSite
        // + WebPage als Homepage-Signale).
        if ($category->getId() === $context->getSalesChannel()->getNavigationCategoryId()) {
            return $this->fromHome($category, $context);
        }

        $customFields = $category->getCustomFields() ?? [];
        $pageUrl = $this->seoUrlReplacer->generate(self::NAVIGATION_ROUTE, ['navigationId' => $category->getId()]);

        return new SchemaContext(
            SchemaContext::TYPE_CATEGORY,
            $this->resolveName($category->getTranslation('name'), $category->getName()),
            $this->resolveDescription($customFields, $category->getTranslation('description'), $category->getDescription()),
            $pageUrl,
            $this->seoUrlReplacer->generate(self::HOME_ROUTE),
            $context->getLanguageInfo()->localeCode,
            $context,
            $this->resolveAdditionalProperties($customFields),
            $category,
            $cmsPage,
            $this->resolveVideoFallback($customFields),
        );
    }

    /**
     * Baut den Home-Kontext (Startseite): nur Homepage-Signale, ohne Category/CmsPage — dadurch
     * feuern Organization/WebSite (Singletons) und WebPage, aber keine CollectionPage/Breadcrumb/
     * FAQ/Video/ItemList.
     */
    private function fromHome(CategoryEntity $category, SalesChannelContext $context): SchemaContext
    {
        $homeUrl = $this->seoUrlReplacer->generate(self::HOME_ROUTE);

        return new SchemaContext(
            SchemaContext::TYPE_HOME,
            $this->resolveName($category->getTranslation('name'), $category->getName()),
            '',
            $homeUrl,
            $homeUrl,
            $context->getLanguageInfo()->localeCode,
            $context,
        );
    }

    /**
     * Baut den Blog-Kontext aus bereits aufgelösten Primitivwerten — dadurch bleibt die Factory
     * frei von WerklOpenBlogware-Typen (der Subscriber liefert die Werte).
     *
     * Die CmsPage wird durchgereicht, damit block-basierte Provider (FAQPage aus rc-faq, VideoObject)
     * auch auf Blog-Detailseiten greifen; ohne passende Blöcke entsteht kein zusätzlicher Knoten.
     *
     * @param array{datePublished?: string, author?: string, image?: string, articleBody?: string} $article
     */
    public function forBlog(
        string $articleId,
        string $name,
        string $description,
        string $localeCode,
        SalesChannelContext $context,
        array $article,
        ?CmsPageEntity $cmsPage = null,
    ): SchemaContext {
        return new SchemaContext(
            SchemaContext::TYPE_BLOG,
            $name,
            $this->toPlainText($description),
            $this->seoUrlReplacer->generate(self::BLOG_DETAIL_ROUTE, ['articleId' => $articleId]),
            $this->seoUrlReplacer->generate(self::HOME_ROUTE),
            $localeCode,
            $context,
            [],
            null,
            $cmsPage,
            ['name' => '', 'description' => '', 'uploadDate' => '', 'duration' => ''],
            $article,
        );
    }

    public function fromLandingPage(LandingPage $page, SalesChannelContext $context): ?SchemaContext
    {
        $landingPage = $page->getLandingPage();
        if ($landingPage === null) {
            return null;
        }

        return $this->fromLandingPageEntity($landingPage, $context);
    }

    /**
     * Baut den Kontext direkt aus einer Landingpage-Entity — genutzt vom Page-Subscriber (über
     * fromLandingPage) und vom Diagnose-Endpunkt (der keine Storefront-Seite lädt).
     */
    public function fromLandingPageEntity(LandingPageEntity $landingPage, SalesChannelContext $context): SchemaContext
    {
        $customFields = $landingPage->getCustomFields() ?? [];
        $pageUrl = $this->seoUrlReplacer->generate(self::LANDING_ROUTE, ['landingPageId' => $landingPage->getId()]);

        return new SchemaContext(
            SchemaContext::TYPE_LANDINGPAGE,
            $this->resolveName($landingPage->getTranslation('name'), $landingPage->getName()),
            $this->resolveDescription($customFields, null, null),
            $pageUrl,
            $this->seoUrlReplacer->generate(self::HOME_ROUTE),
            $context->getLanguageInfo()->localeCode,
            $context,
            $this->resolveAdditionalProperties($customFields),
            null,
            $landingPage->getCmsPage(),
            $this->resolveVideoFallback($customFields),
        );
    }

    private function resolveName(mixed $translated, ?string $fallback): string
    {
        if (\is_string($translated) && $translated !== '') {
            return $translated;
        }

        return trim((string) ($fallback ?? ''));
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function resolveDescription(array $customFields, mixed $translated, ?string $fallback): string
    {
        $override = $customFields[self::FIELD_DESCRIPTION] ?? null;
        if (\is_string($override) && trim($override) !== '') {
            return $this->toPlainText($override);
        }

        if (\is_string($translated) && $translated !== '') {
            return $this->toPlainText($translated);
        }

        return $this->toPlainText((string) ($fallback ?? ''));
    }

    /**
     * Schema-Beschreibungen sind Klartext — HTML aus der CMS-Pflege wird entfernt.
     */
    private function toPlainText(string $value): string
    {
        $stripped = strip_tags($value);
        $collapsed = preg_replace('/\s+/u', ' ', $stripped) ?? $stripped;

        return trim($collapsed);
    }

    /**
     * @param array<string, mixed> $customFields
     *
     * @return list<array{name: string, value: string}>
     */
    private function resolveAdditionalProperties(array $customFields): array
    {
        $raw = $customFields[self::FIELD_ADDITIONAL_PROPERTIES] ?? null;
        if (!\is_array($raw)) {
            return [];
        }

        $properties = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $name = $entry['name'] ?? null;
            $value = $entry['value'] ?? null;
            if (!\is_string($name) || !\is_string($value)) {
                continue;
            }

            $properties[] = ['name' => $name, 'value' => $value];
        }

        return $properties;
    }

    /**
     * Ein Videosatz je Seite als Fallback, wenn am youtube-video-Block keine Metadaten gepflegt sind.
     *
     * @param array<string, mixed> $customFields
     *
     * @return array{name: string, description: string, uploadDate: string, duration: string}
     */
    private function resolveVideoFallback(array $customFields): array
    {
        return [
            'name' => $this->stringField($customFields, self::FIELD_VIDEO_NAME),
            'description' => $this->stringField($customFields, self::FIELD_VIDEO_DESCRIPTION),
            'uploadDate' => $this->stringField($customFields, self::FIELD_VIDEO_UPLOAD_DATE),
            'duration' => $this->stringField($customFields, self::FIELD_VIDEO_DURATION),
        ];
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function stringField(array $customFields, string $key): string
    {
        $value = $customFields[$key] ?? null;

        return \is_string($value) ? trim($value) : '';
    }
}
