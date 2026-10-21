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
 * Postal code validation that overrides both protected hooks with the signatures that an extender would write, and
 * records what they receive
 */
class HookPostal extends FedexPostalCodeValidation
{
    /**
     * What the hooks received: 'check' and 'body', each one the carrier code and the city
     * @var array
     */
    public $seen = [];

    /**
     * Check the parameters, and record the carrier code and the city
     * @param string $carrierCode Carrier code
     * @param string $countryCode Country code
     * @param string $stateOrProvinceCode State or province code
     * @param string $postalCode Postal code
     * @param string $shipDate Ship date
     * @param string|null $routingCode Routing code
     * @param bool|null $checkForMismatch Check for a mismatch
     * @param string|null $city City
     * @return void
     */
    protected function checkParameters(
        string &$carrierCode,
        string &$countryCode,
        string &$stateOrProvinceCode,
        string &$postalCode,
        string &$shipDate,
        ?string &$routingCode = null,
        ?bool &$checkForMismatch = null,
        ?string &$city = null
    )
    {
        parent::checkParameters($carrierCode, $countryCode, $stateOrProvinceCode, $postalCode, $shipDate, $routingCode, $checkForMismatch, $city);
        $this->seen['check'] = [$carrierCode, $city];
    }

    /**
     * Build the body, and record the carrier code and the city
     * @param string $carrierCode Carrier code
     * @param string $countryCode Country code
     * @param string $stateOrProvinceCode State or province code
     * @param string $postalCode Postal code
     * @param string $shipDate Ship date
     * @param string|null $routingCode Routing code
     * @param bool|null $checkForMismatch Check for a mismatch
     * @param string|null $city City
     * @return \stdClass
     */
    protected function body(
        string $carrierCode,
        string $countryCode,
        string $stateOrProvinceCode,
        string $postalCode,
        string $shipDate,
        ?string $routingCode = null,
        ?bool $checkForMismatch = null,
        ?string $city = null
    )
    {
        $this->seen['body'] = [$carrierCode, $city];

        return parent::body($carrierCode, $countryCode, $stateOrProvinceCode, $postalCode, $shipDate, $routingCode, $checkForMismatch, $city);
    }
}
