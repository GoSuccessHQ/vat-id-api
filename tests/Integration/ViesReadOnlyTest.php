<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Tests\Integration;

use GoSuccess\VatId\VatId;
use GoSuccess\VatId\Vies\Vies;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Two read-only calls to VIES; skipped unless VAT_ID_LIVE=1.
 */
#[CoversNothing]
final class ViesReadOnlyTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('VAT_ID_LIVE') !== '1') {
            self::markTestSkipped('Set VAT_ID_LIVE=1 to call VIES.');
        }
    }

    public function testStatus(): void
    {
        $status = new Vies()->status();

        self::assertGreaterThanOrEqual(27, \count($status->countries));
        self::report('VIES status: available=' . var_export($status->available, true) . ', ' . \count($status->countries) . ' countries');
    }

    public function testChecksAGermanNumber(): void
    {
        $check = new Vies()->check(VatId::parse('DE123456789'));

        self::assertSame('DE', $check->countryCode);
        self::report('VIES check DE123456789: valid=' . var_export($check->valid, true));
    }

    private static function report(string $line): void
    {
        fwrite(\STDERR, "\n{$line}\n");
    }
}
