<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Http;

/**
 * Result of an HTTP request sent by a transport
 */
class FedexTransportResponse
{
    /**
     * HTTP status code received, 0 if no response was received
     * @var int
     */
    protected int $status;

    /**
     * Body received, empty if there is none
     * @var string
     */
    protected string $body;

    /**
     * Description of the failure when the request could not be completed, null if it was completed
     * @var string|null
     */
    protected ?string $error;

    /**
     * Initialize the object
     * @param int $status HTTP status code received, 0 if no response was received
     * @param string $body Body received, empty if there is none
     * @param string|null $error Description of the failure when the request could not be completed, null if it was completed
     */
    public function __construct(int $status, string $body = '', ?string $error = null)
    {
        $this->status = $status;
        $this->body = $body;
        $this->error = $error;
    }

    /**
     * Get the HTTP status code received
     * @return int 0 if no response was received
     */
    public function getStatus() : int
    {
        return $this->status;
    }

    /**
     * Get the body received
     * @return string Empty if there is no body
     */
    public function getBody() : string
    {
        return $this->body;
    }

    /**
     * Get the description of the failure
     * @return string|null Null if the request was completed
     */
    public function getError() : ?string
    {
        return $this->error;
    }

    /**
     * Check if the request could not be completed (network failure, timeout, ...).
     * An HTTP error status is not a failure: the request was completed and the status is in getStatus()
     * @return bool
     */
    public function hasFailed() : bool
    {
        return !is_null($this->error);
    }
}
