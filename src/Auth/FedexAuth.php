<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Auth;

use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Misc\FedexConstants;

class FedexAuth
{
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
     * Timestamp at wich the token expires
     * @var int
     */
    protected int $tokenExpiration = 0;

    /**
     * Token scope
     * @var string|null
     */
    protected ?string $tokenScope = null;

    /**
     * Initialize the object
     * @param bool $sandbox True for sandbox mode, false for production mode
     * @param string $grantType Type of grant to request
     * @param string $clientId The client id
     * @param string $clientSecret The client secrent
     * @param string|null $childKey The child key, if needed
     * @param string|null $childSecret The child secret, if needed
     * @throws FedexAuthException
     */
    public function __construct(
        bool $sandbox,
        string $grantType,
        string $clientId,
        string $clientSecret,
        ?string $childKey = null,
        ?string $childSecret = null
    )
    {
        if (!FedexConstants::isValidGrantType($grantType)) {
            throw new FedexAuthException('Invalid grant type specified');
        }

        if (empty($clientId) || empty($clientSecret)) {
            throw new FedexAuthException('Both client id and secret must be specified');
        }

        $needsChild = FedexConstants::grantNeedsChildAuth($grantType);

        if ($needsChild) {
            if (empty($childKey) || empty($childSecret)) {
                throw new FedexAuthException('The grant type requires both child key and secret must be speficied');
            }
        } else {
            if (!empty($childKey) || !empty($childSecret)) {
                throw new FedexAuthException('The grant type does not use child key or secret');
            }
        }

        $this->sandbox = $sandbox;
        $this->grantType = $grantType;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->childKey = $needsChild ? $childKey : null;
        $this->childSecret = $needsChild ? $childSecret : null;
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

        if (FedexConstants::grantNeedsChildAuth($this->grantType)) {
            $params['child_key'] = $this->childKey;
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

        $url = FedexConstants::getUrl($this->sandbox) . 'oauth/token';

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_POSTFIELDS => $this->body()
        ]);

        $response = curl_exec($curl);

        if (false === $response) {
            throw new FedexAuthException(curl_error($curl));
        }

        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $data = json_decode($response);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $data = null;

            if (200 === $httpCode) {
                throw new FedexAuthException('Invalid data received from server', 0, null, [], $response, null);
            }
        }

        if (200 !== $httpCode) {
            if (is_null($data)) {
                throw new FedexAuthException(FedexConstants::httpStatusText($httpCode, true));
            }

            throw new FedexAuthException(
                FedexConstants::httpStatusText($httpCode, true),
                0,
                null,
                $data->errors ?? [],
                null,
                $data->transactionId ?? null
            );
        }

        if (!($data instanceof \stdClass)) {
            throw new FedexAuthException('Invalid data received from server', 0, null, [], $response, null);
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
     * Store token data
     * @param \stdClass $authData Data received from authorize() request
     * @return FedexAuth
     * @throws FedexAuthException
     */
    protected function setToken(\stdClass $authData) : FedexAuth
    {
        $this->clearToken();

        if (!isset($authData->access_token) || !isset($authData->token_type) || !isset($authData->expires_in) || !isset($authData->scope)) {
            throw new FedexAuthException('Invalid data received from server', 0, null, [], $authData, null);
        }

        $this->accessToken = $authData->access_token;
        $this->tokenType = $authData->token_type;
        $this->tokenScope = $authData->scope;
        $this->tokenDuration = $authData->expires_in;
        $this->tokenExpiration = time() + $authData->expires_in;

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

        $now = time();

        if ($now >= $this->tokenExpiration) {
            $this->clearToken();
        }
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
     * @return \stdClass|null Null if no token obtained or if expired, object with token, type, scope and duration properties
     * @throws FedexAuthException
     */
    public function getTokenData(bool $refresh = false) : ?\stdClass
    {
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
}
