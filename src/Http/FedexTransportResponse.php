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
     * Headers received, by name in lower case. The value of a header that was received more than once has the values
     * joined with a comma
     * @var array
     */
    protected array $headers = [];

    /**
     * Initialize the object
     * @param int $status HTTP status code received, 0 if no response was received
     * @param string $body Body received, empty if there is none
     * @param string|null $error Description of the failure when the request could not be completed, null if it was completed
     * @param array $headers Headers received, as name => value (a name may be written in any case, a value that is a
     * list is joined with a comma). Optional: a transport that does not give them is not affected, only what needs a
     * header (the Retry-After of a retry) is not available
     */
    public function __construct(int $status, string $body = '', ?string $error = null, array $headers = [])
    {
        $this->status = $status;
        $this->body = $body;
        $this->error = $error;

        foreach ($headers as $name => $value) {
            $name = strtolower(trim((string) $name));

            if (is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }

            if (('' !== $name) && is_scalar($value)) {
                $this->headers[$name] = trim((string) $value);
            }
        }
    }

    /**
     * Get the headers received
     * @return array The values by name, the names in lower case. Empty when the transport gave none
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }

    /**
     * Get a header received
     * @param string $name The name of the header, in any case
     * @return string|null The value, null when the header was not received
     */
    public function getHeader(string $name) : ?string
    {
        return $this->headers[strtolower(trim($name))] ?? null;
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
