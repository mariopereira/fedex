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
 * JSON HTTP client for the FedEx APIs: adds the authorization headers and retries once when the token is rejected
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

        if (empty($this->locale)) {
            $this->locale = 'en_US';
        }
    }

    /**
     * Generate the url to call
     * @param string $path Path to call
     * @param array $query Query parameters
     * @return string
     */
    protected function buildUrl(string $path, array $query) : string
    {
        $url = FedexUtils::getUrl($this->auth->inSandbox()) . ltrim($path, '/');

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
        ?string $customerTransactionId = null
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

        return $this->retryOnAuth(function (bool $refresh) use ($method, $url, $headers, $postFields, $customerTransactionId) {
            return $this->perform($method, $url, $headers, $postFields, $refresh, $customerTransactionId);
        });
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

        return $this->parse($response->getBody(), $response->getStatus());
    }

    /**
     * Parse the data received from a JSON FedexApi response
     * @param string $raw Response data
     * @param int $status Http status code
     * @return mixed
     * @throws FedexClientException
     */
    protected function parse(string $raw, int $status)
    {
        $data = ($raw === '') ? null : json_decode($raw);

        if (($raw !== '') && is_null($data)) {
            throw new FedexClientException(
                'Invalid JSON response (HTTP ' . $status . ')',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                $status
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
                FedexUtils::extractString($data, 'customerTransactionId')
            );
        }

        return $data;
    }
}
