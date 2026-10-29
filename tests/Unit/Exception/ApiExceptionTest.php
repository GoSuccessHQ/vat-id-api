<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Tests\Unit\Exception;

use GoSuccess\VatId\Exception\ApiException;
use GoSuccess\VatId\Exception\AuthenticationException;
use GoSuccess\VatId\Exception\ErrorDetails;
use GoSuccess\VatId\Exception\NotFoundException;
use GoSuccess\VatId\Exception\RateLimitException;
use GoSuccess\VatId\Exception\VatIdException;
use GoSuccess\VatId\Http\Method;
use GoSuccess\VatId\Http\Request;
use GoSuccess\VatId\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiException::class)]
#[CoversClass(ErrorDetails::class)]
#[CoversClass(RateLimitException::class)]
final class ApiExceptionTest extends TestCase
{
    #[DataProvider('errorBodies')]
    public function testNormalizesTheErrorBodies(string $body, string $message, ?string $errorCode): void
    {
        $exception = ApiException::fromResponse(new Response(400, $body), $this->request());

        self::assertSame($message, $exception->getMessage());
        self::assertSame($errorCode, $exception->errorCode);
        self::assertSame($body, $exception->responseBody);
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function errorBodies(): iterable
    {
        $prefix = 'POST https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number failed with HTTP 400';

        // The shapes of the two services' specifications and of a live eVATR answer (2026-10-18).
        yield 'vies' => ['{"actionSucceed":false,"errorWrappers":[{"error":"INVALID_INPUT","message":"The provided CountryCode is invalid"}]}', "{$prefix}: The provided CountryCode is invalid (INVALID_INPUT)", 'INVALID_INPUT'];
        yield 'vies without message' => ['{"actionSucceed":false,"errorWrappers":[{"error":"MS_UNAVAILABLE"}]}', "{$prefix}: MS_UNAVAILABLE (MS_UNAVAILABLE)", 'MS_UNAVAILABLE'];
        yield 'vies several' => ['{"actionSucceed":false,"errorWrappers":[{"error":"A","message":"One"},{"error":"B","message":"Two"}]}', "{$prefix}: One; Two (A)", 'A'];
        yield 'evatr' => ['{"id":"7220309a2344f277","anfrageZeitpunkt":"2026-10-18T17:19:05.064073706+02:00","status":"evatr-0004"}', "{$prefix} (evatr-0004)", 'evatr-0004'];

        // Defensive fallbacks.
        yield 'wrappers without strings' => ['{"errorWrappers":[1,{"error":2}]}', $prefix, null];
        yield 'other json' => ['{"message":"x"}', $prefix, null];
        yield 'plain text' => ['Bad Gateway', "{$prefix}: Bad Gateway", null];
        yield 'json list' => ['["x"]', $prefix, null];
        yield 'empty' => ['', $prefix, null];
    }

    public function testIncludesTheReasonPhraseButNotTheQuery(): void
    {
        $exception = ApiException::fromResponse(
            new Response(404, '{"actionSucceed":false,"errorWrappers":[{"error":"NOT_FOUND","message":"Not found"}]}', [], 'Not Found'),
            new Request(Method::Post, 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number?key=secret'),
        );

        self::assertInstanceOf(NotFoundException::class, $exception);
        self::assertInstanceOf(VatIdException::class, $exception);
        self::assertSame('POST https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number failed with HTTP 404 Not Found: Not found (NOT_FOUND)', $exception->getMessage());
    }

    public function testMapsAnInvalidKeyToAuthenticationException(): void
    {
        $exception = ApiException::fromResponse(new Response(401, 'Unauthorized'), $this->request());

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame(401, $exception->statusCode);
    }

    public function testShortensHugeBodiesWithoutBreakingUtf8(): void
    {
        // One ASCII byte first, so that the cut after 500 bytes splits an "ä".
        $body = 'a' . str_repeat('ä', 400);
        $exception = ApiException::fromResponse(new Response(502, $body), $this->request());

        self::assertTrue(mb_check_encoding($exception->getMessage(), 'UTF-8'));
        self::assertStringEndsWith('…', $exception->getMessage());
        // The prefix (method, URL, status) plus at most 500 bytes of the body and the ellipsis.
        self::assertLessThan(650, \strlen($exception->getMessage()));
        self::assertSame($body, $exception->responseBody);
    }

    public function testDecodesTheBody(): void
    {
        $exception = ApiException::fromResponse(new Response(400, '{"status":"evatr-0002"}'), $this->request());

        self::assertSame(['status' => 'evatr-0002'], $exception->decodedBody());
        self::assertNull(ApiException::fromResponse(new Response(502), $this->request())->decodedBody());
    }

    public function testParsesRetryAfter(): void
    {
        $now = 1_800_000_000.0;

        self::assertSame(30, RateLimitException::parseRetryAfter('30', $now));
        self::assertSame(0, RateLimitException::parseRetryAfter('Wed, 21 Oct 2015 07:28:00 GMT', $now));
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('D, d M Y H:i:s', 1_800_000_120) . ' GMT', $now));
        // The obsolete RFC 850 and asctime() formats of RFC 9110.
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('l, d-M-y H:i:s', 1_800_000_120) . ' GMT', $now));
        self::assertSame(3600, RateLimitException::parseRetryAfter('Fri Jan 15 09:00:00 2027', (float) gmmktime(8, 0, 0, 1, 15, 2027)));
        self::assertSame(3600, RateLimitException::parseRetryAfter('Wed Jan  6 09:00:00 2027', (float) gmmktime(8, 0, 0, 1, 6, 2027)));

        $future = gmdate('D, d M Y H:i:s', time() + 120) . ' GMT';
        self::assertGreaterThanOrEqual(118, RateLimitException::parseRetryAfter($future));
    }

    #[DataProvider('invalidRetryAfterValues')]
    public function testRejectsRetryAfterValuesOfNeitherForm(string $value): void
    {
        self::assertNull(RateLimitException::parseRetryAfter($value, 1_800_000_000.0));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRetryAfterValues(): iterable
    {
        yield 'empty' => [''];
        yield 'word' => ['soon'];
        // strtotime() read these as times of day, relative dates or zone offsets.
        yield 'fraction' => ['1.5'];
        yield 'fraction read as a time of day' => ['10.0'];
        yield 'negative' => ['-1'];
        yield 'signed' => ['+5'];
        yield 'military zone' => ['x'];
        yield 'now' => ['now'];
        yield 'relative' => ['tomorrow'];
        yield 'weekday' => ['Sun'];
        yield 'unit' => ['30s'];
        yield 'weekday that does not match' => ['Mon, 21 Oct 2015 07:28:00 GMT'];
        yield 'day beyond the month' => ['Thu, 32 Oct 2015 07:28:00 GMT'];
        yield 'other zone' => ['Wed, 21 Oct 2015 07:28:00 CET'];
        yield 'lower case' => ['wed, 21 oct 2015 07:28:00 GMT'];
        yield 'ISO 8601' => ['2015-10-21T07:28:00Z'];
    }

    public function testIgnoresAnInvalidRetryAfter(): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', ['retry-after' => '1.5']), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertNull($exception->retryAfter);
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('rateLimitHeaders')]
    public function testRateLimitExceptionCarriesTheAnnouncedWait(array $headers, ?int $retryAfter): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', $headers), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame($retryAfter, $exception->retryAfter);
    }

    /**
     * @return iterable<string, array{array<string, string>, int|null}>
     */
    public static function rateLimitHeaders(): iterable
    {
        yield 'seconds' => [['retry-after' => '7'], 7];
        yield 'date' => [['retry-after' => 'Fri, 15 Jan 2027 08:00:12 GMT'], 12];
        yield 'date in the past' => [['retry-after' => 'Wed, 21 Oct 2015 07:28:00 GMT'], 0];
        yield 'nothing' => [[], null];
    }

    #[DataProvider('errorStatuses')]
    public function testEveryExceptionCarriesTheResponse(int $status): void
    {
        $response = new Response($status, 'body', ['retry-after' => '1']);

        self::assertSame($response, ApiException::fromResponse($response, $this->request())->response);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function errorStatuses(): iterable
    {
        foreach ([400, 401, 403, 404, 409, 418, 422, 429, 500] as $status) {
            yield (string) $status => [$status];
        }
    }

    private function request(): Request
    {
        return new Request(Method::Post, 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number');
    }
}
