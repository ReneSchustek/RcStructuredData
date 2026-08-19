<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;

/**
 * Erzeugt für Blog-Detailseiten einen BlogPosting-Knoten aus den bereits aufgelösten
 * Artikel-Metadaten des Kontexts.
 *
 * Bewusst ohne Kopplung an das Blog-Plugin: die Werte kommen als Primitive aus dem SchemaContext
 * (der Subscriber liest sie aus dem BlogEntry). Der GraphBuilder verknüpft den Knoten per
 * `mainEntity` mit der WebPage; `publisher` und `mainEntityOfPage` werden hier gesetzt.
 */
final class BlogPostingNodeProvider implements SchemaNodeProvider
{
    public function __construct(
        private readonly IdFactory $idFactory,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return $context->isBlog();
    }

    public function provide(SchemaContext $context): array
    {
        if ($context->getName() === '') {
            return [];
        }

        $article = $context->getArticle();

        $node = [
            '@type' => 'BlogPosting',
            '@id' => $this->idFactory->blogPosting($context),
            'headline' => $context->getName(),
            'url' => $context->getPageUrl(),
            'mainEntityOfPage' => ['@id' => $this->idFactory->page($context)],
            'inLanguage' => $context->getLocaleCode(),
            'publisher' => ['@id' => $this->idFactory->organization($context)],
        ];

        if ($context->getDescription() !== '') {
            $node['description'] = $context->getDescription();
        }

        $datePublished = $this->stringValue($article, 'datePublished');
        if ($datePublished !== '') {
            $node['datePublished'] = $datePublished;
        }

        $author = $this->stringValue($article, 'author');
        if ($author !== '') {
            $node['author'] = ['@type' => 'Person', 'name' => $author];
        }

        $image = $this->stringValue($article, 'image');
        if ($image !== '') {
            $node['image'] = $image;
        }

        $articleBody = $this->stringValue($article, 'articleBody');
        if ($articleBody !== '') {
            $node['articleBody'] = $articleBody;
        }

        return [$node];
    }

    /**
     * @param array<string, mixed> $article
     */
    private function stringValue(array $article, string $key): string
    {
        $value = $article[$key] ?? null;

        return \is_string($value) ? trim($value) : '';
    }
}
