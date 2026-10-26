<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexPickupSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexPickupException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Pickup Request API: checks when a pickup is available, schedules a FedEx Express or Ground pickup, and cancels it (pickup/v1).
 *
 * Checking the availability only asks FedEx, and changes nothing, so a request that failed because of a timeout, or a busy
 * server, can be repeated (see FedexClient::setRetryPolicy()). Creating and cancelling a pickup change data in FedEx, so they are
 * never repeated by this library, except when FedEx answers that the token is not valid, which means that nothing was processed.
 * If an exception is thrown after one of them was sent (a timeout, a network failure), the pickup may or may not have been
 * scheduled or cancelled: check it before sending the request again
 */
class FedexPickup extends AbstractFedexApi
{
    /**
     * Create Pickup endpoint (POST)
     * @var string
     */
    const ENDPOINT_CREATE = 'pickup/v1/pickups';

    /**
     * Check Pickup Availability endpoint (POST)
     * @var string
     */
    const ENDPOINT_AVAILABILITY = 'pickup/v1/pickups/availabilities';

    /**
     * Cancel Pickup endpoint (PUT)
     * @var string
     */
    const ENDPOINT_CANCEL = 'pickup/v1/pickups/cancel';

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
     * Create an empty Create Pickup request: the fields that the schema requires, named as in schemas/pickup.json. The account
     * number is an array with its value, the street lines are an empty list and the contact is empty. Any other field of the
     * schema can be added. Fields set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newCreateRequest() : array
    {
        return [
            'associatedAccountNumber' => [
                'value' => null
            ],
            'originDetail' => [
                'pickupLocation' => [
                    'contact' => [],
                    'address' => [
                        'streetLines' => [],
                        'city' => null,
                        'stateOrProvinceCode' => null,
                        'postalCode' => null,
                        'countryCode' => null
                    ]
                ],
                'readyDateTimestamp' => null,
                'customerCloseTime' => null
            ],
            'carrierCode' => null
        ];
    }

    /**
     * Create an empty Check Pickup Availability request: the fields that the schema requires, named as in schemas/pickup.json.
     * The lists (pickupRequestType, carriers) are empty. Any other field of the schema can be added. Fields set to null and empty
     * lists are left out when the request is sent
     * @return array
     */
    public static function newAvailabilityRequest() : array
    {
        return [
            'pickupAddress' => [
                'postalCode' => null,
                'countryCode' => null
            ],
            'pickupRequestType' => [],
            'carriers' => [],
            'countryRelationship' => null
        ];
    }

    /**
     * Create an empty Cancel Pickup request: the fields that the schema requires, named as in schemas/pickup.json. The account
     * number is an array with its value. Any other field of the schema can be added. Fields set to null and empty lists are left
     * out when the request is sent
     * @return array
     */
    public static function newCancelRequest() : array
    {
        return [
            'associatedAccountNumber' => [
                'value' => null
            ],
            'pickupConfirmationCode' => null,
            'scheduledDate' => null
        ];
    }

    /**
     * Schedule a pickup. THIS ASKS FEDEX TO SEND A DRIVER: it is a real operation.
     * The request is checked before it is sent: the types and the allowed values of the fields are the ones of
     * schemas/pickup.json (a field that is not in the schema is sent as it is, and FedEx decides about it; the types are loose, as
     * in the examples of FedEx: a text can be a number or a boolean, a number can be a text that is a number or blank, and a
     * boolean can be the text true or false in any case, or 0 or 1) and the required fields must be present. What the schema
     * cannot express is checked by FedEx. Fields set to null and empty lists are left out.
     * The request is never repeated by this library, except when FedEx answers that the token is not valid, which means that
     * nothing was processed. If an exception is thrown after the request was sent (a timeout, a network failure), the pickup may
     * have been scheduled: check it before sending the request again
     * @param array|\stdClass $request The request, as returned by newCreateRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema (the confirmation code of the pickup is in
     * output->pickupConfirmationCode)
     * @throws FedexPickupException
     */
    public function createPickup($request) : \stdClass
    {
        return $this->send(
            'POST',
            self::ENDPOINT_CREATE,
            FedexPickupSchemas::CREATE_REQUEST,
            FedexPickupSchemas::CREATE_RESPONSE,
            $request,
            true
        );
    }

    /**
     * Check when a pickup is available. The request is checked before it is sent, as in createPickup(). Nothing is changed in
     * FedEx, so the request can be repeated
     * @param array|\stdClass $request The request, as returned by newAvailabilityRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexPickupException
     */
    public function checkPickupAvailability($request) : \stdClass
    {
        return $this->send(
            'POST',
            self::ENDPOINT_AVAILABILITY,
            FedexPickupSchemas::AVAILABILITY_REQUEST,
            FedexPickupSchemas::AVAILABILITY_RESPONSE,
            $request,
            false
        );
    }

