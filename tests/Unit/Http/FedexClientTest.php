<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Http;

use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\CurlTransport;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\ProbeClient;
use Mariopereira\Fedex\Tests\Support\Reflect;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the client: what it sends through the transport and how it handles what comes back
 */
final class FedexClientTest extends TestCase
{
    /**
     * A client over a fake transport that always gives the same answer
     * @param FakeTransport $transport The transport
     * @param StubAuth|null $auth The authorization, a sandbox one by default
     * @return FedexClient
     */
    private function client(FakeTransport $transport, ?StubAuth $auth = null): FedexClient
    {
        return new FedexClient($auth ?? StubAuth::make(), 'en_US', 30, 10, $transport);
    }

    /**
     * Make a request that has to fail and give the exception
     * @param FedexClient $client The client
     * @return \Throwable
     */
    private function failure(FedexClient $client): \Throwable
    {
        try {
            $client->request('GET', 'x');
        } catch (\Throwable $e) {
            return $e;
        }

        $this->fail('The request had to throw');
    }

    // ---------------------------------------------------------------- what is sent

    /**
     * The request that is sent
     * @return void
     */
    public function testTheRequestThatIsSent(): void
    {
        $transport = new FakeTransport([], Responses::make(200, '{"ok":true}'));
        $auth = StubAuth::make();

        $result = $this->client($transport, $auth)->request('post', '/some/path', ['a' => 1, 'u' => 'çã'], ['q' => 'x y', 'n' => 2], ['X-Extra' => 'yes'], 'CTID-9');
        $call = $transport->calls[0];

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertTrue($result->ok);
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/some/path?q=x+y&n=2', $call['url']);
        $this->assertSame(
            ['Authorization: Bearer TOK', 'Accept: application/json', 'X-locale: en_US', 'Content-Type: application/json', 'X-Extra: yes', 'X-customer-transaction-id: CTID-9'],
            $call['headers']
        );
        $this->assertSame(json_encode(['a' => 1, 'u' => 'çã']), $call['body']);
        $this->assertSame(30, $call['timeout']);
        $this->assertSame(10, $call['connectTimeout']);
        $this->assertSame([false], $auth->forceSeen, 'the first attempt does not force a new token');
    }

    /**
     * Production URL trimmed locale and timeouts capped
     * @return void
     */
    public function testProductionUrlTrimmedLocaleAndTimeoutsCapped(): void
    {
        $transport = new FakeTransport([], Responses::make(200, '{"ok":true}'));
        $client = new FedexClient(new StubAuth(false, 'client_credentials', 'id', 'secret'), '  pt_PT ', 99, 99, $transport);

        $client->request('GET', 'x');
        $call = $transport->calls[0];

        $this->assertSame('https://apis.fedex.com/x', $call['url']);
        $this->assertContains('X-locale: pt_PT', $call['headers']);
        $this->assertSame(30, $call['timeout']);
        $this->assertSame(10, $call['connectTimeout']);
        $this->assertNull($call['body'], 'a request without data has no body');
    }

    /**
     * An empty body gives null
     * @return void
     */
    public function testAnEmptyBodyGivesNull(): void
    {
        $transport = new FakeTransport([], Responses::make(204, ''));

        $this->assertNull($this->client($transport)->request('GET', 'x'));
    }

    /**
     * The default transport is cURL
     * @return void
     */
    public function testTheDefaultTransportIsCurl(): void
    {
        $this->assertInstanceOf(CurlTransport::class, Reflect::prop(new FedexClient(StubAuth::make()), FedexClient::class, 'transport'));
    }

    /**
     * @dataProvider bodiesProvider
     * @param mixed $data The data to send
     * @param string|null $expected The body that has to go out
     */
    public function testTheBodyThatGoesOut($data, ?string $expected): void
    {
        $transport = new FakeTransport([], Responses::make(200, '{}'));

        $this->client($transport)->request('POST', 'x/y', $data);

        $this->assertSame($expected, $transport->calls[0]['body']);
    }

