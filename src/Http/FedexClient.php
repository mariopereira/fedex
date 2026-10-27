<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Http;

use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Misc\FedexUtils;

/**
 * JSON HTTP client for the FedEx APIs: adds the authorization headers and retries once when the token is rejected.
 * Optionally (see setRetryPolicy()) it also retries, with exponential backoff, the requests that are marked as safe to
 * repeat when FedEx answers 429, 503 or 504 or when the request could not be completed, waiting at least as long as
 * the Retry-After header asks
 */
class FedexClient
{
    /**
     * Timeout in seconds
     * @var int
     */
    protected int $timeout = 30;

    /**
     * Connect timeout, in seconds
     * @var int
     */
    protected int $connectTimeout = 10;

    /**
     * Authorization object
     * @var FedexAuth
     */
    protected FedexAuth $auth;

    /**
     * Locale to use in calls
     * @var string
     */
    protected string $locale = 'en_US';

    /**
     * Transport used to send the requests
     * @var FedexTransport
     */
    protected FedexTransport $transport;

    /**
     * The retry with backoff of the requests that are marked as safe to repeat
     * @var FedexRetry
     */
    protected FedexRetry $retry;

    /**
     * Initialize the object
     * @param FedexAuth $auth Fedex Authorization class, to fetch tokens
     * @param string $locale Locale to use (defaults to en_US)
     * @param int $timeout Timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @param FedexTransport|null $transport Transport used to send the requests (defaults to CurlTransport)
     */
    public function __construct(
        FedexAuth $auth,
        string $locale = 'en_US',
        int $timeout = 30,
        int $connectTimeout = 10,
        ?FedexTransport $transport = null
    )
    {
        $this->auth = $auth;
        $this->locale = trim($locale);
        $this->timeout = min(30, $timeout);
        $this->connectTimeout = min(10, $connectTimeout);
        $this->transport = $transport ?? new CurlTransport();
        $this->retry = new FedexRetry(FedexClientException::class);

        if (empty($this->locale)) {
            $this->locale = 'en_US';
        }
    }

    /**
     * Enable the retry, with exponential backoff and full jitter, of the requests that are marked as safe to repeat.
     * They are retried when FedEx answers 429, 503 or 504, or when the request could not be completed (network
     * failure, timeout, ...). The delay before the attempt n is a random value between 0 and
     * min($maxDelayMs, $baseDelayMs * 2^(n-1)). The refresh of a rejected token (401) is separate and does not count
     * as an attempt. When FedEx sends a valid Retry-After header with the failure, the wait is the longer of the header
     * and the backoff delay; if the header asks for more than $maxRetryAfterMs the retries stop and the failure is
     * thrown. Without calling this method nothing is retried
     * @param int $maxAttempts Total number of attempts, from 1 (no retry) to 10
     * @param int $baseDelayMs Delay in milliseconds that the backoff starts from, 0 or more
     * @param int $maxDelayMs Longest delay in milliseconds, not less than $baseDelayMs
     * @param int $maxRetryAfterMs Longest Retry-After in milliseconds that is waited for, 0 or more. Zero ignores the
     * header and uses only the backoff
     * @return FedexClient
     * @throws FedexClientException When a value is out of range
     */
    public function setRetryPolicy(int $maxAttempts = 3, int $baseDelayMs = 500, int $maxDelayMs = 5000, int $maxRetryAfterMs = 30000) : FedexClient
    {
        $problem = FedexRetry::check($maxAttempts, $baseDelayMs, $maxDelayMs, $maxRetryAfterMs);

        if (null !== $problem) {
            throw new FedexClientException($problem, FedexException::EC_BAD_PARAMETER);
        }

        $this->retry->set($maxAttempts, $baseDelayMs, $maxDelayMs, $maxRetryAfterMs);

        return $this;
    }

    /**
     * Replace the functions that wait and that pick the random part of the delay between attempts, so that the
     * backoff can be tested without waiting
     * @param callable|null $sleeper Receives the number of milliseconds to wait. Null restores usleep
     * @param callable|null $jitter Receives a minimum and a maximum and returns an integer between them. Null
     * restores random_int
     * @return FedexClient
     */
    public function setRetryHooks(?callable $sleeper, ?callable $jitter) : FedexClient
    {
        $this->retry->setHooks($sleeper, $jitter);

        return $this;
    }

    /**
     * Generate the url to call
     * @param string $path Path to call, or an absolute url (https://...) for an API that has its own host, which is used as it is
     * @param array $query Query parameters
     * @return string
     */
    protected function buildUrl(string $path, array $query) : string
    {
        // an absolute url (an API with its own host, as the document upload) is used as it is
        $url = (strpos($path, 'https://') === 0)
            ? $path
            : FedexUtils::getUrl($this->auth->inSandbox()) . ltrim($path, '/');

        if (count($query)) {
            $url .= '?' . \http_build_query($query);
        }

        return $url;
    }