    /**
     * Check when a pickup is available at an address
     * @param array $pickupAddress The address of the pickup: postalCode and countryCode, and any other field of the address
     * @param string[] $carriers The carriers to ask about (FDXE, FDXG)
     * @param string $countryRelationship DOMESTIC or INTERNATIONAL
     * @param string[] $pickupRequestType SAME_DAY and/or FUTURE_DAY
     * @param array $options Any other field of the request (dispatchDate, packageReadyTime, customerCloseTime, pickupType,
     * shipmentAttributes, numberOfBusinessDays, packageDetails, associatedAccountNumber, associatedAccountNumberType, version),
     * named as in the schema. pickupAddress, carriers, countryRelationship and pickupRequestType cannot be used: they are set by
     * this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexPickupException
     */
    public function checkAvailabilityAt(array $pickupAddress, array $carriers, string $countryRelationship = 'DOMESTIC', array $pickupRequestType = ['SAME_DAY'], array $options = []) : \stdClass
    {
        $this->refuseReserved(__FUNCTION__, $options, ['pickupAddress', 'carriers', 'countryRelationship', 'pickupRequestType']);

        $options['pickupAddress'] = $pickupAddress;
        $options['carriers'] = array_values($carriers);
        $options['countryRelationship'] = $countryRelationship;
        $options['pickupRequestType'] = array_values($pickupRequestType);

        return $this->checkPickupAvailability($options);
    }

    /**
     * Cancel a pickup. The request is checked before it is sent, as in createPickup(). The request is never repeated by this
     * library, except when FedEx answers that the token is not valid. If an exception is thrown after it was sent (a timeout, a
     * network failure), the pickup may have been cancelled: check it before sending the request again
     * @param array|\stdClass $request The request, as returned by newCancelRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexPickupException
     */
    public function cancelPickup($request) : \stdClass
    {
        return $this->send(
            'PUT',
            self::ENDPOINT_CANCEL,
            FedexPickupSchemas::CANCEL_REQUEST,
            FedexPickupSchemas::CANCEL_RESPONSE,
            $request,
            true
        );
    }

    /**
     * Cancel a pickup with the confirmation code that FedEx gave when it was scheduled
     * @param string $accountNumber The account number that the pickup is associated with
     * @param string $pickupConfirmationCode The confirmation code of the pickup
     * @param string $scheduledDate The date of the pickup (YYYY-MM-DD)
     * @param array $options Any other field of the request (remarks, carrierCode, location, version), named as in the schema.
     * associatedAccountNumber, pickupConfirmationCode and scheduledDate cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexPickupException
     */
    public function cancelByConfirmation(string $accountNumber, string $pickupConfirmationCode, string $scheduledDate, array $options = []) : \stdClass
    {
        $this->refuseReserved(__FUNCTION__, $options, ['associatedAccountNumber', 'pickupConfirmationCode', 'scheduledDate']);

        $options['associatedAccountNumber'] = ['value' => $accountNumber];
        $options['pickupConfirmationCode'] = $pickupConfirmationCode;
        $options['scheduledDate'] = $scheduledDate;

        return $this->cancelPickup($options);
    }

    /**
     * Refuse the options that a method sets itself
     * @param string $method The name of the method, for the message
     * @param array $options The options of the caller
     * @param string[] $reserved The fields that the method sets
     * @return void
     * @throws FedexPickupException When an option is one that the method sets itself
     */
    protected function refuseReserved(string $method, array $options, array $reserved) : void
    {
        foreach ($reserved as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexPickupException(
                    "The option '{$field}' cannot be used with " . $method . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }
    }

    /**
     * Check a request, send it and check the response
     * @param string $method The HTTP method (POST or PUT)
     * @param string $endpoint Endpoint to call
     * @param string $requestSchema Name of the definition of the request
     * @param string $responseSchema Name of the definition of the response
     * @param array|\stdClass $request The request
     * @param bool $changesData True when the operation changes data in FedEx (it is then never repeated)
     * @return \stdClass The response
     * @throws FedexPickupException
     */
    protected function send(string $method, string $endpoint, string $requestSchema, string $responseSchema, $request, bool $changesData) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexPickupException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = (new SchemaValidator(true, FedexPickupSchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . $requestSchema)) {
            throw new FedexPickupException(
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
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx answered is
     * lost
     * @param mixed $response The response
     * @param string $responseSchema Name of the definition of the response
     * @param bool $changesData True when the operation changes data in FedEx: the message then says that it may have been done
     * @return \stdClass
     * @throws FedexPickupException
     */
    protected function validateAndParse($response, string $responseSchema, bool $changesData) : \stdClass
    {
        $note = $changesData ? ' (the operation may have been done by FedEx: check it before repeating it)' : '';

        if (!($response instanceof \stdClass)) {
            throw new FedexPickupException(
                'Invalid response' . $note,
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexPickupSchemas::definitions());

        if (!$validator->validate($response, '@' . $responseSchema)) {
            throw new FedexPickupException(
                'Invalid response received' . $note,
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
