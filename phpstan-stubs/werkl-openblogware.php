<?php

// Symbol-Definitionen für die optionale WerklOpenBlogware-Integration.
// Nur für die statische Analyse (phpstan scanFiles) — zur Laufzeit stammen die Klassen aus dem
// installierten Blog-Plugin; der zugehörige Subscriber wird nur bedingt registriert (Plugin::build()).

namespace Werkl\OpenBlogware\Page\Blog {
    class BlogPageLoadedEvent extends \Shopware\Storefront\Page\PageLoadedEvent
    {
        public function getPage(): BlogPage
        {
        }
    }

    class BlogPage extends \Shopware\Storefront\Page\Page
    {
        public function getBlogEntry(): ?\Werkl\OpenBlogware\Content\Blog\BlogEntryEntity
        {
        }
    }
}

namespace Werkl\OpenBlogware\Content\Blog {
    class BlogEntryEntity
    {
        public function getId(): string
        {
        }

        public function getCmsPageId(): ?string
        {
        }

        public function getTitle(): ?string
        {
        }

        public function getMetaTitle(): ?string
        {
        }

        public function getTeaser(): ?string
        {
        }

        public function getMetaDescription(): ?string
        {
        }

        public function getContent(): ?string
        {
        }

        public function getPublishedAt(): \DateTimeInterface
        {
        }

        public function getBlogAuthor(): ?\Werkl\OpenBlogware\Content\BlogAuthor\BlogAuthorEntity
        {
        }

        public function getMedia(): ?\Shopware\Core\Content\Media\MediaEntity
        {
        }
    }
}

namespace Werkl\OpenBlogware\Content\BlogAuthor {
    class BlogAuthorEntity
    {
        public function getDisplayName(): ?string
        {
        }

        public function getFullName(): string
        {
        }
    }
}
