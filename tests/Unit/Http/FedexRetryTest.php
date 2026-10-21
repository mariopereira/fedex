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
use Mariopereira\Fedex\Http\FedexRetry;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the retry with backoff that FedexClient and FedexAuth share, on its own
 */
final class FedexRetryTest extends TestCase
{
    /**
     * The delays that the sleeper received, in milliseconds
     * @var int[]
     */
    private $sleeps = [];

    /**
     * A retry with a sleeper that records the delays and a jitter that always picks the maximum
     * @param string $class The class of the failures that it retries
     * @param int $attempts Total number of attempts
     * @param int $base Base delay in milliseconds
     * @param int $max Longest delay in milliseconds
     * @param int $maxRetryAfter Longest Retry-After in milliseconds
     * @return FedexRetry
     */
    private function retry(string $class = FedexClientException::class, int $attempts = 3, int $base = 500, int $max = 5000, int $maxRetryAfter = 30000): FedexRetry
    {
        $this->sleeps = [];
        $retry = new FedexRetry($class);
        $retry->set($attempts, $base, $max, $maxRetryAfter);
        $retry->setHooks(function (int $ms) {
            $this->sleeps[] = $ms;
        }, function (int $min, int $cap) {
            return $cap;
        });

        return $retry;
    }

    /**
     * An operation that fails with the exceptions given, one for each call, and then works
     * @param \Throwable[] $failures What to throw, in order
     * @param array $calls Receives the number of calls in the key 'n'
     * @return callable
     */
    private static function failing(array $failures, array &$calls): callable
    {
        $calls['n'] = 0;

        return function () use ($failures, &$calls) {
            $calls['n']++;

            if (isset($failures[$calls['n'] - 1])) {
                throw $failures[$calls['n'] - 1];
            }

            return 'worked after ' . $calls['n'];
        };
    }

    /**
     * An exception of the client with a status
     * @param int $status HTTP status
     * @param int|null $retryAfter Seconds of the Retry-After
     * @return FedexClientException
     */
    private static function client(int $status, ?int $retryAfter = null): FedexClientException
    {
        return new FedexClientException('x', FedexException::EC_TRANSPORT_ERROR, null, $status, null, [], null, null, $retryAfter);
    }

    /**
     * An exception of the authorization with a status
     * @param int $status HTTP status
     * @param int $code The code of the exception
     * @param int|null $retryAfter Seconds of the Retry-After
     * @return FedexAuthException
     */
    private static function auth(int $status, int $code = FedexException::EC_TRANSPORT_ERROR, ?int $retryAfter = null): FedexAuthException
    {
        return new FedexAuthException('x', $code, null, $status, null, [], null, null, $retryAfter);
    }

    /**
     * Make another exception like one, because the same exception cannot be thrown twice with a fresh trace
     * @param FedexException $e The exception
     * @return FedexException
     */
    private static function again(FedexException $e): FedexException
    {
        $class = get_class($e);

        return new $class($e->getMessage(), $e->getCode(), null, $e->getStatusCode());
    }

    /**
     * A retry that is new does not retry anything
     * @return void
     */
    public function testANewRetryDoesNotRetryAnything(): void
    {
        $retry = new FedexRetry(FedexClientException::class);

        $this->assertFalse($retry->isEnabled());
        $this->assertSame([1, 500, 5000, 30000], $retry->getPolicy());
        $this->assertSame(10, FedexRetry::MAX_ATTEMPTS_LIMIT);
    }

    /**
     * The policy is set, read back and enabled by more than one attempt
     * @return void
     */
    public function testThePolicyIsSetReadBackAndEnabledByMoreThanOneAttempt(): void
    {
        $retry = new FedexRetry(FedexAuthException::class);

        $retry->set(2, 10, 20, 30);

        $this->assertSame([2, 10, 20, 30], $retry->getPolicy());
        $this->assertTrue($retry->isEnabled());

        $retry->set(1, 10, 20, 30);

        $this->assertFalse($retry->isEnabled());
    }

    /**
     * The values of a policy are checked
     * @return void
     */
    public function testTheValuesOfAPolicyAreChecked(): void
    {
        $attempts = 'The number of attempts must be between 1 and 10';
        $delays = 'The delays must not be negative and the maximum must not be less than the base delay';
        $cases = [
            [[1, 0, 0, 0], null],
            [[10, 500, 5000, 30000], null],
            [[3, 100, 100, 0], null],
            [[0, 0, 0, 0], $attempts],
            [[-1, 0, 0, 0], $attempts],
            [[11, 0, 0, 0], $attempts],
            [[3, -1, 5, 0], $delays],
            [[3, 100, 99, 0], $delays],
            [[3, 0, 0, -1], $delays],
            [[0, -1, -2, -3], $attempts],
        ];

        foreach ($cases as [$values, $expected]) {
            $this->assertSame($expected, FedexRetry::check(...$values), implode(', ', $values));
        }
    }

