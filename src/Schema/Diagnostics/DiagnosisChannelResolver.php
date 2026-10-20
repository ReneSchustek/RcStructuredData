<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

use RuntimeException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Der Verkaufskanal, in dessen Sicht die Diagnose eine Seite aufbaut. Ein genannter Kanal gilt, wenn er aktiv ist;
 * ohne Angabe der erste aktive Storefront-Kanal, sonst irgendein aktiver. In einem Shop mit mehreren Kanälen
 * unterscheiden sich URLs, Sprache und Einstellungen, deshalb lässt sich der Kanal ausdrücklich wählen.
 */
final class DiagnosisChannelResolver
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    public function contextFor(?string $salesChannelId = null): SalesChannelContext
    {
        $salesChannel = $salesChannelId === null ? $this->defaultChannel() : $this->namedChannel($salesChannelId);

        // Die Default-Sprache des Kanals ist garantiert in seiner Sprachliste, anders als die System-Sprache, die der
        // Context-Factory-Default sonst annimmt.
        return $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannel->getId(),
            [SalesChannelContextService::LANGUAGE_ID => $salesChannel->getLanguageId()],
        );
    }

    private function namedChannel(string $salesChannelId): SalesChannelEntity
    {
        $salesChannel = Uuid::isValid($salesChannelId) ? $this->first(new Criteria([$salesChannelId]), true) : null;
        if ($salesChannel === null) {
            throw new RuntimeException(\sprintf('Kein aktiver Verkaufskanal mit der Kennung "%s".', $salesChannelId));
        }

        return $salesChannel;
    }

    private function defaultChannel(): SalesChannelEntity
    {
        $storefront = new Criteria();
        $storefront->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $salesChannel = $this->first($storefront, true) ?? $this->first(new Criteria(), true);
        if ($salesChannel === null) {
            throw new RuntimeException('Kein aktiver Sales-Channel gefunden.');
        }

        return $salesChannel;
    }

    private function first(Criteria $criteria, bool $activeOnly): ?SalesChannelEntity
    {
        if ($activeOnly) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }
        $criteria->setLimit(1);

        $salesChannel = $this->salesChannelRepository->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first();

        return $salesChannel instanceof SalesChannelEntity ? $salesChannel : null;
    }
}
