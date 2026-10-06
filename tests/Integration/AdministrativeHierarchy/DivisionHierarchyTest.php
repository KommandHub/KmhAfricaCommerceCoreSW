<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Integration\AdministrativeHierarchy;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DalDivisionProvider;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use Kommandhub\AfricaCommerceCore\Tests\Integration\PluginKernelTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * In-stack proof of the administrative-division hierarchy. Requires a booted,
 * plugin-aware Shopware kernel and database — runs under `make test`, not in the
 * framework-agnostic Domain suite. Wrapped in a rolled-back transaction, so it
 * leaves no state behind.
 */
#[Group('kernel')]
#[CoversNothing]
class DivisionHierarchyTest extends TestCase
{
    use PluginKernelTrait;

    public function testHierarchySeedsIdempotentlyAndProviderReturnsTree(): void
    {
        $config = $this->service(SystemConfigService::class);
        static::assertInstanceOf(SystemConfigService::class, $config);
        // Divisions only seed when the feature is on.
        $config->set('KmhAfricaCommerceCoreSW.config.administrativeHierarchyEnabled', true);

        $importer = $this->service(ReferenceImporter::class);
        static::assertInstanceOf(ReferenceImporter::class, $importer);

        $context = Context::createDefaultContext();

        // First run seeds the shipped Lagos divisions.
        $first = $importer->import($context);
        static::assertGreaterThan(0, $first['divisions']->created());

        // Second run changes nothing — idempotent.
        $second = $importer->import($context);
        static::assertSame(0, $second['divisions']->created());
        static::assertSame(0, $second['divisions']->updated());
        static::assertTrue($second['divisions']->converged());

        // The provider rebuilds the tree from the DAL.
        $provider = $this->service(DalDivisionProvider::class);
        static::assertInstanceOf(DalDivisionProvider::class, $provider);

        $tree = $provider->divisionsFor('NG');

        $ikeja = $this->find($tree, 'NG-LA-IKEJA');
        static::assertNotNull($ikeja, 'Ikeja LGA is a top-tier root');
        static::assertCount(2, $ikeja->children, 'Ikeja has two wards below it');

        // Two tiers below the state (LGA -> ward).
        static::assertSame(2, $provider->maxDepth('NG'));
    }

    /**
     * @param list<DivisionNode> $nodes
     */
    private function find(array $nodes, string $code): ?DivisionNode
    {
        foreach ($nodes as $node) {
            if ($node->code === $code) {
                return $node;
            }

            $found = $this->find($node->children, $code);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
