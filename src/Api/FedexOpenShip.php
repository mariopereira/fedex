<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexOpenShipSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexOpenShipException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Open Ship API: creates a shipment that stays open while its packages are added, modified or deleted, over several
 * requests, and confirms it when it is complete, which generates its labels; retrieves an open shipment or one of its
 * packages, deletes an open shipment, and gets the results of the operations that FedEx processes asynchronously
 * (ship/v1/openshipments).
 *
 * Creating, modifying, confirming and deleting, and adding, modifying and deleting packages, change real data in FedEx. A
 * request that fails because of a timeout or a network error may have been processed anyway: such a failure must be checked
 * in FedEx before it is repeated, and this library never repeats those requests on its own. Only retrieveOpenShipment(),
 * retrievePackage() and getResults() change nothing and can be repeated (see FedexClient::setRetryPolicy()).
 *
 * How a request is checked. The requests of FedEx's collection (schemas/collections/open-ship.json) contradict
 * schemas/open-ship.json in the nested objects of the shipments (most of them leave out labelSpecification or
 * commercialInvoice, which the file requires). So a request is checked at its top level only: the required fields, the
 * names of the fields and their types, with each nested object or list accepted as it comes, and FedEx checks the rest and
 * answers with its errors. checkRequest() and checkResponse() check the whole of the schema and report what does not
 * match, without stopping anything
 */
class FedexOpenShip extends AbstractFedexApi
{
    /**
     * Create Open Shipment endpoint (POST)
     * @var string
     */
    const ENDPOINT_CREATE = 'ship/v1/openshipments/create';

    /**
     * Modify Open Shipment endpoint (PUT). The trailing slash is in the path of the schema and of the documentation
     * @var string
     */
    const ENDPOINT_MODIFY = 'ship/v1/openshipments/';

    /**
     * Confirm Open Shipment endpoint (POST). The trailing slash is in the path of the schema and of the documentation
     * @var string
     */
    const ENDPOINT_CONFIRM = 'ship/v1/openshipments/';

    /**
     * Modify Open Shipment Packages (PUT) and Add Open Shipment Packages (POST) endpoint
     * @var string
     */
    const ENDPOINT_PACKAGES = 'ship/v1/openshipments/packages';

    /**
     * Delete Open Shipment Packages endpoint (PUT)
     * @var string
     */
    const ENDPOINT_DELETE_PACKAGES = 'ship/v1/openshipments/packages/delete';

    /**
     * Retrieve Open Shipment Packages endpoint (POST)
     * @var string
     */
    const ENDPOINT_RETRIEVE_PACKAGE = 'ship/v1/openshipments/packages/retrieve';

    /**
     * Delete Open Shipment endpoint (PUT)
     * @var string
     */
    const ENDPOINT_DELETE = 'ship/v1/openshipments/delete';

    /**
     * Retrieve Open Shipment endpoint (POST)
     * @var string
     */
    const ENDPOINT_RETRIEVE = 'ship/v1/openshipments/retrieve';

    /**
     * Get Open Shipment Results endpoint (POST)
     * @var string
     */
    const ENDPOINT_RESULTS = 'ship/v1/openshipments/results';

