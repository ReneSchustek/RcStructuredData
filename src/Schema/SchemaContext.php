<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Unveränderliches Value Object mit allem, was die Provider zum Bau ihrer Knoten brauchen.
 *
 * Sämtliche Auflösung (übersetzte Werte, URLs, Locale) passiert einmalig im Subscriber;
 * die Provider rechnen nur noch zusammen, fragen aber selbst keine Daten mehr ab (TDA/SoC).
 */
final class SchemaContext
{
    public const TYPE_CATEGORY = 'category';
    public const TYPE_LANDINGPAGE = 'landingpage';
    public const TYPE_HOME = 'home';
    public const TYPE_BLOG = 'blog';

    /**
     * @param self::TYPE_* $pageType
     * @param list<array{name: string, value: string}> $additionalProperties
     * @param array{name: string, description: string, uploadDate: string, duration: string} $videoFallback
     * @param array{datePublished?: string, author?: string, image?: string, articleBody?: string} $article
     */
    public function __construct(
        private readonly string $pageType,
        private readonly string $name,
        private readonly string $description,
        private readonly string $pageUrl,
        private readonly string $baseUrl,
        private readonly string $localeCode,
        private readonly SalesChannelContext $salesChannelContext,
        private readonly array $additionalProperties = [],
        private readonly ?CategoryEntity $category = null,
        private readonly ?CmsPageEntity $cmsPage = null,
        private readonly array $videoFallback = ['name' => '', 'description' => '', 'uploadDate' => '', 'duration' => ''],
        private readonly array $article = [],
    ) {
    }

    public function getPageType(): string
    {
        return $this->pageType;
    }

    public function isCategory(): bool
    {
        return $this->pageType === self::TYPE_CATEGORY;
    }

    public function isLandingPage(): bool
    {
        return $this->pageType === self::TYPE_LANDINGPAGE;
    }

    public function isHome(): bool
    {
        return $this->pageType === self::TYPE_HOME;
    }

    public function isBlog(): bool
    {
        return $this->pageType === self::TYPE_BLOG;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * SEO-URL-Platzhalter der aktuellen Seite (wird im Response zur absoluten URL ersetzt).
     */
    public function getPageUrl(): string
    {
        return $this->pageUrl;
    }

    /**
     * SEO-URL-Platzhalter der Startseite — Basis für stabile "@id"-Fragmente.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    public function getAdditionalProperties(): array
    {
        return $this->additionalProperties;
    }

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    /**
     * Seiten-Fallback für VideoObject-Felder (aus den Category-/Landingpage-Custom-Fields).
     *
     * @return array{name: string, description: string, uploadDate: string, duration: string}
     */
    public function getVideoFallback(): array
    {
        return $this->videoFallback;
    }

    /**
     * Artikel-Metadaten für den BlogPosting-Knoten (nur bei TYPE_BLOG befüllt).
     *
     * @return array{datePublished?: string, author?: string, image?: string, articleBody?: string}
     */
    public function getArticle(): array
    {
        return $this->article;
    }
}
