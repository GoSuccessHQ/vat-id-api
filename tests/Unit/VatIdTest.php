<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Tests\Unit;

use GoSuccess\VatId\VatId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VatId::class)]
final class VatIdTest extends TestCase
{
    #[DataProvider('spellings')]
    public function testParsesAndNormalizes(string $input, string $country, string $number): void
    {
        $vatId = VatId::parse($input);

        self::assertSame($country, $vatId->countryCode);
        self::assertSame($number, $vatId->number);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function spellings(): iterable
    {
        yield 'spaces, lower case' => ['de 123 456 789', 'DE', '123456789'];
        yield 'dashes and dots' => ['DE-123.456.789', 'DE', '123456789'];
        yield 'austrian with U' => ['atu12345678', 'AT', 'U12345678'];
        yield 'surrounding whitespace' => ["  FR12345678901\n", 'FR', '12345678901'];
        yield 'greece as GR' => ['GR123456789', 'EL', '123456789'];
        yield 'greece as EL' => ['EL123456789', 'EL', '123456789'];
        yield 'northern ireland' => ['XI123456789', 'XI', '123456789'];
        yield 'non-breaking and thin spaces' => ["DE\u{00A0}123\u{202F}456\u{2009}789", 'DE', '123456789'];
        yield 'letters and symbols allowed' => ['NL123456789B01', 'NL', '123456789B01'];
    }

    #[DataProvider('invalid')]
    public function testRejects(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        VatId::parse($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => [''];
        yield 'only a country' => ['DE'];
        yield 'too short' => ['DE1'];
        yield 'too long' => ['DE1234567890123'];
        yield 'not an EU country' => ['US123456789'];
        yield 'united kingdom' => ['GB123456789'];
        yield 'other characters' => ['DE12345678!'];
        yield 'digits as country' => ['12345678901'];
    }

    public function testTheMessageDoesNotRepeatTheValue(): void
    {
        try {
            VatId::parse('US987654321');
            self::fail('No exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString('987654321', $exception->getMessage());
        }
    }

    public function testInvalidUtf8IsNamedAsSuch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UTF-8');

        VatId::parse("DE\xFF123456789");
    }

    public function testConstructsDirectlyWithTheSameRules(): void
    {
        self::assertSame('EL123456789', (string) new VatId('gr', '123456789'));

        $this->expectException(InvalidArgumentException::class);

        new VatId('DE', '1');
    }

    public function testStringForm(): void
    {
        self::assertSame('DE123456789', (string) VatId::parse('de 123456789'));
    }
}
