<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Exception;

/**
 * The service refused the request as unauthenticated (HTTP 401); neither VIES
 * nor eVATR needs credentials, so a proxy in between is the likely cause.
 */
final class AuthenticationException extends ApiException {}
