<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

/**
 * The company details of a qualified confirmation. Name and city are required
 * for eVATR to run a qualified confirmation; street and postal code are optional.
 */
final readonly class Company
{
    public function __construct(
        public string $name,
        public string $city,
        public ?string $street = null,
        public ?string $postalCode = null,
    ) {}

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
