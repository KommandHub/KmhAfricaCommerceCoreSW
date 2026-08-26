<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * Tally of reconcile outcomes, so a caller can prove idempotency: after a
 * converged run, created + updated are both zero. Skipped-overridden counts the
 * merchant records that were protected.
 */
final class ReconciliationReport
{
    private int $created = 0;
    private int $updated = 0;
    private int $upToDate = 0;
    private int $overridden = 0;

    public function record(ReconcileAction $action): void
    {
        match ($action) {
            ReconcileAction::Create => $this->created++,
            ReconcileAction::Update => $this->updated++,
            ReconcileAction::SkipUpToDate => $this->upToDate++,
            ReconcileAction::SkipOverridden => $this->overridden++,
        };
    }

    public function created(): int
    {
        return $this->created;
    }

    public function updated(): int
    {
        return $this->updated;
    }

    public function upToDate(): int
    {
        return $this->upToDate;
    }

    public function overridden(): int
    {
        return $this->overridden;
    }

    /** True when the run changed nothing — the idempotency signal. */
    public function converged(): bool
    {
        return $this->created === 0 && $this->updated === 0;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'upToDate' => $this->upToDate,
            'overridden' => $this->overridden,
        ];
    }
}
