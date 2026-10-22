<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexConsolidationSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexConsolidationException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Consolidation API: creates, modifies, confirms, retrieves and deletes consolidations, and creates and deletes the
 * shipments of a consolidation (ship/v1/consolidations).
 *
 * Creating, modifying, confirming and deleting change real data in FedEx, and creating a shipment of a consolidation
 * buys its labels. A request that fails because of a timeout or a network error may have been processed anyway: such a
 * failure must be checked in FedEx before it is repeated, and this library never repeats those requests on its own.
 * Only getShipmentResults(), getConfirmationResults() and retrieveConsolidation() change nothing and can be repeated
 * (see FedexClient::setRetryPolicy()).
 *
 * How a request is checked. schemas/consolidation.json contradicts its own sample payloads in the nested objects: only
 * 3 of its 19 samples pass the whole of its schema (for example the schema requires consolidationKey.date and type,
 * and the samples send consolidationType and shipDatestamp instead). So a request is checked at its top level only: the
 * required fields, the names of the fields and their types, with each nested object or list accepted as it comes, and
 * FedEx checks the rest and answers with its errors. checkRequest() and checkResponse() check the whole of the schema
 * and report what does not match, without stopping anything
 */
class FedexConsolidation extends AbstractFedexApi
{
    /**
     * Create Consolidation endpoint (POST)
     * @var string
     */
    const ENDPOINT_CREATE = 'ship/v1/consolidations';

    /**
     * Modify Consolidation endpoint (PUT)
     * @var string
     */
    const ENDPOINT_MODIFY = 'ship/v1/consolidations';

    /**
     * Create Consolidation Shipment endpoint (POST)
     * @var string
     */
    const ENDPOINT_CREATE_SHIPMENT = 'ship/v1/consolidations/shipments';

    /**
     * Consolidation Shipment Results endpoint (POST)
     * @var string
     */
    const ENDPOINT_SHIPMENT_RESULTS = 'ship/v1/consolidations/results';

    /**
     * Confirm Consolidations endpoint (POST)
     * @var string
     */
    const ENDPOINT_CONFIRM = 'ship/v1/consolidations/confirmations';

    /**
     * Confirm Consolidation Results endpoint (POST)
     * @var string
     */
    const ENDPOINT_CONFIRM_RESULTS = 'ship/v1/consolidations/confirmationresults';

    /**
     * Retrieve Consolidation endpoint (POST)
     * @var string
     */
    const ENDPOINT_RETRIEVE = 'ship/v1/consolidations/retrieve';

    /**
     * Delete Consolidation Shipments endpoint (PUT)
     * @var string
     */
    const ENDPOINT_DELETE_SHIPMENTS = 'ship/v1/consolidations/shipments/delete';

    /**
     * Delete Consolidation endpoint (PUT)
     * @var string
     */
    const ENDPOINT_DELETE = 'ship/v1/consolidations/delete';

