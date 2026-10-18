<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

use GoSuccess\VatId\Support\Fields;

/**
 * One entry of eVATR's list of status messages.
 */
final readonly class StatusMessage
{
    public function __construct(
        public string $status,
        public StatusCategory $category,
        public ?int $httpStatus,
        public ?string $field,
        public string $message,
    ) {}

    /**
     * @internal
     */
    public static function fromFields(Fields $fields): self
    {
        return new self(
            $fields->string('status'),
            $fields->nullableEnum('kategorie', StatusCategory::class) ?? StatusCategory::Unknown,
            $fields->nullableInt('httpcode'),
            $fields->nullableString('feld'),
            $fields->string('meldung'),
        );
    }
}
