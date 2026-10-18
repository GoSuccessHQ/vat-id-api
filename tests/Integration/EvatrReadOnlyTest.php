<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Tests\Integration;

use GoSuccess\VatId\Bzst\Company;
use GoSuccess\VatId\Bzst\Evatr;
use GoSuccess\VatId\Bzst\EvatrStatus;
use GoSuccess\VatId\Bzst\StatusMessage;
use GoSuccess\VatId\VatId;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Read-only calls to eVATR with the test numbers the BZSt documents
 * (requester DE123456789, requested ATU12345678) and one with the real
 * requester from VAT_ID_REQUESTER; skipped unless VAT_ID_LIVE=1. The requester
 * is never printed.
 */
#[CoversNothing]
final class EvatrReadOnlyTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('VAT_ID_LIVE') !== '1') {
            self::markTestSkipped('Set VAT_ID_LIVE=1 to call eVATR.');
        }
    }

    public function testDocumentedSimpleTestCall(): void
    {
        $confirmation = new Evatr(VatId::parse('DE123456789'))->confirm(VatId::parse('ATU12345678'));

        self::report('eVATR simple test call: ' . self::code($confirmation->status) . " (HTTP {$confirmation->httpStatus})");
        self::assertNotSame('', self::code($confirmation->status));
    }

    public function testDocumentedQualifiedTestCall(): void
    {
        $confirmation = new Evatr(VatId::parse('DE123456789'))->confirm(VatId::parse('ATU12345678'), new Company('Musterhaus GmbH & Co KG', 'musterort', 'Musterstrasse 22', '12345'));

        self::report('eVATR qualified test call: ' . self::code($confirmation->status) . " (HTTP {$confirmation->httpStatus}), comparisons "
            . implode('', array_map(static fn($comparison): string => $comparison->value ?? '-', [$confirmation->name, $confirmation->street, $confirmation->postalCode, $confirmation->city])));
        self::assertNotSame('', self::code($confirmation->status));
    }

    public function testRealRequester(): void
    {
        $requester = getenv('VAT_ID_REQUESTER');

        if (!\is_string($requester) || $requester === '') {
            self::markTestSkipped('Set VAT_ID_REQUESTER for the call with the real requester.');
        }

        $confirmation = new Evatr(VatId::parse($requester))->confirm(VatId::parse('ATU12345678'));

        self::report('eVATR with the real requester: ' . self::code($confirmation->status) . " (HTTP {$confirmation->httpStatus})");
        self::assertNotSame('', self::code($confirmation->status));
    }

    public function testStatusTextsMatchTheEnum(): void
    {
        $live = array_map(static fn(StatusMessage $message): array => [$message->status, $message->message], new Evatr()->statusMessages());
        $shipped = array_map(static fn(EvatrStatus $status): array => [$status->value, $status->message()], EvatrStatus::cases());

        self::assertSame($shipped, $live, 'eVATR changed its status messages; update EvatrStatus and the fixture.');
    }

    public function testMemberStates(): void
    {
        $states = new Evatr()->memberStates();

        self::assertGreaterThanOrEqual(27, \count($states));
        self::report('eVATR member states: ' . \count($states) . ', unavailable: ' . (implode(', ', array_map(static fn($state): string => $state->countryCode, array_filter($states, static fn($state): bool => !$state->available))) ?: 'none'));
    }

    private static function code(EvatrStatus|string $status): string
    {
        return $status instanceof EvatrStatus ? $status->value : $status;
    }

    private static function report(string $line): void
    {
        fwrite(\STDERR, "\n{$line}\n");
    }
}
