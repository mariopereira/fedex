<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Http;

/**
 * Sends HTTP requests on behalf of the package. The default implementation is CurlTransport;
 * another one can be supplied to FedexClient and FedexAuth, for example to test without the network
 */
interface FedexTransport
{
    /**
     * Send an HTTP request and return what was received.
     * An HTTP error status is not a failure: it is returned in the response. A request that could not be
     * completed (network failure, timeout, ...) is returned as a response with an error, it must not throw
     * @param string $method HTTP method (GET, POST, ...)
     * @param string $url Full url to call
     * @param string[] $headers Header lines, in the form 'Name: value'
     * @param string|null $body Request body, null if there is none
     * @param int $timeout Total timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @return FedexTransportResponse
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        int $connectTimeout
    ) : FedexTransportResponse;
}
