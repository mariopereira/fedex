<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexShipSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexShipException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Ship API: creates, validates and cancels shipments, creates and cancels tags, and retrieves the results of
 * asynchronous shipments (ship/v1/shipments).
 *
 * Creating and cancelling a shipment or a tag change real data in FedEx (a label is bought, a shipment or a pickup is
 * voided), and a request that fails because of a timeout or a network error may have been processed anyway: a failed
 * create(), cancel(), createTag() or cancelTag() must be checked in FedEx before it is repeated. validate() and
 * getAsyncResults() do not change anything
 */
class FedexShip extends AbstractFedexApi
{
    /**
     * Create Shipment endpoint (POST)
     * @var string
     */
    const ENDPOINT_CREATE = 'ship/v1/shipments';

    /**
     * Validate Shipment endpoint (POST)
     * @var string
     */
    const ENDPOINT_VALIDATE = 'ship/v1/shipments/packages/validate';

    /**
     * Cancel Shipment endpoint (PUT)
     * @var string
     */
    const ENDPOINT_CANCEL = 'ship/v1/shipments/cancel';

    /**
     * Create Tag endpoint (POST)
     * @var string
     */
    const ENDPOINT_CREATE_TAG = 'ship/v1/shipments/tag';

    /**
     * Cancel Tag endpoint (PUT): the id of the shipment is added at the end, URL encoded
     * @var string
     */
    const ENDPOINT_CANCEL_TAG = 'ship/v1/shipments/tag/cancel/';

    /**
     * Retrieve Async Ship endpoint (POST)
     * @var string
     */
    const ENDPOINT_RESULTS = 'ship/v1/shipments/results';

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
     * Create an empty Create Shipment request: the fields that the API requires, named as in schemas/ship.json.
     * Nested objects are already in place and the lists (recipients, requestedPackageLineItems, streetLines) are
     * empty. Any other field of the schema can be added. Fields set to null and empty lists are left out when the
     * request is sent
     * @return array
     */
    public static function newCreateRequest() : array
    {
        return [
            'requestedShipment' => [
                'shipper' => [
                    'address' => [
                        'streetLines' => [],
                        'city' => null,
                        'countryCode' => null
                    ],
                    'contact' => [
                        'phoneNumber' => null
                    ]
                ],
                'recipients' => [],
                'pickupType' => null,
                'serviceType' => null,
                'packagingType' => null,
                'totalWeight' => null,
                'shippingChargesPayment' => [
                    'paymentType' => null
                ],
                'labelSpecification' => [
                    'labelStockType' => null,
                    'imageType' => null
                ],
                'requestedPackageLineItems' => []
            ],
            'labelResponseOptions' => null,
            'accountNumber' => [
                'value' => null
            ]
        ];
    }

    /**
     * Create an empty Validate Shipment request: the fields that the API requires, named as in schemas/ship.json.
     * Nested objects are already in place and the lists (recipients, requestedPackageLineItems, streetLines) are
     * empty. Any other field of the schema can be added. Fields set to null and empty lists are left out when the
     * request is sent
     * @return array
     */
    public static function newValidateRequest() : array
    {
        return [
            'requestedShipment' => [
                'pickupType' => null,
                'serviceType' => null,
                'packagingType' => null,
                'totalWeight' => null,
                'shipper' => [
                    'address' => [
                        'streetLines' => [],
                        'city' => null,
                        'countryCode' => null
                    ],
                    'contact' => [
                        'phoneNumber' => null
                    ]
                ],
                'recipients' => [],
                'shippingChargesPayment' => [
                    'paymentType' => null
                ],
                'labelSpecification' => [
                    'labelStockType' => null,
                    'imageType' => null
                ],
                'requestedPackageLineItems' => []
            ]
        ];
    }

