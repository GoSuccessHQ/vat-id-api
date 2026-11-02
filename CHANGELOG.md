# Changelog

## 0.2.1

### Fixed

- The weekly spec-drift check no longer fails when eVATR serves the keys of
  its specification in another order; the snapshots are written with sorted
  keys. Nothing changes for code that uses the package.

## 0.2.0

### Added

- `Confirmation::$response` and `ViesCheck::$response`: the answer of eVATR
  and VIES unchanged (`Http\Response` with status, headers and body), e.g. to
  archive the record that proves a qualified confirmation.
- `ApiException::$response` (and its subclasses) and
  `SerializationException::$response`: the answer behind a failure, when one
  arrived.

### Changed

- `Confirmation` and `ViesCheck` take the new `$response` as their last,
  required constructor argument. Code that only reads them is not affected.

## 0.1.0

- First release: VIES checks and status, eVATR simple and qualified
  confirmations, `VatId` value object.
