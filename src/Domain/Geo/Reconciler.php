<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * Pure upsert-by-key decision: given three facts about the current persisted
 * state of a record, choose what to do. No I/O, no entity types — the Shopware
 * adapter reads the DAL to compute the booleans and applies the result.
 *
 * The order of checks is the guarantee:
 *   1. absent               -> Create
 *   2. merchant-overridden  -> SkipOverridden   (never clobber, even if stale)
 *   3. already matches       -> SkipUpToDate      (idempotent no-op)
 *   4. otherwise             -> Update
 */
final class Reconciler
{
    public function decide(bool $exists, bool $overridden, bool $matchesDesired): ReconcileAction
    {
        if (!$exists) {
            return ReconcileAction::Create;
        }

        if ($overridden) {
            return ReconcileAction::SkipOverridden;
        }

        if ($matchesDesired) {
            return ReconcileAction::SkipUpToDate;
        }

        return ReconcileAction::Update;
    }
}
