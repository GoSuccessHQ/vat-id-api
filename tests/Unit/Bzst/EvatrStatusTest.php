<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Tests\Unit\Bzst;

use GoSuccess\VatId\Bzst\EvatrStatus;
use GoSuccess\VatId\Bzst\StatusCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EvatrStatus::class)]
final class EvatrStatusTest extends TestCase
{
    public function testSnapshotMatchesTheEnum(): void
    {
        $snapshot = json_decode((string) file_get_contents(\dirname(__DIR__, 3) . '/resources/specs/evatr-statusmeldungen.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($snapshot);
        $categories = ['Ergebnis' => StatusCategory::Result, 'Hinweis' => StatusCategory::Notice, 'Fehler' => StatusCategory::Error];

        self::assertSame(array_column($snapshot, 'status'), array_map(static fn(EvatrStatus $status): string => $status->value, EvatrStatus::cases()));

        foreach ($snapshot as $entry) {
            self::assertIsArray($entry);
            self::assertIsString($entry['status']);
            self::assertIsString($entry['kategorie']);
            $status = EvatrStatus::from($entry['status']);
            self::assertSame($categories[$entry['kategorie']], $status->category(), $status->value);
            self::assertSame($entry['meldung'], $status->message(), $status->value);
            self::assertSame($entry['feld'] ?? null, $status->field(), $status->value);
            self::assertSame($entry['httpcode'] ?? null, $status->httpStatus(), $status->value);
        }
    }

    public function testEveryShippedStatusHasCategoryAndMessage(): void
    {
        foreach (EvatrStatus::cases() as $status) {
            self::assertNotSame(StatusCategory::Unknown, $status->category());
            self::assertNotSame('', $status->message());
        }
    }
}
