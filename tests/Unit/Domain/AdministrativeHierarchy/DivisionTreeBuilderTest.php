<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\AdministrativeHierarchy;

use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionRecord;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionTreeBuilder;
use PHPUnit\Framework\TestCase;

final class DivisionTreeBuilderTest extends TestCase
{
    private DivisionTreeBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new DivisionTreeBuilder();
    }

    public function testFlatRecordsAllBecomeRoots(): void
    {
        $tree = $this->builder->build([
            $this->record('A', 'Ikeja'),
            $this->record('B', 'Eti-Osa'),
        ]);

        self::assertCount(2, $tree);
        self::assertSame(['A', 'B'], array_map(static fn (DivisionNode $n): string => $n->code, $tree));
        self::assertSame([], $tree[0]->children);
    }

    public function testChildNestsUnderItsParentToArbitraryDepth(): void
    {
        $tree = $this->builder->build([
            $this->record('LGA', 'Ikeja'),
            $this->record('WARD', 'Ojodu', 'LGA'),
            $this->record('UNIT', 'Unit 1', 'WARD'),
        ]);

        self::assertCount(1, $tree, 'only the top-tier LGA is a root');
        $lga = $tree[0];
        self::assertSame('LGA', $lga->code);
        self::assertCount(1, $lga->children);

        $ward = $lga->children[0];
        self::assertSame('WARD', $ward->code);
        self::assertSame('LGA', $ward->parentCode);
        self::assertCount(1, $ward->children, 'depth 3 nests');
        self::assertSame('UNIT', $ward->children[0]->code);
    }

    public function testSiblingOrderIsPreserved(): void
    {
        $tree = $this->builder->build([
            $this->record('P', 'Parent'),
            $this->record('C2', 'Second', 'P'),
            $this->record('C1', 'First', 'P'),
        ]);

        self::assertSame(['C2', 'C1'], array_map(
            static fn (DivisionNode $n): string => $n->code,
            $tree[0]->children,
        ));
    }

    public function testOrphanWithMissingParentIsPromotedToRoot(): void
    {
        // Parent "GHOST" is not in the set — the child must still render.
        $tree = $this->builder->build([
            $this->record('ORPHAN', 'Orphan', 'GHOST'),
        ]);

        self::assertCount(1, $tree);
        self::assertSame('ORPHAN', $tree[0]->code);
    }

    public function testCycleDoesNotRecurseForever(): void
    {
        // A -> B -> A. The guard must break it and still return a finite tree.
        $tree = $this->builder->build([
            $this->record('A', 'A', 'B'),
            $this->record('B', 'B', 'A'),
        ]);

        // Both point at an existing parent, so neither is a root: a pure cycle
        // yields no roots, and — crucially — the call terminates.
        self::assertCount(0, $tree);
    }

    private function record(string $code, string $name, ?string $parentCode = null): DivisionRecord
    {
        return new DivisionRecord('NG', 'NG-LA', $code, $name, $parentCode);
    }
}
