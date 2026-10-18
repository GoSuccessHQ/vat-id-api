<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Vies;

use GoSuccess\VatId\ClientOptions;
use GoSuccess\VatId\Exception\ApiException;
use GoSuccess\VatId\Exception\SerializationException;
use GoSuccess\VatId\Exception\TransportException;
use GoSuccess\VatId\Exception\ViesException;
use GoSuccess\VatId\Http\Connection;
use GoSuccess\VatId\Http\CurlHttpClient;
use GoSuccess\VatId\Http\HttpClient;
use GoSuccess\VatId\Http\Response;
use GoSuccess\VatId\Support\Fields;
use GoSuccess\VatId\VatId;

/**
 * Client for VIES on-the-Web of the European Commission (REST).
 *
 * A check with a requester VAT ID returns a consultation number
 * ({@see ViesCheck::$requestIdentifier}) as proof, where the member state
 * issues one.
 */
final class Vies
{
    public const string DEFAULT_BASE_URI = 'https://ec.europa.eu/taxation_customs/vies/rest-api';

    private readonly Connection $connection;

    /**
     * @param VatId|null $requester The requester sent with every check unless a call names another.
     */
    public function __construct(
        private readonly ?VatId $requester = null,
        ClientOptions $options = new ClientOptions(),
        ?HttpClient $httpClient = null,
        string $baseUri = self::DEFAULT_BASE_URI,
    ) {
        $this->connection = new Connection($baseUri, $options, $httpClient ?? new CurlHttpClient($options->timeout, $options->connectTimeout));
    }

    /**
     * Checks a VAT ID (`POST /check-vat-number`), optionally comparing trader details.
     *
     * @throws ViesException          when VIES could not run the check (e.g. member state unavailable)
     * @throws ApiException           for another error response
     * @throws TransportException     when the request keeps failing
     * @throws SerializationException when the answer cannot be read
     */
    public function check(VatId $vatId, ?Trader $trader = null, ?VatId $requester = null): ViesCheck
    {
        return $this->checkAt('/check-vat-number', $vatId, $trader, $requester);
    }

    /**
     * The same check against VIES's test service (`POST /check-vat-test-service`).
     *
     * @throws ViesException|ApiException|TransportException|SerializationException
     */
    public function checkTestService(VatId $vatId, ?Trader $trader = null, ?VatId $requester = null): ViesCheck
    {
        return $this->checkAt('/check-vat-test-service', $vatId, $trader, $requester);
    }

    /**
     * Whether VIES and each member state's service are available (`GET /check-status`).
     *
     * @throws ViesException|ApiException|TransportException|SerializationException
     */
    public function status(): ViesStatus
    {
        return ViesStatus::fromFields($this->read($this->connection->get('/check-status'), 'status'));
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['requester' => $this->requester === null ? 'none' : '***'];
    }

    private function checkAt(string $path, VatId $vatId, ?Trader $trader, ?VatId $requester): ViesCheck
    {
        $requester ??= $this->requester;
        $body = ['countryCode' => $vatId->countryCode, 'vatNumber' => $vatId->number];

        if ($requester !== null) {
            $body['requesterMemberStateCode'] = $requester->countryCode;
            $body['requesterNumber'] = $requester->number;
        }

        return ViesCheck::fromFields($this->read($this->connection->post($path, $body + ($trader?->toArray() ?? [])), 'check'));
    }

    /**
     * The fields of a successful answer.
     */
    private function read(Response $response, string $what): Fields
    {
        $decoded = $response->body === '' ? null : json_decode($response->body, true);

        if (\is_array($decoded) && ($decoded['actionSucceed'] ?? null) === false) {
            throw ViesException::fromViesResponse($response);
        }

        if (!$response->isSuccessful) {
            throw $this->connection->failure($response);
        }

        return Fields::of(Connection::json($response), "VIES {$what} answer");
    }
}
