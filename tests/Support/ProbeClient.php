<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Http\FedexClient;

/**
 * Client for the tests that exposes the protected parse() of FedexClient
 */
class ProbeClient extends FedexClient
{
    /**
     * Parse a raw response, as the client does after a request
     * @param string $raw Response body
     * @param int $status HTTP status
     * @return mixed
     * @throws \Mariopereira\Fedex\Exception\FedexClientException
     */
    public function callParse(string $raw, int $status)
    {
        return $this->parse($raw, $status);
    }
}