    /**
     * The cases of testTheBodyThatGoesOut(): the body that goes out
     * @return array
     */
    public function bodiesProvider(): array
    {
        return [
            'an array' => [['a' => 1, 'b' => [1, 2]], '{"a":1,"b":[1,2]}'],
            'an object' => [(object) ['k' => 'v'], '{"k":"v"}'],
            'nothing' => [null, null],
        ];
    }

    // ---------------------------------------------------------------- data that cannot be encoded

    /**
     * @dataProvider unencodableProvider
     * @param array $data Data that cannot be written as JSON
     */
    public function testDataThatCannotBeEncodedFailsBeforeAnythingIsSent(array $data): void
    {
        $transport = new FakeTransport([], Responses::make(200, '{}'));

        try {
            $this->client($transport)->request('POST', 'x/y', $data);
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
            $this->assertSame(0, $e->getStatusCode());
            $this->assertStringNotContainsString('secretValue', $e->getMessage(), 'the data is not written in the message');
            $this->assertSame([], $transport->calls, 'nothing is sent');
        }
    }

    /**
     * The cases of testDataThatCannotBeEncodedFailsBeforeAnythingIsSent(): data that cannot be encoded fails before anything is sent
     * @return array
     */
    public function unencodableProvider(): array
    {
        $deep = [];
        $node = &$deep;

        for ($i = 0; $i < 600; $i++) {
            $node['a'] = [];
            $node = &$node['a'];
        }

        unset($node);

        return [
            'invalid UTF-8' => [['secretValue' => "\xB1\x31"]],
            'NAN' => [['n' => NAN]],
            'INF' => [['n' => INF]],
            'too deep' => [$deep],
        ];
    }

    // ---------------------------------------------------------------- what comes back

    /**
     * @dataProvider errorStatusesProvider
     * @param int $status HTTP status
     * @param string $body Response body
     * @param int $code The code that the exception has to have
     */
    public function testAnErrorStatusIsAClientExceptionWithTheStatusAndTheRightCode(int $status, string $body, int $code): void
    {
        $e = $this->failure($this->client(new FakeTransport([], Responses::make($status, $body))));

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame($code, $e->getCode());
        $this->assertSame($status, $e->getStatusCode());
    }

    /**
     * The cases of testAnErrorStatusIsAClientExceptionWithTheStatusAndTheRightCode(): an error status is a client exception with the status and the right code
     * @return array
     */
    public function errorStatusesProvider(): array
    {
        $auth = FedexException::EC_UNABLE_TO_AUTHENTICATE;
        $transport = FedexException::EC_TRANSPORT_ERROR;

        return [
            '400' => [400, '{"transactionId":"T","customerTransactionId":"C","errors":[{"code":"BAD","message":"m"}]}', $transport],
            '401' => [401, '{"transactionId":"T","errors":[{"code":"NOT.AUTHORIZED.ERROR","message":"expired"}]}', $auth],
            '401 without a body' => [401, '', $auth],
            '403' => [403, '{"errors":[]}', $transport],
            '404' => [404, '{"errors":[]}', $transport],
            '429' => [429, '{"errors":[]}', $transport],
            '500' => [500, '{"errors":"boom"}', $transport],
            '503 without a body' => [503, '', $transport],
            '302 (redirects are not followed)' => [302, '', $transport],
        ];
    }

    /**
     * The details of an error are parsed
     * @return void
     */
    public function testTheDetailsOfAnErrorAreParsed(): void
    {
        $body = '{"transactionId":"T","customerTransactionId":"C","errors":[{"code":"BAD","message":"m"}]}';

        $e = $this->failure($this->client(new FakeTransport([], Responses::make(400, $body))));

        $this->assertSame('T', $e->getTransactionId());
        $this->assertSame('C', $e->getCustomerTransactionId());
        $this->assertSame('BAD: m', $e->getErrorsAsString());
    }

    /**
     * @dataProvider notJsonProvider
     * @param int $status HTTP status
     * @param string $body Response body
     * @param string $message The message that the exception has to have
     */
    public function testABodyThatIsNotJson(int $status, string $body, string $message): void
    {
        $e = $this->failure($this->client(new FakeTransport([], Responses::make($status, $body))));

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertSame($message, $e->getMessage());
        $this->assertSame($status, $e->getStatusCode());
    }

