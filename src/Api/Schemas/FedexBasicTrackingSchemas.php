<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the requests and responses of the Basic Integrated Visibility (tracking) API operations that
 * FedexBasicTracking implements, generated from schemas/track.json and written for SchemaValidator
 */
final class FedexBasicTrackingSchemas
{
    /**
     * Name of the definition of the Track by Tracking Number request
     * @var string
     */
    const TRACKING_NUMBERS_REQUEST = 'Full_Schema_Tracking_Numbers';

    /**
     * Name of the definition of the Track by Tracking Number response
     * @var string
     */
    const TRACKING_NUMBERS_RESPONSE = 'TrkcResponseVO_TrackingNumber';

    /**
     * Name of the definition of the Track Multiple Piece Shipment request
     * @var string
     */
    const ASSOCIATED_REQUEST = 'Full_Schema_Multiple_Piece_Shipment';

    /**
     * Name of the definition of the Track Multiple Piece Shipment response
     * @var string
     */
    const ASSOCIATED_RESPONSE = 'TrkcResponseVO_Associated';

    /**
     * Name of the definition of the Track by References request
     * @var string
     */
    const REFERENCES_REQUEST = 'Full_Schema_Tracking_References';

    /**
     * Name of the definition of the Track by References response
     * @var string
     */
    const REFERENCES_RESPONSE = 'TrkcResponseVO_ReferenceNumber';

    /**
     * Name of the definition of the Track by Transportation Control Number request
     * @var string
     */
    const TCN_REQUEST = 'Full_Schema_TCN';

    /**
     * Name of the definition of the Track by Transportation Control Number response
     * @var string
     */
    const TCN_RESPONSE = 'TrkcResponseVO_TCN';

    /**
     * Name of the definition of the Track Document request
     * @var string
     */
    const DOCUMENTS_REQUEST = 'Full_Schema_SPOD';

    /**
     * Name of the definition of the Track Document response
     * @var string
     */
    const DOCUMENTS_RESPONSE = 'TrkcResponseVO_SPOD';

    /**
     * Name of the definition of the Send Notification request
     * @var string
     */
    const NOTIFICATION_REQUEST = 'Full_Schema_Notification';

    /**
     * Name of the definition of the Send Notification response
     * @var string
     */
    const NOTIFICATION_RESPONSE = 'TrkcResponseVO_Notifications';

