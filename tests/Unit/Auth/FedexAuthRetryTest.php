<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Auth;

use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\ArrayTokenCache;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Reflect;
use Mariopereira\Fedex\Tests\Support\Responses;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the retry of the token request: what is retried, how long it waits, and what is never retried
 */
final class FedexAuthRetryTest extends TestCase
{
    /**
     * The delays that the sleeper received, in milliseconds
     * @var int[]
     */
    private $sleeps = [];

    /**
     * An authorization that retries, with a sleeper that records the delays instead of waiting and a jitter that
     * always picks the maximum
     * @param FakeTransport $transport The transport of the token request
     * @param int $attempts Total number of attempts
     * @param int $base Base delay in milliseconds
     * @param int $max Longest delay in milliseconds
     * @return FedexAuth
     */
    private function retrying(FakeTransport $transport, int $attempts = 3, int $base = 500, int $max = 5000): FedexAuth
    {
        $this->sleeps = [];

        return (new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $transport))
            ->setRetryPolicy($attempts, $base, $max)
            ->setRetryHooks(
                function (int $ms) {
                    $this->sleeps[] = $ms;
                },
                function (int $min, int $cap) {
                    return $cap;
                }
            );
    }

    /**
     * An answer that is not a success, with a Retry-After if one is given
     * @param int $status HTTP status
     * @param string|null $retryAfter The value of the header, null for none
     * @param string $body The body
     * @return FedexTransportResponse
     */
    private static function failure(int $status, ?string $retryAfter = null, string $body = '{"transactionId":"T","errors":[{"code":"E","message":"m"}]}'): FedexTransportResponse
    {
        return Responses::make($status, $body, null, ($retryAfter === null) ? [] : ['Retry-After' => $retryAfter]);
    }

    /**
     * Call authorize() and give the exception it throws
     * @param FedexAuth $auth The authorization
     * @return FedexAuthException
     */
    private function authorizeFails(FedexAuth $auth): FedexAuthException
    {
        try {
            $auth->authorize();
        } catch (FedexAuthException $e) {
            return $e;
        }

        $this->fail('authorize() had to throw');
    }

    /**
     * Nothing is retried until a policy is set
     * @return void
     */
    public function testNothingIsRetriedUntilAPolicyIsSet(): void
    {
        $transport = new FakeTransport([self::failure(503, '1')], Responses::token());
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $transport);

        $e = $this->authorizeFails($auth);

        $this->assertSame(503, $e->getStatusCode());
        $this->assertSame(1, $e->getRetryAfter(), 'the header is given to the caller anyway');
        $this->assertCount(1, $transport->calls);
        $this->assertFalse($auth->hasToken());
    }

    /**
     * A policy of one attempt is no retry
     * @return void
     */
    public function testAPolicyOfOneAttemptIsNoRetry(): void
    {
        $transport = new FakeTransport([self::failure(503)], Responses::token());

        $this->authorizeFails($this->retrying($transport, 1));

        $this->assertCount(1, $transport->calls);
        $this->assertSame([], $this->sleeps);
    }

    /**
     * The statuses 429, 503 and 504 are retried until the token is given
     * @return void
     */
    public function testTheStatuses429503And504AreRetriedUntilTheTokenIsGiven(): void
    {
        foreach ([429, 503, 504] as $status) {
            $transport = new FakeTransport([self::failure($status), self::failure($status), Responses::token(['access_token' => 'THIRD'])]);
            $auth = $this->retrying($transport);

            $this->assertSame('THIRD', $auth->getToken(), "HTTP $status");
            $this->assertCount(3, $transport->calls, "HTTP $status");
            $this->assertCount(2, $this->sleeps, "HTTP $status");
            $this->assertTrue($auth->hasToken());
        }
    }

    /**
     * A failure of the transport is retried
     * @return void
     */
    public function testAFailureOfTheTransportIsRetried(): void
    {
        $transport = new FakeTransport([Responses::make(0, '', 'Operation timed out'), Responses::make(0, '', 'Could not resolve host'), Responses::token(['access_token' => 'THIRD'])]);

        $this->assertSame('THIRD', $this->retrying($transport)->getToken());
        $this->assertCount(3, $transport->calls);
    }

    /**
     * A 503 whose body is not JSON is retried by its status
     * @return void
     */
    public function testA503WhoseBodyIsNotJsonIsRetriedByItsStatus(): void
    {
        $transport = new FakeTransport([self::failure(503, null, '<html>Service Unavailable</html>'), Responses::token()]);

        $this->assertSame('TOK', $this->retrying($transport)->getToken());
        $this->assertCount(2, $transport->calls);
    }

    /**
     * The Retry-After of a failure whose body is not JSON, or is empty, is kept and waited for
     * @return void
     */
    public function testTheRetryAfterOfAFailureWhoseBodyIsNotJsonOrIsEmptyIsKeptAndWaitedFor(): void
    {
        foreach (['<html>Service Unavailable</html>', ''] as $body) {
            $e = $this->authorizeFails($this->retrying(new FakeTransport([self::failure(503, '7', $body)]), 1));

            $this->assertSame(7, $e->getRetryAfter(), var_export($body, true));

            $transport = new FakeTransport([self::failure(503, '2', $body), Responses::token()]);

            $this->retrying($transport, 2, 500, 5000)->getToken();

            $this->assertSame([2000], $this->sleeps, var_export($body, true));
        }
    }

    /**
     * The credentials that are refused are never retried
     * @return void
     */
    public function testTheCredentialsThatAreRefusedAreNeverRetried(): void
    {
        foreach ([self::failure(401), self::failure(401, '1'), self::failure(401, null, '')] as $answer) {
            $transport = new FakeTransport([$answer], Responses::token());

            $e = $this->authorizeFails($this->retrying($transport));

            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame(FedexException::EC_UNABLE_TO_AUTHENTICATE, $e->getCode());
            $this->assertCount(1, $transport->calls, 'a second try with the same credentials could lock the account');
            $this->assertSame([], $this->sleeps);
        }
    }

    /**
     * Any other failure is not retried
     * @return void
     */
    public function testAnyOtherFailureIsNotRetried(): void
    {
        $cases = [
            '400' => self::failure(400),
            '403' => self::failure(403),
            '404' => self::failure(404),
            '500' => self::failure(500),
            '502' => self::failure(502),
            '200 with a body that is not JSON' => Responses::make(200, 'not json'),
            '200 with JSON that is not an object' => Responses::make(200, '[1,2]'),
            '200 with a token that is not valid' => Responses::make(200, '{"access_token":"","token_type":"bearer","expires_in":3600}'),
            '200 without an expiration' => Responses::make(200, '{"access_token":"X","token_type":"bearer"}'),
        ];

        foreach ($cases as $label => $answer) {
            $transport = new FakeTransport([$answer], Responses::token());

            $this->authorizeFails($this->retrying($transport));

            $this->assertCount(1, $transport->calls, $label);
            $this->assertSame([], $this->sleeps, $label);
        }
    }

    /**
     * When the attempts are over the last failure is thrown
     * @return void
     */
    public function testWhenTheAttemptsAreOverTheLastFailureIsThrown(): void
    {
        $transport = new FakeTransport([self::failure(503), self::failure(429), self::failure(504, null, '{"transactionId":"LAST","errors":[]}')], Responses::token());

        $e = $this->authorizeFails($this->retrying($transport));

        $this->assertSame(504, $e->getStatusCode());
        $this->assertSame('LAST', $e->getTransactionId());
        $this->assertCount(3, $transport->calls, 'three attempts and not a fourth');
        $this->assertCount(2, $this->sleeps, 'no wait after the last attempt');
    }

    /**
     * The delay doubles up to the longest delay
     * @return void
     */
    public function testTheDelayDoublesUpToTheLongestDelay(): void
    {
        $transport = new FakeTransport([], self::failure(503));

        $this->authorizeFails($this->retrying($transport, 10, 500, 5000));

        $this->assertCount(10, $transport->calls);
        $this->assertSame([500, 1000, 2000, 4000, 5000, 5000, 5000, 5000, 5000], $this->sleeps);
    }

    /**
     * The retry waits for the Retry-After when it is longer than the backoff, and stops when it is too long
     * @return void
     */
    public function testTheRetryWaitsForTheRetryAfterWhenItIsLongerThanTheBackoffAndStopsWhenItIsTooLong(): void
    {
        $transport = new FakeTransport([self::failure(503, '2'), self::failure(429, '0'), Responses::token()]);

        $this->retrying($transport, 3, 500, 5000)->getToken();

        $this->assertSame([2000, 1000], $this->sleeps, 'the longer of the header and the backoff (500, 1000)');

        $transport = new FakeTransport([self::failure(503, '31'), Responses::token()]);

        $e = $this->authorizeFails($this->retrying($transport, 5, 0, 0));

        $this->assertSame(31, $e->getRetryAfter());
        $this->assertCount(1, $transport->calls, 'a Retry-After of more than thirty seconds ends the retries');
        $this->assertSame([], $this->sleeps);

        $transport = new FakeTransport([self::failure(503, '999'), Responses::token()]);
        $auth = $this->retrying($transport, 2, 500, 5000)->setRetryPolicy(2, 500, 5000, 0);

        $auth->getToken();

        $this->assertSame([500], $this->sleeps, 'a ceiling of zero ignores the header');
    }

    /**
     * Every attempt sends the same token request
     * @return void
     */
    public function testEveryAttemptSendsTheSameTokenRequest(): void
    {
        $transport = new FakeTransport([self::failure(503), Responses::make(0, '', 'timeout'), Responses::token()]);

        $this->retrying($transport)->getToken();
        $calls = $transport->calls;

        $this->assertCount(3, $calls);
        $this->assertSame($calls[0], $calls[1]);
        $this->assertSame($calls[0], $calls[2]);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertStringEndsWith('/oauth/token', $calls[0]['url']);
        $this->assertStringContainsString('client_id=id', (string) $calls[0]['body']);
    }

    /**
     * The token that was there is cleared once and a failure leaves no token
     * @return void
     */
    public function testTheTokenThatWasThereIsClearedOnceAndAFailureLeavesNoToken(): void
    {
        $transport = new FakeTransport([Responses::token(['access_token' => 'OLD']), self::failure(503), self::failure(503)], self::failure(503));
        $auth = $this->retrying($transport, 2);

        $this->assertSame('OLD', $auth->getToken());
        $this->assertTrue($auth->hasToken());

        $this->authorizeFails($auth);

        $this->assertFalse($auth->hasToken(), 'the old token is not kept when a new one cannot be had');
        $this->assertCount(3, $transport->calls);
    }

    /**
     * A token that is retried is stored once in the cache, and a token that is cached needs no request
     * @return void
     */
    public function testATokenThatIsRetriedIsStoredOnceInTheCacheAndATokenThatIsCachedNeedsNoRequest(): void
    {
        $cache = new ArrayTokenCache();
        $transport = new FakeTransport([self::failure(503), Responses::token(['access_token' => 'T1'])]);

        $this->assertSame('T1', $this->retrying($transport)->setTokenCache($cache)->getToken());
        $this->assertSame(['get', 'set'], $cache->operations(), 'a miss, and the token is stored once the request worked');
        $this->assertCount(2, $transport->calls);

        $second = new FakeTransport([self::failure(503)]);

        $this->assertSame('T1', $this->retrying($second)->setTokenCache($cache)->getToken());
        $this->assertCount(0, $second->calls);
        $this->assertSame([], $this->sleeps);
    }

    /**
     * A failure that is not retried is not stored in the cache
     * @return void
     */
    public function testAFailureThatIsNotRetriedIsNotStoredInTheCache(): void
    {
        $cache = new ArrayTokenCache();
        $transport = new FakeTransport([self::failure(503), self::failure(503)]);

        $this->authorizeFails($this->retrying($transport, 2)->setTokenCache($cache));

        $this->assertSame([], $cache->entries);
        $this->assertNotContains('set', $cache->operations());
    }

    /**
     * A failure of the token request inside a request is not retried again by the client
     * @return void
     */
    public function testAFailureOfTheTokenRequestInsideARequestIsNotRetriedAgainByTheClient(): void
    {
        $authTransport = new FakeTransport([], self::failure(503));
        $apiTransport = new FakeTransport([], Responses::make(200, '{"ok":true}'));
        $auth = $this->retrying($authTransport, 2);
        $client = (new FedexClient($auth, 'en_US', 30, 10, $apiTransport))->setRetryPolicy(3, 0, 0);

        try {
            $client->request('GET', 'x', null, [], [], null, true);
            $this->fail('The request had to throw');
        } catch (FedexAuthException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        $this->assertCount(2, $authTransport->calls, 'the attempts of the token request, and not two by three');
        $this->assertCount(0, $apiTransport->calls);
    }

    /**
     * The retry of a request does not ask for another token
     * @return void
     */
    public function testTheRetryOfARequestDoesNotAskForAnotherToken(): void
    {
        $authTransport = new FakeTransport([Responses::token(['access_token' => 'T1'])]);
        $apiTransport = new FakeTransport([self::failure(503), self::failure(503), Responses::make(200, '{"ok":true}')]);
        $client = (new FedexClient($this->retrying($authTransport), 'en_US', 30, 10, $apiTransport))->setRetryPolicy(3, 0, 0);

        $this->assertTrue($client->request('GET', 'x', null, [], [], null, true)->ok);
        $this->assertCount(1, $authTransport->calls);
        $this->assertCount(3, $apiTransport->calls);
    }

    /**
     * A policy out of range is refused and keeps the previous one
     * @return void
     */
    public function testAPolicyOutOfRangeIsRefusedAndKeepsThePreviousOne(): void
    {
        $auth = $this->retrying(new FakeTransport(), 2, 7, 9);

        foreach ([[0, 0, 0, 0], [11, 0, 0, 0], [-1, 0, 0, 0], [3, -1, 5, 0], [3, 100, 99, 0], [3, 0, 0, -1]] as $arguments) {
            try {
                $auth->setRetryPolicy(...$arguments);
                $this->fail('setRetryPolicy(' . implode(', ', $arguments) . ') had to throw');
            } catch (FedexAuthException $e) {
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            }
        }

        $this->assertSame([2, 7, 9, 30000], Reflect::prop($auth, FedexAuth::class, 'retry')->getPolicy());
        $this->assertSame($auth, $auth->setRetryPolicy(10, 0, 0, 0));
        $this->assertSame($auth, $auth->setRetryPolicy());
        $this->assertSame([3, 500, 5000, 30000], Reflect::prop($auth, FedexAuth::class, 'retry')->getPolicy(), 'the defaults');
        $this->assertSame($auth, $auth->setRetryHooks(null, null));
    }

    /**
     * Without hooks the real sleep and random are used
     * @return void
     */
    public function testWithoutHooksTheRealSleepAndRandomAreUsed(): void
    {
        $transport = new FakeTransport([self::failure(503), self::failure(503), Responses::token(['access_token' => 'T3'])]);
        $auth = (new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $transport))->setRetryPolicy(3, 10, 10);
        $start = microtime(true);

        $this->assertSame('T3', $auth->getToken());
        $this->assertLessThan(1.0, microtime(true) - $start, 'the delays are in milliseconds');
    }

    /**
     * The failure of the client is not affected by the policy of the authorization
     * @return void
     */
    public function testTheFailureOfTheClientIsNotAffectedByThePolicyOfTheAuthorization(): void
    {
        $authTransport = new FakeTransport([Responses::token()]);
        $apiTransport = new FakeTransport([self::failure(503)], Responses::make(200, '{"ok":true}'));
        $client = new FedexClient($this->retrying($authTransport), 'en_US', 30, 10, $apiTransport);

        try {
            $client->request('GET', 'x', null, [], [], null, true);
            $this->fail('The request had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        $this->assertCount(1, $apiTransport->calls, 'the client has no policy of its own, so it does not retry');
    }
}
