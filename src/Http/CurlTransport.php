<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Http;

use Mariopereira\Fedex\Misc\FedexUtils;

/**
 * Default transport: sends the requests with cURL
 */
class CurlTransport implements FedexTransport
{
    /**
     * Most response headers that are kept, so that a server cannot make them grow without limit
     * @var int
     */
    private const MAX_HEADERS = 100;

    /**
     * Send an HTTP request with cURL and return what was received
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
    ) : FedexTransportResponse
    {
        $curl = curl_init($url);
        $received = [];
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$received) {
                $text = trim($line);

                if (0 === stripos($text, 'HTTP/')) {
                    // A status line starts a new response (after a 100 Continue, for example): only the last one counts
                    $received = [];
                } elseif (('' !== $text) && (false !== ($colon = strpos($text, ':'))) && !in_array($line[0], [' ', "\t"], true)) {
                    $name = strtolower(trim(substr($text, 0, $colon)));
                    $value = trim(substr($text, $colon + 1));

                    if (isset($received[$name])) {
                        $received[$name] .= ', ' . $value;
                    } elseif (count($received) < self::MAX_HEADERS) {
                        $received[$name] = $value;
                    }
                }

                return strlen($line);
            }
        ];

        if (!is_null($body)) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = (false === $raw) ? curl_error($curl) : null;

        FedexUtils::closeCurl($curl);

        return new FedexTransportResponse($status, (false === $raw) ? '' : (string) $raw, $error, (false === $raw) ? [] : $received);
    }
}
