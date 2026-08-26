<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Phone;

/**
 * Normalizes phone numbers to E.164 for storage and national/international forms
 * for display. ITU-T E.164 via Google libphonenumber — no per-country dialling
 * rules in code.
 *
 * Warn-not-block by default: an unparseable number yields a result flagged
 * invalid with a warning, rather than an exception.
 *
 * Default implementation: LibPhoneNumberNormalizer (giggsey/libphonenumber-for-php).
 */
interface PhoneNormalizerInterface
{
    public function normalize(string $input, string $countryIso2): PhoneNumberResult;
}
