<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\Geo;

use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconcileAction;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconciliationReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReconciliationReport::class)]
class ReconciliationReportTest extends TestCase
{
    public function testMissingIsCountedButStillConverged(): void
    {
        $report = new ReconciliationReport();
        $report->record(ReconcileAction::SkipMissing);
        $report->record(ReconcileAction::SkipUpToDate);

        // A missing record is a merchant to-do, not a pending change: a re-run
        // changes nothing, so the import has still converged.
        $report->record(ReconcileAction::SkipOverridden);

        static::assertSame(1, $report->missing());
        static::assertSame(1, $report->overridden());
        static::assertTrue($report->converged());
        static::assertSame(
            ['created' => 0, 'updated' => 0, 'upToDate' => 1, 'overridden' => 1, 'missing' => 1],
            $report->toArray(),
        );
    }
}
