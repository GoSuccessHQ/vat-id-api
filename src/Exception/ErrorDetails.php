<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Exception;

/**
 * Normalizes the error bodies of VIES and eVATR (observed 2026-10-18):
 *
 * - VIES: `{"actionSucceed": false, "errorWrappers": [{"error": "MS_UNAVAILABLE", "message": "…"}]}`;
 *   the first `error` is the code, the messages (or else the codes) form the message.
 * - eVATR: `{"id": "…", "anfrageZeitpunkt": "…", "status": "evatr-0004"}`; the status is the code.
 * - Plain text or HTML, e.g. from a proxy, is shortened.
 *
 * @internal
 */
final readonly class ErrorDetails
{
    private const int MAX_MESSAGE_BYTES = 500;

    private function __construct(
        public ?string $message,
        public ?string $errorCode,
    ) {}

    public static function parse(string $body): self
    {
        if (trim($body) === '') {
            return new self(null, null);
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            // Plain text or an HTML error page.
            return new self(self::excerpt($body), null);
        }

        $wrappers = $decoded['errorWrappers'] ?? null;
        $codes = [];
        $texts = [];

        foreach (\is_array($wrappers) ? $wrappers : [] as $wrapper) {
            if (!\is_array($wrapper)) {
                continue;
            }

            $code = self::text($wrapper['error'] ?? null);
            $text = self::text($wrapper['message'] ?? null) ?? $code;

            if ($code !== null) {
                $codes[] = $code;
            }

            if ($text !== null) {
                $texts[] = $text;
            }
        }

        $status = self::text($decoded['status'] ?? null);

        return new self(
            $texts === [] ? null : self::excerpt(implode('; ', $texts)),
            $codes[0] ?? $status,
        );
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function excerpt(string $text): string
    {
        $text = trim($text);

        if (\strlen($text) <= self::MAX_MESSAGE_BYTES) {
            return $text;
        }

        $cut = substr($text, 0, self::MAX_MESSAGE_BYTES);

        // Drop a trailing, incomplete multi-byte sequence (ext-mbstring is not required).
        return (preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/', '', $cut) ?? $cut) . '…';
    }
}
