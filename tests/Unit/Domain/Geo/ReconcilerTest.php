<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\Geo;

use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconcileAction;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Reconciler;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconciliationReport;
use PHPUnit\Framework\TestCase;

final class ReconcilerTest extends TestCase
{
    private Reconciler $reconciler;

    protected function setUp(): void
    {
        $this->reconciler = new Reconciler();
    }

    public function testAbsentRecordIsCreated(): void
    {
        self::assertSame(
            ReconcileAction::Create,
            $this->reconciler->decide(exists: false, overridden: false, matchesDesired: false),
        );
    }

    public function testStaleRecordIsUpdated(): void
    {
        self::assertSame(
            ReconcileAction::Update,
            $this->reconciler->decide(exists: true, overridden: false, matchesDesired: false),
        );
    }

    public function testMatchingRecordIsSkippedAsUpToDate(): void
    {
        self::assertSame(
            ReconcileAction::SkipUpToDate,
            $this->reconciler->decide(exists: true, overridden: false, matchesDesired: true),
        );
    }

    public function testMerchantOverrideIsNeverClobbered(): void
    {
        // Even when the record is stale (does not match desired), an override wins.
        self::assertSame(
            ReconcileAction::SkipOverridden,
            $this->reconciler->decide(exists: true, overridden: true, matchesDesired: false),
        );
    }

    public function testSecondRunConverges(): void
    {
        // First run: everything absent -> all created.
        $first = new ReconciliationReport();
        foreach (range(1, 5) as $ignored) {
            $first->record($this->reconciler->decide(false, false, false));
        }
        self::assertFalse($first->converged());
        self::assertSame(5, $first->created());

        // Second run: everything now matches -> nothing changes.
        $second = new ReconciliationReport();
        foreach (range(1, 5) as $ignored) {
            $second->record($this->reconciler->decide(true, false, true));
        }
        self::assertTrue($second->converged());
        self::assertSame(0, $second->created());
        self::assertSame(0, $second->updated());
        self::assertSame(5, $second->upToDate());
    }
}
