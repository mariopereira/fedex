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
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout
        ];

        if (!is_null($body)) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = (false === $raw) ? curl_error($curl) : null;

        FedexUtils::closeCurl($curl);

        return new FedexTransportResponse($status, (false === $raw) ? '' : (string) $raw, $error);
    }
}
