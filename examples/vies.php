<?php

declare(strict_types=1);

/**
 * Checks a VAT ID through VIES.
 *
 * Usage: php examples/vies.php <vat-id>
 */

use GoSuccess\VatId\Exception\ViesException;
use GoSuccess\VatId\VatId;
use GoSuccess\VatId\Vies\Vies;

$requester = require __DIR__ . '/bootstrap.php';
assert($requester === null || $requester instanceof VatId);

try {
    $check = new Vies($requester)->check(VatId::parse($argv[1] ?? 'DE123456789'));
} catch (ViesException $exception) {
    fwrite(STDERR, "VIES could not check it: {$exception->errorCode}\n");

    exit(1);
}

printf("%s%s: %s\n", $check->countryCode, $check->vatNumber, $check->valid ? 'valid' : 'not valid');
printf("Name: %s\nAddress: %s\nConsultation number: %s\n", $check->name ?? '-', $check->address ?? '-', $check->requestIdentifier ?? '-');
