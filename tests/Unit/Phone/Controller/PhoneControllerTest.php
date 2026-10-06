<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Phone\Controller;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Phone\LibPhoneNumberNormalizer;
use Kommandhub\AfricaCommerceCore\Domain\Phone\PhoneNumberResult;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Phone\Controller\PhoneController;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PhoneController::class)]
#[UsesClass(LibPhoneNumberNormalizer::class)]
#[UsesClass(PhoneNumberResult::class)]
#[UsesClass(FeatureGate::class)]
#[UsesClass(Config::class)]
#[UsesClass(Feature::class)]
class PhoneControllerTest extends TestCase
{
    public function testNormalizesToE164(): void
    {
        $response = $this->normalize(true, ['number' => '0803 123 4567', 'countryIso' => 'NG']);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame([
            'e164' => '+2348031234567',
            'national' => '0803 123 4567',
            'international' => '+234 803 123 4567',
            'valid' => true,
            'warning' => null,
        ], json_decode((string)$response->getContent(), true));
    }

    public function testRequiresNumberAndCountry(): void
    {
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->normalize(true, ['number' => '0803'])->getStatusCode());
    }

    public function testIsNotFoundWhenTheFeatureIsOff(): void
    {
        static::assertSame(Response::HTTP_NOT_FOUND, $this->normalize(false, ['number' => '0803', 'countryIso' => 'NG'])->getStatusCode());
    }

    /**
     * @param array<string, string> $body
     */
    private function normalize(bool $enabled, array $body): JsonResponse
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getBool')->willReturn($enabled);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sc-id');

        return (new PhoneController(new LibPhoneNumberNormalizer(), new FeatureGate(new Config($systemConfig))))
            ->normalize(new Request([], $body), $context);
    }
}
