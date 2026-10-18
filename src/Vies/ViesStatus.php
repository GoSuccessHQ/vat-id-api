<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Vies;

use GoSuccess\VatId\Support\Fields;

/**
 * Whether VIES itself is up, and each member state's service.
 */
final readonly class ViesStatus
{
    /**
     * @param list<CountryAvailability> $countries
     */
    public function __construct(
        public bool $available,
        public array $countries,
    ) {}

    /**
     * @internal
     */
    public static function fromFields(Fields $fields): self
    {
        return new self(
            $fields->nullableObject('vow')?->bool('available') ?? false,
            array_map(CountryAvailability::fromFields(...), $fields->objectList('countries')),
        );
    }
}
