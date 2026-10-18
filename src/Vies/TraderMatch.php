<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Vies;

/**
 * How a trader detail sent with the check compares with the registered data.
 */
enum TraderMatch: string
{
    case Valid = 'VALID';
    case Invalid = 'INVALID';
    /** Not compared: not sent, or the member state does not compare it. */
    case NotProcessed = 'NOT_PROCESSED';
}
