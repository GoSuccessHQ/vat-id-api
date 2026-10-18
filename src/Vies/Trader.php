<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Vies;

use InvalidArgumentException;

/**
 * Trader details to compare with the registered data (approximate matching),
 * or the details VIES returns. Unset details are neither sent nor compared.
 */
final readonly class Trader
{
    /**
     * @throws InvalidArgumentException for a blank detail; leave it null instead
     */
    public function __construct(
        public ?string $name = null,
        public ?string $street = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $companyType = null,
    ) {
        foreach ([$name, $street, $postalCode, $city, $companyType] as $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException('A trader detail must not be blank; leave it null instead.');
            }
        }
    }

    /**
     * The request fields of the details that are set.
     *
     * @internal
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'traderName' => $this->name,
            'traderStreet' => $this->street,
            'traderPostalCode' => $this->postalCode,
            'traderCity' => $this->city,
            'traderCompanyType' => $this->companyType,
        ], static fn(?string $value): bool => $value !== null);
    }

    /**
     * Whether no detail is set.
     */
    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }
}
