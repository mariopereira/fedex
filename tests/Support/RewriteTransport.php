<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Http\CurlTransport;
use Mariopereira\Fedex\Http\FedexTransport;
use Mariopereira\Fedex\Http\FedexTransportResponse;

/**
 * The real CurlTransport, but the FedEx urls are rewritten to the loopback server: the token url goes to the route in
 * $tokenRoute and every other FedEx url goes to the same path on the server
 */
class RewriteTransport implements FedexTransport
{
    /**
     * The port of the loopback server
     * @var int
     */
    public $port;

    /**
     * The route of the server that stands for the token url
     * @var string
     */
    public $tokenRoute = '/token-ok';

    /**
     * The calls made: method, rewritten url, timeout and connectTimeout
     * @var array
     */
    public $calls = [];

    /**
     * Initialize the object
     * @param int $port The port of the loopback server
     */
    public function __construct(int $port)
    {
        $this->port = $port;
    }

    /**
     * Send the request to the loopback server with the real cURL
     * @param string $method HTTP method
     * @param string $url Full FedEx url
     * @param string[] $headers Header lines
     * @param string|null $body Request body
     * @param int $timeout Total timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @return FedexTransportResponse
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout, int $connectTimeout) : FedexTransportResponse
    {
        $url = str_replace('https://apis-sandbox.fedex.com/oauth/token', 'http://127.0.0.1:' . $this->port . $this->tokenRoute, $url);
        $url = str_replace(['https://apis-sandbox.fedex.com/', 'https://apis.fedex.com/'], 'http://127.0.0.1:' . $this->port . '/', $url);
        $this->calls[] = ['method' => $method, 'url' => $url, 'timeout' => $timeout, 'connectTimeout' => $connectTimeout];

        return (new CurlTransport())->send($method, $url, $headers, $body, $timeout, $connectTimeout);
    }
}
