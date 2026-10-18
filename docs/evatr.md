# eVATR

Base URI `https://api.evatr.vies.bzst.de`.

| Method | Endpoint |
|---|---|
| `Evatr::confirm()` | `POST /v1/abfrage` |
| `Evatr::statusMessages()` | `GET /v1/info/statusmeldungen` |
| `Evatr::memberStates()` | `GET /v1/info/eu_mitgliedstaaten` |

## Request

`anfragendeUstid` is the German requester, `angefragteUstid` the foreign VAT ID.
With a `Company`, `firmenname` and `ort` (required) and `strasse` and `plz`
(optional) make it a qualified confirmation. A requester that is not German is
refused before any request.

## Answer

eVATR answers every outcome with `{"id", "anfrageZeitpunkt", "status", …}`, also
with HTTP 400, 403, 404, 500 or 503. Every such answer becomes a `Confirmation`
with the HTTP status; only an answer without a status is an error. Network
failures and 5xx are retried first.

`anfrageZeitpunkt` carries nanoseconds and an offset
(`2026-10-18T17:19:05.064073706+02:00`); they are cut to microseconds, the offset
is kept. `gueltigAb` and `gueltigBis` are read as dates.

## Status codes

`EvatrStatus` holds the 26 codes of `GET /v1/info/statusmeldungen` as of
2026-10-18 with category (`Ergebnis` → `Result`, `Hinweis` → `Notice`, `Fehler` →
`Error`), HTTP status, field and the German message. The live test compares them
with the endpoint and fails when the BZSt changes them. An unknown code arrives as
its raw string with category `Unknown`. `isValid()` is true for `evatr-0000` and
`evatr-2008`.

## Comparisons

`ergFirmenname`, `ergStrasse`, `ergPlz`, `ergOrt`: `A` matches, `B` does not
match, `C` not requested, `D` not provided by the member state.

## Measured (2026-10-18)

| Call | Answer |
|---|---|
| test requester, `ATU12345678` | `evatr-0000`, HTTP 200 |
| the same, qualified with the documented test company | `evatr-0000`, HTTP 200, `A` for all four |
| real requester, `ATU12345678` | `evatr-0005`, HTTP 400 (the test number is no real VAT ID) |
| member states | 28, all available |
