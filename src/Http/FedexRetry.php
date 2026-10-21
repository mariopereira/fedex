<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Http;

use Mariopereira\Fedex\Exception\FedexException;

/**
 * Retry with exponential backoff, shared by FedexClient (the API requests) and FedexAuth (the token request). Each of them
 * retries only the failures of its own kind (FedexClientException or FedexAuthException): a failure of the token request
 * that happens inside an API request is left to the token request, so that the attempts of the two are not multiplied.
 *
 * An operation is repeated while it fails with something that is worth trying again: HTTP 429, 503 or 504, or a request
 * that could not be completed (network failure, timeout, ...). The delay before the attempt n is a random value between
 * 0 and min(maximum delay, base delay * 2^(n-1)). When the failure carries a valid Retry-After header the wait is the
 * longer of the header and that delay, and if the header asks for more than the longest Retry-After that is accepted the
 * retries stop and the failure is thrown. Nothing is retried until set() is called.
 */
final class FedexRetry
{
    /**
     * HTTP statuses that are worth retrying: too many requests, service unavailable and gateway timeout
     * @var int[]
     */
    private const RETRY_STATUSES = [429, 503, 504];

    /**
     * Maximum number of attempts that is accepted
     * @var int
     */
    public const MAX_ATTEMPTS_LIMIT = 10;

    /**
     * The class of the failures that are retried
     * @var string
     */
    private $failureClass;

    /**
     * Total number of attempts (1 means that nothing is retried)
     * @var int
     */
    private $maxAttempts = 1;

    /**
     * Delay, in milliseconds, that the backoff starts from
     * @var int
     */
    private $baseDelayMs = 500;

    /**
     * Longest delay, in milliseconds, between two attempts
     * @var int
     */
    private $maxDelayMs = 5000;

    /**
     * Longest Retry-After, in milliseconds, that is waited for. A longer one stops the retries. Zero ignores the header
     * @var int
     */
    private $maxRetryAfterMs = 30000;

    /**
     * Function that waits for the given number of milliseconds, null for the default (usleep)
     * @var callable|null
     */
    private $sleeper = null;

    /**
     * Function that receives a minimum and a maximum and returns an integer between them, null for the default
     * (random_int)
     * @var callable|null
     */
    private $jitter = null;

    /**
     * Initialize the object
     * @param string $failureClass The class of the failures that are retried: FedexClientException::class or
     * FedexAuthException::class. Any other failure is thrown at once
     */
    public function __construct(string $failureClass)
    {
        $this->failureClass = $failureClass;
    }

    /**
     * Check the values of a retry policy
     * @param int $maxAttempts Total number of attempts, from 1 (no retry) to MAX_ATTEMPTS_LIMIT
     * @param int $baseDelayMs Delay in milliseconds that the backoff starts from, 0 or more
     * @param int $maxDelayMs Longest delay in milliseconds, not less than $baseDelayMs
     * @param int $maxRetryAfterMs Longest Retry-After in milliseconds that is waited for, 0 or more
     * @return string|null What is wrong with them, null when they are valid
     */
    public static function check(int $maxAttempts, int $baseDelayMs, int $maxDelayMs, int $maxRetryAfterMs) : ?string
    {
        if (($maxAttempts < 1) || ($maxAttempts > self::MAX_ATTEMPTS_LIMIT)) {
            return 'The number of attempts must be between 1 and ' . self::MAX_ATTEMPTS_LIMIT;
        }

        if (($baseDelayMs < 0) || ($maxDelayMs < $baseDelayMs) || ($maxRetryAfterMs < 0)) {
            return 'The delays must not be negative and the maximum must not be less than the base delay';
        }

        return null;
    }

    /**
     * Set the retry policy. The values have to be valid (see check())
     * @param int $maxAttempts Total number of attempts, from 1 (no retry) to MAX_ATTEMPTS_LIMIT
     * @param int $baseDelayMs Delay in milliseconds that the backoff starts from
     * @param int $maxDelayMs Longest delay in milliseconds
     * @param int $maxRetryAfterMs Longest Retry-After in milliseconds that is waited for, zero to ignore the header
     * @return void
     */
    public function set(int $maxAttempts, int $baseDelayMs, int $maxDelayMs, int $maxRetryAfterMs) : void
    {
        $this->maxAttempts = $maxAttempts;
        $this->baseDelayMs = $baseDelayMs;
        $this->maxDelayMs = $maxDelayMs;
        $this->maxRetryAfterMs = $maxRetryAfterMs;
    }

    /**
     * The retry policy
     * @return int[] The total number of attempts, the base delay, the longest delay and the longest Retry-After, the
     * delays in milliseconds
     */
    public function getPolicy() : array
    {
        return [$this->maxAttempts, $this->baseDelayMs, $this->maxDelayMs, $this->maxRetryAfterMs];
    }

    /**
     * Replace the functions that wait and that pick the random part of the delay between attempts, so that the
     * backoff can be tested without waiting
     * @param callable|null $sleeper Receives the number of milliseconds to wait. Null restores usleep
     * @param callable|null $jitter Receives a minimum and a maximum and returns an integer between them. Null
     * restores random_int
     * @return void
     */
    public function setHooks(?callable $sleeper, ?callable $jitter) : void
    {
        $this->sleeper = $sleeper;
        $this->jitter = $jitter;
    }

    /**
     * Check if anything is retried
     * @return bool True when the policy allows more than one attempt
     */
    public function isEnabled() : bool
    {
        return $this->maxAttempts > 1;
    }

    /**
     * Check if a failure is transient: HTTP 429, 503 or 504, or a request that could not be completed
     * @param FedexException $ex The failure
     * @return bool
     */
    private function isTransient(FedexException $ex) : bool
    {
        $status = $ex->getStatusCode();

        return in_array($status, self::RETRY_STATUSES, true)
            || ((0 === $status) && (FedexException::EC_TRANSPORT_ERROR === $ex->getCode()));
    }

    /**
     * Run an operation, repeating it with exponential backoff while it fails with something transient
     * @param callable $fn Callable without parameters that performs the operation
     * @return mixed What the operation returns
     * @throws FedexException The last failure, when the attempts are over, the failure is not of the kind that is
     * retried or is not transient, or the Retry-After is too long
     */
    public function run(callable $fn)
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $fn();
            } catch (FedexException $ex) {
                if (!($ex instanceof $this->failureClass) || ($attempt >= $this->maxAttempts) || !$this->isTransient($ex)) {
                    throw $ex;
                }
            }

            $cap = (int) min($this->maxDelayMs, $this->baseDelayMs * (2 ** ($attempt - 1)));
            $delay = $this->jitter ? (int) ($this->jitter)(0, $cap) : random_int(0, $cap);
            $retryAfter = method_exists($ex, 'getRetryAfter') ? $ex->getRetryAfter() : null;

            if ((null !== $retryAfter) && ($this->maxRetryAfterMs > 0)) {
                // Never earlier than FedEx asked, and no wait that would hold the request for too long
                if ($retryAfter * 1000 > $this->maxRetryAfterMs) {
                    throw $ex;
                }

                $delay = max($delay, (int) ($retryAfter * 1000));
            }

            if ($this->sleeper) {
                ($this->sleeper)($delay);
            } else {
                usleep($delay * 1000);
            }
        }
    }
}
