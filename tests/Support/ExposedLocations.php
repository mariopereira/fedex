<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Api\FedexLocationsSearch;

/**
 * Locations search for the tests that exposes the definitions, which are protected
 */
class ExposedLocations extends FedexLocationsSearch
{
    /**
     * The definitions used to validate the request and the response
     * @return array
     */
    public function definitions(): array
    {
        return $this->schemas();
    }
}
