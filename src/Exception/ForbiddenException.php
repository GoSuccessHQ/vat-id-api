<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Exception;

/**
 * The request was refused (HTTP 403) without an answer of the service, e.g. by a
 * firewall in between; eVATR's own refusals (evatr-0006, -0007, -0008) come back
 * as a confirmation.
 */
final class ForbiddenException extends ApiException {}
