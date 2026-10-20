<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Auth;

use Mariopereira\Fedex\Exception\FedexException as Fde;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Http\CurlTransport;
use Mariopereira\Fedex\Http\FedexTransport;
use Mariopereira\Fedex\Misc\FedexUtils;

/**
 * OAuth authorization: requests the access token from FedEx and keeps it until it expires
 */
class FedexAuth
{
    /**
     * Seconds subtracted from the token lifetime, so that the token is renewed before FedEx expires it
     * @var int
     */
    private const EXPIRY_MARGIN = 60;

    /**
     * Child key field name in lower case
     * @var string
     */
    public const CHILD_KEY_FIELD_LOWER = 'child_key';

    /**
     * Child key field name as written in the FedEx API schema
     * @var string
     */
    public const CHILD_KEY_FIELD_SCHEMA = 'child_Key';

    /**
     * Are we using sandbox or production?
     * @var bool
     */
    protected bool $sandbox = true;

    /**
     * The type of grant to request
     * @var string|null
     */
    protected ?string $grantType = null;

    /**
     * The client id
     * @var string|null
     */
    protected ?string $clientId = null;

    /**
     * The client secret
     * @var string|null
     */
    protected ?string $clientSecret = null;

    /**
     * The child key
     * @var string|null
     */
    protected ?string $childKey = null;

    /**
     * The child secret
     * @var string|null
     */
    protected ?string $childSecret = null;

    /**
     * Name of the field used to send the child key
     * @var string
     */
    protected string $childKeyField = self::CHILD_KEY_FIELD_LOWER;

    /**
     * Access token retrieved
     * @var string|null
     */
    protected ?string $accessToken = null;

    /**
     * Type of access token retrieved
     * @var string|null
     */
    protected ?string $tokenType = null;

    /**
     * Token duration in seconds
     * @var int
     */
    protected int $tokenDuration = 0;

    /**
     * Timestamp at which the token expires
     * @var int
     */
    protected int $tokenExpiration = 0;

    /**
     * Token scope
     * @var string|null
     */
    protected ?string $tokenScope = null;

    /**
     * Timeout in seconds for the token request
     * @var int
     */
    protected int $timeout = 30;

    /**
     * Connect timeout in seconds for the token request
     * @var int
     */
    protected int $connectTimeout = 10;

    /**
     * Transport used to send the token request
     * @var FedexTransport
     */
    protected FedexTransport $transport;

    /**
     * Initialize the object
     * @param bool $sandbox True for sandbox mode, false for production mode
     * @param string $grantType Type of grant to request
     * @param string $clientId The client id
     * @param string $clientSecret The client secret
     * @param string|null $childKey The child key, if needed
     * @param string|null $childSecret The child secret, if needed
     * @param FedexTransport|null $transport Transport used to send the token request (defaults to CurlTransport)
     * @throws FedexAuthException
     */
    public function __construct(
        bool $sandbox,
        string $grantType,
        string $clientId,
        string $clientSecret,
        ?string $childKey = null,
        ?string $childSecret = null,
        ?FedexTransport $transport = null
    )
    {
        if (!FedexUtils::isValidGrantType($grantType)) {
            throw new FedexAuthException('Invalid grant type specified', Fde::EC_BAD_PARAMETER);
        }

        if (empty($clientId) || empty($clientSecret)) {
            throw new FedexAuthException('Both client id and secret must be specified', Fde::EC_MISSING_PARAMETER);
        }

        $needsChild = FedexUtils::grantNeedsChildAuth($grantType);

        if ($needsChild) {
            if (empty($childKey) || empty($childSecret)) {
                throw new FedexAuthException(
                    'The grant type requires both the child key and the child secret to be specified',
                    Fde::EC_MISSING_PARAMETER
                );
            }
        } else {
            if (!empty($childKey) || !empty($childSecret)) {
                throw new FedexAuthException(
                    'The grant type does not use child key or secret',
                    Fde::EC_BAD_PARAMETER
                );
            }
        }

        $this->sandbox = $sandbox;
        $this->grantType = $grantType;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->childKey = $needsChild ? $childKey : null;
        $this->childSecret = $needsChild ? $childSecret : null;
        $this->transport = $transport ?? new CurlTransport();
    }

