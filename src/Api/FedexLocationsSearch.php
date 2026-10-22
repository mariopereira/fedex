<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexLocationsSearchSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexLocationsSearchException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Locations Search API: finds FedEx locations by address, geographic coordinates or phone number (location/v1/locations)
 */
class FedexLocationsSearch extends AbstractFedexApi
{
    /**
     * Locations Search API endpoint
     * @var string
     */
    const ENDPOINT = 'location/v1/locations';

    /**
     * Search criterion: search by address (the default of the API)
     * @var string
     */
    public const CRITERION_ADDRESS = 'ADDRESS';

    /**
     * Search criterion: search by geographic coordinates
     * @var string
     */
    public const CRITERION_GEOGRAPHIC_COORDINATES = 'GEOGRAPHIC_COORDINATES';

    /**
     * Search criterion: search by (landline) phone number
     * @var string
     */
    public const CRITERION_PHONE_NUMBER = 'PHONE_NUMBER';

    /**
     * Initialize the object
     * @param FedexAuth $auth Fedex Authorization class, to fetch tokens
     * @param string $locale Locale to use (defaults to en_US)
     * @param int $timeout Timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @param FedexClient|null $client Client to use. When it is supplied the locale and the timeouts above are not
     * used: they have to be configured on that client
     */
    public function __construct(
        FedexAuth $auth,
        string $locale = 'en_US',
        int $timeout = 30,
        int $connectTimeout = 10,
        ?FedexClient $client = null
    )
    {
        parent::__construct($auth, $locale, $timeout, $connectTimeout, $client);
    }

    /**
     * Create an empty request: every field of the request defined by the API, named as in schemas/locations.json.
     * Fields that are not needed can be left as they are: null (and the empty lists) are left out when the request
     * is sent. To add an item to locationCapabilities or packageAttributes, append an array with the fields of the item
     * @return array
     */
    public static function newRequest() : array
    {
        return [
            'locationsSummaryRequestControlParameters' => [
                'distance' => [
                    'units' => null,
                    'value' => null
                ],
                'maxResults' => null
            ],
            'constraints' => [
                'locationContentOptions' => [],
                'dropOffTimeNeeded' => null,
                'excludeUnavailableLocations' => null
            ],
            'locationSearchCriterion' => null,
            'location' => [
                'address' => [
                    'streetLines' => [],
                    'city' => null,
                    'stateOrProvinceCode' => null,
                    'postalCode' => null,
                    'countryCode' => null,
                    'residential' => null,
                    'addressVerificationId' => null,
                    'addressClassification' => null,
                    'addressClassificationConfidence' => null
                ],
                'longLat' => [
                    'latitude' => null,
                    'longitude' => null
                ]
            ],
            'phoneNumber' => null,
            'multipleMatchesAction' => null,
            'sort' => [
                'criteria' => null,
                'order' => null
            ],
            'trackingInfo' => [
                'uniqueTrackingId' => null,
                'trackingNumber' => null,
                'shipDate' => null
            ],
            'sameState' => null,
            'sameCountry' => null,
            'redirectToHoldType' => null,
            'locationAttrTypes' => [],
            'locationCapabilities' => [],
            'packageAttributes' => [],
            'locationTypes' => [],
            'includeHoliday' => null,
            'dropoffTime' => null,
            'dropOffServiceType' => null,
            'carrierCodes' => [],
            'getCall' => null,
            'packagingType' => null,
            'totalDeclaredValue' => [
                'amount' => null,
                'currency' => null
            ],
            'totalCustomsValue' => [
                'amount' => null,
                'currency' => null
            ],
            'paymentType' => null
        ];
    }

    /**
     * Search for FedEx locations.
     * The request is checked before it is sent: the fields, their types and their allowed values are the ones of
     * schemas/locations.json (a field that is not in the schema is an error), the country code is required, and the
     * data needed by the search criterion must be present: a city or a postal code for ADDRESS (the default),
     * the latitude and the longitude for GEOGRAPHIC_COORDINATES and the phone number for PHONE_NUMBER.
     * Fields set to null and empty lists are left out. The latitude and the longitude are strings, as in the schema
     * @param array|\stdClass $request The request, as returned by newRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexLocationsSearchException
     */
    public function search($request) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexLocationsSearchException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = new SchemaValidator(false, FedexLocationsSearchSchemas::definitions());

        if (!$validator->validate($request, '@' . FedexLocationsSearchSchemas::REQUEST)) {
            throw new FedexLocationsSearchException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $this->checkRequest($request);

        $customerTransactionId = FedexUtils::uuidV4();

        $response = $this->client->request('POST', self::ENDPOINT, $request, [], [], $customerTransactionId, true);

        return $this->validateAndParse($response);
    }

    /**
     * Search for FedEx locations by address
     * @param array $address The address, with the fields of the address of the schema (streetLines, city,
     * stateOrProvinceCode, postalCode, countryCode, ...). The country code and a city or a postal code are required
     * @param array $options Any other field of the request (maxResults, distance, sort, locationTypes, ...), named as
     * in the schema. locationSearchCriterion and location cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexLocationsSearchException
     */
    public function searchByAddress(array $address, array $options = []) : \stdClass
    {
        $request = $this->helperRequest($options, ['locationSearchCriterion', 'location'], __FUNCTION__);
        $request['locationSearchCriterion'] = self::CRITERION_ADDRESS;
        $request['location'] = ['address' => $address];

        return $this->search($request);
    }

