<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Util;

/**
 * Canonical names for everything this plugin writes into shared Shopware
 * storage.
 *
 * Custom-field keys are global across the installation and are the lookup key
 * for stored data. Defining them here — and only here — means a rename is a
 * one-line change that the type system can follow, instead of a string hunt
 * that silently orphans existing rows.
 */
final class AfricaCommerceCoreConstants
{
    public const CUSTOM_FIELD_SET = 'kommandhub_africacommercecore_fieldset';

    /**
     * Merchant-override marker. When true on a reference entity (country,
     * currency, country_state), the reference importer treats the record as
     * merchant-owned and never touches it — see the Reconciler's SkipOverridden.
     */
    public const CUSTOM_FIELD_MERCHANT_OVERRIDE = 'kmh_af_merchant_override';
}
