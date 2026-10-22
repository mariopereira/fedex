<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexBasicTrackingException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Basic Integrated Visibility API: tracks shipments by tracking number, by the other packages of a multiple piece
 * shipment, by reference and by Transportation Control Number, retrieves tracking documents (such as the signature
 * proof of delivery) and sends tracking notifications (track/v1).
 *
 * Tracking and retrieving documents change nothing in FedEx, so a request that failed because of a timeout, or a
 * busy server, can be repeated (see FedexClient::setRetryPolicy()). sendNotification() sends emails to the recipients
 * it names: it is never repeated by this library, and one that failed after it was sent may have been delivered
 */
class FedexBasicTracking extends AbstractFedexApi
{
    /**
     * Track by Tracking Number endpoint (POST)
     * @var string
     */
    const ENDPOINT_TRACKING_NUMBERS = 'track/v1/trackingnumbers';

    /**
     * Track Multiple Piece Shipment endpoint (POST)
     * @var string
     */
    const ENDPOINT_ASSOCIATED = 'track/v1/associatedshipments';

    /**
     * Track by References endpoint (POST)
     * @var string
     */
    const ENDPOINT_REFERENCES = 'track/v1/referencenumbers';

    /**
     * Track by Transportation Control Number endpoint (POST)
     * @var string
     */
    const ENDPOINT_TCN = 'track/v1/tcn';

    /**
     * Track Document endpoint (POST)
     * @var string
     */
    const ENDPOINT_DOCUMENTS = 'track/v1/trackingdocuments';

    /**
     * Send Notification endpoint (POST)
     * @var string
     */
    const ENDPOINT_NOTIFICATION = 'track/v1/notifications';

    /**
     * Most tracking numbers that a request can have (the description of trackingInfo in the schema)
     * @var int
     */
    const MAX_TRACKING_NUMBERS = 30;

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
     * Create an empty Track by Tracking Number request: the fields that the API requires, named as in
     * schemas/track.json. The list trackingInfo is empty: each entry of it is an array with trackingNumberInfo
     * (trackingNumber, and optionally carrierCode and trackingNumberUniqueId) and optionally shipDateBegin and
     * shipDateEnd. Any other field of the schema can be added. Fields set to null and empty lists are left out when
     * the request is sent
     * @return array
     */
    public static function newTrackingNumbersRequest() : array
    {
        return [
            'includeDetailedScans' => null,
            'trackingInfo' => []
        ];
    }

    /**
     * Create an empty Track Multiple Piece Shipment request: the fields that the API requires, named as in
     * schemas/track.json. Any other field of the schema can be added (includeDetailedScans, pagingDetails). Fields
     * set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newAssociatedShipmentsRequest() : array
    {
        return [
            'masterTrackingNumberInfo' => [
                'trackingNumberInfo' => [
                    'trackingNumber' => null
                ]
            ],
            'associatedType' => null
        ];
    }

    /**
     * Create an empty Track by References request: the fields that the API requires, named as in schemas/track.json.
     * Any other field of the schema can be added (carrierCode, type, accountNumber, destinationCountryCode,
     * destinationPostalCode, includeDetailedScans). The schema requires shipDateEndDate, and the example of the
     * schema writes shipDateEnd: both are accepted. Fields set to null and empty lists are left out when the request
     * is sent
     * @return array
     */
    public static function newReferencesRequest() : array
    {
        return [
            'referencesInformation' => [
                'value' => null,
                'shipDateBegin' => null,
                'shipDateEndDate' => null
            ]
        ];
    }

    /**
     * Create an empty Track by Transportation Control Number request: the fields that the API requires, named as in
     * schemas/track.json. Any other field of the schema can be added (carrierCode, shipDateBegin, shipDateEnd,
     * includeDetailedScans). Fields set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newTcnRequest() : array
    {
        return [
            'tcnInfo' => [
                'value' => null
            ]
        ];
    }

    /**
     * Create an empty Track Document request: the fields that the API requires, named as in schemas/track.json. The
     * list trackDocumentSpecification is empty: each entry of it is an array with trackingNumberInfo and optionally
     * shipDateBegin, shipDateEnd and accountNumber. Fields set to null and empty lists are left out when the request
     * is sent
     * @return array
     */
    public static function newDocumentsRequest() : array
    {
        return [
            'trackDocumentDetail' => [
                'documentType' => null
            ],
            'trackDocumentSpecification' => []
        ];
    }

