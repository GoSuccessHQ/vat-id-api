<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Tests\Unit\Http;

use GoSuccess\VatId\ClientOptions;
use GoSuccess\VatId\Exception\SerializationException;
use GoSuccess\VatId\Exception\TransportException;
use GoSuccess\VatId\Http\Connection;
use GoSuccess\VatId\Http\Response;
use GoSuccess\VatId\Tests\Support\MockHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Connection::class)]
final class ConnectionTest extends TestCase
{
    public function testPostsJsonWithHeaders(): void
    {
        $http = new MockHttpClient(new Response(200, '{"ok":true}'));

        $response = $this->connection($http)->post('/check', ['countryCode' => 'DE', 'vatNumber' => '123456789']);

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://example.test/api/check', $request->uri);
        self::assertSame('{"countryCode":"DE","vatNumber":"123456789"}', $request->body);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertSame('application/json', $request->headers['Accept']);
        self::assertSame(ClientOptions::DEFAULT_USER_AGENT, $request->headers['User-Agent']);
        self::assertSame(30.0, $request->timeout);
        self::assertSame(['ok' => true], Connection::json($response));
    }

    public function testGetsWithoutBody(): void
    {
        $http = new MockHttpClient(new Response(200, '[]'));

        $this->connection($http)->get('/status');

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertNull($http->requests[0]->body);
        self::assertArrayNotHasKey('Content-Type', $http->requests[0]->headers);
    }

    public function testRetriesTransportFailuresAndServerErrors(): void
    {
        $http = new MockHttpClient(new Response(503, 'busy'), new TransportException('reset'), new Response(200, '{}'));

        self::assertSame(200, $this->connection($http)->post('/check', [])->statusCode);
        self::assertSame(3, $http->callCount());
    }

    public function testReturnsTheLastServerErrorAfterTheRetries(): void
    {
        $http = new MockHttpClient(new Response(503, '{"status":"evatr-0011"}'), new Response(503, '{"status":"evatr-0011"}'), new Response(503, '{"status":"evatr-0011"}'));

        $response = $this->connection($http)->post('/check', []);

        self::assertSame(503, $response->statusCode);
        self::assertSame(3, $http->callCount());
    }

    public function testThrowsTheLastTransportFailureAfterTheRetries(): void
    {
        $http = new MockHttpClient(new TransportException('a'), new TransportException('b'), new TransportException('c'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('c');

        $this->connection($http)->get('/status');
    }

    public function testDoesNotRetryClientErrorsOrRateLimits(): void
    {
        $http = new MockHttpClient(new Response(400, '{}'), new Response(429, '{}'));
        $connection = $this->connection($http);

        self::assertSame(400, $connection->post('/check', [])->statusCode);
        self::assertSame(429, $connection->post('/check', [])->statusCode);
        self::assertSame(2, $http->callCount());
    }

    public function testJsonRejectsHtml(): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('HTTP 502');

        Connection::json(new Response(502, '<html><body>Bad gateway</body></html>'));
    }

    private function connection(MockHttpClient $http): Connection
    {
        return new Connection('https://example.test/api/', new ClientOptions(retryBaseDelay: 0.0), $http);
    }
}
