<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

use DateTimeImmutable;
use GoSuccess\VatId\Support\Fields;

/**
 * eVATR's answer to a confirmation request. eVATR answers every outcome with a
 * status, also "not allocated" (HTTP 404) or "try again later" (HTTP 503), so
 * this is a result, not an error: check {@see isValid()} and {@see category()}.
 */
final readonly class Confirmation
{
    /**
     * @param string|null       $id          eVATR's technical ID of the request.
     * @param EvatrStatus|string $status     The raw code when this package does not know it.
     * @param int               $httpStatus  The HTTP status eVATR answered with.
     * @param DateTimeImmutable|null $requestedAt With the offset eVATR sent; null when an error answer lacks it.
     * @param Comparison|null   $name        Comparisons of a qualified confirmation; null for a simple one,
     *                                       when eVATR returned none, or for a letter this package does not know.
     */
    public function __construct(
        public ?string $id,
        public ?DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validUntil,
        public EvatrStatus|string $status,
        public int $httpStatus,
        public ?Comparison $name,
        public ?Comparison $street,
        public ?Comparison $postalCode,
        public ?Comparison $city,
    ) {}

    /**
     * @internal
     */
    public static function fromFields(Fields $fields, int $httpStatus): self
    {
        $code = $fields->string('status');

        return new self(
            id: $fields->nullableString('id'),
            requestedAt: $fields->nullableDate('anfrageZeitpunkt'),
            validFrom: $fields->nullableDate('gueltigAb'),
            validUntil: $fields->nullableDate('gueltigBis'),
            status: EvatrStatus::tryFrom($code) ?? $code,
            httpStatus: $httpStatus,
            name: $fields->tolerantEnum('ergFirmenname', Comparison::class),
            street: $fields->tolerantEnum('ergStrasse', Comparison::class),
            postalCode: $fields->tolerantEnum('ergPlz', Comparison::class),
            city: $fields->tolerantEnum('ergOrt', Comparison::class),
        );
    }

    /**
     * Whether the VAT ID was valid at the time of the request: `evatr-0000`;
     * `evatr-0003`, valid, but a field the qualified confirmation needs was
     * missing; `evatr-2008`, valid, with a peculiarity of the qualified
     * confirmation.
     */
    public function isValid(): bool
    {
        return \in_array($this->status, [EvatrStatus::Code0000, EvatrStatus::Code0003, EvatrStatus::Code2008], true);
    }

    public function category(): StatusCategory
    {
        return $this->status instanceof EvatrStatus ? $this->status->category() : StatusCategory::Unknown;
    }
}
