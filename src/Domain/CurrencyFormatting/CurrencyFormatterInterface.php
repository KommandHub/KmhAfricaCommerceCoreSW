<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\CurrencyFormatting;

/**
 * Formats a minor-unit amount for a currency and locale via Unicode CLDR/ICU.
 *
 * The amount is in minor units and the caller passes the currency's own decimal
 * precision, so zero-decimal (XOF/XAF) and three-decimal (TND) currencies format
 * correctly, never against a hardcoded 2. This is a bridge over ext-intl's
 * NumberFormatter, not a new formatting engine — the documented formatting seam.
 *
 * Default implementation: IntlCurrencyFormatter.
 */
interface CurrencyFormatterInterface
{
    /**
     * @param int $minorAmount amount in minor units (e.g. kobo, cents)
     * @param int $fractionDigits the currency's decimal places (NGN 2, XOF 0, TND 3)
     * @param string $currencyIso ISO 4217 code
     * @param string $locale BCP 47 / ICU locale, e.g. "en-NG"
     */
    public function format(int $minorAmount, int $fractionDigits, string $currencyIso, string $locale): string;
}
