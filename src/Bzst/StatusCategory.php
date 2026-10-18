<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

/**
 * The BZSt's category of an eVATR status.
 */
enum StatusCategory: string
{
    /** Ergebnis: the VAT ID is valid. */
    case Result = 'Ergebnis';

    /** Hinweis: a finding about the request or the VAT ID, e.g. not allocated. */
    case Notice = 'Hinweis';

    /** Fehler: the request was wrong or the service could not answer. */
    case Error = 'Fehler';

    /** A status this package does not know yet. */
    case Unknown = 'unknown';
}
