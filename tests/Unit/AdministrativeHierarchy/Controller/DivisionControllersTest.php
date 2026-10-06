<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\AdministrativeHierarchy\Controller;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\Controller\DivisionController;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\Controller\DivisionStorefrontController;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionProviderInterface;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;

#[CoversClass(DivisionController::class)]
#[CoversClass(DivisionStorefrontController::class)]
#[UsesClass(DivisionNode::class)]
#[UsesClass(FeatureGate::class)]
#[UsesClass(Config::class)]
#[UsesClass(Feature::class)]
class DivisionControllersTest extends TestCase
{
    public function testStoreApiReturnsTheNestedTreeWithItsDepth(): void
    {
        $body = $this->body((new DivisionController($this->provider(), $this->gate(true)))->list('ng', $this->context()));

        static::assertSame('NG', $body['countryIso']);
        static::assertSame(2, $body['maxDepth']);
        static::assertSame('NG-LA-IKEJA', $body['divisions'][0]['code']);
        static::assertSame('NG-LA-IKEJA', $body['divisions'][0]['children'][0]['parentCode']);
        static::assertSame([], $body['divisions'][0]['children'][0]['children']);
    }

    public function testStoreApiReturnsAnEmptyTreeWhenTheFeatureIsOff(): void
    {
        $provider = $this->createMock(DivisionProviderInterface::class);
        $provider->expects(static::never())->method('divisionsFor');

        $body = $this->body((new DivisionController($provider, $this->gate(false)))->list('ng', $this->context()));

        static::assertSame(['countryIso' => 'NG', 'maxDepth' => 0, 'divisions' => []], $body);
    }

    public function testStorefrontFlattensTheTreeWithIndentedNames(): void
    {
        $body = $this->body((new DivisionStorefrontController($this->provider(), $this->gate(true)))->byState('state-id', $this->context()));

        static::assertSame([
            ['code' => 'NG-LA-IKEJA', 'name' => 'Ikeja', 'depth' => 0],
            ['code' => 'NG-LA-IKEJA-OJODU', 'name' => '— Ojodu', 'depth' => 1],
        ], $body['divisions']);
    }

    public function testStorefrontReturnsNothingWhenTheFeatureIsOff(): void
    {
        $body = $this->body((new DivisionStorefrontController($this->provider(), $this->gate(false)))->byState('state-id', $this->context()));

        static::assertSame(['divisions' => []], $body);
    }

    private function provider(): DivisionProviderInterface
    {
        $tree = [new DivisionNode('NG-LA-IKEJA', 'Ikeja', null, [
            new DivisionNode('NG-LA-IKEJA-OJODU', 'Ojodu', 'NG-LA-IKEJA'),
        ])];

        $provider = $this->createMock(DivisionProviderInterface::class);
        // The shopper's language is passed through, so names come back localized.
        $provider->method('divisionsFor')->with(static::anything(), 'lang-id')->willReturn($tree);
        $provider->method('divisionsForState')->with(static::anything(), 'lang-id')->willReturn($tree);

        return $provider;
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
        $context->method('getLanguageId')->willReturn('lang-id');

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(JsonResponse $response): array
    {
        $body = json_decode((string)$response->getContent(), true);
        static::assertIsArray($body);

        return $body;
    }
}
