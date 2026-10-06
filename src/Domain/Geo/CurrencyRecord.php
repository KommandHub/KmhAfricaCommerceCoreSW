<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * A currency whose precision to correct, keyed by ISO 4217 code.
 *
 * {@see $decimals} is the standard's minor-unit precision (XOF/XAF = 0) and is
 * the only field the importer writes. Missing currencies are never created: a
 * currency needs a real exchange factor, which is merchant data, so creating one
 * with a placeholder would silently misprice every product shown in it.
 */
final class CurrencyRecord
{
    public readonly string $isoCode;

    public function __construct(
        string $isoCode,
        public readonly int $decimals,
    ) {
        $this->isoCode = strtoupper($isoCode);
    }
}
