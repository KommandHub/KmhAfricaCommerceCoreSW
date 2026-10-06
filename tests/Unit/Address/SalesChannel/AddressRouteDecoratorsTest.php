<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Address\SalesChannel;

use Kommandhub\AfricaCommerceCore\Address\AddressDataWriter;
use Kommandhub\AfricaCommerceCore\Address\SalesChannel\KmhRegisterRoute;
use Kommandhub\AfricaCommerceCore\Address\SalesChannel\KmhUpsertAddressRoute;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * The two address-persistence decorators: core runs first and untouched, then
 * the additive fields are written for the right address — only when the
 * address feature is on.
 */
#[CoversClass(KmhUpsertAddressRoute::class)]
#[CoversClass(KmhRegisterRoute::class)]
#[UsesClass(AddressDataWriter::class)]
#[UsesClass(FeatureGate::class)]
#[UsesClass(Config::class)]
#[UsesClass(Feature::class)]
class AddressRouteDecoratorsTest extends TestCase
{
    private const BILLING_ID = '0190a0b0c0d0e0f00010203040506070';
    private const SHIPPING_ID = '0190a0b0c0d0e0f00010203040506071';

    private EntityRepository&MockObject $addressData;

    protected function setUp(): void
    {
        $this->addressData = $this->createMock(EntityRepository::class);
        $this->addressData->method('searchIds')->willReturn(
            new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
        );
    }

    public function testDecoratorsExposeTheCoreRoute(): void
    {
        $upsertInner = $this->createMock(AbstractUpsertAddressRoute::class);
        $registerInner = $this->createMock(AbstractRegisterRoute::class);

        static::assertSame($upsertInner, (new KmhUpsertAddressRoute($upsertInner, $this->writer(), $this->gate(true)))->getDecorated());
        static::assertSame($registerInner, (new KmhRegisterRoute($registerInner, $this->writer(), $this->gate(true)))->getDecorated());
    }

    public function testUpsertWritesForTheSavedAddress(): void
    {
        $this->expectWrites([self::BILLING_ID]);

        $this->upsertRoute(true)->upsert(null, new RequestDataBag(['kmhAfLandmark' => 'Tree']), $this->context(), new CustomerEntity());
    }

    public function testUpsertIsANoOpWhenTheFeatureIsOff(): void
    {
        $this->addressData->expects(static::never())->method('upsert');

        $this->upsertRoute(false)->upsert(null, new RequestDataBag(['kmhAfLandmark' => 'Tree']), $this->context(), new CustomerEntity());
    }

    public function testRegisterWritesBillingAndDistinctShipping(): void
    {
        $this->expectWrites([self::BILLING_ID, self::SHIPPING_ID]);

        $this->registerRoute(true, self::SHIPPING_ID)->register($this->registrationData(), $this->context());
    }

    public function testRegisterSkipsShippingWhenItIsTheBillingAddress(): void
    {
        $this->expectWrites([self::BILLING_ID]);

        $this->registerRoute(true, self::BILLING_ID)->register($this->registrationData(), $this->context());
    }

    public function testRegisterIsANoOpWhenTheFeatureIsOff(): void
    {
        $this->addressData->expects(static::never())->method('upsert');

        $this->registerRoute(false, self::SHIPPING_ID)->register($this->registrationData(), $this->context());
    }

    /**
     * @param list<string> $addressIds
     */
    private function expectWrites(array $addressIds): void
    {
        $written = [];
        $this->addressData->expects(static::exactly(\count($addressIds)))
            ->method('upsert')
            ->willReturnCallback(function (array $payload) use (&$written, $addressIds) {
                $written[] = $payload[0]['customerAddressId'];
                static::assertSame(\array_slice($addressIds, 0, \count($written)), $written);

                return $this->createStub(\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent::class);
            });
    }

    private function upsertRoute(bool $enabled): KmhUpsertAddressRoute
    {
        $address = new CustomerAddressEntity();
        $address->setId(self::BILLING_ID);

        $inner = $this->createMock(AbstractUpsertAddressRoute::class);
        $inner->expects(static::once())->method('upsert')->willReturn(new UpsertAddressRouteResponse($address));

        return new KmhUpsertAddressRoute($inner, $this->writer(), $this->gate($enabled));
    }

    private function registerRoute(bool $enabled, string $shippingId): KmhRegisterRoute
    {
        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddressId(self::BILLING_ID);
        $customer->setDefaultShippingAddressId($shippingId);

        $inner = $this->createMock(AbstractRegisterRoute::class);
        $inner->expects(static::once())->method('register')->willReturn(new CustomerResponse($customer));

        return new KmhRegisterRoute($inner, $this->writer(), $this->gate($enabled));
    }

    private function registrationData(): RequestDataBag
    {
        return new RequestDataBag([
            'billingAddress' => ['kmhAfLandmark' => 'Billing tree'],
            'shippingAddress' => ['kmhAfLandmark' => 'Shipping tree'],
        ]);
    }

    private function writer(): AddressDataWriter
    {
        return new AddressDataWriter($this->addressData, $this->createMock(EntityRepository::class));
    }

    private function gate(bool $enabled): FeatureGate
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getBool')->willReturn($enabled);

        return new FeatureGate(new Config($systemConfig));
    }

    private function context(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sc-id');
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }
}
