<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Vies;

use DateTimeImmutable;
use GoSuccess\VatId\Http\Response;
use GoSuccess\VatId\Support\Fields;

/**
 * The answer to a VAT ID check. VIES fills what it does not know with `---`;
 * such values, and an empty request identifier, are null here.
 */
final readonly class ViesCheck
{
    /**
     * @param string|null $requestIdentifier The consultation number that proves the check; only
     *                                       with a requester, and only if the member state issues one.
     * @param Trader|null $trader            The trader details VIES returned; null when it returned none.
     * @param Response    $response          VIES's answer unchanged: status, headers and the body as received.
     */
    public function __construct(
        public string $countryCode,
        public string $vatNumber,
        public DateTimeImmutable $requestDate,
        public bool $valid,
        public ?string $requestIdentifier,
        public ?string $name,
        public ?string $address,
        public ?Trader $trader,
        public TraderMatch $nameMatch,
        public TraderMatch $streetMatch,
        public TraderMatch $postalCodeMatch,
        public TraderMatch $cityMatch,
        public TraderMatch $companyTypeMatch,
        public Response $response,
    ) {}

    /**
     * @internal
     */
    public static function fromFields(Fields $fields, Response $response): self
    {
        $value = static fn(string $key): ?string => self::known($fields->nullableString($key));
        $trader = new Trader($value('traderName'), $value('traderStreet'), $value('traderPostalCode'), $value('traderCity'), $value('traderCompanyType'));
        $match = static fn(string $key): TraderMatch => $fields->tolerantEnum($key, TraderMatch::class) ?? TraderMatch::NotProcessed;

        return new self(
            countryCode: $fields->string('countryCode'),
            vatNumber: $fields->string('vatNumber'),
            requestDate: $fields->date('requestDate'),
            valid: $fields->bool('valid'),
            requestIdentifier: $value('requestIdentifier'),
            name: $value('name'),
            address: $value('address'),
            trader: $trader->isEmpty() ? null : $trader,
            nameMatch: $match('traderNameMatch'),
            streetMatch: $match('traderStreetMatch'),
            postalCodeMatch: $match('traderPostalCodeMatch'),
            cityMatch: $match('traderCityMatch'),
            companyTypeMatch: $match('traderCompanyTypeMatch'),
            response: $response,
        );
    }

    /**
     * Null for VIES's placeholder `---` and for an empty value.
     */
    private static function known(?string $value): ?string
    {
        return $value === null || trim($value) === '' || trim($value) === '---' ? null : $value;
    }
}
