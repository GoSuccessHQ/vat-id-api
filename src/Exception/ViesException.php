<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Exception;

use GoSuccess\VatId\Http\Response;

/**
 * VIES reported that it could not run the check (`actionSucceed: false`), even
 * with HTTP 200. {@see $errorCode} holds its first error, e.g. `MS_UNAVAILABLE`,
 * `MS_MAX_CONCURRENT_REQ`, `INVALID_INPUT` or `SERVICE_UNAVAILABLE`.
 */
final class ViesException extends ApiException
{
    /**
     * @internal
     */
    public static function fromViesResponse(Response $response): self
    {
        $details = ErrorDetails::parse($response->body);
        $message = "VIES could not run the check (HTTP {$response->statusCode})";

        if ($details->message !== null) {
            $message .= ": {$details->message}";
        }

        if ($details->errorCode !== null) {
            $message .= " ({$details->errorCode})";
        }

        return new self($message, $response->statusCode, $response->body, $details->errorCode);
    }
}
