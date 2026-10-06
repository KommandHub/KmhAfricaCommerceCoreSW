<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Address\Subscriber;

use Kommandhub\AfricaCommerceCore\Address\DataAbstractionLayer\CustomerAddressAfricaDataEntity;
use Kommandhub\AfricaCommerceCore\Address\Subscriber\AddressPageSubscriber;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use Kommandhub\AfricaCommerceCore\Tests\Unit\Support\RepositoryStubTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPage;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AddressPageSubscriber::class)]
#[UsesClass(CustomerAddressAfricaDataEntity::class)]
#[UsesClass(FeatureGate::class)]
#[UsesClass(Config::class)]
#[UsesClass(Feature::class)]
class AddressPageSubscriberTest extends TestCase
{
    use RepositoryStubTrait;

    private const ADDRESS_ID = '0190a0b0c0d0e0f00010203040506070';

    public function testSubscribesToTheAddressDetailPage(): void
    {
        static::assertArrayHasKey(AddressDetailPageLoadedEvent::class, AddressPageSubscriber::getSubscribedEvents());
    }

    public function testAttachesTheStoredDataForTheEditForm(): void
    {
        $address = $this->address();
        $this->subscriber(true, [$this->data(self::ADDRESS_ID)], $writes)->onAddressDetailLoaded($this->event($address));

        static::assertInstanceOf(CustomerAddressAfricaDataEntity::class, $address->getExtension('kmhAfData'));
        static::assertContains('division', array_keys($writes['search'][0]->getAssociations()));
    }

    public function testLeavesTheAddressAloneWithoutStoredData(): void
    {
        $address = $this->address();
        $this->subscriber(true, [$this->data('ffffffffffffffffffffffffffffffff')])->onAddressDetailLoaded($this->event($address));

        static::assertNull($address->getExtension('kmhAfData'));
    }

    public function testSkipsWhenOffNewOrAlreadyLoaded(): void
    {
        // Feature off.
        $this->subscriber(false, [], $writes)->onAddressDetailLoaded($this->event($this->address()));
        static::assertSame([], $writes['search']);

        // New address form: no address yet.
        $this->subscriber(true, [], $writes)->onAddressDetailLoaded($this->event(null));
        static::assertSame([], $writes['search']);

        // Already loaded by someone else.
        $address = $this->address();
        $address->addExtension('kmhAfData', $this->data(self::ADDRESS_ID));
        $this->subscriber(true, [], $writes)->onAddressDetailLoaded($this->event($address));
        static::assertSame([], $writes['search']);
    }

    /**
     * @param list<CustomerAddressAfricaDataEntity> $rows
     * @param array<string, list<mixed>>|null $writes
     */
    private function subscriber(bool $enabled, array $rows, ?array &$writes = null): AddressPageSubscriber
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getBool')->willReturn($enabled);

        return new AddressPageSubscriber($this->repository($rows, $writes), new FeatureGate(new Config($systemConfig)));
    }

    private function event(?CustomerAddressEntity $address): AddressDetailPageLoadedEvent
    {
        $page = new AddressDetailPage();
        $page->setAddress($address);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sc-id');
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return new AddressDetailPageLoadedEvent($page, $context, new Request());
    }

    private function address(): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId(self::ADDRESS_ID);

        return $address;
    }

    private function data(string $addressId): CustomerAddressAfricaDataEntity
    {
        $data = new CustomerAddressAfricaDataEntity();
        $data->setId(md5($addressId));
        $data->setCustomerAddressId($addressId);

        return $data;
    }
}
