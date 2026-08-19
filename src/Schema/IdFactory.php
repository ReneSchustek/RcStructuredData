<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

/**
 * Erzeugt stabile, eindeutige "@id"-Werte für die Graph-Knoten.
 *
 * Singletons (Organization, WebSite) hängen an der Basis-URL und sind so seitenübergreifend
 * identisch — dadurch können alle Seiten denselben Knoten per "@id" referenzieren. Seiten-
 * gebundene Knoten (Breadcrumb, FAQ, Video) hängen an der jeweiligen Seiten-URL.
 */
final class IdFactory
{
    private const ORGANIZATION_FRAGMENT = '#/schema/organization/1';
    private const WEBSITE_FRAGMENT = '#/schema/website/1';
    private const LOCALBUSINESS_FRAGMENT = '#/schema/localbusiness/1';
    private const BREADCRUMB_FRAGMENT = '#breadcrumb';
    private const FAQ_FRAGMENT = '#faq';
    private const VIDEO_FRAGMENT = '#video';
    private const ITEMLIST_FRAGMENT = '#itemlist';
    private const BLOGPOSTING_FRAGMENT = '#article';

    public function organization(SchemaContext $context): string
    {
        return $context->getBaseUrl() . self::ORGANIZATION_FRAGMENT;
    }

    public function website(SchemaContext $context): string
    {
        return $context->getBaseUrl() . self::WEBSITE_FRAGMENT;
    }

    public function localBusiness(SchemaContext $context): string
    {
        return $context->getBaseUrl() . self::LOCALBUSINESS_FRAGMENT;
    }

    /**
     * Die Seite selbst wird über ihre URL identifiziert (Schema.org-Konvention).
     */
    public function page(SchemaContext $context): string
    {
        return $context->getPageUrl();
    }

    public function breadcrumb(SchemaContext $context): string
    {
        return $context->getPageUrl() . self::BREADCRUMB_FRAGMENT;
    }

    /**
     * Die Produktliste ist seitengebunden (paginierte Scheibe) und hängt an der Seiten-URL.
     */
    public function itemList(SchemaContext $context): string
    {
        return $context->getPageUrl() . self::ITEMLIST_FRAGMENT;
    }

    public function blogPosting(SchemaContext $context): string
    {
        return $context->getPageUrl() . self::BLOGPOSTING_FRAGMENT;
    }

    public function faq(SchemaContext $context): string
    {
        return $context->getPageUrl() . self::FAQ_FRAGMENT;
    }

    /**
     * Mehrere Videos je Seite erhalten fortlaufende, eindeutige Fragmente (#video-1, #video-2 ...).
     */
    public function video(SchemaContext $context, int $index): string
    {
        return $context->getPageUrl() . self::VIDEO_FRAGMENT . '-' . $index;
    }
}