    /**
     * The operations, by the name that checkRequest() and checkResponse() take: the HTTP method, the endpoint, the
     * definition of the request, the definition of the response and whether the operation changes data in FedEx
     * (which also means that it is never repeated)
     * @var array
     */
    const OPERATIONS = [
        'create' => ['POST', self::ENDPOINT_CREATE, FedexOpenShipSchemas::CREATE_REQUEST, FedexOpenShipSchemas::CREATE_RESPONSE, true],
        'modify' => ['PUT', self::ENDPOINT_MODIFY, FedexOpenShipSchemas::MODIFY_REQUEST, FedexOpenShipSchemas::MODIFY_RESPONSE, true],
        'confirm' => ['POST', self::ENDPOINT_CONFIRM, FedexOpenShipSchemas::CONFIRM_REQUEST, FedexOpenShipSchemas::CONFIRM_RESPONSE, true],
        'modifyPackage' => ['PUT', self::ENDPOINT_PACKAGES, FedexOpenShipSchemas::MODIFY_PACKAGE_REQUEST, FedexOpenShipSchemas::MODIFY_PACKAGE_RESPONSE, true],
        'addPackages' => ['POST', self::ENDPOINT_PACKAGES, FedexOpenShipSchemas::ADD_PACKAGES_REQUEST, FedexOpenShipSchemas::ADD_PACKAGES_RESPONSE, true],
        'deletePackages' => ['PUT', self::ENDPOINT_DELETE_PACKAGES, FedexOpenShipSchemas::DELETE_PACKAGES_REQUEST, FedexOpenShipSchemas::DELETE_PACKAGES_RESPONSE, true],
        'retrievePackage' => ['POST', self::ENDPOINT_RETRIEVE_PACKAGE, FedexOpenShipSchemas::RETRIEVE_PACKAGE_REQUEST, FedexOpenShipSchemas::RETRIEVE_PACKAGE_RESPONSE, false],
        'delete' => ['PUT', self::ENDPOINT_DELETE, FedexOpenShipSchemas::DELETE_REQUEST, FedexOpenShipSchemas::DELETE_RESPONSE, true],
        'retrieve' => ['POST', self::ENDPOINT_RETRIEVE, FedexOpenShipSchemas::RETRIEVE_REQUEST, FedexOpenShipSchemas::RETRIEVE_RESPONSE, false],
        'results' => ['POST', self::ENDPOINT_RESULTS, FedexOpenShipSchemas::RESULTS_REQUEST, FedexOpenShipSchemas::RESULTS_RESPONSE, false],
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
     * Create an empty Create Open Shipment request: the fields that the schema requires at the top level, named as in
     * schemas/open-ship.json. requestedShipment is empty and has to be filled in (see the schema). index, the id of the open
     * shipment in the other requests, can be added: when it is not given, the master tracking number is the index. Any
     * other field of the schema can be added. Fields set to null and empty lists and objects are left out when the request
     * is sent
     * @return array
     */
    public static function newCreateRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'requestedShipment' => []
        ];
    }

    /**
     * Create an empty Modify Open Shipment request: the fields that the schema requires at the top level.
     * requestedShipment is empty and has to be filled in. Any other field of the schema can be added. Fields set to null
     * and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newModifyRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'index' => null,
            'requestedShipment' => []
        ];
    }

    /**
     * Create an empty Confirm Open Shipment request: the fields that the schema requires at the top level.
     * labelResponseOptions is URL_ONLY or LABEL, and labelSpecification is empty and has to be filled in. Any other field of
     * the schema can be added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newConfirmRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'index' => null,
            'labelResponseOptions' => null,
            'labelSpecification' => []
        ];
    }

    /**
     * Create an empty Modify Open Shipment Packages request: the fields that the schema requires at the top level.
     * trackingId (the package) and requestedPackageLineItem are empty and have to be filled in. Any other field of the
     * schema can be added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newModifyPackageRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'index' => null,
            'trackingId' => [],
            'requestedPackageLineItem' => []
        ];
    }

    /**
     * Create an empty Add Open Shipment Packages request: the fields that the schema requires at the top level.
     * requestedPackageLineItems is empty and has to be filled in with the packages. Any other field of the schema can be
     * added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newAddPackagesRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'index' => null,
            'requestedPackageLineItems' => []
        ];
    }

    /**
     * Create an empty Delete Open Shipment Packages request: the fields that the schema requires at the top level.
     * trackingIds is empty and has to be filled in with the packages to delete. Any other field of the schema can be added.
     * Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newDeletePackagesRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'index' => null,
            'trackingIds' => []
        ];
    }

    /**
     * Create an empty Retrieve Open Shipment Packages request: the fields that the schema requires at the top level.
     * trackingId (the package) is empty and has to be filled in. Any other field of the schema can be added. Fields set to
     * null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newRetrievePackageRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'index' => null,
            'trackingId' => []
        ];
    }

    /**
     * Create an empty Delete Open Shipment request: the fields that the schema requires at the top level. Any other field
     * of the schema can be added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newDeleteRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'index' => null
        ];
    }

    /**
     * Create an empty Retrieve Open Shipment request: the fields that the schema requires at the top level. The schema
     * also requires trackingId, which it never defines: it is optional, of any type. Any other field of the schema can be
     * added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newRetrieveRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'index' => null
        ];
    }

    /**
     * Create an empty Get Open Shipment Results request: the fields that the schema requires at the top level. jobId is
     * the one that FedEx gave, and resultMethodType is the operation that gave it: CREATE, MODIFY or CONFIRM. Any other field
     * of the schema can be added. Fields set to null and empty lists and objects are left out when the request is sent
     * @return array
     */
    public static function newResultsRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'jobId' => null,
            'resultMethodType' => null
        ];
    }

    /**
     * Create an open shipment. THIS CHANGES DATA IN FEDEX.
     * The request is checked at its top level before it is sent (see the description of the class). It is never
     * repeated by this library, except when FedEx answers that the token is not valid, which means that nothing was
     * processed. If an exception is thrown after the request was sent (a timeout, a network error, or a response that is
     * not valid), the open shipment may have been created: check it before trying again
     * @param array|\stdClass $request The request, as returned by newCreateRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function createOpenShipment($request) : \stdClass
    {
        return $this->send('create', $request);
    }

    /**
     * Modify an open shipment. THIS CHANGES DATA IN FEDEX. The request is checked at its top level before it is sent, and
     * it is never repeated, as in createOpenShipment()
     * @param array|\stdClass $request The request, as returned by newModifyRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function modifyOpenShipment($request) : \stdClass
    {
        return $this->send('modify', $request);
    }

    /**
     * Confirm an open shipment once all its packages are added: FedEx generates its labels. THIS CHANGES DATA IN FEDEX.
     * The request is checked at its top level before it is sent, and it is never repeated, as in createOpenShipment().
     * A shipment of many packages (about 40 or more) is processed asynchronously: the response gives a job id, for
     * getResults()
     * @param array|\stdClass $request The request, as returned by newConfirmRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function confirmOpenShipment($request) : \stdClass
    {
        return $this->send('confirm', $request);
    }

    /**
     * Modify a package of an open shipment. THIS CHANGES DATA IN FEDEX. The request is checked at its top level before it
     * is sent, and it is never repeated, as in createOpenShipment()
     * @param array|\stdClass $request The request, as returned by newModifyPackageRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function modifyPackage($request) : \stdClass
    {
        return $this->send('modifyPackage', $request);
    }

    /**
     * Add packages to an open shipment. THIS CHANGES DATA IN FEDEX. The request is checked at its top level before it is
     * sent, and it is never repeated, as in createOpenShipment()
     * @param array|\stdClass $request The request, as returned by newAddPackagesRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function addPackages($request) : \stdClass
    {
        return $this->send('addPackages', $request);
    }

    /**
     * Delete packages of an open shipment, before it is confirmed. THIS CHANGES DATA IN FEDEX. The request is checked at
     * its top level before it is sent, and it is never repeated, as in createOpenShipment()
     * @param array|\stdClass $request The request, as returned by newDeletePackagesRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function deletePackages($request) : \stdClass
    {
        return $this->send('deletePackages', $request);
    }

    /**
     * Retrieve a package of an open shipment. This changes nothing, so it can be repeated. The request is checked at its
     * top level before it is sent
     * @param array|\stdClass $request The request, as returned by newRetrievePackageRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function retrievePackage($request) : \stdClass
    {
        return $this->send('retrievePackage', $request);
    }

    /**
     * Delete an open shipment and all its packages, before it is confirmed. THIS CHANGES DATA IN FEDEX. The request is
     * checked at its top level before it is sent, and it is never repeated, as in createOpenShipment()
     * @param array|\stdClass $request The request, as returned by newDeleteRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function deleteOpenShipment($request) : \stdClass
    {
        return $this->send('delete', $request);
    }

    /**
     * Delete an open shipment, from its index
     * @param string $accountNumber The account number
     * @param string $index The index of the open shipment
     * @param array $options Any other field of the request (version), named as in the schema. accountNumber and index
     * cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function deleteByIndex(string $accountNumber, string $index, array $options = []) : \stdClass
    {
        return $this->deleteOpenShipment($this->withFields(__FUNCTION__, $options, ['accountNumber' => ['value' => $accountNumber], 'index' => $index]));
    }

    /**
     * Retrieve an open shipment. This changes nothing, so it can be repeated. The request is checked at its top level
     * before it is sent
     * @param array|\stdClass $request The request, as returned by newRetrieveRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function retrieveOpenShipment($request) : \stdClass
    {
        return $this->send('retrieve', $request);
    }

    /**
     * Retrieve an open shipment, from its index. This changes nothing
     * @param string $accountNumber The account number
     * @param string $index The index of the open shipment
     * @param array $options Any other field of the request (version), named as in the schema. accountNumber and index
     * cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function retrieveByIndex(string $accountNumber, string $index, array $options = []) : \stdClass
    {
        return $this->retrieveOpenShipment($this->withFields(__FUNCTION__, $options, ['accountNumber' => ['value' => $accountNumber], 'index' => $index]));
    }

    /**
     * Get the result of an operation that FedEx processed asynchronously (a create, a modification or a confirmation of a
     * shipment of many packages): its labels and documents, or its errors. This changes nothing, so it can be repeated until
     * the result is ready. The request is checked at its top level before it is sent
     * @param array|\stdClass $request The request, as returned by newResultsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function getResults($request) : \stdClass
    {
        return $this->send('results', $request);
    }

    /**
     * Get the result of an operation that FedEx processed asynchronously, from the job id that FedEx gave. This changes
     * nothing
     * @param string $accountNumber The account number
     * @param string $jobId The job id
     * @param string $resultMethodType The operation that gave the job id: CREATE, MODIFY or CONFIRM
     * @param array $options Any other field of the request (version), named as in the schema. accountNumber, jobId and
     * resultMethodType cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexOpenShipException
     */
    public function getResultsByJobId(string $accountNumber, string $jobId, string $resultMethodType, array $options = []) : \stdClass
    {
        return $this->getResults($this->withFields(__FUNCTION__, $options, [
            'accountNumber' => ['value' => $accountNumber],
            'jobId' => $jobId,
            'resultMethodType' => $resultMethodType
        ]));
    }

    /**
     * Check a request against the whole of the schema and say what does not match. Nothing is sent and nothing stops: the
     * requests of FedEx's collection contradict the schema in the nested objects, so a problem that is reported here is not
     * necessarily one that FedEx refuses
     * @param string $operation The operation: create, modify, confirm, modifyPackage, addPackages, deletePackages,
     * retrievePackage, delete, retrieve or results
     * @param array|\stdClass $request The request. Fields set to null and empty lists are left out, as when it is sent
     * @return string[] The problems, each one with the path of the field. Empty when everything matches
     * @throws FedexOpenShipException When the operation is not one of the list, or the request is neither an array nor an
     * object
     */
    public static function checkRequest(string $operation, $request) : array
    {
        $request = self::asArray($request, 'The request must be an array or an object');
        $validator = new SchemaValidator(false, FedexOpenShipSchemas::definitions());
        $validator->validate(FedexUtils::pruneEmpty($request) ?? [], '@' . self::operation($operation)[2]);

        return $validator->getErrors();
    }

    /**
     * Check a response against the whole of the schema and say what does not match. A field that FedEx adds is not a
     * problem
     * @param string $operation The operation: create, modify, confirm, modifyPackage, addPackages, deletePackages,
     * retrievePackage, delete, retrieve or results
     * @param array|\stdClass $response The response, as returned by the method of the operation
     * @return string[] The problems, each one with the path of the field. Empty when everything matches
     * @throws FedexOpenShipException When the operation is not one of the list, or the response is neither an array nor an
     * object
     */
    public static function checkResponse(string $operation, $response) : array
    {
        $response = self::asArray($response, 'The response must be an array or an object');
        $validator = new SchemaValidator(true, FedexOpenShipSchemas::definitions());
        $validator->validate($response, '@' . self::operation($operation)[3]);

        return $validator->getErrors();
    }

    /**
     * The description of an operation
     * @param string $operation The name of the operation
     * @return array The HTTP method, the endpoint, the definition of the request, the definition of the response and
     * whether the operation changes data in FedEx
     * @throws FedexOpenShipException When there is no such operation
     */
    protected static function operation(string $operation) : array
    {
        if (!isset(self::OPERATIONS[$operation])) {
            throw new FedexOpenShipException(
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
     * @throws FedexOpenShipException
     */
    protected static function asArray($value, string $message) : array
    {
        if (is_object($value)) {
            $value = FedexUtils::objectToArray($value);
        }

        if (!is_array($value)) {
            throw new FedexOpenShipException($message, FedexException::EC_BAD_PARAMETER);
        }

        return $value;
    }

    /**
     * Add the fields that a method sets to the options that the caller gave, refusing the options that would replace them
     * @param string $method The name of the method, for the message
     * @param array $options The options of the caller
     * @param array $fields The fields that the method sets
     * @return array
     * @throws FedexOpenShipException
     */
    protected function withFields(string $method, array $options, array $fields) : array
    {
        foreach (array_keys($fields) as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexOpenShipException(
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
        return self::$shallow ?? (self::$shallow = FedexOpenShipSchemas::shallowDefinitions());
    }

    /**
     * Check a request at its top level, send it and check the response at its top level
     * @param string $operation The name of the operation (a key of OPERATIONS)
     * @param array|\stdClass $request The request
     * @return \stdClass The response
     * @throws FedexOpenShipException
     */
    protected function send(string $operation, $request) : \stdClass
    {
        [$method, $endpoint, $requestSchema, $responseSchema, $changesData] = self::operation($operation);
        $request = self::asArray($request, 'The request must be an array or an object');
        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = (new SchemaValidator(true, self::shallowDefinitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . $requestSchema)) {
            throw new FedexOpenShipException(
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
     * @throws FedexOpenShipException
     */
    protected function validateAndParse($response, string $responseSchema, bool $changesData) : \stdClass
    {
        $warning = $changesData ? ' (the operation may have been done by FedEx: check it before repeating it)' : '';

        if (!($response instanceof \stdClass)) {
            throw new FedexOpenShipException(
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
            throw new FedexOpenShipException(
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