    /**
     * Create an empty Cancel Shipment request: the fields that the API requires, named as in schemas/ship.json.
     * Any other field of the schema can be added. Fields set to null are left out when the request is sent
     * @return array
     */
    public static function newCancelRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'trackingNumber' => null
        ];
    }

    /**
     * Create an empty Create Tag request: the fields that the API requires, named as in schemas/ship.json.
     * Nested objects are already in place and the lists (recipients, requestedPackageLineItems, streetLines) are
     * empty. Any other field of the schema can be added. Fields set to null and empty lists are left out when the
     * request is sent
     * @return array
     */
    public static function newCreateTagRequest() : array
    {
        return [
            'requestedShipment' => [
                'shipper' => [
                    'address' => [
                        'streetLines' => [],
                        'city' => null,
                        'countryCode' => null
                    ],
                    'contact' => [
                        'phoneNumber' => null
                    ]
                ],
                'recipients' => [],
                'pickupType' => null,
                'serviceType' => null,
                'packagingType' => null,
                'totalWeight' => null,
                'shippingChargesPayment' => [
                    'paymentType' => null
                ],
                'labelSpecification' => [
                    'labelStockType' => null,
                    'imageType' => null
                ],
                'requestedPackageLineItems' => [],
                'pickupDetail' => [
                    'readyPickupDateTime' => null,
                    'latestPickupDateTime' => null
                ]
            ],
            'accountNumber' => [
                'value' => null
            ]
        ];
    }

    /**
     * Create an empty Cancel Tag request: the fields that the API requires, named as in schemas/ship.json.
     * Any other field of the schema can be added. Fields set to null are left out when the request is sent
     * @return array
     */
    public static function newCancelTagRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'serviceType' => null,
            'completedTagDetail' => [
                'confirmationNumber' => null,
                'location' => null,
                'dispatchDate' => null
            ]
        ];
    }

    /**
     * Create an empty Retrieve Async Ship request: the fields that the API requires, named as in schemas/ship.json.
     * Any other field of the schema can be added. Fields set to null are left out when the request is sent
     * @return array
     */
    public static function newResultsRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'jobId' => null
        ];
    }

    /**
     * Create a shipment (and its labels). THIS BUYS A LABEL: it is a real, billable operation.
     * The request is checked before it is sent: the fields, their types and their allowed values are the ones of
     * schemas/ship.json (a field that is not in the schema is an error) and the required fields must be present.
     * What the schema cannot express (for example the fields that are required for some services, or for customs)
     * is checked by FedEx. Fields set to null and empty lists are left out.
     * The request is never repeated by this library, except when FedEx answers that the token is not valid, which
     * means that nothing was processed. If an exception is thrown after the request was sent (a timeout, a network
     * error, or a response that is not valid), the shipment may have been created: check it before trying again
     * @param array|\stdClass $request The request, as returned by newCreateRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexShipException
     */
    public function create($request) : \stdClass
    {
        return $this->send('POST', self::ENDPOINT_CREATE, FedexShipSchemas::CREATE_REQUEST, FedexShipSchemas::CREATE_RESPONSE, $request, true);
    }

    /**
     * Validate a shipment: FedEx checks the request as if it were a shipment, without creating anything.
     * The request is checked before it is sent, as in create()
     * @param array|\stdClass $request The request, as returned by newValidateRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexShipException
     */
    public function validate($request) : \stdClass
    {
        return $this->send('POST', self::ENDPOINT_VALIDATE, FedexShipSchemas::VALIDATE_REQUEST, FedexShipSchemas::VALIDATE_RESPONSE, $request, false);
    }

    /**
     * Cancel a shipment. THIS VOIDS A REAL SHIPMENT.
     * The request is checked before it is sent, as in create(). If an exception is thrown after the request was sent,
     * the shipment may have been cancelled
     * @param array|\stdClass $request The request, as returned by newCancelRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexShipException
     */
    public function cancel($request) : \stdClass
    {
        return $this->send('PUT', self::ENDPOINT_CANCEL, FedexShipSchemas::CANCEL_REQUEST, FedexShipSchemas::CANCEL_RESPONSE, $request, true);
    }

    /**
     * Cancel all the packages of a shipment, from its tracking number. THIS VOIDS A REAL SHIPMENT
     * @param string $accountNumber The account number of the shipment
     * @param string $trackingNumber The tracking number of the shipment (of its master package)
     * @param array $options Any other field of the request (emailShipment, senderCountryCode, deletionControl,
     * version), named as in the schema. accountNumber and trackingNumber cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexShipException
     */
    public function cancelByTrackingNumber(string $accountNumber, string $trackingNumber, array $options = []) : \stdClass
    {
        foreach (['accountNumber', 'trackingNumber'] as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexShipException(
                    "The option '{$field}' cannot be used with " . __FUNCTION__ . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        $options['accountNumber'] = ['value' => $accountNumber];
        $options['trackingNumber'] = $trackingNumber;

        return $this->cancel($options);
    }

    /**
     * Create a tag: FedEx creates a return label for the customer and collects the package (FedEx Express and FedEx
     * Ground). THIS ORDERS A PICKUP: it is a real, billable operation.
     * The request is checked before it is sent, as in create(), and the same warning applies: if an exception is thrown
     * after the request was sent, the tag may have been created: check it before trying again.
     * The schema lists three fields of requestedShipment as required without defining them (shipTimestamp,
     * shippingLabelType and specialServicesRequested): they are accepted, with any value, and are not checked here
     * @param array|\stdClass $request The request, as returned by newCreateTagRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexShipException
     */
    public function createTag($request) : \stdClass
    {
        return $this->send('POST', self::ENDPOINT_CREATE_TAG, FedexShipSchemas::CREATE_TAG_REQUEST, FedexShipSchemas::CREATE_TAG_RESPONSE, $request, true);
    }

    /**
     * Cancel a tag and its pickup, if the courier has not collected the package yet. THIS CANCELS A REAL PICKUP.
     * The request is checked before it is sent, as in create(). If an exception is thrown after the request was sent,
     * the tag may have been cancelled
     * @param string $shipmentId The id of the shipment, which goes in the path of the call. The schema does not say
     * what it is: it is sent as given, URL encoded, and it cannot be empty, "." or ".." (cURL would remove that
     * part of the path, and the call would go to another endpoint)
     * @param array|\stdClass $request The request, as returned by newCancelTagRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexShipException
     */
    public function cancelTag(string $shipmentId, $request) : \stdClass
    {
        if ('' === trim($shipmentId)) {
            throw new FedexShipException(
                'The shipment id cannot be empty',
                FedexException::EC_BAD_PARAMETER
            );
        }

        if (('.' === $shipmentId) || ('..' === $shipmentId)) {
            throw new FedexShipException(
                "The shipment id cannot be '.' or '..'",
                FedexException::EC_BAD_PARAMETER
            );
        }

        return $this->send('PUT', self::ENDPOINT_CANCEL_TAG . rawurlencode($shipmentId), FedexShipSchemas::CANCEL_TAG_REQUEST, FedexShipSchemas::CANCEL_TAG_RESPONSE, $request, true);
    }

    /**
     * Retrieve the results of a shipment that was confirmed asynchronously (the jobId is the one that FedEx returned
     * for it). It does not create anything: it can be repeated until the results are ready.
     * The request is checked before it is sent, as in create()
     * @param array|\stdClass $request The request, as returned by newResultsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexShipException
     */
    public function getAsyncResults($request) : \stdClass
    {
        return $this->send('POST', self::ENDPOINT_RESULTS, FedexShipSchemas::RESULTS_REQUEST, FedexShipSchemas::RESULTS_RESPONSE, $request, false);
    }

    /**
     * Retrieve the results of an asynchronous shipment, from its job id
     * @param string $accountNumber The account number of the shipment
     * @param string $jobId The job id that FedEx returned for the shipment
     * @param array $options Any other field of the request (version), named as in the schema. accountNumber and jobId
     * cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexShipException
     */
    public function getAsyncResultsByJobId(string $accountNumber, string $jobId, array $options = []) : \stdClass
    {
        foreach (['accountNumber', 'jobId'] as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexShipException(
                    "The option '{$field}' cannot be used with " . __FUNCTION__ . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        $options['accountNumber'] = ['value' => $accountNumber];
        $options['jobId'] = $jobId;

        return $this->getAsyncResults($options);
    }

    /**
     * Check a request, send it and check the response
     * @param string $method HTTP method
     * @param string $endpoint Endpoint to call
     * @param string $requestSchema Name of the definition of the request
     * @param string $responseSchema Name of the definition of the response
     * @param array|\stdClass $request The request
     * @param bool $changesData True when the operation changes data in FedEx (used in the message of an invalid response)
     * @return \stdClass The response
     * @throws FedexShipException
     */
    protected function send(string $method, string $endpoint, string $requestSchema, string $responseSchema, $request, bool $changesData) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexShipException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = new SchemaValidator(false, FedexShipSchemas::definitions());

        if (!$validator->validate($request, '@' . $requestSchema)) {
            throw new FedexShipException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $response = $this->client->request($method, $endpoint, $request, [], [], FedexUtils::uuidV4(), !$changesData);

        return $this->validateAndParse($response, $responseSchema, $changesData);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx
     * answered is lost
     * @param mixed $response The response
     * @param string $responseSchema Name of the definition of the response
     * @param bool $changesData True when the operation changes data in FedEx
     * @return \stdClass
     * @throws FedexShipException
     */
    protected function validateAndParse($response, string $responseSchema, bool $changesData) : \stdClass
    {
        $warning = $changesData ? ' (the operation may have been done by FedEx: check it before repeating it)' : '';

        if (!($response instanceof \stdClass)) {
            throw new FedexShipException(
                'Invalid response' . $warning,
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexShipSchemas::definitions());

        if (!$validator->validate($response, '@' . $responseSchema)) {
            throw new FedexShipException(
                'Invalid response received' . $warning,
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
