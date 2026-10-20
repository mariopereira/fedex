<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Http\FedexClient;

/**
 * Base class of the FedEx API operations: holds the shared settings and creates the HTTP client
 */
abstract class AbstractFedexApi
{
    /**
     * Communication client
     * @var FedexClient
     */
    protected FedexClient $client;

    /**
     * Initialize the object
     * @param FedexAuth $auth Fedex Authorization class, to fetch tokens
     * @param string $locale Locale to use (defaults to en_US)
     * @param int $timeout Timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @param FedexClient|null $client Client to use. When it is supplied the locale and the timeouts above are not
     * used: they have to be configured on that client
     */
    public function __construct(
        FedexAuth $auth,
        string $locale = 'en_US',
        int $timeout = 30,
        int $connectTimeout = 10,
        ?FedexClient $client = null
    )
    {
        $this->client = $client ?? new FedexClient($auth, $locale, $timeout, $connectTimeout);
    }
}
