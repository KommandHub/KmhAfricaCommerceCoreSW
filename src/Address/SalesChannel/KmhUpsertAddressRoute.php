<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address\SalesChannel;

use Kommandhub\AfricaCommerceCore\Address\AddressDataWriter;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRouteResponse;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Persists the additive African address fields alongside a saved address.
 *
 * DECORATION SEAM (address persistence). Core's UpsertAddressRoute maps a fixed
 * whitelist of fields and ignores anything else on the databag, so the additive
 * fields — which live in the kmh_af_customer_address_data 1:1 aggregate, not on
 * customer_address — cannot ride along natively. This decorator lets the core
 * save run untouched, then writes the aggregate for the resulting address.
 * Gated by the address-extensions feature and a no-op when it is off.
 *
 * Covers the account address routes (prefix `address`). Registration is covered
 * by {@see KmhRegisterRoute}.
 */
class KmhUpsertAddressRoute extends AbstractUpsertAddressRoute
{
    public function __construct(
        private readonly AbstractUpsertAddressRoute $decorated,
        private readonly AddressDataWriter $addressDataWriter,
        private readonly FeatureGate $featureGate,
    ) {
    }

    public function getDecorated(): AbstractUpsertAddressRoute
    {
        return $this->decorated;
    }

    public function upsert(
        ?string $addressId,
        RequestDataBag $data,
        SalesChannelContext $context,
        CustomerEntity $customer,
    ): UpsertAddressRouteResponse {
        $response = $this->decorated->upsert($addressId, $data, $context, $customer);

        if ($this->featureGate->isEnabled(Feature::AddressExtensions, $context->getSalesChannelId())) {
            $this->addressDataWriter->write($response->getAddress()->getId(), $data, $context->getContext());
        }

        return $response;
    }
}
