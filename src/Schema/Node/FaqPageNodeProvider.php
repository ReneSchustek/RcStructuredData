<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\FaqCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;

/**
 * Erzeugt aus den rc-faq-Slots der Seite einen FAQPage-Knoten.
 *
 * Mehrere rc-faq-Blöcke werden zu einer FAQPage zusammengeführt. Der GraphBuilder verknüpft sie
 * anschließend automatisch per `mainEntity` mit der CollectionPage/WebPage.
 */
final class FaqPageNodeProvider implements SchemaNodeProvider
{
    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
        private readonly FaqCmsBlockExtractor $extractor,
        private readonly IdFactory $idFactory,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return $context->getCmsPage() !== null;
    }

    public function provide(SchemaContext $context): array
    {
        $questions = $this->collectQuestions($context);
        if ($questions === []) {
            return [];
        }

        return [[
            '@type' => 'FAQPage',
            '@id' => $this->idFactory->faq($context),
            'mainEntity' => $questions,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectQuestions(SchemaContext $context): array
    {
        $questions = [];
        foreach ($this->slotIterator->slots($context->getCmsPage()) as $slot) {
            if (!$this->extractor->supports($slot)) {
                continue;
            }

            foreach ($this->extractor->extract($slot) as $item) {
                $questions[] = [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ];
            }
        }

        return $questions;
    }
}
