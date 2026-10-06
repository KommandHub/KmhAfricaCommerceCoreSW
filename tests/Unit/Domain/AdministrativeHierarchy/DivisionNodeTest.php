<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\AdministrativeHierarchy;

use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DivisionNode::class)]
class DivisionNodeTest extends TestCase
{
    public function testDepthCountsTheDeepestBranch(): void
    {
        $ward = new DivisionNode('NG-LA-IKEJA-OJODU', 'Ojodu', 'NG-LA-IKEJA');
        $forest = [
            new DivisionNode('NG-LA-IKEJA', 'Ikeja', null, [$ward]),
            new DivisionNode('NG-LA-ETIOSA', 'Eti-Osa'),
        ];

        static::assertSame(0, DivisionNode::depthOf([]));
        static::assertSame(1, DivisionNode::depthOf([$ward]));
        static::assertSame(2, DivisionNode::depthOf($forest));
    }
}
