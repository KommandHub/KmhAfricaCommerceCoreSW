<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Phone\Controller;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Phone\LibPhoneNumberNormalizer;
use Kommandhub\AfricaCommerceCore\Domain\Phone\PhoneNumberResult;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Phone\Controller\PhoneStorefrontController;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(PhoneStorefrontController::class)]
#[UsesClass(LibPhoneNumberNormalizer::class)]
#[UsesClass(PhoneNumberResult::class)]
#[UsesClass(FeatureGate::class)]
#[UsesClass(Config::class)]
#[UsesClass(Feature::class)]
class PhoneStorefrontControllerTest extends TestCase
{
    private const COUNTRY_ID = '0190a0b0c0d0e0f00010203040506070';

    /**
     * The shopper sees a translated warning, never libphonenumber's English text.
     */
    public function testInvalidNumberGetsTheTranslatedWarning(): void
    {
        $body = $this->normalize('12');

        static::assertFalse($body['valid']);
        static::assertSame('translated:kmhAf.phone.invalid', $body['warning']);
    }

    public function testValidNumberHasNoWarning(): void
    {
        $body = $this->normalize('08031234567');

        static::assertTrue($body['valid']);
        static::assertSame('+2348031234567', $body['e164']);
        static::assertNull($body['warning']);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(string $number): array
    {
        $country = new CountryEntity();
        $country->setId(self::COUNTRY_ID);
        $country->setIso('NG');

        $countries = $this->createMock(EntityRepository::class);
        $countries->method('search')->willReturn(new EntitySearchResult(
            'country',
            1,
            new CountryCollection([$country]),
            null,
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(),
            Context::createDefaultContext(),
        ));

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getBool')->willReturn(true);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'translated:' . $id);

        $container = new Container();
        $container->set('translator', $translator);

        $controller = new PhoneStorefrontController(new LibPhoneNumberNormalizer(), $countries, new FeatureGate(new Config($systemConfig)));
        $controller->setContainer($container);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sc-id');
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $response = $controller->normalize(
            new Request([], ['number' => $number, 'countryId' => self::COUNTRY_ID]),
            $context,
        );

        $body = json_decode((string)$response->getContent(), true);
        static::assertIsArray($body);

        return $body;
    }
}
