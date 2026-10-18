<?php

declare(strict_types=1);

/**
 * Shared setup of the examples: the autoloader and the requester VAT ID from
 * the environment (VAT_ID_REQUESTER), e.g. `set -a; . ./.env; set +a`.
 */

use GoSuccess\VatId\VatId;

require __DIR__ . '/../vendor/autoload.php';

$requester = getenv('VAT_ID_REQUESTER');

return is_string($requester) && $requester !== '' ? VatId::parse($requester) : null;
