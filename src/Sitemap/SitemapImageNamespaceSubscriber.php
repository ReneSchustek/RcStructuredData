<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Sitemap;

use Shopware\Core\Content\Sitemap\Event\SitemapFilterOpenTagEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Der Namensraum für `<image:image>` im Wurzelelement der Sitemap. Der Kern sieht dafür ein Ereignis vor.
 */
class SitemapImageNamespaceSubscriber implements EventSubscriberInterface
{
    public const NAMESPACE = 'http://www.google.com/schemas/sitemap-image/1.1';

    public static function getSubscribedEvents(): array
    {
        return [SitemapFilterOpenTagEvent::class => 'onOpenTag'];
    }

    public function onOpenTag(SitemapFilterOpenTagEvent $event): void
    {
        $event->addUrlsetNamespace('xmlns:image', self::NAMESPACE);
    }
}
