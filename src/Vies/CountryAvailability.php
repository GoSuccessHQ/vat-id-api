<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Vies;

use GoSuccess\VatId\Support\Fields;

/**
 * The availability of one member state's service; an availability this package
 * does not know counts as unavailable.
 */
final readonly class CountryAvailability
{
    public function __construct(
        public string $countryCode,
        public Availability $availability,
    ) {}

    /**
     * @internal
     */
    public static function fromFields(Fields $fields): self
    {
        return new self($fields->string('countryCode'), $fields->tolerantEnum('availability', Availability::class) ?? Availability::Unavailable);
    }
}
