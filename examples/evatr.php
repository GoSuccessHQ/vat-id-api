<?php

declare(strict_types=1);

/**
 * Confirms a foreign VAT ID through eVATR; with name and city, qualified.
 *
 * Usage: php examples/evatr.php <vat-id> [<company name> <city>]
 */

use GoSuccess\VatId\Bzst\Company;
use GoSuccess\VatId\Bzst\Evatr;
use GoSuccess\VatId\Bzst\EvatrStatus;
use GoSuccess\VatId\VatId;

$requester = require __DIR__ . '/bootstrap.php';
assert($requester === null || $requester instanceof VatId);

if ($requester === null) {
    fwrite(STDERR, "Set VAT_ID_REQUESTER to your German VAT ID first.\n");

    exit(1);
}

$company = isset($argv[2], $argv[3]) ? new Company($argv[2], $argv[3]) : null;
$confirmation = new Evatr($requester)->confirm(VatId::parse($argv[1] ?? 'ATU12345678'), $company);

$status = $confirmation->status;
printf("%s (%s): %s\n", $status instanceof EvatrStatus ? $status->value : $status, $confirmation->category()->value, $confirmation->isValid() ? 'valid' : 'not valid');

if ($status instanceof EvatrStatus) {
    echo $status->message(), "\n";
}

foreach (['name' => $confirmation->name, 'street' => $confirmation->street, 'postal code' => $confirmation->postalCode, 'city' => $confirmation->city] as $field => $comparison) {
    if ($comparison !== null) {
        printf("%s: %s\n", $field, $comparison->name);
    }
}
