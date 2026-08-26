<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Integration\AdministrativeHierarchy;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DalDivisionProvider;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * In-stack proof of the administrative-division hierarchy. Requires a booted,
 * plugin-aware Shopware kernel and database — runs under `make test`, not in the
 * framework-agnostic Domain suite. Wrapped in a rolled-back transaction by
 * IntegrationTestBehaviour, so it leaves no state behind.
 *
 * @group kernel
 */
class DivisionHierarchyTest extends TestCase
{
    use IntegrationTestBehaviour;
    use KernelTestBehaviour;

    public function testHierarchySeedsIdempotentlyAndProviderReturnsTree(): void
    {
        $config = $this->getContainer()->get(SystemConfigService::class);
        static::assertInstanceOf(SystemConfigService::class, $config);
        // Divisions only seed when the feature is on.
        $config->set('KmhAfricaCommerceCoreSW.config.administrativeHierarchyEnabled', true);

        $importer = $this->getContainer()->get(ReferenceImporter::class);
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
        $provider = $this->getContainer()->get(DalDivisionProvider::class);
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
