<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

/**
 * How a company detail of a qualified confirmation compares with the data the
 * member state registered.
 */
enum Comparison: string
{
    /** A: matches. */
    case Matches = 'A';

    /** B: does not match. */
    case DoesNotMatch = 'B';

    /** C: not requested. */
    case NotRequested = 'C';

    /** D: not provided by the member state. */
    case NotProvided = 'D';
}
