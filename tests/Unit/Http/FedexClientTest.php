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

    // ---------------------------------------------------------------- retry with backoff

    /**
     * The delays that the sleeper received, in milliseconds
     * @var int[]
     */
    private $sleeps = [];

    /**
     * The minimum and the maximum that the jitter received, one pair for each wait
     * @var array
     */
    private $jitterCalls = [];

    /**
     * A client that retries, with a sleeper that records the delays instead of waiting and a jitter that always picks
     * the maximum
     * @param FakeTransport $transport The transport
     * @param int $attempts Total number of attempts
     * @param int $base Base delay in milliseconds
     * @param int $max Longest delay in milliseconds
     * @param StubAuth|null $auth The authorization
     * @return FedexClient
     */
    private function retrying(FakeTransport $transport, int $attempts = 3, int $base = 500, int $max = 5000, ?StubAuth $auth = null): FedexClient
    {
        $this->sleeps = [];
        $this->jitterCalls = [];

        return $this->client($transport, $auth)
            ->setRetryPolicy($attempts, $base, $max)
            ->setRetryHooks(
                function (int $ms) {
                    $this->sleeps[] = $ms;
                },
                function (int $min, int $max) {
                    $this->jitterCalls[] = [$min, $max];

                    return $max;
                }
            );
    }

    /**
     * Make a request that is safe to repeat
     * @param FedexClient $client The client
     * @return mixed
     */
    private function safeRequest(FedexClient $client)
    {
        return $client->request('POST', 'x', ['a' => 1], [], [], 'CT-1', true);
    }

    /**
     * Nothing is retried until a policy is set
     * @return void
     */
    public function testNothingIsRetriedUntilAPolicyIsSet(): void
    {
        $transport = new FakeTransport([Responses::make(503, '{"errors":[]}')], Responses::make(200, '{"ok":1}'));

        try {
            $this->safeRequest($this->client($transport));
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        $this->assertCount(1, $transport->calls);
    }

    /**
     * A request that is not marked as safe is never retried, even with a policy
     * @return void
     */
    public function testARequestThatIsNotMarkedAsSafeIsNeverRetried(): void
    {
        foreach ([Responses::make(503, '{"errors":[]}'), Responses::make(0, '', 'Operation timed out')] as $failure) {
            $transport = new FakeTransport([$failure], Responses::make(200, '{"ok":1}'));
            $client = $this->retrying($transport);

            try {
                $client->request('POST', 'x', ['a' => 1]);
                $this->fail('The request had to throw');
            } catch (FedexClientException $e) {
                $this->assertCount(1, $transport->calls);
            }

            $this->assertSame([], $this->sleeps);
        }
    }

    /**
     * A policy of one attempt is no retry
     * @return void
     */
    public function testAPolicyOfOneAttemptIsNoRetry(): void
    {
        $transport = new FakeTransport([Responses::make(503, '{"errors":[]}')], Responses::make(200, '{"ok":1}'));

        try {
            $this->safeRequest($this->retrying($transport, 1));
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertCount(1, $transport->calls);
            $this->assertSame([], $this->sleeps);
        }
    }

    /**
     * The statuses 429, 503 and 504 are retried until the request works
     * @return void
     */
    public function testTheStatuses429503And504AreRetriedUntilTheRequestWorks(): void
    {
        foreach ([429, 503, 504] as $status) {
            $transport = new FakeTransport([Responses::make($status, '{"errors":[]}'), Responses::make($status, '{"errors":[]}'), Responses::make(200, '{"ok":"third"}')]);

            $result = $this->safeRequest($this->retrying($transport));

            $this->assertSame('third', $result->ok, "HTTP $status");
            $this->assertCount(3, $transport->calls, "HTTP $status");
            $this->assertCount(2, $this->sleeps, "HTTP $status");
        }
    }

    /**
     * A failure of the transport is retried
     * @return void
     */
    public function testAFailureOfTheTransportIsRetried(): void
    {
        $transport = new FakeTransport([Responses::make(0, '', 'Operation timed out'), Responses::make(0, '', 'Could not resolve host'), Responses::make(200, '{"ok":"third"}')]);

        $result = $this->safeRequest($this->retrying($transport));

        $this->assertSame('third', $result->ok);
        $this->assertCount(3, $transport->calls);
    }

    /**
     * A 503 whose body is not JSON is retried by its status
     * @return void
     */
    public function testA503WhoseBodyIsNotJsonIsRetriedByItsStatus(): void
    {
        $transport = new FakeTransport([Responses::make(503, '<html>Service Unavailable</html>'), Responses::make(200, '{"ok":1}')]);

        $this->assertSame(1, $this->safeRequest($this->retrying($transport))->ok);
        $this->assertCount(2, $transport->calls);
    }

    /**
     * Any other failure is not retried
     * @return void
     */
    public function testAnyOtherFailureIsNotRetried(): void
    {
        $cases = [
            '400' => Responses::make(400, '{"errors":[]}'),
            '403' => Responses::make(403, '{"errors":[]}'),
            '404' => Responses::make(404, '{"errors":[]}'),
            '500' => Responses::make(500, '{"errors":[]}'),
            '502' => Responses::make(502, '{"errors":[]}'),
            '200 with a body that is not JSON' => Responses::make(200, 'not json'),
        ];

        foreach ($cases as $label => $failure) {
            $transport = new FakeTransport([$failure], Responses::make(200, '{"ok":1}'));

            try {
                $this->safeRequest($this->retrying($transport));
                $this->fail("$label had to throw");
            } catch (FedexClientException $e) {
                $this->assertCount(1, $transport->calls, $label);
                $this->assertSame([], $this->sleeps, $label);
            }
        }
    }

    /**
     * When the attempts are over the last failure is thrown
     * @return void
     */
    public function testWhenTheAttemptsAreOverTheLastFailureIsThrown(): void
    {
        $transport = new FakeTransport([Responses::make(503, '{"errors":[]}'), Responses::make(429, '{"errors":[]}'), Responses::make(504, '{"transactionId":"LAST","errors":[]}')], Responses::make(200, '{"ok":1}'));

        try {
            $this->safeRequest($this->retrying($transport));
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame(504, $e->getStatusCode());
            $this->assertSame('LAST', $e->getTransactionId());
        }

        $this->assertCount(3, $transport->calls, 'three attempts and not a fourth');
        $this->assertCount(2, $this->sleeps, 'no wait after the last attempt');
    }

    /**
     * A failure that is not transient stops the retries
     * @return void
     */
    public function testAFailureThatIsNotTransientStopsTheRetries(): void
    {
        $transport = new FakeTransport([Responses::make(503, '{"errors":[]}'), Responses::make(400, '{"errors":[]}'), Responses::make(200, '{"ok":1}')]);

        try {
            $this->safeRequest($this->retrying($transport, 5));
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame(400, $e->getStatusCode());
        }

        $this->assertCount(2, $transport->calls);
        $this->assertCount(1, $this->sleeps);
    }

    /**
     * The delay doubles up to the longest delay
     * @return void
     */
    public function testTheDelayDoublesUpToTheLongestDelay(): void
    {
        $transport = new FakeTransport([], Responses::make(503, '{"errors":[]}'));

        try {
            $this->safeRequest($this->retrying($transport, 10, 500, 5000));
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertCount(10, $transport->calls);
        }

        $this->assertSame([500, 1000, 2000, 4000, 5000, 5000, 5000, 5000, 5000], $this->sleeps);
        $this->assertSame(array_fill(0, 9, 0), array_column($this->jitterCalls, 0), 'the jitter starts at zero');
        $this->assertSame($this->sleeps, array_column($this->jitterCalls, 1), 'the jitter is given the cap and its answer is the delay');
    }

    /**
     * The jitter picks the delay
     * @return void
     */
    public function testTheJitterPicksTheDelay(): void
    {
        $transport = new FakeTransport([Responses::make(503, '{"errors":[]}'), Responses::make(200, '{"ok":1}')]);
        $slept = [];
        $client = $this->client($transport)->setRetryPolicy(3, 800, 5000)->setRetryHooks(
            function (int $ms) use (&$slept) {
                $slept[] = $ms;
            },
            function (int $min, int $max) {
                return 123;
            }
        );

        $this->safeRequest($client);

        $this->assertSame([123], $slept);
    }

    /**
     * The defaults of the policy are three attempts, half a second and five seconds
     * @return void
     */
    public function testTheDefaultsOfThePolicyAreThreeAttemptsHalfASecondAndFiveSeconds(): void
    {
        $transport = new FakeTransport([], Responses::make(503, '{"errors":[]}'));
        $this->sleeps = [];
        $client = $this->client($transport)->setRetryPolicy()->setRetryHooks(
            function (int $ms) {
                $this->sleeps[] = $ms;
            },
            function (int $min, int $max) {
                return $max;
            }
        );

        try {
            $this->safeRequest($client);
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertCount(3, $transport->calls);
        }

        $this->assertSame([500, 1000], $this->sleeps);
        $this->assertSame(500, Reflect::prop($client, FedexClient::class, 'baseDelayMs'));
        $this->assertSame(5000, Reflect::prop($client, FedexClient::class, 'maxDelayMs'));
    }

    /**
     * Without hooks the real sleep and random are used
     * @return void
     */
    public function testWithoutHooksTheRealSleepAndRandomAreUsed(): void
    {
        $transport = new FakeTransport([Responses::make(503, '{"errors":[]}'), Responses::make(200, '{"ok":1}')]);
        $client = $this->client($transport)->setRetryPolicy(2, 0, 0)->setRetryHooks(null, null);

        $this->assertSame(1, $this->safeRequest($client)->ok);
        $this->assertCount(2, $transport->calls);

        $transport = new FakeTransport([], Responses::make(503, '{"errors":[]}'));
        $client = $this->client($transport)->setRetryPolicy(4, 20, 20)->setRetryHooks(null, null);
        $start = microtime(true);

        try {
            $this->safeRequest($client);
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertCount(4, $transport->calls);
        }

        $this->assertLessThan(1.0, microtime(true) - $start, 'the delays are in milliseconds');
    }

    /**
     * The default jitter is random and null restores it after a jitter was set
     * @return void
     */
    public function testTheDefaultJitterIsRandomAndNullRestoresItAfterAJitterWasSet(): void
    {
        $transport = new FakeTransport([], Responses::make(503, '{"errors":[]}'));
        $slept = [];
        $client = $this->client($transport)->setRetryPolicy(10, 1000, 1000);
        $client->setRetryHooks(null, function (int $min, int $max) {
            return -1;
        });
        $client->setRetryHooks(function (int $ms) use (&$slept) {
            $slept[] = $ms;
        }, null);

        try {
            $this->safeRequest($client);
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertCount(10, $transport->calls);
        }

        $this->assertCount(9, $slept);

        foreach ($slept as $ms) {
            $this->assertGreaterThanOrEqual(0, $ms);
            $this->assertLessThanOrEqual(1000, $ms);
        }

        $this->assertNotSame(array_fill(0, 9, 1000), $slept, 'the delay is a random part of the cap, not the cap');
        $this->assertGreaterThan(1, count(array_unique($slept)), 'the delays are not all the same');
    }

    /**
     * Every attempt sends the same request
     * @return void
     */
    public function testEveryAttemptSendsTheSameRequest(): void
    {
        $transport = new FakeTransport([Responses::make(503, '{"errors":[]}'), Responses::make(0, '', 'timeout'), Responses::make(200, '{"ok":1}')]);

        $this->safeRequest($this->retrying($transport));
        $calls = $transport->calls;

        $this->assertCount(3, $calls);
        $this->assertSame($calls[0], $calls[1]);
        $this->assertSame($calls[0], $calls[2]);
        $this->assertContains('X-customer-transaction-id: CT-1', $calls[2]['headers']);
    }

    /**
     * The refresh of a rejected token does not count as an attempt
     * @return void
     */
    public function testTheRefreshOfARejectedTokenDoesNotCountAsAnAttempt(): void
    {
        $auth = StubAuth::make();
        $transport = new FakeTransport([Responses::make(401, '{"errors":[]}'), Responses::make(503, '{"errors":[]}'), Responses::make(200, '{"ok":"third"}')]);

        $result = $this->safeRequest($this->retrying($transport, 2, 500, 5000, $auth));

        $this->assertSame('third', $result->ok);
        $this->assertSame([false, true, false], $auth->forceSeen);
        $this->assertCount(3, $transport->calls);
        $this->assertCount(1, $this->sleeps);

        $auth = StubAuth::make();
        $transport = new FakeTransport([Responses::make(503, '{"errors":[]}'), Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"ok":"third"}')]);

        $result = $this->safeRequest($this->retrying($transport, 2, 500, 5000, $auth));

        $this->assertSame('third', $result->ok);
        $this->assertSame([false, false, true], $auth->forceSeen);
    }

    /**
     * A failure to get a token is not retried
     * @return void
     */
    public function testAFailureToGetATokenIsNotRetried(): void
    {
        $auth = StubAuth::make();
        $auth->throwOnToken = new FedexAuthException('token failed', FedexException::EC_UNABLE_TO_AUTHENTICATE, null, 401);
        $transport = new FakeTransport([], Responses::make(200, '{"ok":1}'));

        try {
            $this->safeRequest($this->retrying($transport, 3, 500, 5000, $auth));
            $this->fail('The request had to throw');
        } catch (FedexAuthException $e) {
            $this->assertSame([false], $auth->forceSeen);
            $this->assertCount(0, $transport->calls);
        }
    }

    /**
     * A policy out of range is refused and keeps the previous one
     * @return void
     */
    public function testAPolicyOutOfRangeIsRefusedAndKeepsThePreviousOne(): void
    {
        $transport = new FakeTransport([], Responses::make(503, '{"errors":[]}'));
        $client = $this->retrying($transport, 2, 0, 0);

        foreach ([[0, 0, 0], [11, 0, 0], [-1, 0, 0], [3, -1, 5], [3, 100, 99]] as $arguments) {
            try {
                $client->setRetryPolicy(...$arguments);
                $this->fail('setRetryPolicy(' . implode(', ', $arguments) . ') had to throw');
            } catch (FedexClientException $e) {
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            }
        }

        try {
            $this->safeRequest($client);
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertCount(2, $transport->calls, 'the policy of two attempts is still the one in use');
        }

        $this->assertSame($client, $client->setRetryPolicy(10, 0, 0));
        $this->assertSame($client, $client->setRetryPolicy(1, 0, 0));
        $this->assertSame($client, $client->setRetryPolicy(2, 7, 7), 'the limits themselves, and a maximum equal to the base, are accepted');
    }

    /**
     * The setters return the client
     * @return void
     */
    public function testTheSettersReturnTheClient(): void
    {
        $client = $this->client(new FakeTransport());

        $this->assertSame($client, $client->setRetryPolicy(2, 1, 2));
        $this->assertSame($client, $client->setRetryHooks(null, null));
    }
}
