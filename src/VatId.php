<?php

declare(strict_types=1);

namespace GoSuccess\VatId;

use InvalidArgumentException;
use Stringable;

/**
 * A VAT identification number of an EU member state or Northern Ireland,
 * split into the country prefix and the number. Only the form is checked here;
 * whether the number exists is what VIES and eVATR answer.
 */
final readonly class VatId implements Stringable
{
    /**
     * The prefixes VAT IDs use: the EU member states (Greece is `EL`) and
     * Northern Ireland (`XI`).
     */
    public const array COUNTRIES = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI'];

    public string $countryCode;

    public string $number;

    /**
     * @param string $countryCode Two letters, any case; `GR` becomes `EL`.
     * @param string $number      The number without the prefix: 2 to 12 letters,
     *                            digits, `+` or `*`.
     *
     * @throws InvalidArgumentException naming the reason, never the value
     */
    public function __construct(string $countryCode, string $number)
    {
        $countryCode = strtoupper($countryCode);
        $countryCode = $countryCode === 'GR' ? 'EL' : $countryCode;
        $number = strtoupper($number);

        if (!\in_array($countryCode, self::COUNTRIES, true)) {
            throw new InvalidArgumentException('The VAT ID does not start with the prefix of an EU member state or Northern Ireland.');
        }

        if (preg_match('/^[A-Z0-9+*]{2,12}$/D', $number) !== 1) {
            throw new InvalidArgumentException('The number of the VAT ID must have 2 to 12 letters, digits, "+" or "*".');
        }

        $this->countryCode = $countryCode;
        $this->number = $number;
    }

    /**
     * Reads a VAT ID as people write it: spaces, dots and dashes are dropped,
     * case does not matter, the first two letters are the country.
     *
     * @throws InvalidArgumentException naming the reason, never the value
     */
    public static function parse(string $value): self
    {
        $compact = (string) preg_replace('/[\s.\-]+/u', '', $value);

        return new self(substr($compact, 0, 2), substr($compact, 2));
    }

    public function __toString(): string
    {
        return $this->countryCode . $this->number;
    }
}
