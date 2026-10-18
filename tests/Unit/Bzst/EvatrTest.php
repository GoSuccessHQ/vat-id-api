<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Tests\Unit\Bzst;

use GoSuccess\VatId\Bzst\Company;
use GoSuccess\VatId\Bzst\Comparison;
use GoSuccess\VatId\Bzst\Evatr;
use GoSuccess\VatId\Bzst\EvatrStatus;
use GoSuccess\VatId\Bzst\StatusCategory;
use GoSuccess\VatId\ClientOptions;
use GoSuccess\VatId\Exception\SerializationException;
use GoSuccess\VatId\Http\Response;
use GoSuccess\VatId\Tests\Support\MockHttpClient;
use GoSuccess\VatId\VatId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversNamespace('GoSuccess\VatId\Bzst')]
final class EvatrTest extends TestCase
{
    private const string REQUESTER = 'DE999888777';

    public function testSimpleConfirmationSendsBothIds(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('valid-qualified')));

        $this->client($http)->confirm(VatId::parse('ATU12345678'));

        self::assertSame('POST', $http->requests[0]->method->value);
        self::assertSame('https://api.evatr.vies.bzst.de/v1/abfrage', $http->requests[0]->uri);
        self::assertSame(['anfragendeUstid' => self::REQUESTER, 'angefragteUstid' => 'ATU12345678'], $http->jsonBody());
    }

    public function testQualifiedConfirmationSendsTheCompany(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('valid-qualified')));

        $this->client($http)->confirm(VatId::parse('ATU12345678'), new Company('Musterhaus GmbH & Co KG', 'musterort', 'Musterstrasse 22', '12345'));

        self::assertSame(
            ['anfragendeUstid' => self::REQUESTER, 'angefragteUstid' => 'ATU12345678', 'firmenname' => 'Musterhaus GmbH & Co KG', 'ort' => 'musterort', 'strasse' => 'Musterstrasse 22', 'plz' => '12345'],
            $http->jsonBody(),
        );
    }

    public function testRequesterPerCallOverridesTheDefault(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('valid-qualified')));

        $this->client($http)->confirm(VatId::parse('ATU12345678'), requester: VatId::parse('DE111222333'));

        self::assertSame('DE111222333', $http->jsonBody()['anfragendeUstid']);
    }

    public function testOnlyAGermanRequesterIsAccepted(): void
    {
        $http = new MockHttpClient();

        try {
            $this->client($http)->confirm(VatId::parse('ATU12345678'), requester: VatId::parse('FR12345678901'));
            self::fail('No exception.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $http->callCount());
        }
    }

    public function testARequesterIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Evatr(options: new ClientOptions(retryBaseDelay: 0.0), httpClient: new MockHttpClient())->confirm(VatId::parse('ATU12345678'));
    }

    public function testReadsAValidQualifiedAnswer(): void
    {
        $confirmation = $this->client(new MockHttpClient(new Response(200, self::fixture('valid-qualified'))))->confirm(VatId::parse('ATU12345678'));

        self::assertSame(EvatrStatus::Code0000, $confirmation->status);
        self::assertTrue($confirmation->isValid());
        self::assertSame(StatusCategory::Result, $confirmation->category());
        self::assertSame(200, $confirmation->httpStatus);
        self::assertSame('9a1b2c3d4e5f6a7b', $confirmation->id);
        self::assertSame(Comparison::Matches, $confirmation->name);
        self::assertSame(Comparison::DoesNotMatch, $confirmation->street);
        self::assertSame(Comparison::NotRequested, $confirmation->postalCode);
        self::assertSame(Comparison::NotProvided, $confirmation->city);
    }

    public function testNotAllocatedIsAResultNotAnException(): void
    {
        $confirmation = $this->client(new MockHttpClient(new Response(404, self::fixture('not-allocated'))))->confirm(VatId::parse('ATU12345678'));

        self::assertSame(EvatrStatus::Code2001, $confirmation->status);
        self::assertFalse($confirmation->isValid());
        self::assertSame(404, $confirmation->httpStatus);
        self::assertNull($confirmation->name);
    }

    public function testInputErrorIsAResult(): void
    {
        $confirmation = $this->client(new MockHttpClient(new Response(400, self::fixture('input-error'))))->confirm(VatId::parse('ATU12345678'));

        self::assertSame(EvatrStatus::Code0004, $confirmation->status);
        self::assertSame(StatusCategory::Error, $confirmation->category());
    }

    public function testServiceUnavailableIsRetriedThenReturned(): void
    {
        $body = self::fixture('unavailable');
        $http = new MockHttpClient(new Response(503, $body), new Response(503, $body), new Response(503, $body));

        $confirmation = $this->client($http)->confirm(VatId::parse('ATU12345678'));

        self::assertSame(3, $http->callCount());
        self::assertSame(EvatrStatus::Code0011, $confirmation->status);
        self::assertSame(503, $confirmation->httpStatus);
    }

    public function testUnknownStatusIsKeptRaw(): void
    {
        $confirmation = $this->client(new MockHttpClient(new Response(200, self::fixture('unknown'))))->confirm(VatId::parse('ATU12345678'));

        self::assertSame('evatr-9999', $confirmation->status);
        self::assertSame(StatusCategory::Unknown, $confirmation->category());
        self::assertFalse($confirmation->isValid());
    }

    public function testHtmlIsASerializationError(): void
    {
        $this->expectException(SerializationException::class);

        $this->client(new MockHttpClient(new Response(200, '<html>maintenance</html>')))->confirm(VatId::parse('ATU12345678'));
    }

    public function testHtmlErrorPageIsAnApiException(): void
    {
        $http = new MockHttpClient(new Response(403, '<html>Forbidden</html>'));

        $this->expectExceptionCode(403);

        $this->client($http)->confirm(VatId::parse('ATU12345678'));
    }

    public function testNanosecondTimestampIsRead(): void
    {
        $confirmation = $this->client(new MockHttpClient(new Response(200, self::fixture('valid-qualified'))))->confirm(VatId::parse('ATU12345678'));

        self::assertSame('2026-10-18T17:19:05.064073+02:00', $confirmation->requestedAt->format('Y-m-d\TH:i:s.uP'));
    }

    public function testValidFromAndUntil(): void
    {
        $confirmation = $this->client(new MockHttpClient(new Response(200, self::fixture('expired'))))->confirm(VatId::parse('ATU12345678'));

        self::assertSame(EvatrStatus::Code2006, $confirmation->status);
        self::assertSame('2020-01-01', $confirmation->validFrom?->format('Y-m-d'));
        self::assertSame('2024-12-31', $confirmation->validUntil?->format('Y-m-d'));
    }

    public function testRequesterIsNotInExceptionsOrDebugInfo(): void
    {
        $client = $this->client(new MockHttpClient(new Response(500, 'Internal error'), new Response(500, ''), new Response(500, '')));

        self::assertStringNotContainsString('999888777', print_r($client, true));

        try {
            $client->confirm(VatId::parse('ATU12345678'));
            self::fail('No exception.');
        } catch (Throwable $exception) {
            self::assertStringNotContainsString('999888777', $exception->getMessage());
            self::assertStringNotContainsString('999888777', $exception->getTraceAsString());
        }
    }

    public function testReadsStatusMessagesAndMemberStates(): void
    {
        $http = new MockHttpClient(
            new Response(200, (string) file_get_contents(\dirname(__DIR__, 2) . '/Fixtures/bzst/statusmeldungen.json')),
            new Response(200, '[{"alpha2":"AT","name":"Österreich","verfuegbar":true},{"alpha2":"DE","name":"Deutschland","verfuegbar":false}]'),
        );
        $client = $this->client($http);

        $messages = $client->statusMessages();
        $states = $client->memberStates();

        self::assertSame('https://api.evatr.vies.bzst.de/v1/info/statusmeldungen', $http->requests[0]->uri);
        self::assertSame('https://api.evatr.vies.bzst.de/v1/info/eu_mitgliedstaaten', $http->requests[1]->uri);
        self::assertCount(26, $messages);
        self::assertSame('evatr-0000', $messages[0]->status);
        self::assertSame(StatusCategory::Result, $messages[0]->category);
        self::assertSame(200, $messages[0]->httpStatus);
        self::assertNull($messages[0]->field);
        self::assertSame('firmenname,ort', $messages[3]->field);
        self::assertSame(['AT', 'DE'], array_map(static fn($state): string => $state->countryCode, $states));
        self::assertSame('Österreich', $states[0]->name);
        self::assertFalse($states[1]->available);
    }

    private function client(MockHttpClient $http): Evatr
    {
        return new Evatr(VatId::parse(self::REQUESTER), new ClientOptions(retryBaseDelay: 0.0), $http);
    }

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . "/Fixtures/bzst/{$name}.json");
    }
}
