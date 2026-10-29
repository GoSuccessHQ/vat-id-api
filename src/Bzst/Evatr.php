<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Bzst;

use GoSuccess\VatId\ClientOptions;
use GoSuccess\VatId\Exception\ApiException;
use GoSuccess\VatId\Exception\SerializationException;
use GoSuccess\VatId\Exception\TransportException;
use GoSuccess\VatId\Http\Connection;
use GoSuccess\VatId\Http\CurlHttpClient;
use GoSuccess\VatId\Http\HttpClient;
use GoSuccess\VatId\Http\Response;
use GoSuccess\VatId\Support\Fields;
use GoSuccess\VatId\VatId;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Client for eVATR, the confirmation of foreign EU VAT IDs by the German
 * Federal Central Tax Office (BZSt). The requester must be a German VAT ID;
 * eVATR does not confirm German VAT IDs.
 */
final class Evatr
{
    public const string DEFAULT_BASE_URI = 'https://api.evatr.vies.bzst.de';

    private readonly Connection $connection;

    /**
     * @param VatId|null $requester The German VAT ID used for every request unless a call names another.
     */
    public function __construct(
        #[SensitiveParameter]
        private readonly ?VatId $requester = null,
        ClientOptions $options = new ClientOptions(),
        ?HttpClient $httpClient = null,
        string $baseUri = self::DEFAULT_BASE_URI,
    ) {
        $this->connection = new Connection($baseUri, $options, $httpClient ?? new CurlHttpClient($options->timeout, $options->connectTimeout));
    }

    /**
     * Confirms a VAT ID (`POST /v1/abfrage`): simply, or qualified when the
     * company is given. Every answer with a status is returned, whatever its
     * HTTP status; network failures and 5xx are retried first.
     *
     * @throws InvalidArgumentException without a German requester, before any request
     * @throws ApiException             for an error response without an eVATR status
     * @throws TransportException       when the request keeps failing
     * @throws SerializationException   when the answer cannot be read
     */
    public function confirm(VatId $requested, ?Company $company = null, #[SensitiveParameter] ?VatId $requester = null): Confirmation
    {
        $requester ??= $this->requester;

        if ($requester === null || $requester->countryCode !== 'DE') {
            throw new InvalidArgumentException('eVATR needs a German VAT ID as requester.');
        }

        $response = $this->connection->post('/v1/abfrage', ['anfragendeUstid' => (string) $requester, 'angefragteUstid' => (string) $requested] + ($company?->toArray() ?? []));
        $decoded = $response->body === '' ? null : json_decode($response->body, true);

        // Only an eVATR status makes an answer; a proxy's {"status": "error"} is an error response.
        if (\is_array($decoded) && \is_string($decoded['status'] ?? null) && str_starts_with($decoded['status'], 'evatr-')) {
            return Confirmation::fromFields(Fields::of($decoded, 'eVATR answer', $response), $response);
        }

        if (!$response->isSuccessful) {
            throw $this->connection->failure($response);
        }

        throw new SerializationException("The eVATR answer (HTTP {$response->statusCode}) carries no status.", response: $response);
    }

    /**
     * The status messages (`GET /v1/info/statusmeldungen`).
     *
     * @return list<StatusMessage>
     *
     * @throws ApiException|TransportException|SerializationException
     */
    public function statusMessages(): array
    {
        return array_map(StatusMessage::fromFields(...), $this->list($this->connection->get('/v1/info/statusmeldungen'), 'statusmeldungen'));
    }

    /**
     * The member states and their availability (`GET /v1/info/eu_mitgliedstaaten`).
     *
     * @return list<MemberState>
     *
     * @throws ApiException|TransportException|SerializationException
     */
    public function memberStates(): array
    {
        return array_map(MemberState::fromFields(...), $this->list($this->connection->get('/v1/info/eu_mitgliedstaaten'), 'eu_mitgliedstaaten'));
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['requester' => $this->requester === null ? 'none' : '***'];
    }

    /**
     * @return list<Fields>
     */
    private function list(Response $response, string $what): array
    {
        if (!$response->isSuccessful) {
            throw $this->connection->failure($response);
        }

        $decoded = Connection::json($response);

        if (!\is_array($decoded) || !array_is_list($decoded)) {
            throw new SerializationException("The eVATR answer of {$what} is not a JSON list.", response: $response);
        }

        return new Fields([$what => $decoded], response: $response)->objectList($what);
    }
}
