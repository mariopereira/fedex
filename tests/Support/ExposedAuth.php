<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Auth\FedexAuth;

/**
 * Authorization for the tests that exposes what FedexAuth keeps protected: the body of the token request, the storing of
 * a token and what is stored
 */
class ExposedAuth extends FedexAuth
{
    /**
     * Call the protected setToken()
     * @param \stdClass $data Data received from the token request
     * @return FedexAuth
     * @throws \Mariopereira\Fedex\Exception\FedexAuthException
     */
    public function callSetToken(\stdClass $data)
    {
        return $this->setToken($data);
    }

    /**
     * The body of the token request, as a text
     * @return string
     */
    public function rawBody(): string
    {
        return $this->body();
    }

    /**
     * The body of the token request, as an array
     * @return array
     */
    public function callBody(): array
    {
        parse_str($this->body(), $out);

        return $out;
    }

    /**
     * What is stored: access token, token type, duration, scope and expiration
     * @return array
     */
    public function stored(): array
    {
        return [$this->accessToken, $this->tokenType, $this->tokenDuration, $this->tokenScope, $this->tokenExpiration];
    }

    /**
     * The timeouts in use: total and connect
     * @return int[]
     */
    public function timeouts(): array
    {
        return [$this->timeout, $this->connectTimeout];
    }

    /**
     * A sandbox ExposedAuth with the usual credentials
     * @return ExposedAuth
     */
    public static function make(): self
    {
        return new self(true, 'client_credentials', 'id', 'secret');
    }
}
