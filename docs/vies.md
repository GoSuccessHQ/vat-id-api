# VIES

Base URI `https://ec.europa.eu/taxation_customs/vies/rest-api`.

| Method | Endpoint |
|---|---|
| `Vies::check()` | `POST /check-vat-number` |
| `Vies::checkTestService()` | `POST /check-vat-test-service` |
| `Vies::status()` | `GET /check-status` |

## Request

`countryCode` and `vatNumber` from the `VatId`; `requesterMemberStateCode` and
`requesterNumber` from the requester (client default or per call); the trader
details that are set (`traderName`, `traderStreet`, `traderPostalCode`,
`traderCity`, `traderCompanyType`).

## Answer

VIES answers unknown values with `---` and an empty `requestIdentifier` when it
issues none; the client turns both into `null`. Comparisons are `VALID`,
`INVALID` or `NOT_PROCESSED`; a missing one counts as not processed. Measured on
2026-10-18: `DE123456789` without requester → HTTP 200, `valid: false`.

## Errors

A body with `actionSucceed: false` raises `ViesException` with the first
`errorWrappers[].error` as `errorCode`, whatever the HTTP status. Other error
responses raise the matching `ApiException`.
