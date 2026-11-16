<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Twig;

use Ruhrcoder\RcStructuredData\Schema\Product\ProductVideoObject;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `rc_schema_product_video(videoId, product)` für die Produktdaten: Die Vorlage kennt die Kennung des Videos aus dem
 * Reiter des Themes, die Angaben dazu kommen nur in PHP über die YouTube-Schnittstelle.
 */
final class ProductVideoExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductVideoObject $videoObject,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rc_schema_product_video', $this->videoObject->forProduct(...)),
        ];
    }
}
