<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Block\Documents\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Trägt die Nachweise eines rc-documents-Slots ins Storefront-Template und in den Graphen.
 *
 * Enthalten sind nur Einträge, deren Datei gefunden wurde — mit ihrer Adresse. Die sichtbare Liste
 * und die `DigitalDocument`-Knoten lesen beide aus diesem Objekt und können deshalb nicht
 * auseinanderlaufen: Was nicht verlinkt wird, wird auch nicht ausgezeichnet.
 */
final class DocumentsStruct extends Struct
{
    /**
     * @param list<array{name: string, description: string, url: string, encodingFormat: string, datePublished: string, expires: string}> $documents
     */
    public function __construct(
        private readonly string $title = '',
        private readonly array $documents = [],
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return list<array{name: string, description: string, url: string, encodingFormat: string, datePublished: string, expires: string}>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function getApiAlias(): string
    {
        return 'rc_schema_documents';
    }
}