    /**
     * What the operation returns is returned and it is called once when it works
     * @return void
     */
    public function testWhatTheOperationReturnsIsReturnedAndItIsCalledOnceWhenItWorks(): void
    {
        $calls = [];

        $this->assertSame('worked after 1', $this->retry()->run(self::failing([], $calls)));
        $this->assertSame(1, $calls['n']);
        $this->assertSame([], $this->sleeps);
        $this->assertNull($this->retry()->run(function () {
            return null;
        }));
    }

    /**
     * The failures of the kind that was given are retried
     * @return void
     */
    public function testTheFailuresOfTheKindThatWasGivenAreRetried(): void
    {
        foreach ([429, 503, 504, 0] as $status) {
            $calls = [];

            $this->assertSame('worked after 3', $this->retry(FedexClientException::class)->run(self::failing([self::client($status), self::client($status)], $calls)), "client, $status");
            $this->assertCount(2, $this->sleeps);

            $this->assertSame('worked after 3', $this->retry(FedexAuthException::class)->run(self::failing([self::auth($status), self::auth($status)], $calls)), "auth, $status");
            $this->assertCount(2, $this->sleeps);
        }
    }

    /**
     * The failures of the other kind are never retried
     * @return void
     */
    public function testTheFailuresOfTheOtherKindAreNeverRetried(): void
    {
        $cases = [
            [FedexClientException::class, self::auth(503)],
            [FedexClientException::class, self::auth(0)],
            [FedexAuthException::class, self::client(503)],
            [FedexAuthException::class, self::client(0)],
            [FedexAuthException::class, self::client(429)],
        ];

        foreach ($cases as [$class, $failure]) {
            $calls = [];

            try {
                $this->retry($class)->run(self::failing([$failure], $calls));
                $this->fail('It had to throw');
            } catch (FedexException $e) {
                $this->assertSame($failure, $e);
                $this->assertSame(1, $calls['n'], "$class does not retry " . get_class($failure));
            }
        }

        $this->assertSame([], $this->sleeps);
    }

    /**
     * Any other failure is thrown at once
     * @return void
     */
    public function testAnyOtherFailureIsThrownAtOnce(): void
    {
        $clients = [
            self::client(400), self::client(401), self::client(403), self::client(404), self::client(500), self::client(502),
            new FedexClientException('x', FedexException::EC_BAD_RESULT_RETURNED, null, 0),
            new FedexClientException('x', FedexException::EC_BAD_PARAMETER, null, 0),
        ];
        $auths = [
            self::auth(400), self::auth(401, FedexException::EC_UNABLE_TO_AUTHENTICATE), self::auth(500), self::auth(200, FedexException::EC_BAD_RESULT_RETURNED),
            new FedexAuthException('x', FedexException::EC_BAD_RESULT_RETURNED, null, 0),
        ];

        foreach ([[FedexClientException::class, $clients], [FedexAuthException::class, $auths]] as [$class, $failures]) {
            foreach ($failures as $failure) {
                $calls = [];

                try {
                    $this->retry($class)->run(self::failing([$failure], $calls));
                    $this->fail('It had to throw');
                } catch (FedexException $e) {
                    $this->assertSame($failure, $e);
                    $this->assertSame(1, $calls['n'], get_class($failure) . ' ' . $failure->getStatusCode() . ' ' . $failure->getCode());
                }
            }
        }

        $this->assertSame([], $this->sleeps);
    }

    /**
     * An exception that is not one of the package is never retried
     * @return void
     */
    public function testAnExceptionThatIsNotOneOfThePackageIsNeverRetried(): void
    {
        foreach ([new \RuntimeException('x'), new \TypeError('x'), new \Exception('x', 503)] as $failure) {
            $calls = [];

            try {
                $this->retry()->run(self::failing([$failure], $calls));
                $this->fail('It had to throw');
            } catch (\Throwable $e) {
                $this->assertSame($failure, $e);
                $this->assertSame(1, $calls['n']);
            }
        }
    }

    /**
     * The attempts are the limit and the last failure is thrown without a wait after it
     * @return void
     */
    public function testTheAttemptsAreTheLimitAndTheLastFailureIsThrownWithoutAWaitAfterIt(): void
    {
        $calls = [];
        $last = self::client(504);

        try {
            $this->retry(FedexClientException::class, 3)->run(self::failing([self::client(503), self::client(429), $last, self::client(503)], $calls));
            $this->fail('It had to throw');
        } catch (FedexException $e) {
            $this->assertSame($last, $e);
        }

        $this->assertSame(3, $calls['n']);
        $this->assertCount(2, $this->sleeps);
    }

