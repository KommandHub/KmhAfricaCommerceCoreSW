<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\CurrencyFormatting;

use Kommandhub\AfricaCommerceCore\Domain\CurrencyFormatting\IntlCurrencyFormatter;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('intl')]
final class IntlCurrencyFormatterTest extends TestCase
{
    private IntlCurrencyFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new IntlCurrencyFormatter();
    }

    public function testTwoDecimalCurrencyFormatsWithCentsFromMinorUnits(): void
    {
        // 123456 minor (cents) -> 1,234.56 major.
        $out = $this->formatter->format(123456, 2, 'USD', 'en_US');

        self::assertStringContainsString('1,234.56', $out);
        self::assertStringContainsString('$', $out);
    }

    public function testZeroDecimalCurrencyShowsNoFractionPart(): void
    {
        // XOF has 0 minor units: 150000 minor == 150000 major.
        $out = $this->formatter->format(150000, 0, 'XOF', 'en_US');

        self::assertStringContainsString('150,000', $out);
        self::assertStringNotContainsString('.', $out, 'zero-decimal currency must have no fraction');
    }

    public function testThreeDecimalCurrencyShowsThreeFractionDigits(): void
    {
        // TND has 3 minor units: 1234 minor == 1.234 major.
        $out = $this->formatter->format(1234, 3, 'TND', 'en_US');

        self::assertStringContainsString('1.234', $out);
    }

    public function testZeroAmountFormats(): void
    {
        self::assertStringContainsString('0.00', $this->formatter->format(0, 2, 'USD', 'en_US'));
    }

    public function testNegativeFractionDigitsAreClampedToZero(): void
    {
        $out = $this->formatter->format(150000, -1, 'XOF', 'en_US');

        self::assertStringContainsString('150,000', $out);
        self::assertStringNotContainsString('.', $out);
    }

    public function testUnformattableInputThrowsWithContext(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to format 100');

        // Invalid UTF-8 as the currency code makes ICU refuse to format.
        $this->formatter->format(100, 2, "\xff\xfe", 'en_US');
    }
}
