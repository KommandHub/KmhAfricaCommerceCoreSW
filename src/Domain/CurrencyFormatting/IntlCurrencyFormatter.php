<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\CurrencyFormatting;

/**
 * ext-intl (ICU/CLDR) implementation of the currency formatter.
 *
 * Pure and framework-agnostic — it depends only on ext-intl, never Shopware. The
 * caller-supplied {@see $fractionDigits} drives both the minor->major conversion
 * and the displayed precision, so a zero-decimal currency shows no fraction part
 * and a three-decimal one shows three. No per-country / per-currency branch.
 */
final class IntlCurrencyFormatter implements CurrencyFormatterInterface
{
    public function format(int $minorAmount, int $fractionDigits, string $currencyIso, string $locale): string
    {
        $digits = max(0, $fractionDigits);

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        // Pin the precision to the currency's own decimals rather than ICU's
        // locale default (which is 2 for every currency).
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $digits);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $digits);

        $major = $minorAmount / (10 ** $digits);

        $formatted = $formatter->formatCurrency($major, strtoupper($currencyIso));

        if ($formatted === false) {
            throw new \RuntimeException(sprintf(
                'Failed to format %d %s for locale "%s": %s',
                $minorAmount,
                $currencyIso,
                $locale,
                $formatter->getErrorMessage(),
            ));
        }

        return $formatted;
    }
}