    /**
     * Get the definitions of all the requests and responses. The names of the definitions, and of their fields, are the
     * ones of schemas/track.json, with these differences, all of them decided with the owner:
     *  - ReferenceInformation requires shipDateEndDate, and its example writes shipDateEnd, as every other schema does:
     *    shipDateEnd is accepted too, as an optional field
     *  - AddressVO requires the field addressVerificationId, which the file never defines, so FedEx can never send it:
     *    it is accepted as an optional field of any type
     *  - DelayDetail.subType (an enum with the values SNOW, TORNADO and "EARTHQUAKE etc") and ServiceCommitMessage.type
     *    (an enum with a stray quote in USPS_DELIVERING") are defective in the file: they are checked as text
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['AdditionalTrackingInfo'] = [
            'hasAssociatedShipments?' => 'bool',
            'nickname?' => 'string',
            'packageIdentifiers?' => ['@PackageIdentifier'],
            'shipmentNotes?' => 'string'
        ];

        $schemas['AddressVO'] = [
            'classification?' => 'string',
            'residential?' => ['@type' => 'bool', '@enum' => [true, false]],
            'streetLines' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'countryName?' => 'string',
            'addressVerificationId?' => 'mixed'
        ];

        $schemas['AddressVO1'] = [
            'addressClassification?' => 'string',
            'residential?' => ['@type' => 'bool', '@enum' => [true, false]],
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'countryName?' => 'string'
        ];

        $schemas['Alert'] = [
            'code?' => 'string',
            'alertType?' => ['@type' => 'string', '@enum' => ['NOTE', 'WARNING']],
            'message?' => 'string'
        ];

        $schemas['Amount'] = [
            'currency?' => 'string',
            'value?' => 'number'
        ];

        $schemas['AvailableImagesDetail'] = [
            'size?' => ['@type' => 'string', '@enum' => ['SMALL', 'LARGE']],
            'type?' => ['@type' => 'string', '@enum' => ['SIGNATURE_PROOF_OF_DELIVERY', 'BILL_OF_LADING']]
        ];

        $schemas['BaseProcessOutputVO_Associated'] = '@TrackingMPSResponse';

        $schemas['BaseProcessOutputVO_Notification'] = '@SendNotificationOutputVO';

        $schemas['BaseProcessOutputVO_ReferenceNumber'] = '@TrackingReferencesResponse';

        $schemas['BaseProcessOutputVO_SPOD'] = '@SPODResponseVO';

        $schemas['BaseProcessOutputVO_TCN'] = '@TrackingNumbersResponse_TCN';

        $schemas['BaseProcessOutputVO_TrackingNumber'] = '@TrackingNumbersResponse';

        $schemas['CXSError'] = [
            'code?' => 'string',
            'parameterList?' => ['@Parameter'],
            'message?' => 'string'
        ];

        $schemas['CompleteTrackResult'] = [
            'trackingNumber?' => 'string',
            'trackResults?' => ['@TrackResult']
        ];

        $schemas['ConsolidationDetail'] = [
            'timeStamp?' => 'string',
            'consolidationID?' => 'string',
            'reasonDetail?' => '@ReasonDetail',
            'packageCount?' => 'int',
            'eventType?' => 'string'
        ];

        $schemas['ContactAndAddress1'] = [
            'address?' => '@AddressVO1'
        ];

        $schemas['CustomDeliveryOption'] = [
            'requestedAppointmentDetail?' => '@RequestedAppointmentDetail',
            'description?' => 'string',
            'type?' => ['@type' => 'string', '@enum' => ['REROUTE', 'APPOINTMENT', 'DATE_CERTAIN', 'EVENING', 'REDIRECT_TO_HOLD_AT_LOCATION', 'ELECTRONIC_SIGNATURE_RELEASE']],
            'status?' => 'string'
        ];

        $schemas['DelayDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['WEATHER', 'OPERATIONAL', 'LOCAL', 'GENERAL', 'CLEARANCE']],
            'subType?' => 'string',
            'status?' => ['@type' => 'string', '@enum' => ['DELAYED', 'ON_TIME', 'EARLY']]
        ];

        $schemas['DeliveryDetails'] = [
            'receivedByName?' => 'string',
            'destinationServiceArea?' => 'string',
            'destinationServiceAreaDescription?' => 'string',
            'locationDescription?' => 'string',
            'actualDeliveryAddress?' => '@AddressVO1',
            'deliveryToday?' => 'bool',
            'locationType?' => ['@type' => 'string', '@enum' => ['RECEPTIONIST_OR_FRONT_DESK', 'SHIPPING_RECEIVING', 'MAILROOM', 'RESIDENCE', 'GUARD_OR_SECURITY_STATION', 'FEDEX_LOCATION', 'IN_BOND_OR_CAGE', 'PHARMACY', 'GATE_HOUSE', 'MANAGER_OFFICE', 'MAIN_OFFICE', 'LEASING_OFFICE', 'RENTAL_OFFICE', 'APARTMENT_OFFICE', 'OTHER']],
            'signedByName?' => 'string',
            'officeOrderDeliveryMethod?' => 'string',
            'deliveryAttempts?' => 'string',
            'deliveryOptionEligibilityDetails?' => ['@DeliveryOptionElgibilityDetails']
        ];

        $schemas['DeliveryOptionElgibilityDetails'] = [
            'option?' => ['@type' => 'string', '@enum' => ['DISPUTE_DELIVERY', 'INDIRECT_SIGNATURE_RELEASE', 'REDIRECT_TO_HOLD_AT_LOCATION', 'REROUTE', 'RESCHEDULE', 'RETURN_TO_SHIPPER', 'SUPPLEMENT_ADDRESS']],
            'eligibility?' => 'string'
        ];

        $schemas['Dimensions'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['Distance'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KM', 'MI']],
            'value?' => 'number'
        ];

        $schemas['EmailDetail'] = [
            'emailAddress' => 'string',
            'name?' => 'string'
        ];

        $schemas['Full_Schema_Multiple_Piece_Shipment'] = [
            'includeDetailedScans?' => 'bool',
            'masterTrackingNumberInfo' => '@MasterTrackingInfo',
            'associatedType' => ['@type' => 'string', '@enum' => ['OUTBOUND_LINK_TO_RETURN', 'STANDARD_MPS', 'GROUP_MPS']],
            'pagingDetails?' => '@PagingDetails'
        ];

        $schemas['Full_Schema_Notification'] = [
            'senderContactName' => 'string',
            'senderEMailAddress' => 'string',
            'trackingEventNotificationDetail' => '@TrackingEventNotificationDetail',
            'trackingNumberInfo' => '@TrackingNumberInfo',
            'shipDateBegin?' => 'string',
            'shipDateEnd?' => 'string'
        ];

        $schemas['Full_Schema_SPOD'] = [
            'trackDocumentDetail' => '@TrackDocumentDetail',
            'trackDocumentSpecification' => ['@TrackDocumentSpecification']
        ];

        $schemas['Full_Schema_TCN'] = [
            'tcnInfo' => '@TCNInfo',
            'includeDetailedScans?' => 'bool'
        ];

        $schemas['Full_Schema_Tracking_Numbers'] = [
            'includeDetailedScans' => 'bool',
            'trackingInfo' => ['@TrackingInfo']
        ];

        $schemas['Full_Schema_Tracking_References'] = [
            'referencesInformation' => '@ReferenceInformation',
            'includeDetailedScans?' => 'bool'
        ];

        $schemas['InformationNoteDetail'] = [
            'code?' => 'string',
            'description?' => 'string'
        ];

        $schemas['Localization'] = [
            'languageCode' => 'string',
            'localeCode?' => 'string'
        ];

        $schemas['LocationDetail1'] = [
            'locationId?' => 'string',
            'locationContactAndAddress?' => '@ContactAndAddress1',
            'locationType?' => ['@type' => 'string', '@enum' => ['FEDEX_AUTHORIZED_SHIP_CENTER', 'FEDEX_OFFICE', 'FEDEX_SELF_SERVICE_LOCATION', 'FEDEX_GROUND_TERMINAL', 'FEDEX_ONSITE', 'FEDEX_EXPRESS_STATION', 'FEDEX_FACILITY', 'FEDEX_FREIGHT_SERVICE_CENTER', 'FEDEX_HOME_DELIVERY_STATION', 'FEDEX_SHIP_AND_GET', 'FEDEX_SHIPSITE', 'FEDEX_SMART_POST_HUB']]
        ];

        $schemas['LocationDetail_origin'] = [
            'locationId?' => 'string',
            'locationContactAndAddress?' => '@ContactAndAddress1'
        ];

        $schemas['MasterTrackingInfo'] = '@TrackingInfo';

        $schemas['OriginLocation'] = '@LocationDetail_origin';

        $schemas['PackageDetail'] = [
            'physicalPackagingType?' => 'string',
            'sequenceNumber?' => 'string',
            'undeliveredCount?' => 'string',
            'packagingDescription?' => '@PackagingDescription',
            'count?' => 'string',
            'weightAndDimensions?' => '@TrackingWeightAndDimensions',
            'packageContent?' => ['string'],
            'contentPieceCount?' => 'string',
            'declaredValue?' => '@Amount'
        ];

        $schemas['PackageIdentifier'] = [
            'type?' => ['@type' => 'string', '@enum' => ['BILL_OF_LADING', 'COD_RETURN_TRACKING_NUMBER', 'CUSTOMER_AUTHORIZATION_NUMBER', 'CUSTOMER_REFERENCE', 'DEPARTMENT', 'DOCUMENT_AIRWAY_BILL', 'EXPRESS_ALTERNATE_REFERENCE', 'FEDEX_OFFICE_JOB_ORDER_NUMBER', 'FREE_FORM_REFERENCE', 'GROUND_INTERNATIONAL', 'GROUND_SHIPMENT_ID', 'GROUP_MPS', 'INTERNATIONAL_DISTRIBUTION', 'INVOICE', 'JOB_GLOBAL_TRACKING_NUMBER', 'ORDER_GLOBAL_TRACKING_NUMBER', 'ORDER_TO_PAY_NUMBER', 'OUTBOUND_LINK_TO_RETURN', 'PART_NUMBER', 'PARTNER_CARRIER_NUMBER', 'PURCHASE_ORDER', 'REROUTE_TRACKING_NUMBER', 'RETURN_MATERIALS_AUTHORIZATION', 'RETURNED_TO_SHIPPER_TRACKING_NUMBER', 'SHIPPER_REFERENCE', 'STANDARD_MPS', 'TRACKING_CONTROL_NUMBER', 'TRACKING_NUMBER_OR_DOORTAG', 'TRANSBORDER_DISTRIBUTION', 'TRANSPORTATION_CONTROL_NUMBER', 'VIRTUAL_CONSOLIDATION']],
            'value?' => ['string'],
            'trackingNumberUniqueId?' => 'string'
        ];

        $schemas['PackagingDescription'] = [
            'description?' => 'string',
            'type?' => 'string'
        ];

        $schemas['PagingDetails'] = '@PagingDetails_2';

        $schemas['PagingDetails_2'] = [
            'resultsPerPage?' => 'int',
            'pagingToken?' => 'string'
        ];

        $schemas['Parameter'] = [
            'value?' => 'string',
            'key?' => 'string'
        ];

        $schemas['PieceCountDetail'] = [
            'count?' => 'string',
            'description?' => 'string',
            'type?' => ['@type' => 'string', '@enum' => ['DESTINATION', 'ORIGIN']]
        ];

        $schemas['ReasonDetail'] = [
            'description?' => 'string',
            'type?' => 'string'
        ];

        $schemas['ReferenceInformation'] = [
            'carrierCode?' => 'string',
            'type?' => 'string',
            'value' => 'string',
            'accountNumber?' => 'string',
            'shipDateBegin' => 'string',
            'shipDateEndDate' => 'string',
            'destinationCountryCode?' => 'string',
            'destinationPostalCode?' => 'string',
            'shipDateEnd?' => 'string'
        ];

        $schemas['RequestedAppointmentDetail'] = [
            'date?' => 'string',
            'window?' => ['@TimeWindow']
        ];

        $schemas['ReturnDetail'] = [
            'authorizationName?' => 'string',
            'reasonDetail?' => ['@ReasonDetail']
        ];

        $schemas['SPODResponseVO'] = [
            'localization?' => '@Localization',
            'documentType?' => 'string',
            'documentFormat?' => 'string',
            'document?' => ['string'],
            'alerts?' => ['@Alert']
        ];

        $schemas['ScanEvent'] = [
            'date?' => 'string',
            'derivedStatus?' => 'string',
            'scanLocation?' => '@AddressVO1',
            'locationId?' => 'string',
            'locationType?' => ['@type' => 'string', '@enum' => ['AIRPORT', 'CUSTOMS_BROKER', 'CUSTOMER', 'DELIVERY_LOCATION', 'DESTINATION_AIRPORT', 'DROP_BOX', 'DESTINATION_FEDEX_FACILITY', 'ENROUTE', 'FEDEX_FACILITY', 'INTERLINE_CARRIER', 'FEDEX_OFFICE_LOCATION', 'NON_FEDEX_FACILITY', 'ORIGIN_AIRPORT', 'ORIGIN_FEDEX_FACILITY', 'PORT_OF_ENTRY', 'PICKUP_LOCATION', 'PLANE', 'SORT_FACILITY', 'SHIP_AND_GET_LOCATION', 'TURNPOINT', 'VEHICLE']],
            'exceptionDescription?' => 'string',
            'eventDescription?' => 'string',
            'eventType?' => 'string',
            'derivedStatusCode?' => 'string',
            'exceptionCode?' => 'string',
            'delayDetail?' => '@DelayDetail'
        ];

        $schemas['SendNotificationOutputVO'] = [
            'TrackingNumberInfo?' => '@TrackingNumberInfo_2',
            'destinationAddress?' => '@AddressVO',
            'recipientDetails?' => ['@notificationEventTypes'],
            'alerts?' => ['@Alert']
        ];

        $schemas['ServiceCommitMessage'] = [
            'message?' => 'string',
            'type?' => 'string'
        ];

        $schemas['ServiceDescriptionDetail'] = [
            'description?' => 'string',
            'shortDescription?' => 'string',
            'type?' => 'string'
        ];

        $schemas['ShipmentContent'] = [
            'itemNumber?' => 'string',
            'receivedQuantity?' => 'string',
            'description?' => 'string',
            'partNumber?' => 'string'
        ];

        $schemas['StatusAncillaryDetail'] = [
            'reason?' => 'string',
            'reasonDescription?' => 'string',
            'action?' => 'string',
            'actionDescription?' => 'string'
        ];

        $schemas['StatusDetail'] = [
            'scanLocation?' => '@AddressVO1',
            'code?' => 'string',
            'derivedCode?' => 'string',
            'ancillaryDetails?' => ['@StatusAncillaryDetail'],
            'statusByLocale?' => 'string',
            'description?' => 'string',
            'delayDetail?' => '@DelayDetail'
        ];

        $schemas['TCNInfo'] = [
            'value' => 'string',
            'carrierCode?' => 'string',
            'shipDateBegin?' => 'string',
            'shipDateEnd?' => 'string'
        ];

        $schemas['TimeRange'] = [
            'begins?' => 'string',
            'ends?' => 'string'
        ];

        $schemas['TimeWindow'] = [
            'description?' => 'string',
            'window?' => '@TimeRange',
            'type?' => ['@type' => 'string', '@enum' => ['ACTUAL_DELIVERY', 'ACTUAL_PICKUP', 'ACTUAL_TENDER', 'ANTICIPATED_TENDER', 'APPOINTMENT_DELIVERY', 'ATTEMPTED_DELIVERY', 'COMMITMENT', 'ESTIMATED_ARRIVAL_AT_GATEWAY', 'ESTIMATED_DELIVERY', 'ESTIMATED_PICKUP', 'ESTIMATED_RETURN_TO_STATION', 'SHIP', 'SHIPMENT_DATA_RECEIVED', 'TYPICAL', 'PROJECTED', 'PRE_DELIVERY']]
        ];

        $schemas['TrackDocumentDetail'] = [
            'documentType' => 'string',
            'documentFormat?' => 'string'
        ];

        $schemas['TrackDocumentSpecification'] = [
            'trackingNumberInfo' => '@TrackingNumberInfo',
            'shipDateBegin?' => 'string',
            'shipDateEnd?' => 'string',
            'accountNumber?' => 'string'
        ];

        $schemas['TrackResult'] = [
            'trackingNumberInfo?' => '@TrackingNumberInfo_1',
            'additionalTrackingInfo?' => '@AdditionalTrackingInfo',
            'distanceToDestination?' => '@Distance',
            'consolidationDetail?' => ['@ConsolidationDetail'],
            'meterNumber?' => 'string',
            'returnDetail?' => '@ReturnDetail',
            'serviceDetail?' => '@ServiceDescriptionDetail',
            'destinationLocation?' => '@LocationDetail1',
            'latestStatusDetail?' => '@StatusDetail',
            'serviceCommitMessage?' => '@ServiceCommitMessage',
            'informationNotes?' => ['@InformationNoteDetail'],
            'error?' => '@CXSError',
            'specialHandlings?' => ['@TrackSpecialHandling'],
            'availableImages?' => ['@AvailableImagesDetail'],
            'deliveryDetails?' => '@DeliveryDetails',
            'scanEvents?' => ['@ScanEvent'],
            'dateAndTimes?' => ['@TrackingDateAndTime'],
            'packageDetails?' => '@PackageDetail',
            'goodsClassificationCode?' => 'string',
            'holdAtLocation?' => '@LocationDetail1',
            'customDeliveryOptions?' => ['@CustomDeliveryOption'],
            'estimatedDeliveryTimeWindow?' => '@TimeWindow',
            'pieceCounts?' => ['@PieceCountDetail'],
            'originLocation?' => '@OriginLocation',
            'recipientInformation?' => '@ContactAndAddress1',
            'standardTransitTimeWindow?' => '@TimeWindow',
            'shipmentDetails?' => '@TrackShipmentDetail',
            'reasonDetail?' => '@ReasonDetail',
            'availableNotifications?' => ['string'],
            'shipperInformation?' => '@ContactAndAddress1',
            'lastUpdatedDestinationAddress?' => '@AddressVO1'
        ];

        $schemas['TrackShipmentDetail'] = [
            'contents?' => ['@ShipmentContent'],
            'beforePossessionStatus?' => 'bool',
            'weight?' => ['@Weight'],
            'contentPieceCount?' => 'string',
            'splitShipments?' => ['@TrackSplitShipment']
        ];

        $schemas['TrackSpecialHandling'] = [
            'description?' => 'string',
            'type?' => 'string',
            'paymentType?' => 'string'
        ];

        $schemas['TrackSplitShipment'] = [
            'pieceCount?' => 'string',
            'statusDescription?' => 'string',
            'timestamp?' => 'string',
            'statusCode?' => 'string'
        ];

        $schemas['TrackingDateAndTime'] = [
            'dateTime?' => 'string',
            'type?' => ['@type' => 'string', '@enum' => ['ACTUAL_DELIVERY', 'ACTUAL_PICKUP', 'ACTUAL_TENDER', 'ANTICIPATED_TENDER', 'APPOINTMENT_DELIVERY', 'ATTEMPTED_DELIVERY', 'COMMITMENT', 'ESTIMATED_ARRIVAL_AT_GATEWAY', 'ESTIMATED_DELIVERY', 'ESTIMATED_PICKUP', 'ESTIMATED_RETURN_TO_STATION', 'SHIP', 'SHIPMENT_DATA_RECEIVED']]
        ];

        $schemas['TrackingEventNotificationDetail'] = '@TrackingEventNotificationDetail_2';

        $schemas['TrackingEventNotificationDetail_2'] = [
            'trackingNotifications' => ['@TrackingNotification'],
            'personalMessage?' => 'string',
            'supportHTML?' => 'mixed'
        ];

        $schemas['TrackingInfo'] = [
            'shipDateBegin?' => 'string',
            'shipDateEnd?' => 'string',
            'trackingNumberInfo' => '@TrackingNumberInfo'
        ];

        $schemas['TrackingMPSResponse'] = [
            'completeTrackResults?' => ['@CompleteTrackResult'],
            'alerts?' => ['@Alert']
        ];

        $schemas['TrackingNotification'] = [
            'notificationDetail' => '@TrackingNotificationDetail',
            'role?' => 'string',
            'notificationEventTypes' => ['string'],
            'currentResultRequestedFlag?' => 'bool'
        ];

        $schemas['TrackingNotificationDetail'] = [
            'localization' => '@Localization',
            'emailDetail' => '@EmailDetail',
            'notificationType' => 'string'
        ];

        $schemas['TrackingNumberInfo'] = [
            'trackingNumber' => 'string',
            'carrierCode?' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG', 'FXSP', 'FXFR', 'FDXC', 'FXCC', 'FEDEX_CARGO', 'FEDEX_CUSTOM_CRITICAL', 'FEDEX_EXPRESS', 'FEDEX_FREIGHT', 'FEDEX_GROUND', 'FEDEX_OFFICE', 'FEDEX_KINKOS', 'FX', 'FDFR', 'FDEG', 'FXK', 'FDC', 'FDCC']],
            'trackingNumberUniqueId?' => 'string'
        ];

        $schemas['TrackingNumberInfo_1'] = [
            'trackingNumber?' => 'string',
            'carrierCode?' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG', 'FXSP', 'FXFR', 'FDXC', 'FXCC', 'FEDEX_CARGO', 'FEDEX_CUSTOM_CRITICAL', 'FEDEX_EXPRESS', 'FEDEX_FREIGHT', 'FEDEX_GROUND', 'FEDEX_OFFICE', 'FEDEX_KINKOS', 'FX', 'FDFR', 'FDEG', 'FXK', 'FDC', 'FDCC']],
            'trackingNumberUniqueId?' => 'string'
        ];

        $schemas['TrackingNumberInfo_2'] = [
            'trackingNumber' => 'string',
            'carrierCode?' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG', 'FXSP', 'FXFR', 'FDXC', 'FXCC', 'FEDEX_CARGO', 'FEDEX_CUSTOM_CRITICAL', 'FEDEX_EXPRESS', 'FEDEX_FREIGHT', 'FEDEX_GROUND', 'FEDEX_OFFICE', 'FEDEX_KINKOS', 'FX', 'FDFR', 'FDEG', 'FXK', 'FDC', 'FDCC']],
            'trackingNumberUniqueId?' => 'string'
        ];

        $schemas['TrackingNumbersResponse'] = [
            'completeTrackResults?' => ['@CompleteTrackResult'],
            'alerts?' => ['@Alert']
        ];

        $schemas['TrackingNumbersResponse_TCN'] = [
            'completeTrackResults?' => ['@CompleteTrackResult'],
            'alerts?' => ['@Alert']
        ];

        $schemas['TrackingReferencesResponse'] = [
            'cxsErrors?' => ['@CXSError'],
            'completeTrackResults?' => ['@CompleteTrackResult'],
            'alerts?' => ['@Alert'],
            'successful?' => 'bool'
        ];

        $schemas['TrackingWeightAndDimensions'] = [
            'weight?' => ['@Weight'],
            'dimensions?' => ['@Dimensions']
        ];

        $schemas['TrkcResponseVO_Associated'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_Associated'
        ];

        $schemas['TrkcResponseVO_Notifications'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_Notification'
        ];

        $schemas['TrkcResponseVO_ReferenceNumber'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_ReferenceNumber'
        ];

        $schemas['TrkcResponseVO_SPOD'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_SPOD'
        ];

        $schemas['TrkcResponseVO_TCN'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_TCN'
        ];

        $schemas['TrkcResponseVO_TrackingNumber'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_TrackingNumber'
        ];

        $schemas['Weight'] = [
            'unit?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'string'
        ];

        $schemas['notificationEventTypes'] = ['string'];

        return $schemas;
    }
}
