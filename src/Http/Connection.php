<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Http;

use GoSuccess\VatId\ClientOptions;
use GoSuccess\VatId\Exception\ApiException;
use GoSuccess\VatId\Exception\SerializationException;
use GoSuccess\VatId\Exception\TransportException;
use JsonException;
use SensitiveParameter;

/**
 * JSON requests to one service with retries.
 *
 * Network failures and server errors (5xx) are retried with exponential
 * backoff; both services only read, so a repeated check is safe. The last
 * response is returned whatever its status: what a status means is up to the
 * service client (eVATR answers every outcome with a body, even a 404).
 *
 * @internal
 */
final class Connection
{
    private readonly string $baseUri;

    /** The request behind the last response, for {@see failure()}. */
    private ?Request $lastRequest = null;

    public function __construct(
        string $baseUri,
        private readonly ClientOptions $options,
        private readonly HttpClient $httpClient,
    ) {
        $this->baseUri = rtrim($baseUri, '/');
    }

    /**
     * @param array<string, mixed> $body
     */
    public function post(string $path, #[SensitiveParameter] array $body): Response
    {
        try {
            $json = json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new SerializationException('The request body cannot be encoded as JSON.');
        }

        return $this->send(Method::Post, $path, $json);
    }

    public function get(string $path): Response
    {
        return $this->send(Method::Get, $path, null);
    }

    /**
     * The decoded body.
     *
     * @throws SerializationException if the body is not JSON (e.g. a proxy's HTML page)
     */
    public static function json(Response $response): mixed
    {
        try {
            return json_decode($response->body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SerializationException("The response (HTTP {$response->statusCode}) is not JSON.");
        }
    }

    /**
     * The exception for an error response of the last request.
     */
    public function failure(Response $response): ApiException
    {
        return $this->lastRequest === null
            ? new ApiException("The request failed with HTTP {$response->statusCode}.", $response->statusCode, $response->body)
            : ApiException::fromResponse($response, $this->lastRequest);
    }

    private function send(Method $method, string $path, #[SensitiveParameter] ?string $body): Response
    {
        $headers = ['Accept' => 'application/json', 'User-Agent' => $this->options->userAgent];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $request = new Request(
            method: $method,
            uri: $this->baseUri . '/' . ltrim($path, '/'),
            headers: $headers,
            body: $body,
            timeout: $this->options->timeout,
            connectTimeout: $this->options->connectTimeout,
        );

        $this->lastRequest = $request;

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->httpClient->send($request);
            } catch (TransportException $exception) {
                if ($attempt >= $this->options->maxRetries) {
                    throw $exception;
                }

                $this->wait($attempt);

                continue;
            }

            if ($response->statusCode < 500 || $attempt >= $this->options->maxRetries) {
                return $response;
            }

            $this->wait($attempt);
        }
    }

    private function wait(int $attempt): void
    {
        $delay = min($this->options->maxRetryDelay, $this->options->retryBaseDelay * (2 ** $attempt));

        if ($delay > 0.0) {
            usleep((int) ($delay * 1_000_000));
        }
    }
}