    /**
     * Call the api, retrying once on auth failure
     * @param callable $fn Callable with a boolean parameter: if true, try to auth
     * @return mixed
     * @throws FedexException
     */
    protected function retryOnAuth(callable $fn)
    {
        try {
            return $fn(false);
        } catch (FedexClientException $ex) {
            // Token may have expired or been revoked
            // Refresh once and retry
            if ($ex->getStatusCode() === 401) {
                return $fn(true);
            }

            throw $ex;
        }
    }

    /**
     * Send a JSON request to FedEx's API
     * @param string $method GET, POST, ...
     * @param string $path Endpoint to call
     * @param mixed $body Request body content
     * @param array $query Request url parameters
     * @param array $headers Headers to send
     * @param string|null $customerTransactionId Customer transaction id to send
     * @param bool $safeToRetry True when repeating the request cannot do any harm (it does not change data in FedEx),
     * so that it may be retried according to setRetryPolicy()
     * @return mixed
     * @throws FedexClientException
     * @throws FedexAuthException
     */
    public function request(
        string $method,
        string $path,
        $body = null,
        array $query = [],
        array $headers = [],
        ?string $customerTransactionId = null,
        bool $safeToRetry = false
    )
    {
        $url = $this->buildUrl($path, $query);
        $postFields = null;

        if (!is_null($body)) {
            try {
                $postFields = json_encode($body, JSON_THROW_ON_ERROR);
            } catch (\JsonException $ex) {
                throw new FedexClientException(
                    'Unable to encode the request body as JSON: ' . $ex->getMessage(),
                    FedexException::EC_BAD_PARAMETER,
                    $ex
                );
            }
        }
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $headers
        );

