<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Api\FedexPostalCodeValidation;

/**
 * Postal code validation for the tests that exposes the check of the response, which is protected
 */
class ExposedPostal extends FedexPostalCodeValidation
{
    /**
     * Check a response as validate() does after the call
     * @param mixed $response The response
     * @return \stdClass
     * @throws \Mariopereira\Fedex\Exception\FedexPostalCodeValidationException
     */
    public function callValidate($response)
    {
        return $this->validateAndParse($response);
    }
}
