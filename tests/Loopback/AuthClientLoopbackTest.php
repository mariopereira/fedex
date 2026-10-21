<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Loopback;

use Mariopereira\Fedex\Api\FedexPostalCodeValidation;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexPostalCodeValidationException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Tests\Support\LoopbackServer;
use Mariopereira\Fedex\Tests\Support\LoopbackTestCase;
use Mariopereira\Fedex\Tests\Support\RewriteTransport;

/**
 * Tests of the real FedexAuth and FedexClient (and the postal code API on top of them) with the real cURL, against the
 * throwaway server on 127.0.0.1: the token request, the retry after a 401, the timeouts and the failures
 *
 * @group loopback
 */
final class AuthClientLoopbackTest extends LoopbackTestCase
{
    // ---------------------------------------------------------------- FedexAuth over the real transport

    /**
     * The token is stored with its scope and duration
     * @return void
     */
    public function testTheTokenIsStoredWithItsScopeAndDuration(): void
    {
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, new RewriteTransport(self::$server->port()));

        $auth->authorize();
        $request = self::$server->requests()[0];
        parse_str($request['body'], $sent);

        $this->assertTrue($auth->hasToken());
        $this->assertSame('TOK', $auth->getToken());
        $this->assertSame('CXS', $auth->getTokenData(false)->scope);
        $this->assertGreaterThanOrEqual(3538, $auth->getTokenData(false)->duration);
        $this->assertSame('POST /token-ok HTTP/1.1', strtok($request['head'], "\r\n"));
        $this->assertContains('Content-Type: application/x-www-form-urlencoded', LoopbackServer::headerLines($request));
        $this->assertSame(['grant_type' => 'client_credentials', 'client_id' => 'id', 'client_secret' => 'secret'], $sent);
    }

    /**
     * @dataProvider tokenFailuresProvider
     * @param string $route The route of the server that stands for the token url
     * @param int $code The code of the exception
     * @param int $status The HTTP status
     * @param string|null $transactionId The transaction id in the exception
     * @param string $errors The errors, as a text
     */
    public function testATokenRequestThatFails(string $route, int $code, int $status, ?string $transactionId, string $errors): void
    {
        $transport = new RewriteTransport(self::$server->port());
        $transport->tokenRoute = $route;
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $transport);

        $e = self::thrown(function () use ($auth) {
            $auth->authorize();
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame($code, $e->getCode());
        $this->assertSame($status, $e->getStatusCode());
        $this->assertSame($transactionId, $e->getTransactionId());
        $this->assertSame($errors, $e->getErrorsAsString());
        $this->assertFalse($auth->hasToken());
    }

    /**
     * The cases of testATokenRequestThatFails(): a token request that fails
     * @return array
     */
    public function tokenFailuresProvider(): array
    {
        $auth = FedexException::EC_UNABLE_TO_AUTHENTICATE;
        $transport = FedexException::EC_TRANSPORT_ERROR;

        return [
            '/token-401' => ['/token-401', $auth, 401, '624deea6', 'NOT.AUTHORIZED.ERROR: The given client credentials were not valid.'],
            '/status/400' => ['/status/400', $transport, 400, 'T-400', 'ERR.400: status 400'],
            '/status/500' => ['/status/500', $transport, 500, 'T-500', 'ERR.500: status 500'],
            '/html502' => ['/html502', $transport, 502, null, ''],
            '/nobody' => ['/nobody', $transport, 204, null, ''],
        ];
    }

    /**
     * A 200 with a body that is not JSON has code 101 and the body is not kept
     * @return void
     */
    public function testA200WithABodyThatIsNotJsonHasCode101AndTheBodyIsNotKept(): void
    {
        $transport = new RewriteTransport(self::$server->port());
        $transport->tokenRoute = '/bad-json';
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $transport);

        $e = self::thrown(function () use ($auth) {
            $auth->authorize();
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertStringStartsWith('Invalid data received from server (', $e->getMessage());
        $this->assertNull($e->getData());
    }

    /**
     * A connection refused is a transport error
     * @return void
     */
    public function testAConnectionRefusedIsATransportError(): void
    {
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, new RewriteTransport(self::$closedPort));

        $e = self::thrown(function () use ($auth) {
            $auth->authorize();
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertStringStartsWith('Transport error: ', $e->getMessage());
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
    }

    /**
     * A token request that times out fails quickly
     * @return void
     */
    public function testATokenRequestThatTimesOutFailsQuickly(): void
    {
        $transport = new RewriteTransport(self::$server->port());
        $transport->tokenRoute = '/slow';
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $transport);
        $auth->setTimeouts(1, 1);
        $start = microtime(true);

        $e = self::thrown(function () use ($auth) {
            $auth->authorize();
        });
        $elapsed = microtime(true) - $start;

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertStringContainsStringIgnoringCase('timed out', $e->getMessage());
        $this->assertLessThan(2.5, $elapsed);
        $this->assertCount(1, $transport->calls);
        $this->assertSame(1, $transport->calls[0]['timeout']);

        self::waitForTheSlowRoute();
    }

    /**
     * The token cache over the real transport
     * @return void
     */
    public function testTheTokenCacheOverTheRealTransport(): void
    {
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, new RewriteTransport(self::$server->port()));

        $auth->getToken();
        $auth->getToken();
        $auth->getToken(true);

        $this->assertSame(['POST /token-ok', 'POST /token-ok'], self::$server->seen(), 'two requests for three calls, one of them forced');
    }

    // ---------------------------------------------------------------- FedexClient and FedexAuth together

    /**
     * The token is requested first and the call carries it with the other headers
     * @return void
     */
    public function testTheTokenIsRequestedFirstAndTheCallCarriesItWithTheOtherHeaders(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());

        $result = $client->request('POST', 'echo', ['a' => 1], ['q' => 'x y', 'n' => 'ü'], ['X-Extra' => 'yes'], 'CTID-7');
        $requests = self::$server->requests();
        $headers = LoopbackServer::headerLines($requests[1]);

        $this->assertSame(['POST /token-ok', 'POST /echo?q=x+y&n=%C3%BC'], self::$server->seen());
        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('Accept: application/json', $headers);
        $this->assertContains('X-locale: pt_PT', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('X-Extra: yes', $headers);
        $this->assertContains('X-customer-transaction-id: CTID-7', $headers);
        $this->assertSame('{"a":1}', $requests[1]['body']);
        $this->assertSame('POST', $result->method);
    }

    /**
     * The token is requested only once for two calls
     * @return void
     */
    public function testTheTokenIsRequestedOnlyOnceForTwoCalls(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());

        $client->request('GET', 'ok');
        $client->request('GET', 'ok');

        $this->assertSame(['POST /token-ok', 'GET /ok', 'GET /ok'], self::$server->seen());
    }

    /**
     * A 401 then a 200 is retried once with a fresh token
     * @return void
     */
    public function testA401ThenA200IsRetriedOnceWithAFreshToken(): void
    {
        self::$server->get('/flaky-reset');
        self::$server->forget();
        [, $client] = self::chain(self::$server->port(), self::$server->port());

        $result = $client->request('GET', 'flaky');

        $this->assertTrue($result->ok);
        $this->assertSame(2, $result->attempt);
        $this->assertSame(['POST /token-ok', 'GET /flaky', 'POST /token-ok', 'GET /flaky'], self::$server->seen());
    }

    /**
     * Two 401 s stop after two calls with the auth code
     * @return void
     */
    public function testTwo401sStopAfterTwoCallsWithTheAuthCode(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());

        $e = self::thrown(function () use ($client) {
            $client->request('GET', 'always-401');
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_UNABLE_TO_AUTHENTICATE, $e->getCode());
        $this->assertSame(401, $e->getStatusCode());
        $this->assertSame(['POST /token-ok', 'GET /always-401', 'POST /token-ok', 'GET /always-401'], self::$server->seen());
    }

    /**
     * Bad credentials make one token request and no API request
     * @return void
     */
    public function testBadCredentialsMakeOneTokenRequestAndNoApiRequest(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port(), '/token-401');

        $e = self::thrown(function () use ($client) {
            $client->request('GET', 'ok');
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_UNABLE_TO_AUTHENTICATE, $e->getCode());
        $this->assertSame(['POST /token-401'], self::$server->seen());
    }

    /**
     * An HTTP 500 is not retried and its errors are parsed
     * @return void
     */
    public function testAnHttp500IsNotRetriedAndItsErrorsAreParsed(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());

        $e = self::thrown(function () use ($client) {
            $client->request('GET', 'status/500');
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(500, $e->getStatusCode());
        $this->assertSame('T-500', $e->getTransactionId());
        $this->assertSame('C-500', $e->getCustomerTransactionId());
        $this->assertSame('ERR.500: status 500', $e->getErrorsAsString());
        $this->assertSame(['POST /token-ok', 'GET /status/500'], self::$server->seen());
    }

    /**
     * A 200 with a body that is not JSON has code 101
     * @return void
     */
    public function testA200WithABodyThatIsNotJsonHasCode101(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());

        $e = self::thrown(function () use ($client) {
            $client->request('GET', 'bad-json');
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
    }

    /**
     * A 204 with no body gives null
     * @return void
     */
    public function testA204WithNoBodyGivesNull(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());

        $this->assertNull($client->request('GET', 'nobody'));
    }

    /**
     * An API timeout is a transport error not retried and quick
     * @return void
     */
    public function testAnApiTimeoutIsATransportErrorNotRetriedAndQuick(): void
    {
        [, $client, , $apiTransport] = self::chain(self::$server->port(), self::$server->port(), '/token-ok', 1, 1);
        $start = microtime(true);

        $e = self::thrown(function () use ($client) {
            $client->request('GET', 'slow');
        });
        $elapsed = microtime(true) - $start;

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertStringStartsWith('Transport error: ', $e->getMessage());
        $this->assertStringContainsStringIgnoringCase('timed out', $e->getMessage());
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
        $this->assertLessThan(2.5, $elapsed);
        $this->assertCount(1, $apiTransport->calls);
        $this->assertSame(1, $apiTransport->calls[0]['timeout']);

        self::waitForTheSlowRoute();
    }

    // ---------------------------------------------------------------- the postal code API end to end

    /**
     * The postal code API end to end
     * @return void
     */
    public function testThePostalCodeApiEndToEnd(): void
    {
        $port = self::$server->port();
        $authTransport = new RewriteTransport($port);
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $authTransport);
        $postal = new FedexPostalCodeValidation($auth, 'en_US', 30, 10, 1, 1, 1, new FedexClient($auth, 'en_US', 30, 10, new RewriteTransport($port)));

        $out = $postal->validate('fdxg', 'AU', 'NSW', '2000', '2026-10-21', 'R1', true, ' Sydney ');
        $requests = self::$server->requests();
        $sent = json_decode($requests[1]['body']);

        $this->assertSame(['POST /token-ok', 'POST /country/v1/postal/validate'], self::$server->seen());
        $this->assertSame('FDXG', $sent->carrierCode, 'the carrier code is normalized');
        $this->assertSame('Sydney', $sent->city, 'the city is trimmed');
        $this->assertSame('AU', $sent->countryCode);
        $this->assertSame('R1', $sent->routingCode);
        $this->assertTrue($sent->checkForMismatch);
        $this->assertSame(1, $sent->version->major);
        $this->assertContains('Authorization: Bearer TOK', LoopbackServer::headerLines($requests[1]));
        $this->assertNotEmpty(preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', LoopbackServer::headerLines($requests[1])));
        $this->assertInstanceOf(\stdClass::class, $out);
        $this->assertSame('POST', $out->method);
    }

    /**
     * An invalid carrier never reaches the network
     * @return void
     */
    public function testAnInvalidCarrierNeverReachesTheNetwork(): void
    {
        $port = self::$server->port();
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, new RewriteTransport($port));
        $postal = new FedexPostalCodeValidation($auth, 'en_US', 30, 10, 1, 1, 1, new FedexClient($auth, 'en_US', 30, 10, new RewriteTransport($port)));

        $e = self::thrown(function () use ($postal) {
            $postal->validate('XXXX', 'US', 'TN', '38017', '2026-10-21');
        });

        $this->assertInstanceOf(FedexPostalCodeValidationException::class, $e);
        $this->assertSame([], self::$server->seen());
    }
}