    /**
     * Create an empty Send Notification request: the fields that the API requires, named as in schemas/track.json.
     * The list trackingNotifications is empty: each entry of it is an array with notificationDetail (emailDetail,
     * localization and notificationType) and notificationEventTypes. Any other field of the schema can be added.
     * Fields set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newNotificationRequest() : array
    {
        return [
            'senderContactName' => null,
            'senderEMailAddress' => null,
            'trackingEventNotificationDetail' => [
                'trackingNotifications' => []
            ],
            'trackingNumberInfo' => [
                'trackingNumber' => null
            ]
        ];
    }

    /**
     * Track shipments by tracking number: up to 30 tracking numbers in one request.
     * The request is checked before it is sent: the fields, their types and their allowed values are the ones of
     * schemas/track.json (a field that is not in the schema is an error), the required fields must be present and
     * trackingInfo has from 1 to 30 entries. What the schema cannot express is checked by FedEx. Fields set to null
     * and empty lists are left out. Nothing is changed in FedEx, so the request can be repeated
     * @param array|\stdClass $request The request, as returned by newTrackingNumbersRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexBasicTrackingException
     */
    public function trackByTrackingNumbers($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_TRACKING_NUMBERS,
            FedexBasicTrackingSchemas::TRACKING_NUMBERS_REQUEST,
            FedexBasicTrackingSchemas::TRACKING_NUMBERS_RESPONSE,
            $request,
            false
        );
    }

    /**
     * Track one shipment by its tracking number
     * @param string $trackingNumber The tracking number
     * @param bool $includeDetailedScans True to include the scans of the shipment, false for the latest status only
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexBasicTrackingException
     */
    public function trackByNumber(string $trackingNumber, bool $includeDetailedScans = true) : \stdClass
    {
        return $this->trackByNumbers([$trackingNumber], $includeDetailedScans);
    }

    /**
     * Track shipments by their tracking numbers: from 1 to 30
     * @param array $trackingNumbers The tracking numbers (text that is not empty). An entry can also be an array in
     * the form of an entry of trackingInfo of the schema, to give the carrier code, the unique id or the dates
     * @param bool $includeDetailedScans True to include the scans of the shipments, false for the latest status only
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexBasicTrackingException
     */
    public function trackByNumbers(array $trackingNumbers, bool $includeDetailedScans = true) : \stdClass
    {
        $info = [];

        foreach ($trackingNumbers as $number) {
            if (is_string($number) && ('' !== trim($number))) {
                $info[] = ['trackingNumberInfo' => ['trackingNumber' => $number]];
            } elseif (is_array($number)) {
                $info[] = $number;
            } else {
                throw new FedexBasicTrackingException(
                    'Each tracking number must be text that is not empty, or an array of the schema',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        return $this->trackByTrackingNumbers(['includeDetailedScans' => $includeDetailedScans, 'trackingInfo' => $info]);
    }

    /**
     * Track the other packages of a multiple piece shipment (standard or group), or the return linked to an outbound
     * shipment, from the master tracking number. The request is checked before it is sent, as in
     * trackByTrackingNumbers()
     * @param array|\stdClass $request The request, as returned by newAssociatedShipmentsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexBasicTrackingException
     */
    public function trackAssociatedShipments($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_ASSOCIATED,
            FedexBasicTrackingSchemas::ASSOCIATED_REQUEST,
            FedexBasicTrackingSchemas::ASSOCIATED_RESPONSE,
            $request,
            false
        );
    }

    /**
     * Track shipments by a reference (a purchase order number, an invoice number, ...). FedEx requires the account
     * number, or the destination country and postal code, and checks it. The request is checked before it is sent,
     * as in trackByTrackingNumbers()
     * @param array|\stdClass $request The request, as returned by newReferencesRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexBasicTrackingException
     */
    public function trackByReferences($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_REFERENCES,
            FedexBasicTrackingSchemas::REFERENCES_REQUEST,
            FedexBasicTrackingSchemas::REFERENCES_RESPONSE,
            $request,
            false
        );
    }

    /**
     * Track shipments by Transportation Control Number. The request is checked before it is sent, as in
     * trackByTrackingNumbers()
     * @param array|\stdClass $request The request, as returned by newTcnRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexBasicTrackingException
     */
    public function trackByTcn($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_TCN,
            FedexBasicTrackingSchemas::TCN_REQUEST,
            FedexBasicTrackingSchemas::TCN_RESPONSE,
            $request,
            false
        );
    }

    /**
     * Retrieve tracking documents: the signature proof of delivery, the bill of lading or the freight billing
     * document. The document comes in the response as text that has to be decoded (see the description of the schema).
     * The request is checked before it is sent, as in trackByTrackingNumbers()
     * @param array|\stdClass $request The request, as returned by newDocumentsRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexBasicTrackingException
     */
    public function getTrackingDocuments($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_DOCUMENTS,
            FedexBasicTrackingSchemas::DOCUMENTS_REQUEST,
            FedexBasicTrackingSchemas::DOCUMENTS_RESPONSE,
            $request,
            false
        );
    }

    /**
     * Send tracking notifications: FedEx sends emails to the recipients of the request. THIS SENDS REAL EMAILS.
     * The request is checked before it is sent, as in trackByTrackingNumbers(). It is never repeated by this library,
     * except when FedEx answers that the token is not valid, which means that nothing was processed. If an exception
     * is thrown after the request was sent (a timeout, a network error, or a response that is not valid), the
     * notification may have been sent: check before trying again
     * @param array|\stdClass $request The request, as returned by newNotificationRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexBasicTrackingException
     */
    public function sendNotification($request) : \stdClass
    {
        return $this->send(
            self::ENDPOINT_NOTIFICATION,
            FedexBasicTrackingSchemas::NOTIFICATION_REQUEST,
            FedexBasicTrackingSchemas::NOTIFICATION_RESPONSE,
            $request,
            true
        );
    }

    /**
     * Check a request, send it and check the response
     * @param string $endpoint Endpoint to call
     * @param string $requestSchema Name of the definition of the request
     * @param string $responseSchema Name of the definition of the response
     * @param array|\stdClass $request The request
     * @param bool $changesData True when the operation has an effect outside FedEx's records (used in the message of an
     * invalid response, and to never repeat the request)
     * @return \stdClass The response
     * @throws FedexBasicTrackingException
     */
    protected function send(string $endpoint, string $requestSchema, string $responseSchema, $request, bool $changesData) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexBasicTrackingException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = new SchemaValidator(false, FedexBasicTrackingSchemas::definitions());

        if (!$validator->validate($request, '@' . $requestSchema)) {
            throw new FedexBasicTrackingException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $this->checkRequest($requestSchema, $request);

        $response = $this->client->request('POST', $endpoint, $request, [], [], FedexUtils::uuidV4(), !$changesData);

        return $this->validateAndParse($response, $responseSchema, $changesData);
    }

    /**
     * Check what the schema cannot express and its description says: a request to track by tracking number has at most
     * 30 entries
     * @param string $requestSchema Name of the definition of the request
     * @param array $request The request, already checked against the schema
     * @return void
     * @throws FedexBasicTrackingException
     */
    protected function checkRequest(string $requestSchema, array $request) : void
    {
        if ((FedexBasicTrackingSchemas::TRACKING_NUMBERS_REQUEST === $requestSchema)
            && (count($request['trackingInfo'] ?? []) > self::MAX_TRACKING_NUMBERS)) {
            throw new FedexBasicTrackingException(
                'A request can have at most ' . self::MAX_TRACKING_NUMBERS . ' tracking numbers',
                FedexException::EC_BAD_PARAMETER
            );
        }
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx
     * answered is lost
     * @param mixed $response The response
     * @param string $responseSchema Name of the definition of the response
     * @param bool $changesData True when the operation has an effect outside FedEx's records
     * @return \stdClass
     * @throws FedexBasicTrackingException
     */
    protected function validateAndParse($response, string $responseSchema, bool $changesData) : \stdClass
    {
        $warning = $changesData ? ' (the notification may have been sent by FedEx: check it before repeating it)' : '';

        if (!($response instanceof \stdClass)) {
            throw new FedexBasicTrackingException(
                'Invalid response' . $warning,
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexBasicTrackingSchemas::definitions());

        if (!$validator->validate($response, '@' . $responseSchema)) {
            throw new FedexBasicTrackingException(
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
