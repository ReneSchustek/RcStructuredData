<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Block\Faq\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Trägt die Frage/Antwort-Paare eines rc-faq-Slots ins Storefront-Template.
 *
 * `displayMode` steuert die Darstellung (accordion|list); das FAQPage-Schema bleibt davon
 * unberührt (Anzeige und Schema stammen weiter aus derselben Quelle).
 */
final class FaqStruct extends Struct
{
    /**
     * @param list<array{question: string, answer: string}> $items
     */
    public function __construct(
        private readonly array $items = [],
        private readonly string $displayMode = 'accordion',
    ) {
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    public function getApiAlias(): string
    {
        return 'rc_schema_faq';
    }
}
