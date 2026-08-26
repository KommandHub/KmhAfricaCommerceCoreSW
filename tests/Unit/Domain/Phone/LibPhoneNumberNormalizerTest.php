<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\Phone;

use Kommandhub\AfricaCommerceCore\Domain\Phone\LibPhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;

final class LibPhoneNumberNormalizerTest extends TestCase
{
    private LibPhoneNumberNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new LibPhoneNumberNormalizer();
    }

    public function testLocallyDialledNumberNormalisesToE164(): void
    {
        $result = $this->normalizer->normalize('08031234567', 'NG');

        self::assertTrue($result->valid);
        self::assertSame('+2348031234567', $result->e164);
        self::assertStringContainsString('803', $result->national);
        self::assertStringStartsWith('+234', $result->international);
        self::assertNull($result->warning);
    }

    public function testInternationalInputParsesToSameE164(): void
    {
        $result = $this->normalizer->normalize('+234 803 123 4567', 'NG');

        self::assertTrue($result->valid);
        self::assertSame('+2348031234567', $result->e164);
    }

    public function testUnparseableInputWarnsInsteadOfThrowing(): void
    {
        $result = $this->normalizer->normalize('not a phone', 'NG');

        self::assertFalse($result->valid);
        self::assertSame('', $result->e164);
        self::assertNotNull($result->warning);
        // Raw input is preserved for display.
        self::assertSame('not a phone', $result->national);
    }

    public function testParseableButInvalidNumberIsFlaggedNotBlocked(): void
    {
        // Parses (right shape) but is not a valid NG number.
        $result = $this->normalizer->normalize('12345', 'NG');

        self::assertFalse($result->valid);
        self::assertNotNull($result->warning);
    }
}