    /**
     * The operations, by the name that checkRequest() and checkResponse() take: the HTTP method, the endpoint, the
     * definition of the request, the definition of the response and whether the operation changes data in FedEx
     * (which also means that it is never repeated)
     * @var array
     */
    const OPERATIONS = [
        'create' => ['POST', self::ENDPOINT_CREATE, FedexConsolidationSchemas::CREATE_REQUEST, FedexConsolidationSchemas::CREATE_RESPONSE, true],
        'modify' => ['PUT', self::ENDPOINT_MODIFY, FedexConsolidationSchemas::MODIFY_REQUEST, FedexConsolidationSchemas::MODIFY_RESPONSE, true],
        'createShipment' => ['POST', self::ENDPOINT_CREATE_SHIPMENT, FedexConsolidationSchemas::CREATE_SHIPMENT_REQUEST, FedexConsolidationSchemas::CREATE_SHIPMENT_RESPONSE, true],
        'shipmentResults' => ['POST', self::ENDPOINT_SHIPMENT_RESULTS, FedexConsolidationSchemas::SHIPMENT_RESULTS_REQUEST, FedexConsolidationSchemas::SHIPMENT_RESULTS_RESPONSE, false],
        'confirm' => ['POST', self::ENDPOINT_CONFIRM, FedexConsolidationSchemas::CONFIRM_REQUEST, FedexConsolidationSchemas::CONFIRM_RESPONSE, true],
        'confirmResults' => ['POST', self::ENDPOINT_CONFIRM_RESULTS, FedexConsolidationSchemas::CONFIRM_RESULTS_REQUEST, FedexConsolidationSchemas::CONFIRM_RESULTS_RESPONSE, false],
        'retrieve' => ['POST', self::ENDPOINT_RETRIEVE, FedexConsolidationSchemas::RETRIEVE_REQUEST, FedexConsolidationSchemas::RETRIEVE_RESPONSE, false],
        'deleteShipments' => ['PUT', self::ENDPOINT_DELETE_SHIPMENTS, FedexConsolidationSchemas::DELETE_SHIPMENTS_REQUEST, FedexConsolidationSchemas::DELETE_SHIPMENTS_RESPONSE, true],
        'delete' => ['PUT', self::ENDPOINT_DELETE, FedexConsolidationSchemas::DELETE_REQUEST, FedexConsolidationSchemas::DELETE_RESPONSE, true],
    ];

