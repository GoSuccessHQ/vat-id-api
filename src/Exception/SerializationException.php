<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Exception;

use GoSuccess\VatId\Http\Response;
use RuntimeException;
use Throwable;

/**
 * A request payload could not be encoded, or a response body could not be
 * decoded into the expected shape.
 */
final class SerializationException extends RuntimeException implements VatIdException
{
    /**
     * @param Response|null $response The answer that could not be read, unchanged (status, headers,
     *                                body); null when the request could not be encoded.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
