<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Block\Faq\DataResolver;

use Ruhrcoder\RcStructuredData\Block\Faq\Struct\FaqStruct;
use Ruhrcoder\RcStructuredData\RcStructuredData;
use Ruhrcoder\RcStructuredData\Schema\Extractor\FaqCmsBlockExtractor;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Registriert das rc-faq-CMS-Element und stellt die Frage/Antwort-Paare fürs Accordion bereit.
 *
 * Die Daten liegen vollständig in der Slot-Konfiguration (keine DB-Abfrage), daher ist collect()
 * leer. Das Parsing teilt sich der Resolver mit dem FaqPageNodeProvider über den Extractor —
 * sichtbarer Inhalt und FAQPage stammen so aus einer Quelle.
 */
final class FaqResolver extends AbstractCmsElementResolver
{
    private const DISPLAY_MODE_KEY = 'displayMode';
    private const DEFAULT_DISPLAY_MODE = 'accordion';

    /**
     * @var list<string>
     */
    private const ALLOWED_DISPLAY_MODES = ['accordion', 'list'];

    public function __construct(
        private readonly FaqCmsBlockExtractor $extractor,
    ) {
    }

    public function getType(): string
    {
        return RcStructuredData::CMS_ELEMENT_FAQ;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $slot->setData(new FaqStruct($this->extractor->extract($slot), $this->resolveDisplayMode($slot)));
    }

    /**
     * Unbekannte oder fehlende Werte fallen sicher auf das Accordion zurück.
     */
    private function resolveDisplayMode(CmsSlotEntity $slot): string
    {
        $value = $slot->getFieldConfig()->get(self::DISPLAY_MODE_KEY)?->getValue();

        return \is_string($value) && \in_array($value, self::ALLOWED_DISPLAY_MODES, true)
            ? $value
            : self::DEFAULT_DISPLAY_MODE;
    }
}
