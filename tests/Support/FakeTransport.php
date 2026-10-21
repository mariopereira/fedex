<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Http\FedexTransport;
use Mariopereira\Fedex\Http\FedexTransportResponse;

/**
 * Transport for the tests: records every call and answers from a queue, so that nothing goes to the network
 */
class FakeTransport implements FedexTransport
{
    /**
     * The calls received, in order: method, url, headers, body, timeout and connectTimeout
     * @var array
     */
    public $calls = [];

    /**
     * The answers to give, in order: a FedexTransportResponse, or a callable that receives the method, the url, the
     * headers and the body and returns one
     * @var array
     */
    public $queue = [];

    /**
     * The answer to give when the queue is empty
     * @var FedexTransportResponse|null
     */
    public $default;

    /**
     * Initialize the object
     * @param array $queue The answers to give, in order
     * @param FedexTransportResponse|null $default The answer to give when the queue is empty
     */
    public function __construct(array $queue = [], ?FedexTransportResponse $default = null)
    {
        $this->queue = $queue;
        $this->default = $default;
    }

    /**
     * Record the call and answer it
     * @param string $method HTTP method
     * @param string $url Full url
     * @param string[] $headers Header lines, each one 'Name: value'
     * @param string|null $body Request body, or null when there is none
     * @param int $timeout Total timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @return FedexTransportResponse
     * @throws \RuntimeException When there is nothing to answer with
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout, int $connectTimeout) : FedexTransportResponse
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body', 'timeout', 'connectTimeout');
        $next = array_shift($this->queue);

        if (is_callable($next)) {
            return $next($method, $url, $headers, $body);
        }

        if ($next instanceof FedexTransportResponse) {
            return $next;
        }

        if ($this->default instanceof FedexTransportResponse) {
            return $this->default;
        }

        throw new \RuntimeException('FakeTransport: no response queued for call #' . count($this->calls));
    }

    /**
     * The body of a call, decoded
     * @param int $index Number of the call, from 0
     * @return array|null Null when there was no such call or it had no body
     */
    public function bodyOf(int $index = 0): ?array
    {
        if (!isset($this->calls[$index]) || ($this->calls[$index]['body'] === null)) {
            return null;
        }

        return json_decode($this->calls[$index]['body'], true);
    }
}