    /**
     * The cases of testABodyThatIsNotJson(): a body that is not JSON
     * @return array
     */
    public function notJsonProvider(): array
    {
        return [
            'a success' => [200, 'not json', 'Invalid JSON response (HTTP 200)'],
            'a bad gateway' => [502, '<html>x</html>', 'Invalid JSON response (HTTP 502)'],
        ];
    }

    /**
     * A transport failure is reported and not retried
     * @return void
     */
    public function testATransportFailureIsReportedAndNotRetried(): void
    {
        $transport = new FakeTransport([], Responses::make(0, '', 'Failed to connect'));

        $e = $this->failure($this->client($transport));

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame('Transport error: Failed to connect', $e->getMessage());
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
        $this->assertCount(1, $transport->calls);
    }

    // ---------------------------------------------------------------- retries

    /**
     * A 401 is retried once with a fresh token
     * @return void
     */
    public function testA401IsRetriedOnceWithAFreshToken(): void
    {
        $auth = StubAuth::make();
        $transport = new FakeTransport([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"ok":"second"}')]);

        $result = $this->client($transport, $auth)->request('GET', 'x');

        $this->assertSame('second', $result->ok);
        $this->assertCount(2, $transport->calls);
        $this->assertSame([false, true], $auth->forceSeen, 'the retry forces a new token');
    }

    /**
     * Two 401 s in a row stop after two requests
     * @return void
     */
    public function testTwo401sInARowStopAfterTwoRequests(): void
    {
        $transport = new FakeTransport([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"ok":1}')]);

        $e = $this->failure($this->client($transport));

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_UNABLE_TO_AUTHENTICATE, $e->getCode());
        $this->assertCount(2, $transport->calls);
    }

    /**
     * A failed token request is not retried and nothing is sent
     * @return void
     */
    public function testAFailedTokenRequestIsNotRetriedAndNothingIsSent(): void
    {
        $auth = StubAuth::make();
        $auth->throwOnToken = new FedexAuthException('token failed', FedexException::EC_UNABLE_TO_AUTHENTICATE, null, 401);
        $transport = new FakeTransport([], Responses::make(200, '{"ok":1}'));

        $e = $this->failure($this->client($transport, $auth));

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame([false], $auth->forceSeen);
        $this->assertSame([], $transport->calls);
    }

    /**
     * @dataProvider notRetriedProvider
     * @param int $status HTTP status
     */
    public function testOtherErrorsAreNotRetried(int $status): void
    {
        $transport = new FakeTransport([Responses::make($status, '{"errors":[]}'), Responses::make(200, '{"ok":1}')]);

        $e = $this->failure($this->client($transport));

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame($status, $e->getStatusCode());
        $this->assertCount(1, $transport->calls);
    }

    /**
     * The cases of testOtherErrorsAreNotRetried(): other errors are not retried
     * @return array
     */
    public function notRetriedProvider(): array
    {
        return [[400], [403], [404], [429], [500], [503]];
    }

    /**
     * A success is a single request
     * @return void
     */
    public function testASuccessIsASingleRequest(): void
    {
        $auth = StubAuth::make();
        $transport = new FakeTransport([], Responses::make(200, '{"ok":"fine"}'));

        $this->assertSame('fine', $this->client($transport, $auth)->request('GET', 'a')->ok);
        $this->assertCount(1, $transport->calls);
        $this->assertSame([false], $auth->forceSeen);
    }

    // ---------------------------------------------------------------- parse()

    /**
     * @dataProvider oddErrorBodiesProvider
     * @param string $raw A response body with errors that are odd in one way or another
     */
    public function testOddErrorBodiesNeverRaiseATypeError(string $raw): void
    {
        $client = new ProbeClient(StubAuth::make());

        try {
            $client->callParse($raw, 400);
            $this->fail('parse() had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame(400, $e->getStatusCode());
        }
    }

    /**
     * The cases of testOddErrorBodiesNeverRaiseATypeError(): odd error bodies never raise a type error
     * @return array
     */
    public function oddErrorBodiesProvider(): array
    {
        return [
            'errors as a string' => ['{"errors":"boom"}'],
            'errors as an object' => ['{"errors":{"code":"A","message":"m"}}'],
            'errors as a number' => ['{"errors":5}'],
            'transactionId as a list' => ['{"transactionId":[],"errors":[]}'],
            'transactionId as an object' => ['{"transactionId":{},"errors":[]}'],
            'customerTransactionId as a list' => ['{"customerTransactionId":[1],"errors":[]}'],
            'a JSON string' => ['"oops"'],
            'a JSON list' => ['[1,2]'],
        ];
    }

    /**
     * A string error keeps the transaction IDs
     * @return void
     */
    public function testAStringErrorKeepsTheTransactionIds(): void
    {
        try {
            (new ProbeClient(StubAuth::make()))->callParse('{"errors":"boom","transactionId":"T9","customerTransactionId":"C9"}', 400);
            $this->fail('parse() had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame(['boom'], $e->getErrors());
            $this->assertSame('boom', $e->getErrorsAsString());
            $this->assertSame('T9', $e->getTransactionId());
            $this->assertSame('C9', $e->getCustomerTransactionId());
        }
    }

    /**
     * An error object is wrapped and written
     * @return void
     */
    public function testAnErrorObjectIsWrappedAndWritten(): void
    {
        try {
            (new ProbeClient(StubAuth::make()))->callParse('{"errors":{"code":"X","message":"bad"}}', 422);
            $this->fail('parse() had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame('X: bad', $e->getErrorsAsString());
        }
    }

    /**
     * IDs of the wrong type become null
     * @return void
     */
    public function testIdsOfTheWrongTypeBecomeNull(): void
    {
        try {
            (new ProbeClient(StubAuth::make()))->callParse('{"transactionId":[],"customerTransactionId":{},"errors":[]}', 500);
            $this->fail('parse() had to throw');
        } catch (FedexClientException $e) {
            $this->assertNull($e->getTransactionId());
            $this->assertNull($e->getCustomerTransactionId());
        }
    }

    /**
     * A normal error payload is unchanged
     * @return void
     */
    public function testANormalErrorPayloadIsUnchanged(): void
    {
        $raw = '{"transactionId":"T1","errors":[{"code":"A","message":"m","parameterList":[{"key":"k","value":"v"}]}]}';

        try {
            (new ProbeClient(StubAuth::make()))->callParse($raw, 400);
            $this->fail('parse() had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame('A: m Parameters: k = v', $e->getErrorsAsString());
            $this->assertSame('T1', $e->getTransactionId());
        }
    }

    /**
     * A 401 in parse is an authentication failure
     * @return void
     */
    public function testA401InParseIsAnAuthenticationFailure(): void
    {
        $client = new ProbeClient(StubAuth::make());

        foreach (['{"transactionId":"t1","errors":[]}', ''] as $raw) {
            try {
                $client->callParse($raw, 401);
                $this->fail('parse() had to throw');
            } catch (FedexClientException $e) {
                $this->assertSame(FedexException::EC_UNABLE_TO_AUTHENTICATE, $e->getCode());
                $this->assertSame(401, $e->getStatusCode());
            }
        }

        try {
            $client->callParse('{"transactionId":"t1","errors":[]}', 401);
        } catch (FedexClientException $e) {
            $this->assertSame('t1', $e->getTransactionId());
        }
    }

    /**
     * Other error statuses in parse are transport errors
     * @return void
     */
    public function testOtherErrorStatusesInParseAreTransportErrors(): void
    {
        $client = new ProbeClient(StubAuth::make());

        foreach ([400, 403, 500] as $status) {
            try {
                $client->callParse('{"errors":[]}', $status);
                $this->fail('parse() had to throw');
            } catch (FedexClientException $e) {
                $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
            }
        }
    }

    /**
     * A success in parse returns the data
     * @return void
     */
    public function testASuccessInParseReturnsTheData(): void
    {
        $this->assertSame(1, (new ProbeClient(StubAuth::make()))->callParse('{"a":1}', 200)->a);
    }
}
