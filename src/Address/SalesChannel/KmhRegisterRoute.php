<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address\SalesChannel;

use Kommandhub\AfricaCommerceCore\Address\AddressDataWriter;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Persists the additive African address fields for the addresses created at
 * registration (billing and, when distinct, shipping).
 *
 * Same decoration seam as {@see KmhUpsertAddressRoute} — registration goes
 * through a different route, so it is covered here. The core register runs
 * untouched; afterwards the submitted billing/shipping sub-bags are written to
 * the aggregate for the customer's new default addresses.
 */
class KmhRegisterRoute extends AbstractRegisterRoute
{
    public function __construct(
        private readonly AbstractRegisterRoute $decorated,
        private readonly AddressDataWriter $addressDataWriter,
        private readonly FeatureGate $featureGate,
    ) {
    }

    public function getDecorated(): AbstractRegisterRoute
    {
        return $this->decorated;
    }

    public function register(
        RequestDataBag $data,
        SalesChannelContext $context,
        bool $validateStorefrontUrl = true,
        ?DataValidationDefinition $additionalValidationDefinitions = null,
    ): CustomerResponse {
        $response = $this->decorated->register($data, $context, $validateStorefrontUrl, $additionalValidationDefinitions);

        if (!$this->featureGate->isEnabled(Feature::AddressExtensions, $context->getSalesChannelId())) {
            return $response;
        }

        $customer = $response->getCustomer();
        $billingAddressId = $customer->getDefaultBillingAddressId();
        $shippingAddressId = $customer->getDefaultShippingAddressId();

        $billing = $data->get('billingAddress');

        if ($billing instanceof RequestDataBag) {
            $this->addressDataWriter->write($billingAddressId, $billing, $context->getContext());
        }

        // Only when a distinct shipping address was submitted.
        $shipping = $data->get('shippingAddress');

        if ($shipping instanceof RequestDataBag && $shippingAddressId !== $billingAddressId) {
            $this->addressDataWriter->write($shippingAddressId, $shipping, $context->getContext());
        }

        return $response;
    }
}
