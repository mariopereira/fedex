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
 * Authorization for the tests: never goes to the network, always has the token 'TOK', and records how it is asked for it
 */
class StubAuth extends FedexAuth
{
    /**
     * The value of $forceRefresh of each call to getToken()
     * @var bool[]
     */
    public $forceSeen = [];

    /**
     * Exception that getToken() has to throw, if any
     * @var \Throwable|null
     */
    public $throwOnToken = null;

    /**
     * Record the call and give the token
     * @param bool $forceRefresh True when a new token is wanted
     * @return string
     * @throws \Throwable The one in $throwOnToken, when it is set
     */
    public function getToken(bool $forceRefresh = false) : string
    {
        $this->forceSeen[] = $forceRefresh;

        if ($this->throwOnToken) {
            throw $this->throwOnToken;
        }

        return 'TOK';
    }

    /**
     * A StubAuth with the usual credentials
     * @return StubAuth
     */
    public static function make(): self
    {
        return new self(true, 'client_credentials', 'id', 'secret');
    }
}
