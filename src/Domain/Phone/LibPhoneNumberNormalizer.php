<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Phone;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Google libphonenumber implementation of the phone normalizer.
 *
 * Pure and framework-agnostic — depends only on the libphonenumber library. The
 * region hint is the ISO 3166-1 alpha-2 country, so a locally-dialled number
 * parses without its country code. There is no per-country dialling logic here:
 * the library carries the ITU-T E.164 metadata for every region.
 *
 * Warn, don't block: an unparseable or invalid number returns a result flagged
 * invalid with a warning, never an exception.
 */
final class LibPhoneNumberNormalizer implements PhoneNormalizerInterface
{
    public function normalize(string $input, string $countryIso2): PhoneNumberResult
    {
        $util = PhoneNumberUtil::getInstance();
        $region = strtoupper($countryIso2);

        try {
            $number = $util->parse($input, $region);
        } catch (NumberParseException $exception) {
            // Unparseable: keep the raw input for display, flag it, do not throw.
            return new PhoneNumberResult('', $input, $input, false, $exception->getMessage());
        }

        $valid = $util->isValidNumber($number);

        return new PhoneNumberResult(
            $util->format($number, PhoneNumberFormat::E164),
            $util->format($number, PhoneNumberFormat::NATIONAL),
            $util->format($number, PhoneNumberFormat::INTERNATIONAL),
            $valid,
            $valid ? null : sprintf('Number is not valid for region %s', $region),
        );
    }
}
