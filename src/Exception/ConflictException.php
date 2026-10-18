<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Exception;

/**
 * The request conflicts with the current state of the resource (HTTP 409).
 */
final class ConflictException extends ApiException {}
