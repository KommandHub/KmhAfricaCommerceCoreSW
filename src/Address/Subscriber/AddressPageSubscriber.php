<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address\Subscriber;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Pre-fills the additive address fields on the account address edit form.
 *
 * The aggregate is a 1:1 association that the address page does not load by
 * default, so the form would render blank on edit. Here it is loaded for the
 * edited address and attached as the `kmhAfData` extension the Twig reads.
 * Gated by the address feature.
 */
class AddressPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $addressDataRepository,
        private readonly FeatureGate $featureGate,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AddressDetailPageLoadedEvent::class => 'onAddressDetailLoaded',
        ];
    }

    public function onAddressDetailLoaded(AddressDetailPageLoadedEvent $event): void
    {
        if (!$this->featureGate->isEnabled(Feature::AddressExtensions, $event->getSalesChannelContext()->getSalesChannelId())) {
            return;
        }

        $address = $event->getPage()->getAddress();

        if ($address === null || $address->getExtension('kmhAfData') !== null) {
            return;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customerAddressId', $address->getId()))
            ->addAssociation('division')
            ->setLimit(1);

        $data = $this->addressDataRepository->search($criteria, $event->getContext())->first();

        if ($data !== null) {
            $address->addExtension('kmhAfData', $data);
        }
    }
}
