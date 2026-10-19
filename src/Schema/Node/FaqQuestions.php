<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

/**
 * Die `Question`-Knoten einer FAQPage, für den FAQ-Baustein und die Fragen auf der Produktseite gleich.
 */
final class FaqQuestions
{
    /**
     * @param list<array{question: string, answer: string}> $items
     *
     * @return list<array<string, mixed>>
     */
    public static function fromItems(array $items): array
    {
        return array_map(
            static fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ],
            $items,
        );
    }
}
