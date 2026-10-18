<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Tests\Unit\Vies;

use GoSuccess\VatId\ClientOptions;
use GoSuccess\VatId\Exception\ApiException;
use GoSuccess\VatId\Exception\SerializationException;
use GoSuccess\VatId\Exception\ServerException;
use GoSuccess\VatId\Exception\ViesException;
use GoSuccess\VatId\Http\Response;
use GoSuccess\VatId\Tests\Support\MockHttpClient;
use GoSuccess\VatId\VatId;
use GoSuccess\VatId\Vies\Availability;
use GoSuccess\VatId\Vies\Trader;
use GoSuccess\VatId\Vies\TraderMatch;
use GoSuccess\VatId\Vies\Vies;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversNamespace('GoSuccess\VatId\Vies')]
final class ViesTest extends TestCase
{
    private const string REQUESTER = 'DE999888777';

    public function testCheckSendsTheNumberAndTheDefaultRequester(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('check-valid')));

        $this->client($http)->check(VatId::parse('ATU12345678'));

        self::assertSame('https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number', $http->requests[0]->uri);
        self::assertSame(['countryCode' => 'AT', 'vatNumber' => 'U12345678', 'requesterMemberStateCode' => 'DE', 'requesterNumber' => '999888777'], $http->jsonBody());
    }

    public function testWithoutRequesterOnlyTheNumberIsSent(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('check-valid')));

        new Vies(options: new ClientOptions(retryBaseDelay: 0.0), httpClient: $http)->check(VatId::parse('ATU12345678'));

        self::assertSame(['countryCode' => 'AT', 'vatNumber' => 'U12345678'], $http->jsonBody());
    }

    public function testRequesterPerCallOverridesTheDefault(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('check-valid')));

        $this->client($http)->check(VatId::parse('ATU12345678'), requester: VatId::parse('FR12345678901'));

        self::assertSame('FR', $http->jsonBody()['requesterMemberStateCode']);
        self::assertSame('12345678901', $http->jsonBody()['requesterNumber']);
    }

    public function testTraderFieldsAreSentOnlyWhenSet(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('check-valid')));

        $this->client($http)->check(VatId::parse('ATU12345678'), new Trader(name: 'Musterhaus GmbH', city: 'Musterort'));

        $body = $http->jsonBody();
        self::assertSame('Musterhaus GmbH', $body['traderName']);
        self::assertSame('Musterort', $body['traderCity']);
        self::assertArrayNotHasKey('traderStreet', $body);
        self::assertArrayNotHasKey('traderPostalCode', $body);
        self::assertArrayNotHasKey('traderCompanyType', $body);
    }

    public function testReadsAValidCheck(): void
    {
        $check = $this->client(new MockHttpClient(new Response(200, self::fixture('check-valid'))))->check(VatId::parse('ATU12345678'));

        self::assertTrue($check->valid);
        self::assertSame('AT', $check->countryCode);
        self::assertSame('U12345678', $check->vatNumber);
        self::assertSame('2026-10-18T15:19:05.160+00:00', $check->requestDate->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('WAPIAAAAZ1234567', $check->requestIdentifier);
        self::assertSame('MUSTERHAUS GMBH', $check->name);
        self::assertSame("MUSTERSTRASSE 22\n1234 MUSTERORT", $check->address);
        self::assertSame('Musterhaus GmbH', $check->trader?->name);
        self::assertNull($check->trader->street);
        self::assertSame(TraderMatch::Valid, $check->nameMatch);
        self::assertSame(TraderMatch::NotProcessed, $check->streetMatch);
        self::assertSame(TraderMatch::Valid, $check->postalCodeMatch);
        self::assertSame(TraderMatch::Invalid, $check->cityMatch);
        self::assertSame(TraderMatch::NotProcessed, $check->companyTypeMatch);
    }

    public function testPlaceholdersBecomeNull(): void
    {
        $check = $this->client(new MockHttpClient(new Response(200, self::fixture('check-invalid'))))->check(VatId::parse('DE123456789'));

        self::assertFalse($check->valid);
        self::assertNull($check->requestIdentifier);
        self::assertNull($check->name);
        self::assertNull($check->address);
        self::assertNull($check->trader);
    }

    public function testReadsTheStatus(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('status')));

        $status = $this->client($http)->status();

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://ec.europa.eu/taxation_customs/vies/rest-api/check-status', $http->requests[0]->uri);
        self::assertTrue($status->available);
        self::assertSame(['AT', 'DE', 'ES'], array_map(static fn($country): string => $country->countryCode, $status->countries));
        self::assertSame([Availability::Available, Availability::MonitoringDisabled, Availability::Unavailable], array_map(static fn($country): Availability => $country->availability, $status->countries));
    }

    public function testErrorWrappersRaiseViesExceptionEvenWith200(): void
    {
        $http = new MockHttpClient(new Response(200, '{"actionSucceed":false,"errorWrappers":[{"error":"MS_UNAVAILABLE","message":"The member state is unavailable"}]}'));

        try {
            $this->client($http)->check(VatId::parse('ATU12345678'));
            self::fail('No exception.');
        } catch (ViesException $exception) {
            self::assertSame('MS_UNAVAILABLE', $exception->errorCode);
            self::assertSame(200, $exception->statusCode);
            self::assertStringContainsString('The member state is unavailable', $exception->getMessage());
        }
    }

    public function testOtherErrorsRaiseTheirApiException(): void
    {
        $http = new MockHttpClient(new Response(502, '<html>Bad gateway</html>'), new Response(502, ''), new Response(502, ''));

        $this->expectException(ServerException::class);

        $this->client($http)->status();
    }

    public function testHtmlWith200IsASerializationError(): void
    {
        $this->expectException(SerializationException::class);

        $this->client(new MockHttpClient(new Response(200, '<html>maintenance</html>')))->check(VatId::parse('ATU12345678'));
    }

    public function testRequesterIsNotInExceptionsOrDebugInfo(): void
    {
        $client = $this->client(new MockHttpClient(new Response(400, '{"actionSucceed":false,"errorWrappers":[{"error":"INVALID_REQUESTER_INFO"}]}')));

        self::assertStringNotContainsString('999888777', print_r($client, true));

        try {
            $client->check(VatId::parse('ATU12345678'));
            self::fail('No exception.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(ApiException::class, $exception);
            self::assertStringNotContainsString('999888777', $exception->getMessage());
            self::assertStringNotContainsString('999888777', $exception->getTraceAsString());
        }
    }

    public function testTestServiceUsesItsPath(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('check-valid')));

        $this->client($http)->checkTestService(VatId::parse('ATU12345678'));

        self::assertSame('https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-test-service', $http->requests[0]->uri);
    }

    private function client(MockHttpClient $http): Vies
    {
        return new Vies(VatId::parse(self::REQUESTER), new ClientOptions(retryBaseDelay: 0.0), $http);
    }

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . "/Fixtures/vies/{$name}.json");
    }
}
