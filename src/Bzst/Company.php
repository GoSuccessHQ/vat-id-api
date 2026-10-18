<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

use InvalidArgumentException;

/**
 * The company details of a qualified confirmation. Name and city are required
 * for eVATR to run a qualified confirmation; street and postal code are optional.
 */
final readonly class Company
{
    /**
     * @throws InvalidArgumentException for a blank detail: eVATR would answer evatr-0003
     *                                   or compare an empty value
     */
    public function __construct(
        public string $name,
        public string $city,
        public ?string $street = null,
        public ?string $postalCode = null,
    ) {
        foreach (['name' => $name, 'city' => $city, 'street' => $street, 'postal code' => $postalCode] as $detail => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException("The company's {$detail} must not be blank.");
            }
        }
    }

    /**
     * @internal
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter(
            ['firmenname' => $this->name, 'ort' => $this->city, 'strasse' => $this->street, 'plz' => $this->postalCode],
            static fn(?string $value): bool => $value !== null,
        );
    }
}
