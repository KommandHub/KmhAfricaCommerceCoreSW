<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Phone;

/**
 * The outcome of normalizing a phone number: E.164 for storage plus display
 * forms. {@see $valid} is advisory — the default policy warns, it does not block.
 *
 * Produced by LibPhoneNumberNormalizer.
 */
final class PhoneNumberResult
{
    public function __construct(
        public readonly string $e164,
        public readonly string $national,
        public readonly string $international,
        public readonly bool $valid,
        public readonly ?string $warning = null,
    ) {
    }
}