    /**
     * Search for FedEx locations by geographic coordinates
     * @param int|float|string $latitude The latitude, as a number
     * @param int|float|string $longitude The longitude, as a number
     * @param string $countryCode The country code
     * @param array $options Any other field of the request (maxResults, distance, sort, locationTypes, ...), named as
     * in the schema. locationSearchCriterion and location cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexLocationsSearchException
     */
    public function searchByCoordinates($latitude, $longitude, string $countryCode, array $options = []) : \stdClass
    {
        $request = $this->helperRequest($options, ['locationSearchCriterion', 'location'], __FUNCTION__);
        $request['locationSearchCriterion'] = self::CRITERION_GEOGRAPHIC_COORDINATES;
        $request['location'] = [
            'address' => ['countryCode' => $countryCode],
            'longLat' => [
                'latitude' => $this->coordinate($latitude, 'latitude'),
                'longitude' => $this->coordinate($longitude, 'longitude')
            ]
        ];

        return $this->search($request);
    }

    /**
     * Search for FedEx locations by phone number
     * @param string $phoneNumber The phone number (only landline numbers are allowed)
     * @param string $countryCode The country code
     * @param array $options Any other field of the request (maxResults, distance, sort, locationTypes, ...), named as
     * in the schema. locationSearchCriterion, location and phoneNumber cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexLocationsSearchException
     */
    public function searchByPhoneNumber(string $phoneNumber, string $countryCode, array $options = []) : \stdClass
    {
        $request = $this->helperRequest($options, ['locationSearchCriterion', 'location', 'phoneNumber'], __FUNCTION__);
        $request['locationSearchCriterion'] = self::CRITERION_PHONE_NUMBER;
        $request['phoneNumber'] = $phoneNumber;
        $request['location'] = ['address' => ['countryCode' => $countryCode]];

        return $this->search($request);
    }

    /**
     * Start the request of a search method with the options received
     * @param array $options Options received by the search method
     * @param string[] $reserved Fields that the search method sets and that cannot be used as options
     * @param string $method Name of the search method, for the error message
     * @return array
     * @throws FedexLocationsSearchException
     */
    protected function helperRequest(array $options, array $reserved, string $method) : array
    {
        foreach ($reserved as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexLocationsSearchException(
                    "The option '" . $field . "' cannot be used with " . $method . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        return $options;
    }

    /**
     * Convert a coordinate to the string that the API expects
     * @param int|float|string $value The coordinate
     * @param string $name Name of the coordinate, for the error message
     * @return string
     * @throws FedexLocationsSearchException
     */
    protected function coordinate($value, string $name) : string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value) && is_finite($value)) {
            // sprintf() with F does not depend on the locale
            return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
        }

        // white space around a number is accepted by is_numeric() only from PHP 8: trim it first
        if (is_string($value) && is_numeric(trim($value))) {
            return trim($value);
        }

        throw new FedexLocationsSearchException(
            'The ' . $name . ' must be a number',
            FedexException::EC_BAD_PARAMETER
        );
    }

    /**
     * Check what the schema cannot express: the country code and the data needed by the search criterion.
     * Called with a request that already passed the schema, so that location and its address exist
     * @param array $request The request
     * @return void
     * @throws FedexLocationsSearchException
     */
    protected function checkRequest(array $request) : void
    {
        $criterion = $request['locationSearchCriterion'] ?? self::CRITERION_ADDRESS;
        $address = $request['location']['address'];
        $country = trim($address['countryCode']);
        $length = function_exists('mb_strlen') ? mb_strlen($country) : strlen($country);

        if (($length < 1) || ($length > 5)) {
            throw new FedexLocationsSearchException(
                'The country code must have from 1 to 5 characters',
                FedexException::EC_BAD_PARAMETER
            );
        }

        foreach ($address['streetLines'] ?? [] as $line) {
            if ('' === trim($line)) {
                throw new FedexLocationsSearchException(
                    'The street lines cannot be empty',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        if (self::CRITERION_ADDRESS === $criterion) {
            $hasCity = ('' !== trim($address['city'] ?? ''));
            $hasPostalCode = ('' !== trim($address['postalCode'] ?? ''));

            if (!$hasCity && !$hasPostalCode) {
                throw new FedexLocationsSearchException(
                    'A city or a postal code is required to search by address',
                    FedexException::EC_MISSING_PARAMETER
                );
            }
        } elseif (self::CRITERION_GEOGRAPHIC_COORDINATES === $criterion) {
            $coordinates = $request['location']['longLat'] ?? [];

            if (!isset($coordinates['latitude']) || !isset($coordinates['longitude'])) {
                throw new FedexLocationsSearchException(
                    'The latitude and the longitude are required to search by geographic coordinates',
                    FedexException::EC_MISSING_PARAMETER
                );
            }
        } elseif ('' === trim($request['phoneNumber'] ?? '')) {
            throw new FedexLocationsSearchException(
                'The phone number is required to search by phone number',
                FedexException::EC_MISSING_PARAMETER
            );
        }
    }

    /**
     * Validate the response received from FedEx
     * @param \stdClass $response
     * @return \stdClass
     * @throws FedexLocationsSearchException
     */
    protected function validateAndParse($response) : \stdClass
    {
        if (!($response instanceof \stdClass)) {
            throw new FedexLocationsSearchException(
                'Invalid response',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexLocationsSearchSchemas::definitions());

        if (!$validator->validate($response, '@' . FedexLocationsSearchSchemas::RESPONSE)) {
            throw new FedexLocationsSearchException(
                'Invalid response received',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                $validator->getErrors(),
                $response
            );
        }

        return $response;
    }
}
