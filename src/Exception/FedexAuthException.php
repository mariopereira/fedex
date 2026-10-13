<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Exception;

class FedexAuthException extends FedexException
{
    /**
     * Transaction id returned from server (null if none)
     * @var string|null
     */
    protected ?string $transactionId = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $errors = [],
        $data = null,
        ?string $transactionId = null
    )
    {
        parent::__construct($message, $code, $previous, $errors, $data);
        $this->transactionId = $transactionId;
    }

    /**
     * Get the transaction id returned from the server
     * @return string|null
     */
    public function getTransactionId() : ?string
    {
        return $this->transactionId;
    }
}
