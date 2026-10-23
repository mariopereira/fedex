<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexServiceAvailabilitySchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexServiceAvailabilityException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Service Availability API: retrieves the services and the transit times, the services and the packaging options, and the
 * special service options that are available for a shipment (availability/v1).
 *
 * The three operations only ask FedEx what is available and change nothing, so a request that failed because of a timeout, or a
 * busy server, can be repeated (see FedexClient::setRetryPolicy())
 */
class FedexServiceAvailability extends AbstractFedexApi
{
    /**
     * Retrieve Services and Transit Times endpoint (POST)
     * @var string
     */
    const ENDPOINT_TRANSIT_TIMES = 'availability/v1/transittimes';

    /**
     * Retrieve Services and Packaging Options endpoint (POST)
     * @var string
     */
    const ENDPOINT_PACKAGE_OPTIONS = 'availability/v1/packageandserviceoptions';

    /**
     * Retrieve Special Service Options endpoint (POST)
     * @var string
     */
    const ENDPOINT_SPECIAL_OPTIONS = 'availability/v1/specialserviceoptions';

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
     * Create an empty Retrieve Services and Transit Times request: the fields that the schema requires of the shipment, named as
     * in schemas/service-availability.json. The schema does not require requestedShipment at the top, but every sample payload
     * has it, so it is in place. The lists (recipients, requestedPackageLineItems) are empty: a recipient is an array with an
     * address, a package an array with its weight. Any other field of the schema can be added. Fields set to null and empty
     * lists are left out when the request is sent
     * @return array
     */
    public static function newTransitTimesRequest() : array
    {
        return [
            'requestedShipment' => [
                'shipper' => [
                    'address' => [
                        'postalCode' => null,
                        'countryCode' => null
                    ]
                ],
                'recipients' => [],
                'packagingType' => null,
                'requestedPackageLineItems' => []
            ]
        ];
    }

