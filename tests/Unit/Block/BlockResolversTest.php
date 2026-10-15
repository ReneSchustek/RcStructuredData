<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Block;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Block\DefinedTerms\DataResolver\DefinedTermsResolver;
use Ruhrcoder\RcStructuredData\Block\DefinedTerms\Struct\DefinedTermsStruct;
use Ruhrcoder\RcStructuredData\Block\Documents\DataResolver\DocumentsResolver;
use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsSlotHydrator;
use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsStructBuilder;
use Ruhrcoder\RcStructuredData\Block\Documents\Struct\DocumentsStruct;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DefinedTermsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\StaticRepositoryTrait;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * Prüft die Anbindung der Bausteine „Begriffe“ und „Dokumente“ an die Erlebniswelt: was der Kern laden soll und welche
 * Daten der Baustein danach trägt.
 */
#[CoversClass(DocumentsResolver::class)]
#[CoversClass(DefinedTermsResolver::class)]
#[CoversClass(DocumentsSlotHydrator::class)]
final class BlockResolversTest extends TestCase
{
    use StaticRepositoryTrait;
    use CmsFixtureTrait;

    private const PDF_ID = '0190a1b2c3d4e5f60718293a4b5c6d7e';

    /**
     * Was: ein Dokumente-Baustein mit einer Datei, Laden und Anreichern über den Kern.
     * Warum: Die Medien kommen gebündelt mit allen anderen Abfragen der Seite; der Schlüssel muss je Baustein eindeutig
     *        sein.
     * Erwartet: eine Abfrage für genau diese Datei, danach der Baustein mit ihrer Adresse.
     */
    public function testTheDocumentsResolverLoadsTheFilesOfItsSlot(): void
    {
        $resolver = $this->documentsResolver();
        $slot = $this->makeDocumentsSlot([['name' => 'Prüfzeugnis', 'mediaId' => self::PDF_ID]]);

        $criteria = $resolver->collect($slot, $this->createMock(ResolverContext::class));
        self::assertNotNull($criteria);
        $key = 'rc_documents_media_' . $slot->getUniqueIdentifier();
        $collected = $criteria->all()[MediaDefinition::class][$key] ?? null;
        self::assertInstanceOf(Criteria::class, $collected);
        self::assertSame([self::PDF_ID], $collected->getIds());

        $result = new ElementDataCollection();
        $result->add($key, new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$this->makeMedia(self::PDF_ID, 'https://shop.example/media/abp.pdf')]),
            null,
            $collected,
            Context::createDefaultContext(),
        ));
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), $result);

        $data = $slot->getData();
        self::assertInstanceOf(DocumentsStruct::class, $data);
        self::assertSame('https://shop.example/media/abp.pdf', $data->getDocuments()[0]['url']);
        self::assertSame('rc-documents', $resolver->getType());
    }

    /**
     * Was: ein Dokumente-Baustein ohne Dateien.
     * Warum: Eine Abfrage ohne Kennungen wäre nicht leer, sondern uneingeschränkt.
     * Erwartet: keine Abfrage.
     */
    public function testTheDocumentsResolverAsksForNothingWithoutFiles(): void
    {
        self::assertNull($this->documentsResolver()->collect($this->makeDocumentsSlot(), $this->createMock(ResolverContext::class)));
    }

    /**
     * Was: ein Begriffe-Baustein mit Name, Erläuterung und zwei Begriffen.
     * Warum: Der Baustein braucht keine Abfrage, nur die aufbereiteten Einstellungen.
     * Erwartet: keine Abfrage, danach Name, Erläuterung und Begriffe am Baustein.
     */
    public function testTheDefinedTermsResolverCopiesTheSettings(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);
        $resolver = new DefinedTermsResolver(new DefinedTermsCmsBlockExtractor($sanitizer));
        $slot = $this->makeDefinedTermsSlot('Glossar', [
            ['term' => 'Handlauf', 'definition' => 'Das, woran man sich festhält.'],
            ['term' => 'Pfosten', 'definition' => 'Trägt den Handlauf.'],
        ], 'Begriffe rund ums Geländer');

        self::assertNull($resolver->collect($slot, $this->createMock(ResolverContext::class)));
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(DefinedTermsStruct::class, $data);
        self::assertSame('Glossar', $data->getName());
        self::assertSame('Begriffe rund ums Geländer', $data->getDescription());
        self::assertSame(['Handlauf', 'Pfosten'], array_column($data->getTerms(), 'term'));
        self::assertSame('rc-defined-terms', $resolver->getType());
    }

    /**
     * Was: eine Seite mit zwei Dokumente-Bausteinen, die dieselbe Datei nennen, und einem fremden Baustein.
     * Warum: Seiten außerhalb der Erlebniswelt-Auflösung (etwa ein Blogbeitrag) brauchen die Dateien trotzdem, und
     *        zwar mit einer einzigen Abfrage.
     * Erwartet: eine Abfrage mit der Datei einmal, beide Bausteine mit Daten, der fremde unverändert.
     */
    public function testTheHydratorLoadsAllFilesOfAPageAtOnce(): void
    {
        $first = $this->makeDocumentsSlot([['name' => 'Prüfzeugnis', 'mediaId' => self::PDF_ID]]);
        $second = $this->makeDocumentsSlot([['name' => 'Prüfzeugnis', 'mediaId' => self::PDF_ID]]);
        $other = $this->makeSlot('rc-faq');
        $media = new MediaCollection([$this->makeMedia(self::PDF_ID, 'https://shop.example/media/abp.pdf')]);
        $repository = self::repository(MediaCollection::class, [
            static function (Criteria $criteria) use ($media): MediaCollection {
                self::assertSame([self::PDF_ID], $criteria->getIds());

                return $media;
            },
        ]);

        $this->hydrator($repository)->hydrate($this->makeCmsPage($first, $other, $second), Context::createDefaultContext());

        self::assertInstanceOf(DocumentsStruct::class, $first->getData());
        self::assertInstanceOf(DocumentsStruct::class, $second->getData());
        self::assertNull($other->getData());
        self::assertSame([], $repository->searches, 'Genau eine Abfrage');
    }

    /**
     * Was: eine Seite ohne Dokumente-Baustein und eine mit einem Baustein ohne Dateien.
     * Warum: Ohne Baustein gibt es nichts zu tun, ohne Dateien nichts zu laden.
     * Erwartet: keine Abfrage; der leere Baustein bekommt trotzdem seine (leeren) Daten.
     */
    public function testTheHydratorQueriesNothingWithoutFiles(): void
    {
        $repository = self::repository(MediaCollection::class);
        $empty = $this->makeDocumentsSlot([], 'Nachweise');

        $this->hydrator($repository)->hydrate(null, Context::createDefaultContext());
        $this->hydrator($repository)->hydrate($this->makeCmsPage($empty), Context::createDefaultContext());

        $data = $empty->getData();
        self::assertInstanceOf(DocumentsStruct::class, $data);
        self::assertSame([], $data->getDocuments());
    }

    private function documentsResolver(): DocumentsResolver
    {
        return new DocumentsResolver(new DocumentsCmsBlockExtractor(), new DocumentsStructBuilder(new DocumentsCmsBlockExtractor()));
    }

    /**
     * @param StaticEntityRepository<MediaCollection> $repository
     */
    private function hydrator(StaticEntityRepository $repository): DocumentsSlotHydrator
    {
        return new DocumentsSlotHydrator(
            new CmsSlotIterator(),
            new DocumentsCmsBlockExtractor(),
            new DocumentsStructBuilder(new DocumentsCmsBlockExtractor()),
            $repository,
        );
    }
}
