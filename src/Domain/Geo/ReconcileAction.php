<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * The outcome the reconciler chooses for one record. Idempotency lives here:
 * a record that already matches is SkipUpToDate, so a second run creates and
 * updates nothing. A merchant-owned record is SkipOverridden and never touched.
 * SkipMissing is an absent record the importer must not create on its own (a
 * currency needs a real exchange rate only the merchant can supply).
 */
enum ReconcileAction
{
    case Create;
    case Update;
    case SkipUpToDate;
    case SkipOverridden;
    case SkipMissing;
}
