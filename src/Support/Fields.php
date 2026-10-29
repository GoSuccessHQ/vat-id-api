<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Support;

use BackedEnum;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GoSuccess\VatId\Exception\SerializationException;
use GoSuccess\VatId\Http\Response;

/**
 * Typed access to a decoded JSON answer. A failure names the path of the
 * field, never its value, and carries the response the answer came from.
 *
 * @internal
 */
final readonly class Fields
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private array $data,
        private string $path = '',
        private ?Response $response = null,
    ) {}

    /**
     * The fields of a decoded document, or a SerializationException when it is not an object.
     */
    public static function of(mixed $decoded, string $what, ?Response $response = null): self
    {
        return \is_array($decoded) && !array_is_list($decoded) ? new self($decoded, '', $response) : throw new SerializationException("The {$what} is not a JSON object.", response: $response);
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : throw $this->invalid($key, 'a string');
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return $value === null || \is_string($value) ? $value : throw $this->invalid($key, 'a string or null');
    }

    public function bool(string $key): bool
    {
        $value = $this->data[$key] ?? null;

        return \is_bool($value) ? $value : throw $this->invalid($key, 'a boolean');
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        return \is_int($value) ? $value : throw $this->invalid($key, 'an integer');
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return $value === null || \is_int($value) ? $value : throw $this->invalid($key, 'an integer or null');
    }

    public function nullableObject(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return \is_array($value) ? new self($value, $this->field($key), $this->response) : throw $this->invalid($key, 'an object');
    }

    /**
     * The items of a JSON array of objects; a missing field is an empty list.
     *
     * @return list<self>
     */
    public function objectList(string $key): array
    {
        $value = $this->data[$key] ?? [];

        if (!\is_array($value) || !array_is_list($value)) {
            throw $this->invalid($key, 'a list');
        }

        $items = [];

        foreach ($value as $index => $item) {
            $items[] = \is_array($item) ? new self($item, $this->field("{$key}[{$index}]"), $this->response) : throw $this->invalid("{$key}[{$index}]", 'an object');
        }

        return $items;
    }

    /**
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null null when the field is missing
     */
    public function nullableEnum(string $key, string $enum): ?BackedEnum
    {
        $value = $this->nullableString($key);

        return $value === null ? null : ($enum::tryFrom($value) ?? throw $this->invalid($key, 'one of the known values'));
    }

    /**
     * Like {@see nullableEnum()}, but a value this package does not know (also
     * an empty one) is null instead of an error: for secondary fields whose
     * unknown value must not make the whole answer unreadable.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public function tolerantEnum(string $key, string $enum): ?BackedEnum
    {
        $value = $this->nullableString($key);

        return $value === null ? null : $enum::tryFrom($value);
    }

    public function date(string $key): DateTimeImmutable
    {
        return $this->nullableDate($key) ?? throw $this->invalid($key, 'a date');
    }

    /**
     * An ISO 8601 date or date-time; fractions beyond microseconds (eVATR sends
     * nanoseconds) are cut, the offset is kept. A date without time is midnight UTC;
     * read it with `format('Y-m-d')`, not after a change of time zone.
     */
    public function nullableDate(string $key): ?DateTimeImmutable
    {
        $value = $this->nullableString($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2}))?$/D', $value) !== 1) {
            throw $this->invalid($key, 'a date');
        }

        try {
            return new DateTimeImmutable((string) preg_replace('/(\.\d{6})\d+/', '$1', $value), new DateTimeZone('UTC'));
        } catch (Exception) {
            throw $this->invalid($key, 'a date');
        }
    }

    private function field(string $key): string
    {
        return $this->path === '' ? $key : "{$this->path}.{$key}";
    }

    private function invalid(string $key, string $expected): SerializationException
    {
        return new SerializationException("Field {$this->field($key)} must be {$expected}.", response: $this->response);
    }
}
