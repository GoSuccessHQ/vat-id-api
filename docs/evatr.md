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

`Confirmation::$response` holds the answer unchanged, as the record that proves
the request: `statusCode`, `body` (the JSON exactly as received, after the
transfer's content encoding is undone), `headers` (names lower-cased, a repeated
header joined with `, `) and `reasonPhrase`. After retries it is the last
answer. An exception thrown after an answer arrived carries it the same way:
`ApiException::$response` and `SerializationException::$response` (null only
when the request itself could not be encoded). The answer holds no part of the
request, so not the requester.

`anfrageZeitpunkt` carries nanoseconds and an offset
(`2026-10-18T17:19:05.064073706+02:00`); they are cut to microseconds, the offset
is kept. `gueltigAb` and `gueltigBis` are read as dates.

## Status codes

`EvatrStatus` holds the 26 codes of `GET /v1/info/statusmeldungen` as of
2026-10-18 with category (`Ergebnis` → `Result`, `Hinweis` → `Notice`, `Fehler` →
`Error`), HTTP status, field and the German message. The snapshot
`resources/specs/evatr-statusmeldungen.json` pins them in the unit tests; the
weekly spec-drift workflow and the live test fail when the BZSt changes them. An
unknown code arrives as its raw string with category `Unknown`. `isValid()` is
true for `evatr-0000`, `evatr-0003` (valid, but a field of the qualified request
was missing) and `evatr-2008`.

Only a status starting with `evatr-` makes an answer; another JSON with a
`status` (a proxy's `{"status": "error"}`) is an error response. An error answer
without `anfrageZeitpunkt` has `requestedAt` null. A comparison letter this
package does not know is null, so one odd field never hides a valid result.

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
