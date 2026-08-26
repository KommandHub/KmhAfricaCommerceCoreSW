<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * A currency to seed/correct, keyed by ISO 4217 code.
 *
 * {@see $decimals} is the standard's minor-unit precision (XOF/XAF = 0) and is
 * the field the importer corrects on an existing currency. {@see $name},
 * {@see $symbol} and {@see $factor} are used only when *creating* a missing
 * currency — an existing currency's live exchange {@see $factor} is a merchant
 * concern and is never overwritten by the importer.
 *
 * ponytail: seed {@see $factor} is a placeholder (1.0). Real exchange rates are
 * merchant data; the importer only guarantees correct precision, not rates.
 */
final class CurrencyRecord
{
    public readonly string $isoCode;

    public function __construct(
        string $isoCode,
        public readonly int $decimals,
        public readonly string $name,
        public readonly string $symbol,
        public readonly float $factor = 1.0,
    ) {
        $this->isoCode = strtoupper($isoCode);
    }
}