        return $this->dispatch($method, $url, $headers, $postFields, $customerTransactionId, $safeToRetry);
    }

    /**
     * Send a multipart/form-data request (text fields and files) to FedEx's API, as the document upload API needs
     * @param string $method GET, POST, ...
     * @param string $path Endpoint to call, or an absolute url (https://...)
     * @param array $fields Text parts: name => value
     * @param array $files File parts: a list of ['name' => part name, 'filename' => file name, 'contentType' => media type,
     * 'content' => the bytes]
     * @param array $headers Headers to send
     * @param string|null $customerTransactionId Customer transaction id to send
     * @param bool $safeToRetry True when repeating the request cannot do any harm (it does not change data in FedEx),
     * so that it may be retried according to setRetryPolicy()
     * @return mixed
     * @throws FedexClientException When a part is not valid, or the request fails
     * @throws FedexAuthException
     */
    public function requestMultipart(
        string $method,
        string $path,
        array $fields,
        array $files,
        array $headers = [],
        ?string $customerTransactionId = null,
        bool $safeToRetry = false
    )
    {
        $boundary = '----FedexBoundary' . bin2hex(random_bytes(16));
        $body = self::multipartBody($boundary, $fields, $files);
        $headers = array_merge(
            ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
            $headers
        );

        return $this->dispatch($method, $this->buildUrl($path, []), $headers, $body, $customerTransactionId, $safeToRetry);
    }

    /**
     * Build a multipart/form-data body (RFC 7578): the text fields first, then the files
     * @param string $boundary The boundary, which must not appear in the content
     * @param array $fields Text parts: name => value
     * @param array $files File parts: a list of ['name' => part name, 'filename' => file name, 'contentType' => media type,
     * 'content' => the bytes]
     * @return string
     * @throws FedexClientException When a name, a file name or a media type is not valid (empty, or with a quote, a carriage
     * return or a line feed, which would break the headers of the part), or a file lacks one of its keys
     */
    public static function multipartBody(string $boundary, array $fields, array $files) : string
    {
        $body = '';

        foreach ($fields as $name => $value) {
            self::checkPartText('name', (string) $name);
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n"
                . $value . "\r\n";
        }

        foreach ($files as $file) {
            foreach (['name', 'filename', 'contentType', 'content'] as $key) {
                if (!is_array($file) || !array_key_exists($key, $file) || !is_string($file[$key])) {
                    throw new FedexClientException(
                        "Each file of a multipart request must have the text '{$key}'",
                        FedexException::EC_BAD_PARAMETER
                    );
                }
            }

            self::checkPartText('name', $file['name']);
            self::checkPartText('filename', $file['filename']);
            self::checkPartText('contentType', $file['contentType']);
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . $file['name'] . '"; filename="' . $file['filename'] . '"' . "\r\n"
                . 'Content-Type: ' . $file['contentType'] . "\r\n\r\n"
                . $file['content'] . "\r\n";
        }

        return $body . '--' . $boundary . "--\r\n";
    }

    /**
     * Check a text that goes into the headers of a part of a multipart body
     * @param string $what What the text is, for the message
     * @param string $text The text
     * @return void
     * @throws FedexClientException When the text is empty or has a quote, a carriage return or a line feed
     */
    protected static function checkPartText(string $what, string $text) : void
    {
        if ($text === '' || strpbrk($text, "\"\r\n") !== false) {
            throw new FedexClientException(
                "The {$what} of a part of a multipart request must not be empty, nor have a quote or a line break",
                FedexException::EC_BAD_PARAMETER
            );
        }
    }

    /**
     * Send a request that is ready (the url, the headers and the body), retrying once on auth failure, and with the retry
     * policy when it is safe to repeat
     * @param string $method HTTP method
     * @param string $url Url to call
     * @param array $headers Headers to send
     * @param string|null $postFields The body, null if none
     * @param string|null $customerTransactionId Customer transaction id to send
     * @param bool $safeToRetry True when repeating the request cannot do any harm
     * @return mixed
     * @throws FedexClientException
     * @throws FedexAuthException
     */
    protected function dispatch(
        string $method,
        string $url,
        array $headers,
        ?string $postFields,
        ?string $customerTransactionId,
        bool $safeToRetry
    )
    {
        $call = function () use ($method, $url, $headers, $postFields, $customerTransactionId) {
            return $this->retryOnAuth(function (bool $refresh) use ($method, $url, $headers, $postFields, $customerTransactionId) {
                return $this->perform($method, $url, $headers, $postFields, $refresh, $customerTransactionId);
            });
        };

        if (!$safeToRetry || !$this->retry->isEnabled()) {
            return $call();
        }

        return $this->retry->run($call);
    }

    /**
     * Call the FedEx api endpoint and return result
     * @param string $method HTTP Method
     * @param string $url Url to call
     * @param array $headers Headers to send
     * @param mixed $postFields Data to send as post, null if none
     * @param bool $forceRefresh Force auth token refresh?
     * @param string|null $customerTransactionId Customer transaction id to send
     * @return mixed
     * @throws FedexClientException
     * @throws FedexAuthException
     */
    protected function perform(
        string $method,
        string $url,
        array $headers,
        $postFields,
        bool $forceRefresh,
        ?string $customerTransactionId
    )
    {
        $headers = array_merge(
            [
                'Authorization' => 'Bearer ' . $this->auth->getToken($forceRefresh),
                'Accept' => 'application/json',
                'X-locale' => $this->locale
            ],
            $headers
        );

        if (!empty($customerTransactionId)) {
            $headers['X-customer-transaction-id'] = $customerTransactionId;
        }

        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        $response = $this->transport->send(
            strtoupper($method),
            $url,
            $formatted,
            $postFields,
            $this->timeout,
            $this->connectTimeout
        );

        if ($response->hasFailed()) {
            throw new FedexClientException(
                'Transport error: ' . $response->getError(),
                FedexException::EC_TRANSPORT_ERROR,
                null,
                $response->getStatus()
            );
        }

        return $this->parse(
            $response->getBody(),
            $response->getStatus(),
            FedexUtils::parseRetryAfter($response->getHeader('Retry-After'))
        );
    }

    /**
     * Parse the data received from a JSON FedexApi response
     * @param string $raw Response data
     * @param int $status Http status code
     * @param int|null $retryAfter Seconds that FedEx asked to wait (the Retry-After header). It is kept in the
     * exception of a response that is not a success
     * @return mixed
     * @throws FedexClientException
     */
    protected function parse(string $raw, int $status, ?int $retryAfter = null)
    {
        $data = ($raw === '') ? null : json_decode($raw);
        $retryAfter = (($status < 200) || ($status >= 300)) ? $retryAfter : null;

        if (($raw !== '') && is_null($data)) {
            throw new FedexClientException(
                'Invalid JSON response (HTTP ' . $status . ')',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                $status,
                null,
                [],
                null,
                null,
                $retryAfter
            );
        }

        if (($status < 200) || ($status >= 300)) {
            throw new FedexClientException(
                FedexUtils::httpStatusText($status, true),
                (401 === $status) ? FedexException::EC_UNABLE_TO_AUTHENTICATE : FedexException::EC_TRANSPORT_ERROR,
                null,
                $status,
                FedexUtils::extractString($data, 'transactionId'),
                FedexUtils::extractErrors($data),
                null,
                FedexUtils::extractString($data, 'customerTransactionId'),
                $retryAfter
            );
        }

        return $data;
    }
}