    /**
     * Are we running in the sandbox
     * @return bool
     */
    public function inSandbox() : bool
    {
        return $this->sandbox;
    }

    /**
     * Set the timeouts used when requesting a token
     * @param int $timeout Timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @return FedexAuth
     * @throws FedexAuthException
     */
    public function setTimeouts(int $timeout, int $connectTimeout) : FedexAuth
    {
        if (($timeout < 1) || ($connectTimeout < 1)) {
            throw new FedexAuthException('Timeouts must be positive numbers', Fde::EC_BAD_PARAMETER);
        }

        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;

        return $this;
    }

    /**
     * Set the name of the field used to send the child key.
     * The default is child_key, the FedEx API schema writes it as child_Key. Only one of the two names is ever sent, never both
     * @param string $field FedexAuth::CHILD_KEY_FIELD_LOWER or FedexAuth::CHILD_KEY_FIELD_SCHEMA
     * @return FedexAuth
     * @throws FedexAuthException
     */
    public function setChildKeyField(string $field) : FedexAuth
    {
        if (!in_array($field, [self::CHILD_KEY_FIELD_LOWER, self::CHILD_KEY_FIELD_SCHEMA], true)) {
            throw new FedexAuthException('Invalid child key field name', Fde::EC_BAD_PARAMETER);
        }

        $this->childKeyField = $field;

        return $this;
    }

    /**
     * Create the body to send to the authorization endpoint
     * @return string
     */
    protected function body() : string
    {
        $params = [
            'grant_type' => $this->grantType,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];

        if (FedexUtils::grantNeedsChildAuth($this->grantType)) {
            $params[$this->childKeyField] = $this->childKey;
            $params['child_secret'] = $this->childSecret;
        }

        $body = http_build_query($params);

        return $body;
    }

    /**
     * Get access token from FedEx
     * @return FedexAuth
     * @throws FedexAuthException
     */
    public function authorize() : FedexAuth
    {
        $this->clearToken();

        $url = FedexUtils::getUrl($this->sandbox) . 'oauth/token';

        $result = $this->transport->send(
            'POST',
            $url,
            ['Content-Type: application/x-www-form-urlencoded'],
            $this->body(),
            $this->timeout,
            $this->connectTimeout
        );

        $response = $result->getBody();
        $httpCode = $result->getStatus();

        if ($result->hasFailed()) {
            throw new FedexAuthException(
                'Transport error: ' . $result->getError(),
                Fde::EC_TRANSPORT_ERROR,
                null,
                $httpCode
            );
        }

        $data = json_decode($response);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $jsonError = json_last_error_msg();
            $data = null;

            if (200 === $httpCode) {
                throw new FedexAuthException(
                    'Invalid data received from server (' . $jsonError . ')',
                    Fde::EC_BAD_RESULT_RETURNED,
                    null,
                    200
                );
            }
        }

        if (200 !== $httpCode) {
            $code = (401 === $httpCode) ? Fde::EC_UNABLE_TO_AUTHENTICATE : Fde::EC_TRANSPORT_ERROR;

            if (is_null($data)) {
                throw new FedexAuthException(
                    FedexUtils::httpStatusText($httpCode, true),
                    $code,
                    null,
                    $httpCode
                );
            }

            throw new FedexAuthException(
                FedexUtils::httpStatusText($httpCode, true),
                $code,
                null,
                $httpCode,
                FedexUtils::extractString($data, 'transactionId'),
                FedexUtils::extractErrors($data),
                null
            );
        }

