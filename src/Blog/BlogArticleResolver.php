<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Blog;

use DateTimeInterface;
use Ruhrcoder\RcStructuredData\Schema\PlainText;
use Werkl\OpenBlogware\Content\Blog\BlogEntryEntity;

/**
 * Liest aus einem Blogbeitrag, was der BlogPosting-Knoten braucht: Überschrift mit Rückfall, Zusammenfassung, Daten,
 * Autor, Bild und Text ohne Auszeichnung.
 *
 * Nur registriert, wenn WerklOpenBlogware installiert ist; die Klasse verweist auf dessen Typen.
 */
final class BlogArticleResolver
{
    /** Titel, sonst Meta-Titel; leer, wenn der Beitrag keinen von beiden hat. */
    public function headline(BlogEntryEntity $entry): string
    {
        return PlainText::firstFilled($entry->getTitle(), $entry->getMetaTitle());
    }

    /** Anrisstext, sonst Meta-Beschreibung. */
    public function summary(BlogEntryEntity $entry): string
    {
        return PlainText::firstFilled($entry->getTeaser(), $entry->getMetaDescription());
    }

    /**
     * @return array{datePublished: string, dateModified: string, author: string, image: string, articleBody: string}
     */
    public function article(BlogEntryEntity $entry): array
    {
        return [
            'datePublished' => $entry->getPublishedAt()->format(DateTimeInterface::ATOM),
            'dateModified' => $this->dateModified($entry),
            'author' => $this->author($entry),
            'image' => $entry->getMedia()?->getUrl() ?? '',
            'articleBody' => PlainText::fromHtml($entry->getContent()),
        ];
    }

    /**
     * Ein nie bearbeiteter Beitrag hat kein Änderungsdatum. Dann gilt das Datum der Veröffentlichung, nicht das
     * heutige, wie es entsteht, wenn man `null` durch eine Datumsformatierung schickt; so macht es der Block von
     * WerklOpenBlogware.
     */
    private function dateModified(BlogEntryEntity $entry): string
    {
        return ($entry->getUpdatedAt() ?? $entry->getPublishedAt())->format(DateTimeInterface::ATOM);
    }

    private function author(BlogEntryEntity $entry): string
    {
        $author = $entry->getBlogAuthor();
        if ($author === null) {
            return '';
        }

        $displayName = $author->getDisplayName();
        if (\is_string($displayName) && trim($displayName) !== '') {
            return trim($displayName);
        }

        return trim($author->getFullName());
    }


}
