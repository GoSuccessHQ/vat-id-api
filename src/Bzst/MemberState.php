<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

use GoSuccess\VatId\Support\Fields;

/**
 * A member state and whether eVATR can currently confirm its VAT IDs.
 */
final readonly class MemberState
{
    /**
     * @param string $name In German, as eVATR sends it.
     */
    public function __construct(
        public string $countryCode,
        public string $name,
        public bool $available,
    ) {}

    /**
     * @internal
     */
    public static function fromFields(Fields $fields): self
    {
        return new self($fields->string('alpha2'), $fields->string('name'), $fields->bool('verfuegbar'));
    }
}
