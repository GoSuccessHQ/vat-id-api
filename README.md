# VAT ID API for PHP

A modern, strongly-typed, dependency-free PHP client to confirm EU VAT IDs
through [VIES](https://ec.europa.eu/taxation_customs/vies/) (European
Commission) and [eVATR](https://evatr.vies.bzst.de/) (German Federal Central Tax
Office, BZSt), simple and qualified.

## Features

- **VIES**: check any EU VAT ID, optionally compare trader details, get a
  consultation number as proof, and see which member states are available.
- **eVATR**: confirm foreign EU VAT IDs with a German requester, simply or
  qualified (company name, city, street, postal code), with the BZSt's status
  codes and German messages.
- **VatId** value object that reads VAT IDs as people write them.
- **Results, not exceptions**, where the service answers with a result: eVATR
  answers "not allocated" with HTTP 404 and "try again later" with 503, and both
  come back as a `Confirmation`.
- **No dependencies**: only `ext-curl` and `ext-json`; bring your own transport
  if you like.

## Requirements

- PHP 8.4 or newer
- `ext-curl`, `ext-json`

## Installation

```bash
composer require gosuccess/vat-id-api
```

## VAT IDs

```php
use GoSuccess\VatId\VatId;

$vatId = VatId::parse('de 123.456-789'); // DE / 123456789
(string) $vatId;                          // "DE123456789"
```

Spaces, dots and dashes are dropped, case does not matter, `GR` becomes `EL`
(the prefix Greek VAT IDs use), and Northern Ireland (`XI`) is accepted. Anything
else throws `InvalidArgumentException` before a request; the message names the
reason, never the number.

## VIES

```php
use GoSuccess\VatId\Vies\Trader;
use GoSuccess\VatId\Vies\Vies;

$vies = new Vies(VatId::parse('DE123456789')); // your VAT ID as requester, optional

$check = $vies->check(VatId::parse('ATU12345678'));
$check->valid;             // bool
$check->name;              // ?string, null where VIES has none ("---")
$check->address;           // ?string
$check->requestIdentifier; // ?string, the consultation number (needs a requester)

// Compare trader details (approximate matching where the member state supports it):
$check = $vies->check(VatId::parse('ATU12345678'), new Trader(name: 'Musterhaus GmbH', city: 'Wien'));
$check->nameMatch;         // TraderMatch::Valid, Invalid or NotProcessed

$vies->status();           // ViesStatus: VIES and each member state's availability
```

When VIES cannot run a check (e.g. the member state's service is down), it
answers `actionSucceed: false`, even with HTTP 200; the client throws
`ViesException` with VIES's code in `errorCode` (`MS_UNAVAILABLE`,
`MS_MAX_CONCURRENT_REQ`, `INVALID_INPUT`, …).

## eVATR

The requester must be a German VAT ID; eVATR does not confirm German VAT IDs.

```php
use GoSuccess\VatId\Bzst\Company;
use GoSuccess\VatId\Bzst\Evatr;

$evatr = new Evatr(VatId::parse('DE123456789'));

$simple = $evatr->confirm(VatId::parse('ATU12345678'));
$simple->isValid();   // true for evatr-0000, evatr-0003 and evatr-2008
$simple->status;      // EvatrStatus, or the raw code if this package does not know it
$simple->category();  // StatusCategory::Result, Notice, Error or Unknown
$simple->httpStatus;  // as eVATR answered
$simple->validFrom;   // ?DateTimeImmutable, for evatr-2002 and evatr-2006 (a date: midnight UTC,
$simple->validUntil;  // read it with format('Y-m-d'), not after a change of time zone)

$qualified = $evatr->confirm(VatId::parse('ATU12345678'), new Company('Musterhaus GmbH & Co KG', 'musterort', 'Musterstrasse 22', '12345'));
$qualified->name;     // Comparison::Matches (A), DoesNotMatch (B), NotRequested (C), NotProvided (D)
$qualified->city;
```

A confirmation is qualified when the company is given (name and city are
required, street and postal code optional; a blank detail is refused before the
request, since eVATR would answer `evatr-0003`). `EvatrStatus` knows the 26 codes the
BZSt published on 2026-10-18, each with `category()`, `message()` (German),
`field()` and `httpStatus()`; `statusMessages()` and `memberStates()` read the
info endpoints live.

The BZSt documents test values: requester `DE123456789`, requested
`ATU12345678`, and for a qualified test `Musterhaus GmbH & Co KG`,
`Musterstrasse 22`, `12345`, `musterort`. On 2026-10-18 they answered
`evatr-0000`, qualified with `A` for every field; with a real requester the same
requested number is `evatr-0005` (syntactically wrong).

## Requester

Both clients take the requester as a default and accept another one per call
(`requester:`). It is never written to exception messages, debug output or the
arguments of stack traces (`#[SensitiveParameter]`), which error trackers collect.

## Error handling

Every exception implements `GoSuccess\VatId\Exception\VatIdException`.

| Exception | When |
|---|---|
| `ViesException` | VIES could not run the check (`actionSucceed: false`) |
| `ApiException` and its subclasses | an error response without a readable answer (e.g. a proxy's HTML page) |
| `TransportException` | network failure after the retries |
| `SerializationException` | an answer cannot be read (also an eVATR answer with a status whose other fields are malformed), or the request cannot be encoded (e.g. invalid UTF-8 in a company name); the message names the field path |
| `InvalidArgumentException` | a malformed VAT ID, or eVATR without a German requester |

Network failures and server errors (5xx) are retried with backoff (both services
only read); eVATR's 5xx answers with a status are returned after the retries.

## Configuration and custom transport

`ClientOptions` sets timeouts (30 s), retries (2), backoff and user agent. A
custom transport implements `GoSuccess\VatId\Http\HttpClient`.

## Specification drift

`resources/specs` holds snapshots of both specifications and of eVATR's status
messages; `composer specs` refreshes them. A weekly workflow fails when one
changed, so `EvatrStatus` and the clients follow the services.

## Not covered

- The old eVATR XML-RPC interface and VIES's SOAP interface.
- Batch checks of many VAT IDs at once.

## Documentation and examples

- [docs/vies.md](docs/vies.md), [docs/evatr.md](docs/evatr.md)
- [examples/vies.php](examples/vies.php), [examples/evatr.php](examples/evatr.php)
- Specifications: [VIES](https://ec.europa.eu/assets/taxud/vow-information/swagger_publicVAT.yaml),
  [eVATR](https://api.evatr.vies.bzst.de/api-docs)

## Development

```bash
composer check                                   # code style, static analysis, unit tests
VAT_ID_LIVE=1 composer test:integration          # a few read-only live calls
```

The call with a real requester also needs `VAT_ID_REQUESTER`, e.g. from a `.env`
that is never committed: `set -a; . ./.env; set +a`.

## License

MIT