    /**
     * The definitions of the top level of the requests and responses, which are made once
     * @var array|null
     */
    private static $shallow;

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
     * Create an empty Create Consolidation request: the fields that the schema requires at the top level, named as in
     * schemas/consolidation.json. requestedConsolidation is empty and has to be filled in (see the schema and its
     * sample payloads). Any other field of the schema can be added. Fields set to null and empty lists and objects are
     * left out when the request is sent
     * @return array
     */
    public static function newCreateRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'requestedConsolidation' => []
        ];
    }

    /**
     * Create an empty Modify Consolidation request: the fields that the schema requires at the top level.
     * consolidationKey and requestedConsolidation are empty and have to be filled in. Any other field of the schema can
     * be added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newModifyRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'consolidationKey' => [],
            'requestedConsolidation' => []
        ];
    }

    /**
     * Create an empty Create Consolidation Shipment request: the fields that the schema requires at the top level.
     * consolidationKey and requestedShipment are empty and have to be filled in. Any other field of the schema can be
     * added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newCreateShipmentRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'consolidationKey' => [],
            'requestedShipment' => []
        ];
    }

    /**
     * Create an empty Consolidation Shipment Results request: the fields that the schema requires. Any other field of
     * the schema can be added. Fields set to null are left out when the request is sent
     * @return array
     */
    public static function newShipmentResultsRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'jobId' => null
        ];
    }

    /**
     * Create an empty Confirm Consolidations request: the fields that the schema requires at the top level.
     * consolidationKey and labelSpecification are empty and have to be filled in. Any other field of the schema can be
     * added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newConfirmRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'consolidationKey' => [],
            'labelSpecification' => [],
            'processingOptionType' => null
        ];
    }

    /**
     * Create an empty Confirm Consolidation Results request: the fields that the schema requires. Any other field of
     * the schema can be added. Fields set to null are left out when the request is sent
     * @return array
     */
    public static function newConfirmResultsRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'jobId' => null
        ];
    }

    /**
     * Create an empty Retrieve Consolidation request: the fields that the schema requires at the top level.
     * consolidationKey is empty and has to be filled in. Any other field of the schema can be added. Fields set to
     * null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newRetrieveRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'consolidationKey' => []
        ];
    }

    /**
     * Create an empty Delete Consolidation Shipments request: the fields that the schema requires. Any other field of
     * the schema can be added. Fields set to null are left out when the request is sent
     * @return array
     */
    public static function newDeleteShipmentsRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'trackingNumber' => null
        ];
    }

    /**
     * Create an empty Delete Consolidation request: the fields that the schema requires at the top level.
     * consolidationKey and trackingId are empty and have to be filled in. Any other field of the schema can be added.
     * Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newDeleteRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'consolidationKey' => [],
            'trackingId' => []
        ];
    }

    /**
     * Create a consolidation. THIS CHANGES DATA IN FEDEX.
     * The request is checked at its top level before it is sent (see the description of the class). It is never
     * repeated by this library, except when FedEx answers that the token is not valid, which means that nothing was
     * processed. If an exception is thrown after the request was sent (a timeout, a network error, or a response that is
     * not valid), the consolidation may have been created: check it before trying again
     * @param array|\stdClass $request The request, as returned by newCreateRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function createConsolidation($request) : \stdClass
    {
        return $this->send('create', $request);
    }

    /**
     * Modify a consolidation. THIS CHANGES DATA IN FEDEX, and it is treated like the other operations that do: never
     * repeated, even though the same modification repeated would probably do no harm.
     * The request is checked at its top level before it is sent, as in createConsolidation()
     * @param array|\stdClass $request The request, as returned by newModifyRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function modifyConsolidation($request) : \stdClass
    {
        return $this->send('modify', $request);
    }

    /**
     * Create a shipment of a consolidation. THIS BUYS LABELS: it is a real, billable operation. A request that is
     * processed asynchronously answers with a job id, and the result is retrieved with getShipmentResults().
     * The request is checked at its top level before it is sent, and it is never repeated, as in createConsolidation()
     * @param array|\stdClass $request The request, as returned by newCreateShipmentRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function createConsolidationShipment($request) : \stdClass
    {
        return $this->send('createShipment', $request);
    }

    /**
     * Get the result of a shipment of a consolidation that is processed asynchronously. This changes nothing, so it can
     * be repeated until the result is ready. The request is checked at its top level before it is sent
     * @param array|\stdClass $request The request, as returned by newShipmentResultsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function getShipmentResults($request) : \stdClass
    {
        return $this->send('shipmentResults', $request);
    }

    /**
     * Get the result of a shipment of a consolidation, from the job id that FedEx gave. This changes nothing
     * @param string $accountNumber The account number
     * @param string $jobId The job id
     * @param array $options Any other field of the request (version), named as in the schema. accountNumber and jobId
     * cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function getShipmentResultsByJobId(string $accountNumber, string $jobId, array $options = []) : \stdClass
    {
        return $this->getShipmentResults($this->withFields(__FUNCTION__, $options, ['accountNumber' => ['value' => $accountNumber], 'jobId' => $jobId]));
    }

    /**
     * Confirm a consolidation. THIS CHANGES DATA IN FEDEX. A request that is processed asynchronously answers with a job
     * id, and the result is retrieved with getConfirmationResults().
     * The request is checked at its top level before it is sent, and it is never repeated, as in createConsolidation()
     * @param array|\stdClass $request The request, as returned by newConfirmRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function confirmConsolidation($request) : \stdClass
    {
        return $this->send('confirm', $request);
    }

    /**
     * Get the result of a confirmation that is processed asynchronously. This changes nothing, so it can be repeated
     * until the result is ready. The request is checked at its top level before it is sent
     * @param array|\stdClass $request The request, as returned by newConfirmResultsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function getConfirmationResults($request) : \stdClass
    {
        return $this->send('confirmResults', $request);
    }

    /**
     * Get the result of a confirmation, from the job id that FedEx gave. This changes nothing
     * @param string $accountNumber The account number
     * @param string $jobId The job id
     * @param array $options Any other field of the request (version), named as in the schema. accountNumber and jobId
     * cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function getConfirmationResultsByJobId(string $accountNumber, string $jobId, array $options = []) : \stdClass
    {
        return $this->getConfirmationResults($this->withFields(__FUNCTION__, $options, ['accountNumber' => ['value' => $accountNumber], 'jobId' => $jobId]));
    }

    /**
     * Retrieve a consolidation. This changes nothing, so it can be repeated. The request is checked at its top level
     * before it is sent
     * @param array|\stdClass $request The request, as returned by newRetrieveRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function retrieveConsolidation($request) : \stdClass
    {
        return $this->send('retrieve', $request);
    }

    /**
     * Retrieve a consolidation, from its key. This changes nothing
     * @param string $accountNumber The account number
     * @param array $consolidationKey The key of the consolidation, as in the schema and its samples
     * @param array $options Any other field of the request (version), named as in the schema. accountNumber and
     * consolidationKey cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function retrieveByKey(string $accountNumber, array $consolidationKey, array $options = []) : \stdClass
    {
        return $this->retrieveConsolidation($this->withFields(__FUNCTION__, $options, ['accountNumber' => ['value' => $accountNumber], 'consolidationKey' => $consolidationKey]));
    }

    /**
     * Delete shipments of a consolidation. THIS CHANGES DATA IN FEDEX.
     * The request is checked at its top level before it is sent, and it is never repeated, as in createConsolidation()
     * @param array|\stdClass $request The request, as returned by newDeleteShipmentsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function deleteConsolidationShipments($request) : \stdClass
    {
        return $this->send('deleteShipments', $request);
    }

    /**
     * Delete a consolidation. THIS CHANGES DATA IN FEDEX.
     * The request is checked at its top level before it is sent, and it is never repeated, as in createConsolidation()
     * @param array|\stdClass $request The request, as returned by newDeleteRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function deleteConsolidation($request) : \stdClass
    {
        return $this->send('delete', $request);
    }

    /**
     * Delete a consolidation, from its key and its tracking id. THIS CHANGES DATA IN FEDEX
     * @param string $accountNumber The account number
     * @param array $consolidationKey The key of the consolidation, as in the schema and its samples
     * @param array $trackingId The tracking id (trackingNumber, formId and trackingIdType in the samples)
     * @param array $options Any other field of the request (version), named as in the schema. accountNumber,
     * consolidationKey and trackingId cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexConsolidationException
     */
    public function deleteByKey(string $accountNumber, array $consolidationKey, array $trackingId, array $options = []) : \stdClass
    {
        return $this->deleteConsolidation($this->withFields(__FUNCTION__, $options, [
            'accountNumber' => ['value' => $accountNumber],
            'consolidationKey' => $consolidationKey,
            'trackingId' => $trackingId
        ]));
    }

    /**
     * Check a request against the whole of the schema and say what does not match. Nothing is sent and nothing stops: the
     * schema contradicts its own sample payloads in the nested objects (only 3 of its 19 samples pass), so a problem
     * that is reported here is not necessarily one that FedEx refuses
     * @param string $operation The operation: create, modify, createShipment, shipmentResults, confirm, confirmResults,
     * retrieve, deleteShipments or delete
     * @param array|\stdClass $request The request. Fields set to null and empty lists are left out, as when it is sent
     * @return string[] The problems, each one with the path of the field. Empty when everything matches
     * @throws FedexConsolidationException When the operation is not one of the list, or the request is neither an array
     * nor an object
     */
    public static function checkRequest(string $operation, $request) : array
    {
        $request = self::asArray($request, 'The request must be an array or an object');
        $validator = new SchemaValidator(false, FedexConsolidationSchemas::definitions());
        $validator->validate(FedexUtils::pruneEmpty($request) ?? [], '@' . self::operation($operation)[2]);

        return $validator->getErrors();
    }

    /**
     * Check a response against the whole of the schema and say what does not match. A field that FedEx adds is not a
     * problem
     * @param string $operation The operation: create, modify, createShipment, shipmentResults, confirm, confirmResults,
     * retrieve, deleteShipments or delete
     * @param array|\stdClass $response The response, as returned by the method of the operation
     * @return string[] The problems, each one with the path of the field. Empty when everything matches
     * @throws FedexConsolidationException When the operation is not one of the list, or the response is neither an array
     * nor an object
     */
    public static function checkResponse(string $operation, $response) : array
    {
        $response = self::asArray($response, 'The response must be an array or an object');
        $validator = new SchemaValidator(true, FedexConsolidationSchemas::definitions());
        $validator->validate($response, '@' . self::operation($operation)[3]);

        return $validator->getErrors();
    }

    /**
     * The description of an operation
     * @param string $operation The name of the operation
     * @return array The HTTP method, the endpoint, the definition of the request, the definition of the response and
     * whether the operation changes data in FedEx
     * @throws FedexConsolidationException When there is no such operation
     */
    protected static function operation(string $operation) : array
    {
        if (!isset(self::OPERATIONS[$operation])) {
            throw new FedexConsolidationException(
                "Unknown operation '" . $operation . "' (use one of: " . implode(', ', array_keys(self::OPERATIONS)) . ')',
                FedexException::EC_BAD_PARAMETER
            );
        }

        return self::OPERATIONS[$operation];
    }

    /**
     * Give an array or an object as an array
     * @param mixed $value The array or the object
     * @param string $message The message of the exception when it is neither
     * @return array
     * @throws FedexConsolidationException
     */
    protected static function asArray($value, string $message) : array
    {
        if (is_object($value)) {
            $value = FedexUtils::objectToArray($value);
        }

        if (!is_array($value)) {
            throw new FedexConsolidationException($message, FedexException::EC_BAD_PARAMETER);
        }

        return $value;
    }

    /**
     * Add the fields that a method sets to the options that the caller gave, refusing the options that would replace them
     * @param string $method The name of the method, for the message
     * @param array $options The options of the caller
     * @param array $fields The fields that the method sets
     * @return array
     * @throws FedexConsolidationException
     */
    protected function withFields(string $method, array $options, array $fields) : array
    {
        foreach (array_keys($fields) as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexConsolidationException(
                    "The option '{$field}' cannot be used with " . $method . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        return $options + $fields;
    }

    /**
     * The definitions of the top level of the requests and responses
     * @return array
     */
    protected static function shallowDefinitions() : array
    {
        return self::$shallow ?? (self::$shallow = FedexConsolidationSchemas::shallowDefinitions());
    }

    /**
     * Check a request at its top level, send it and check the response at its top level
     * @param string $operation The name of the operation (a key of OPERATIONS)
     * @param array|\stdClass $request The request
     * @return \stdClass The response
     * @throws FedexConsolidationException
     */
    protected function send(string $operation, $request) : \stdClass
    {
        [$method, $endpoint, $requestSchema, $responseSchema, $changesData] = self::operation($operation);
        $request = self::asArray($request, 'The request must be an array or an object');
        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = new SchemaValidator(false, self::shallowDefinitions());

        if (!$validator->validate($request, '@' . $requestSchema)) {
            throw new FedexConsolidationException(
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
     * Validate the top level of the response received from FedEx. The response is given to the exception, so that nothing
     * that FedEx answered is lost
     * @param mixed $response The response
     * @param string $responseSchema Name of the definition of the response
     * @param bool $changesData True when the operation changes data in FedEx
     * @return \stdClass
     * @throws FedexConsolidationException
     */
    protected function validateAndParse($response, string $responseSchema, bool $changesData) : \stdClass
    {
        $warning = $changesData ? ' (the operation may have been done by FedEx: check it before repeating it)' : '';

        if (!($response instanceof \stdClass)) {
            throw new FedexConsolidationException(
                'Invalid response' . $warning,
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, self::shallowDefinitions());

        if (!$validator->validate($response, '@' . $responseSchema)) {
            throw new FedexConsolidationException(
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