        if (!($data instanceof \stdClass)) {
            throw new FedexAuthException(
                'Invalid data received from server',
                Fde::EC_BAD_RESULT_RETURNED,
                null,
                200
            );
        }

        $this->setToken($data);

        return $this;
    }

    /**
     * Clear token data
     * @return FedexAuth
     */
    public function clearToken() : FedexAuth
    {
        $this->accessToken = null;
        $this->tokenType = null;
        $this->tokenScope = null;
        $this->tokenDuration = 0;
        $this->tokenExpiration = 0;

        return $this;
    }

    /**
     * Store token data.
     * The access token and the token type must be non-empty strings and the expiration time a positive integer
     * @param \stdClass $authData Data received from authorize() request
     * @return FedexAuth
     * @throws FedexAuthException
     */
    protected function setToken(\stdClass $authData) : FedexAuth
    {
        $this->clearToken();

        $accessToken = $authData->access_token ?? null;
        $tokenType = $authData->token_type ?? null;
        $expiresIn = FedexUtils::extractInt($authData, 'expires_in');
        $invalid = [];

        if (!is_string($accessToken) || ('' === $accessToken)) {
            $invalid[] = 'access_token';
        }

        if (!is_string($tokenType) || ('' === $tokenType)) {
            $invalid[] = 'token_type';
        }

        if (is_null($expiresIn) || ($expiresIn < 1)) {
            $invalid[] = 'expires_in';
        }

        if (count($invalid)) {
            $redacted = clone $authData;

            if (isset($redacted->access_token)) {
                $redacted->access_token = '[redacted]';
            }

            throw new FedexAuthException(
                'Invalid data received from server (' . implode(', ', $invalid) . ')',
                Fde::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $redacted
            );
        }

        $this->accessToken = $accessToken;
        $this->tokenType = $tokenType;
        $this->tokenScope = FedexUtils::extractString($authData, 'scope');
        $this->tokenDuration = $expiresIn;
        $this->tokenExpiration = time() + $expiresIn - self::EXPIRY_MARGIN;

        return $this;
    }

    /**
     * Check if stored token has expired and clear it if so
     * @return FedexAuth
     */
    protected function checkExpiration() : FedexAuth
    {
        if (is_null($this->accessToken)) {
            return $this;
        }

        if (time() >= $this->tokenExpiration) {
            $this->clearToken();
        }

        return $this;
    }

    /**
     * Check if we have a token
     * @return bool
     */
    public function hasToken() : bool
    {
        $this->checkExpiration();

        return !is_null($this->accessToken);
    }

    /**
     * Get the current access token data
     * @param bool $refresh If there is no current token (or if expired) attempt to retrieve new token
     * @param bool $forceRefresh Force token refresh, even if there is a current token
     * @return \stdClass|null Null if no token obtained or if expired, object with token, type, scope and duration properties (scope is null if the server did not send one or did not send text)
     * @throws FedexAuthException
     */
    public function getTokenData(bool $refresh = true, bool $forceRefresh = false) : ?\stdClass
    {
        if ($forceRefresh) {
            $refresh = true;
            $this->clearToken();
        }

        if (!$this->hasToken()) {
            if (!$refresh) {
                return null;
            }

            $this->authorize();
        }

        $data = (object) [
            'token' => $this->accessToken,
            'type' => $this->tokenType,
            'scope' => $this->tokenScope,
            'duration' => $this->tokenExpiration - time()
        ];

        return $data;
    }

    /**
     * Get the current access token
     * @param bool $forceRefresh Force token refresh, even if there is a current token
     * @return string Access token
     * @throws FedexAuthException
     */
    public function getToken(bool $forceRefresh = false) : string
    {
        $data = $this->getTokenData(true, $forceRefresh);

        if (is_null($data)) {
            throw new FedexAuthException('Unable to retrieve access token', Fde::EC_UNABLE_TO_AUTHENTICATE);
        }

        return $data->token;
    }
}
