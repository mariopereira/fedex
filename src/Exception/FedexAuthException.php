<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Exception;

class FedexAuthException extends FedexException {
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
        parent::__construct($message, $code, $previous, $httpStatus, $transactionId, $errors, $data, $customerTransactionId);
    }
}
