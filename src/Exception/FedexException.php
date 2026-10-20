<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Exception;

use Mariopereira\Fedex\Misc\FedexUtils;

/**
 * Base exception of the package: carries the details returned by FedEx, if any
 */
class FedexException extends \RuntimeException
{
    /**
     * Error code: a required parameter is missing
     * @var int
     */
    public const EC_MISSING_PARAMETER = 1;

    /**
     * Error code: a parameter has an invalid value
     * @var int
     */
    public const EC_BAD_PARAMETER = 2;

    /**
     * Error code: the request failed (network failure or HTTP error status)
     * @var int
     */
    public const EC_TRANSPORT_ERROR = 100;

    /**
     * Error code: the response received is not valid
     * @var int
     */
    public const EC_BAD_RESULT_RETURNED = 101;

    /**
     * Error code: authentication failed (no access token could be obtained, or HTTP status 401)
     * @var int
     */
    public const EC_UNABLE_TO_AUTHENTICATE = 200;

    /**
     * HTTP status code returned by the call that generated the exception
     * @var int
     */
    protected int $httpStatus = 0;

    /**
     * Transaction id returned from server (null if none)
     * @var string|null
     */
    protected ?string $transactionId = null;

    /**
     * Errors returned from server
     * @var array
     */
    protected array $errors = [];

    /**
     * Other data sent to the exception
     * @var mixed
     */
    protected $data = null;

    /**
     * Supplied customer transaction id
     * @var string|null
     */
    protected ?string $customerTransactionId = null;

    /**
     * Initialize the exception
     * @param string $message The Exception message to throw
     * @param int $code The Exception code
     * @param \Throwable|null $previous The previous exception used for the exception chaining
     * @param int $httpStatus HTTP status code returned by the call that generated the exception
     * @param string|null $transactionId Unique identifier returned in the reply and helps you match the request to the reply.
     * @param array $errors Array of errors returned
     * @param mixed $data Any other data to send with the exception
     * @param string|null $customerTransactionId Unique identifier supplied when calling the API
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        int $httpStatus = 0,
        ?string $transactionId = null,
        array $errors = [],
        $data = null,
        ?string $customerTransactionId = null
    )
    {
        parent::__construct($message, $code, $previous);

        $this->httpStatus = $httpStatus;
        $this->transactionId = $transactionId;
        $this->errors = $errors;
        $this->data = $data;
        $this->customerTransactionId = $customerTransactionId;
    }

    /**
     * Get the HTTP status code returned by the call that generated the exception
     * @return int
     */
    public function getStatusCode() : int
    {
        return $this->httpStatus;
    }

    /**
     * Get the transaction id returned from the server
     * @return string|null
     */
    public function getTransactionId() : ?string
    {
        return $this->transactionId;
    }

    /**
     * Get the errors returned from the server
     * @return array
     */
    public function getErrors() : array
    {
        return $this->errors;
    }

    /**
     * Get the errors returned from the server as a string.
     * @return string
     */
    public function getErrorsAsString() : string
    {
        $errors = [];

        foreach ($this->errors as $err) {
            // Plain text errors (e.g. from the schema validator) are used as they are
            if (is_string($err)) {
                $errors[] = $err;
                continue;
            }

            if (is_array($err)) {
                $err = FedexUtils::arrayToObject($err);
            }

            if (!is_object($err)) {
                continue;
            }

            $tmp = '';

            if (isset($err->code)) {
                $tmp = $err->code;
            }

            if (isset($err->message)) {
                if (!empty($tmp)) {
                    $tmp .= ': ';
                }

                $tmp .= $err->message;
            }

            if (isset($err->parameterList) && is_array($err->parameterList)) {
                $tmpParam = [];

                foreach ($err->parameterList as $p) {
                    if (is_array($p)) {
                        $p = FedexUtils::arrayToObject($p);
                    }

                    $ptext = isset($p->key) ? $p->key : '?key?';
                    $ptext .= ' = ';
                    $ptext .= isset($p->value) ? $p->value : '?value?';

                    $tmpParam[] = $ptext;
                }

                if (count($tmpParam)) {
                    $tmp .= ' Parameters: ' . implode(', ', $tmpParam);
                }
            }

            if (!empty($tmp)) {
                $errors[] = $tmp;
            }
        }

        $errors = array_filter(array_map('trim', $errors), 'strlen');

        return implode('; ', $errors);
    }

    /**
     * Get additional data sent to the exception
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the unique identifier sent when calling the API
     * @return string|null
     */
    public function getCustomerTransactionId() : ?string
    {
        return $this->customerTransactionId;
    }
}
