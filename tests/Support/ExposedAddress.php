<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Api\FedexAddressValidation;

/**
 * Address validation for the tests that exposes the definitions of the response, which are protected
 */
class ExposedAddress extends FedexAddressValidation
{
    /**
     * The definitions used to validate the response
     * @return array
     */
    public function outputDefinitions(): array
    {
        return $this->outputSchemas();
    }
}