    /**
     * Create an empty Retrieve Services and Packaging Options request: the fields that the schema requires, named as in
     * schemas/service-availability.json. The list recipients is empty: a recipient is an array with an address. Any other field of
     * the schema can be added. Fields set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newPackageAndServiceOptionsRequest() : array
    {
        return [
            'requestedShipment' => [
                'shipper' => [
                    'address' => [
                        'countryCode' => null
                    ]
                ],
                'recipients' => []
            ]
        ];
    }

    /**
     * Create an empty Retrieve Special Service Options request: the fields that the schema requires, named as in
     * schemas/service-availability.json (note that here the recipient is one object, and in the other two requests it is a list).
     * The list requestedPackageLineItems is empty: a package is an array with its weight. Any other field of the schema can be
     * added. Fields set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newSpecialServiceOptionsRequest() : array
    {
        return [
            'requestedShipment' => [
                'shipper' => [
                    'address' => [
                        'countryCode' => null
                    ]
                ],
                'recipient' => [
                    'address' => [
                        'countryCode' => null
                    ]
                ],
                'packagingType' => null,
                'requestedPackageLineItems' => []
            ]
        ];
    }

    /**
     * Retrieve the services and the transit times that are available for a shipment.
     * The request is checked before it is sent: the fields, their types and their allowed values are the ones of
     * schemas/service-availability.json (a field that is not in the schema is an error) and the required fields must be present.
     * A number can be given as a number or as a text that is a number, as in the samples of the schema. What the schema cannot
     * express is checked by FedEx. Fields set to null and empty lists are left out. Nothing is changed in FedEx, so the request
     * can be repeated
     * @param array|\stdClass $request The request, as returned by newTransitTimesRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexServiceAvailabilityException
     */
    public function getTransitTimes($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_TRANSIT_TIMES,
            FedexServiceAvailabilitySchemas::TRANSIT_TIMES_REQUEST,
            FedexServiceAvailabilitySchemas::TRANSIT_TIMES_RESPONSE,
            $request
        );
    }

    /**
     * Retrieve the services and the transit times for a shipment between two addresses, with one package
     * @param array $shipperAddress The address of the shipper: postalCode and countryCode, and any other field of the address
     * @param array $recipientAddress The address of the recipient, as the one of the shipper
     * @param float|int|string $weight The weight of the package, a number or a text that is a number
     * @param string $weightUnits The unit of the weight (KG or LB)
     * @param string $packagingType The packaging type
     * @param string[] $carrierCodes The carriers to ask about (FDXE, FDXG, ...), none to let FedEx decide
     * @param array $options Any other field of the request (version), named as in the schema. requestedShipment and carrierCodes
     * cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexServiceAvailabilityException
     */
    public function getTransitTimesBetween(array $shipperAddress, array $recipientAddress, $weight, string $weightUnits = 'LB', string $packagingType = 'YOUR_PACKAGING', array $carrierCodes = [], array $options = []) : \stdClass
    {
        return $this->getTransitTimes($this->between(__FUNCTION__, $options, $carrierCodes, [
            'shipper' => ['address' => $shipperAddress],
            'recipients' => [['address' => $recipientAddress]],
            'packagingType' => $packagingType,
            'requestedPackageLineItems' => [['weight' => ['units' => $weightUnits, 'value' => $weight]]]
        ]));
    }

    /**
     * Retrieve the services and the packaging options that are available for a shipment.
     * The request is checked before it is sent, as in getTransitTimes()
     * @param array|\stdClass $request The request, as returned by newPackageAndServiceOptionsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexServiceAvailabilityException
     */
    public function getPackageAndServiceOptions($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_PACKAGE_OPTIONS,
            FedexServiceAvailabilitySchemas::PACKAGE_OPTIONS_REQUEST,
            FedexServiceAvailabilitySchemas::PACKAGE_OPTIONS_RESPONSE,
            $request
        );
    }

    /**
     * Retrieve the services and the packaging options for a shipment between two addresses
     * @param array $shipperAddress The address of the shipper: countryCode, and any other field of the address
     * @param array $recipientAddress The address of the recipient, as the one of the shipper
     * @param string[] $carrierCodes The carriers to ask about (FDXE, FDXG, ...), none to let FedEx decide
     * @param array $options Any other field of the request (accountNumber, systemOfMeasureType, version), named as in the schema.
     * requestedShipment and carrierCodes cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexServiceAvailabilityException
     */
    public function getPackageAndServiceOptionsBetween(array $shipperAddress, array $recipientAddress, array $carrierCodes = [], array $options = []) : \stdClass
    {
        return $this->getPackageAndServiceOptions($this->between(__FUNCTION__, $options, $carrierCodes, [
            'shipper' => ['address' => $shipperAddress],
            'recipients' => [['address' => $recipientAddress]]
        ]));
    }

    /**
     * Retrieve the special service options that are available for a shipment.
     * The request is checked before it is sent, as in getTransitTimes()
     * @param array|\stdClass $request The request, as returned by newSpecialServiceOptionsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexServiceAvailabilityException
     */
    public function getSpecialServiceOptions($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_SPECIAL_OPTIONS,
            FedexServiceAvailabilitySchemas::SPECIAL_OPTIONS_REQUEST,
            FedexServiceAvailabilitySchemas::SPECIAL_OPTIONS_RESPONSE,
            $request
        );
    }

    /**
     * Retrieve the special service options for a shipment between two addresses, with one package
     * @param array $shipperAddress The address of the shipper: countryCode, and any other field of the address
     * @param array $recipientAddress The address of the recipient, as the one of the shipper
     * @param float|int|string $weight The weight of the package, a number or a text that is a number
     * @param string $weightUnits The unit of the weight (KG or LB)
     * @param string $packagingType The packaging type
     * @param string[] $carrierCodes The carriers to ask about (FDXE, FDXG, ...), none to let FedEx decide
     * @param array $options Any other field of the request (accountNumber, version), named as in the schema. requestedShipment and
     * carrierCodes cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexServiceAvailabilityException
     */
    public function getSpecialServiceOptionsBetween(array $shipperAddress, array $recipientAddress, $weight, string $weightUnits = 'LB', string $packagingType = 'YOUR_PACKAGING', array $carrierCodes = [], array $options = []) : \stdClass
    {
        return $this->getSpecialServiceOptions($this->between(__FUNCTION__, $options, $carrierCodes, [
            'shipper' => ['address' => $shipperAddress],
            'recipient' => ['address' => $recipientAddress],
            'packagingType' => $packagingType,
            'requestedPackageLineItems' => [['weight' => ['units' => $weightUnits, 'value' => $weight]]]
        ]));
    }

    /**
     * Make the request of one of the methods that take two addresses
     * @param string $method The name of the method, for the message
     * @param array $options The options of the caller
     * @param string[] $carrierCodes The carriers to ask about, none to leave them out
     * @param array $requestedShipment The shipment that the method makes
     * @return array
     * @throws FedexServiceAvailabilityException When an option is one that the method sets itself
     */
    protected function between(string $method, array $options, array $carrierCodes, array $requestedShipment) : array
    {
        foreach (['requestedShipment', 'carrierCodes'] as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexServiceAvailabilityException(
                    "The option '{$field}' cannot be used with " . $method . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        $options['requestedShipment'] = $requestedShipment;

        if (count($carrierCodes)) {
            $options['carrierCodes'] = array_values($carrierCodes);
        }

        return $options;
    }

    /**
     * Check a request, send it and check the response
     * @param string $endpoint Endpoint to call
     * @param string $requestSchema Name of the definition of the request
     * @param string $responseSchema Name of the definition of the response
     * @param array|\stdClass $request The request
     * @return \stdClass The response
     * @throws FedexServiceAvailabilityException
     */
    protected function send(string $endpoint, string $requestSchema, string $responseSchema, $request) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexServiceAvailabilityException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = new SchemaValidator(false, FedexServiceAvailabilitySchemas::definitions());

        if (!$validator->validate($request, '@' . $requestSchema)) {
            throw new FedexServiceAvailabilityException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $response = $this->client->request('POST', $endpoint, $request, [], [], FedexUtils::uuidV4(), true);

        return $this->validateAndParse($response, $responseSchema);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx answered is
     * lost
     * @param mixed $response The response
     * @param string $responseSchema Name of the definition of the response
     * @return \stdClass
     * @throws FedexServiceAvailabilityException
     */
    protected function validateAndParse($response, string $responseSchema) : \stdClass
    {
        if (!($response instanceof \stdClass)) {
            throw new FedexServiceAvailabilityException(
                'Invalid response',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexServiceAvailabilitySchemas::definitions());

        if (!$validator->validate($response, '@' . $responseSchema)) {
            throw new FedexServiceAvailabilityException(
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
