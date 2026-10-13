<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Exception;

class FedexException extends \Exception
{
    /**
     * Errors returned from server (
     * @var array
     */
    protected array $errors = [];

    /**
     * Other data sent to the exception
     * @var mixed
     */
    protected $data = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $errors = [],
        $data = null
    )
    {
        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
        $this->data = $data;
    }

    /**
     * Get the errors returned from the server
     * @return array
     */
    public function getErrors() : array
    {
        return $this->errors();
    }

    /**
     * Get the errors returned from the server as a string.
     * Assumes that each element of the errors array is an array with a code and message index.
     * @return string
     */
    public function getErrorsAsString() : string
    {
        if (!count($this->errors)) {
            return '';
        }

        $errors = [];

        foreach ($this->errors as $err) {
            $tmp = '';

            if (isset($err['code'])) {
                $tmp = $err['code'];
            }

            if (isset($err['message'])) {
                if (!empty($tmp)) {
                    $tmp .= ': ';
                }

                $tmp .= $err['message'];
            }

            if (!empty($tmp)) {
                $errors[] = $tmp;
            }
        }

        if (!count($errors)) {
            return '';
        }

        $errors = array_map('trim', $errors);
        $errors = array_filter($errors);

        return implode(', ', $errors);
    }

    /**
     * Get aditional data sent to the exception
     * @return type
     */
    public function getData()
    {
        return $this->data;
    }
}
