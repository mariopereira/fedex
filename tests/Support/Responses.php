<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Http\FedexTransportResponse;

/**
 * Builders of the answers that the tests give to the code
 */
final class Responses
{
    /**
     * A response of the transport
     * @param int $status HTTP status, 0 when the request failed before getting one
     * @param string $body Response body
     * @param string|null $error Transport error, or null when there was none
     * @param array $headers Headers of the response, as name => value
     * @return FedexTransportResponse
     */
    public static function make(int $status, string $body = '', ?string $error = null, array $headers = []): FedexTransportResponse
    {
        return new FedexTransportResponse($status, $body, $error, $headers);
    }

    /**
     * The body of a valid token response
     * @param array $override Fields to replace
     * @return string
     */
    public static function tokenBody(array $override = []): string
    {
        return json_encode(array_merge(['access_token' => 'TOK', 'token_type' => 'bearer', 'expires_in' => 3600, 'scope' => 'CXS'], $override));
    }

    /**
     * A valid token response
     * @param array $override Fields to replace
     * @return FedexTransportResponse
     */
    public static function token(array $override = []): FedexTransportResponse
    {
        return self::make(200, self::tokenBody($override));
    }
}