    /**
     * The delay doubles up to the longest delay
     * @return void
     */
    public function testTheDelayDoublesUpToTheLongestDelay(): void
    {
        $calls = [];

        try {
            $this->retry(FedexClientException::class, 10, 500, 5000)->run(self::failing(array_fill(0, 10, self::client(503)), $calls));
            $this->fail('It had to throw');
        } catch (FedexClientException $e) {
            $this->assertSame(10, $calls['n']);
        }

        $this->assertSame([500, 1000, 2000, 4000, 5000, 5000, 5000, 5000, 5000], $this->sleeps);
    }

    /**
     * The jitter is given zero and the cap, and its answer is the delay
     * @return void
     */
    public function testTheJitterIsGivenZeroAndTheCapAndItsAnswerIsTheDelay(): void
    {
        $retry = new FedexRetry(FedexClientException::class);
        $retry->set(3, 800, 5000, 30000);
        $given = [];
        $slept = [];
        $retry->setHooks(function (int $ms) use (&$slept) {
            $slept[] = $ms;
        }, function (int $min, int $max) use (&$given) {
            $given[] = [$min, $max];

            return 123;
        });
        $calls = [];

        $retry->run(self::failing([self::client(503), self::client(503)], $calls));

        $this->assertSame([[0, 800], [0, 1600]], $given);
        $this->assertSame([123, 123], $slept);
    }

    /**
     * The Retry-After is the wait when it is longer than the backoff, and not shorter
     * @return void
     */
    public function testTheRetryAfterIsTheWaitWhenItIsLongerThanTheBackoffAndNotShorter(): void
    {
        $calls = [];

        $this->retry(FedexClientException::class, 4, 500, 5000)->run(self::failing([self::client(503, 2), self::client(429, 0), self::client(504, 1)], $calls));

        $this->assertSame([2000, 1000, 2000], $this->sleeps, 'the longer of the header and the backoff (500, 1000, 2000)');

        $this->retry(FedexAuthException::class, 2, 4000, 4000)->run(self::failing([self::auth(503, FedexException::EC_TRANSPORT_ERROR, 1)], $calls));

        $this->assertSame([4000], $this->sleeps);
    }

    /**
     * A Retry-After that is too long ends the retries, and zero ignores the header
     * @return void
     */
    public function testARetryAfterThatIsTooLongEndsTheRetriesAndZeroIgnoresTheHeader(): void
    {
        $calls = [];

        try {
            $this->retry(FedexAuthException::class, 5, 0, 0)->run(self::failing([self::auth(503, FedexException::EC_TRANSPORT_ERROR, 31)], $calls));
            $this->fail('It had to throw');
        } catch (FedexAuthException $e) {
            $this->assertSame(31, $e->getRetryAfter());
        }

        $this->assertSame(1, $calls['n']);
        $this->assertSame([], $this->sleeps);

        $this->retry(FedexClientException::class, 2, 0, 0)->run(self::failing([self::client(503, 30)], $calls));

        $this->assertSame([30000], $this->sleeps, 'the ceiling itself is waited for');

        $this->retry(FedexClientException::class, 2, 500, 5000, 0)->run(self::failing([self::client(503, 999)], $calls));

        $this->assertSame([500], $this->sleeps, 'a ceiling of zero ignores the header');
    }

    /**
     * Without hooks the real sleep and random are used
     * @return void
     */
    public function testWithoutHooksTheRealSleepAndRandomAreUsed(): void
    {
        $retry = new FedexRetry(FedexClientException::class);
        $retry->set(3, 10, 10, 30000);
        $retry->setHooks(function (int $ms) {
            throw new \RuntimeException('not this one');
        }, function (int $min, int $max) {
            return -1;
        });
        $retry->setHooks(null, null);
        $calls = [];
        $start = microtime(true);

        $this->assertSame('worked after 3', $retry->run(self::failing([self::client(503), self::client(503)], $calls)));
        $this->assertLessThan(1.0, microtime(true) - $start, 'the delays are in milliseconds');
    }

    /**
     * Another exception made like one has the same data, so that the helper of these tests is sound
     * @return void
     */
    public function testTheHelperThatMakesAnotherExceptionKeepsTheData(): void
    {
        $e = self::again(self::auth(503));

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame([503, FedexException::EC_TRANSPORT_ERROR], [$e->getStatusCode(), $e->getCode()]);
    }
}
