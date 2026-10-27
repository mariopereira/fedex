<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the requests and responses of the Open Ship API operations that FedexOpenShip implements, generated from
 * schemas/open-ship.json and written for SchemaValidator.
 *
 * There are two sets. definitions() has all of the file, down to the last field. The requests of FedEx's collection
 * (schemas/collections/open-ship.json) contradict it in the nested objects of the shipments (most of them leave out
 * requestedShipment.labelSpecification or customsClearanceDetail.commercialInvoice, which the file requires), so that set
 * is only used to report problems (FedexOpenShip::checkRequest() and checkResponse()). shallowDefinitions() is the one that
 * decides whether a request is sent: the top level of each request and response, as the file says it, with each nested
 * object or list accepted as it comes
 */
final class FedexOpenShipSchemas
{
    /**
     * Name of the definition of the Create Open Shipment request
     * @var string
     */
    const CREATE_REQUEST = 'FullSchema_createOpenShipment';

    /**
     * Name of the definition of the Create Open Shipment response
     * @var string
     */
    const CREATE_RESPONSE = 'SHPCResponseVO_CreateOpenShipment';

    /**
     * Name of the definition of the Modify Open Shipment request
     * @var string
     */
    const MODIFY_REQUEST = 'FullSchema_modifyOpenShipment';

    /**
     * Name of the definition of the Modify Open Shipment response
     * @var string
     */
    const MODIFY_RESPONSE = 'SHPCResponseVO_ModifyOpenShipment';

    /**
     * Name of the definition of the Confirm Open Shipment request
     * @var string
     */
    const CONFIRM_REQUEST = 'FullSchema_confirmOpenShipment';

    /**
     * Name of the definition of the Confirm Open Shipment response
     * @var string
     */
    const CONFIRM_RESPONSE = 'SHPCResponseVO_ConfirmOpenShipment';

    /**
     * Name of the definition of the Modify Open Shipment Packages request
     * @var string
     */
    const MODIFY_PACKAGE_REQUEST = 'FullSchema_modifyPackageInOpenShipment';

    /**
     * Name of the definition of the Modify Open Shipment Packages response
     * @var string
     */
    const MODIFY_PACKAGE_RESPONSE = 'SHPCResponseVO_ModifyPackageInOpenShipment';

    /**
     * Name of the definition of the Add Open Shipment Packages request
     * @var string
     */
    const ADD_PACKAGES_REQUEST = 'FullSchema_addPackagesToOpenShipment';

    /**
     * Name of the definition of the Add Open Shipment Packages response
     * @var string
     */
    const ADD_PACKAGES_RESPONSE = 'SHPCResponseVO_AddPackagesToOpenShipment';

    /**
     * Name of the definition of the Delete Open Shipment Packages request
     * @var string
     */
    const DELETE_PACKAGES_REQUEST = 'FullSchema_deletePackagesFromOpenShipment';

    /**
     * Name of the definition of the Delete Open Shipment Packages response
     * @var string
     */
    const DELETE_PACKAGES_RESPONSE = 'SHPCResponseVO_DeletePackages';

    /**
     * Name of the definition of the Retrieve Open Shipment Packages request
     * @var string
     */
    const RETRIEVE_PACKAGE_REQUEST = 'FullSchema_retrievePackageInOpenShipment';

    /**
     * Name of the definition of the Retrieve Open Shipment Packages response
     * @var string
     */
    const RETRIEVE_PACKAGE_RESPONSE = 'SHPCResponseVO_RetrievePackageInOpenShipment';

    /**
     * Name of the definition of the Delete Open Shipment request
     * @var string
     */
    const DELETE_REQUEST = 'FullSchema_deleteOpenShipment';

    /**
     * Name of the definition of the Delete Open Shipment response
     * @var string
     */
    const DELETE_RESPONSE = 'SHPCResponseVO_DeleteOpenShipment';

    /**
     * Name of the definition of the Retrieve Open Shipment request
     * @var string
     */
    const RETRIEVE_REQUEST = 'FullSchema_RetrieveOpenShipment';

    /**
     * Name of the definition of the Retrieve Open Shipment response
     * @var string
     */
    const RETRIEVE_RESPONSE = 'SHPCResponseVO_RetrieveOpenShipment';

    /**
     * Name of the definition of the Get Open Shipment Results request
     * @var string
     */
    const RESULTS_REQUEST = 'FullSchema_getOpenShipmentResults';

    /**
     * Name of the definition of the Get Open Shipment Results response
     * @var string
     */
    const RESULTS_RESPONSE = 'SHPCResponseVO_GetOpenShipmentResults';

    /**
     * The roots of the file that FedexOpenShip implements: the request and the response of each operation
     * @var string[]
     */
    const ROOTS = [
        self::CREATE_REQUEST,
        self::CREATE_RESPONSE,
        self::MODIFY_REQUEST,
        self::MODIFY_RESPONSE,
        self::CONFIRM_REQUEST,
        self::CONFIRM_RESPONSE,
        self::MODIFY_PACKAGE_REQUEST,
        self::MODIFY_PACKAGE_RESPONSE,
        self::ADD_PACKAGES_REQUEST,
        self::ADD_PACKAGES_RESPONSE,
        self::DELETE_PACKAGES_REQUEST,
        self::DELETE_PACKAGES_RESPONSE,
        self::RETRIEVE_PACKAGE_REQUEST,
        self::RETRIEVE_PACKAGE_RESPONSE,
        self::DELETE_REQUEST,
        self::DELETE_RESPONSE,
        self::RETRIEVE_REQUEST,
        self::RETRIEVE_RESPONSE,
        self::RESULTS_REQUEST,
        self::RESULTS_RESPONSE
    ];

    /**
     * Get the definitions of all the requests and responses, down to the last field. The names of the definitions, and
     * of their fields, are the ones of schemas/open-ship.json, with these differences:
     *  - the ten requests (FullSchema-createOpenShipment, ...) are named with an underscore instead of the hyphen (the
     *    validator does not accept a hyphen in a name)
     *  - what the file writes inline or as an overlay of another definition ("this definition, but these keys are
     *    required") is a definition of its own, named after the definition and the field that contain it
     *  - FullSchema-RetrieveOpenShipment requires trackingId, which it never defines: it is accepted as an optional field
     *    of any type
     *  - the values of AlcoholDetail.alcoholRecipientType are LICENSEE and CONSUMER, as in schemas/ship.json (the file has
     *    CONSUMERstomer, a typo; decided with the owner)
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['AccountNumber'] = '@PartyAccountNumber';

        $schemas['AddPackagesToOpenShipmentOutputVO'] = [
            'transactionShipments?' => ['@TransactionShipmentOutputVO_1'],
            'alerts?' => ['@Alert']
        ];

        $schemas['AdditionalLabelsDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['BROKER', 'CONSIGNEE', 'CUSTOMS', 'DESTINATION', 'DESTINATION_CONTROL_STATEMENT', 'FREIGHT_REFERENCE', 'MANIFEST', 'ORIGIN', 'RECIPIENT', 'SECOND_ADDRESS', 'SHIPPER']],
            'count?' => 'int'
        ];

        $schemas['AdditionalMeasures'] = [
            'quantity?' => 'number',
            'units?' => 'string'
        ];

        $schemas['Address'] = [
            'streetLines' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['Address_1'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['AdrLicenseDetail'] = [
            'licenseOrPermitDetail?' => '@LicenseOrPermitDetail'
        ];

        $schemas['AlcoholDetail'] = [
            'alcoholRecipientType?' => ['@type' => 'string', '@enum' => ['LICENSEE', 'CONSUMER']],
            'shipperAgreementType?' => 'string'
        ];

        $schemas['Alert'] = [
            'code?' => 'string',
            'alertType?' => ['@type' => 'string', '@enum' => ['NOTE', 'WARNING']],
            'message?' => 'string'
        ];

        $schemas['BaseProcessOutputVO_AddPackagesToOpenShipment'] = '@AddPackagesToOpenShipmentOutputVO';

        $schemas['BaseProcessOutputVO_ConfirmOpenShipment'] = '@ConfirmOpenShipmentOutputVO';

        $schemas['BaseProcessOutputVO_CreateOpenshipmentOutputVO'] = '@CreateOpenshipmentOutputVO';

        $schemas['BaseProcessOutputVO_DeleteOpenShipment'] = '@DeleteOpenShipmentOutputVO';

        $schemas['BaseProcessOutputVO_DeletePackages'] = '@DeletePackagesFromOpenShipmentOutputVO';

        $schemas['BaseProcessOutputVO_GetOpenShipmentResults'] = '@GetOpenShipmentResultsOutputVO';

        $schemas['BaseProcessOutputVO_ModifyOpenShipment'] = '@ModifyOpenShipmentOutputVO';

        $schemas['BaseProcessOutputVO_ModifyPackageInOpenShipment'] = '@ModifyPackageInOpenShipmentOutputVO';

        $schemas['BaseProcessOutputVO_RetrieveOpenShipment'] = '@RetrieveOpenShipmentOutputVO';

        $schemas['BaseProcessOutputVO_RetrievePackageInOpenShipment'] = '@RetrievePackageInOpenShipmentOutputVO';

        $schemas['BatteryDetail'] = [
            'batteryPackingType?' => ['@type' => 'string', '@enum' => ['CONTAINED_IN_EQUIPMENT', 'PACKED_WITH_EQUIPMENT']],
            'batteryRegulatoryType?' => ['@type' => 'string', '@enum' => ['IATA_SECTION_II']],
            'batteryMaterialType?' => ['@type' => 'string', '@enum' => ['LITHIUM_METAL', 'LITHIUM_ION']]
        ];

        $schemas['BillingDetails'] = [
            'billingCode?' => 'string',
            'billingType?' => 'string',
            'aliasId?' => 'string',
            'accountNickname?' => 'string',
            'accountNumber?' => 'string',
            'accountNumberCountryCode?' => 'string'
        ];

        $schemas['BinaryBarcode'] = [
            'type?' => 'string',
            'value?' => 'string'
        ];

        $schemas['BrokerDetail'] = [
            'broker?' => '@BrokerDetail_broker',
            'type?' => ['@type' => 'string', '@enum' => ['IMPORT']]
        ];

        $schemas['CODTransportationChargesDetail'] = [
            'rateType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'CURRENT', 'CUSTOM']],
            'rateLevelType?' => ['@type' => 'string', '@enum' => ['BUNDLED_RATE', 'INDIVIDUAL_PACKAGE_RATE']],
            'chargeLevelType?' => ['@type' => 'string', '@enum' => ['CURRENT_PACKAGE', 'SUM_OF_PACKAGES']],
            'chargeType?' => ['@type' => 'string', '@enum' => ['COD_SURCHARGE', 'NET_CHARGE', 'NET_FREIGHT', 'TOTAL_CUSTOMER_CHARGE']]
        ];

        $schemas['CertificateOfOriginDetail'] = [
            'customerImageUsages?' => ['@CustomerImageUsage'],
            'documentFormat?' => '@ShippingDocumentFormat'
        ];

        $schemas['ClearanceItemDetail'] = [
            'role?' => ['@type' => 'string', '@enum' => ['MANUFACTURER']],
            'id?' => 'string',
            'contact?' => '@ClearanceItemDetail_contact',
            'address?' => '@ClearanceItemDetail_address'
        ];

        $schemas['ClearanceItemDetail_address'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['ClearanceItemDetail_contact'] = [
            'companyName?' => 'string'
        ];

        $schemas['CommercialInvoice'] = [
            'originatorName?' => 'string',
            'comments?' => ['string'],
            'customerReferences?' => ['@CustomerReference'],
            'taxesOrMiscellaneousCharge?' => '@Money',
            'taxesOrMiscellaneousChargeType?' => ['@type' => 'string', '@enum' => ['COMMISSIONS', 'DISCOUNTS', 'HANDLING_FEES', 'OTHER', 'ROYALTIES_AND_LICENSE_FEES', 'TAXES']],
            'freightCharge?' => '@Money',
            'packingCosts?' => '@Money',
            'handlingCosts?' => '@Money',
            'declarationStatement?' => 'string',
            'termsOfSale?' => 'string',
            'specialInstructions?' => 'string',
            'shipmentPurpose?' => ['@type' => 'string', '@enum' => ['GIFT', 'NOT_SOLD', 'PERSONAL_EFFECTS', 'REPAIR_AND_RETURN', 'SAMPLE', 'SOLD']],
            'emailNotificationDetail?' => '@EmailDispositionDetail'
        ];

        $schemas['CommercialInvoiceDetail'] = [
            'customerImageUsages?' => ['@CustomerImageUsage'],
            'documentFormat?' => '@ShippingDocumentFormat'
        ];

        $schemas['Commodity'] = [
            'unitPrice?' => '@Money',
            'additionalMeasures?' => ['@AdditionalMeasures'],
            'numberOfPieces?' => 'int',
            'quantity?' => 'int',
            'quantityUnits?' => 'string',
            'customsValue?' => '@Money',
            'countryOfManufacture?' => 'string',
            'cIMarksAndNumbers?' => 'string',
            'harmonizedCode?' => 'string',
            'description' => 'string',
            'name?' => 'string',
            'weight?' => '@Weight_Without_Link',
            'exportLicenseNumber?' => 'string',
            'exportLicenseExpirationDate?' => 'string',
            'partNumber?' => 'string',
            'purpose?' => ['@type' => 'string', '@enum' => ['BUSINESS', 'CONSUMER']],
            'usmcaDetail?' => '@UsmcaDetail',
            'clearanceItemDetail?' => ['@ClearanceItemDetail']
        ];

        $schemas['CommodityClarificationDetail'] = [
            'commodityIndex?' => 'int',
            'suggestions?' => ['@SuggestedCommodityDetail']
        ];

        $schemas['CompletedEtdDetail'] = [
            'folderId?' => 'string',
            'type?' => 'string',
            'uploadDocumentReferenceDetails?' => ['@UploadDocumentReferenceDetail']
        ];

        $schemas['CompletedHazardousPackageDetail'] = [
            'regulation?' => 'string',
            'accessibility?' => 'string',
            'labelType?' => 'string',
            'containers?' => ['@ValidatedHazardousContainer'],
            'cargoAircraftOnly?' => 'bool',
            'referenceId?' => 'string',
            'radioactiveTransportIndex?' => 'number'
        ];

        $schemas['CompletedHazardousShipmentDetail'] = [
            'hazardousSummaryDetail?' => '@CompletedHazardousSummaryDetail',
            'adrLicense?' => '@AdrLicenseDetail',
            'dryIceDetail?' => '@ShipmentDryIceDetail'
        ];

        $schemas['CompletedHazardousSummaryDetail'] = [
            'smallQuantityExceptionPackageCount?' => 'int'
        ];

        $schemas['CompletedHoldAtLocationDetail'] = [
            'holdingLocationType?' => 'string',
            'holdingLocation?' => '@JustContactAndAddress'
        ];

        $schemas['CompletedPackageDetail'] = [
            'sequenceNumber?' => 'int',
            'operationalDetail?' => '@PackageOperationalDetail',
            'signatureOption?' => 'string',
            'trackingIds?' => ['@TrackingId'],
            'groupNumber?' => 'int',
            'oversizeClass?' => 'string',
            'packageRating?' => '@PackageRating',
            'dryIceWeight?' => '@Weight',
            'hazardousPackageDetail?' => '@CompletedHazardousPackageDetail'
        ];

        $schemas['CompletedShipmentDetail'] = [
            'completedPackageDetails?' => ['@CompletedPackageDetail'],
            'operationalDetail?' => '@ShipmentOperationalDetail',
            'carrierCode?' => 'string',
            'completedHoldAtLocationDetail?' => '@CompletedHoldAtLocationDetail',
            'completedEtdDetail?' => '@CompletedEtdDetail',
            'packagingDescription?' => 'string',
            'masterTrackingId?' => '@TrackingId',
            'serviceDescription?' => '@ServiceDescription',
            'usDomestic?' => 'bool',
            'hazardousShipmentDetail?' => '@CompletedHazardousShipmentDetail',
            'shipmentRating?' => '@ShipmentRating',
            'documentRequirements?' => '@DocumentRequirementsDetail',
            'exportComplianceStatement?' => 'string',
            'accessDetail?' => '@PendingShipmentAccessDetail'
        ];

        $schemas['ConfirmOpenShipmentOutputVO'] = [
            'jobId?' => 'string',
            'transactionShipments?' => ['@TransactionShipmentOutputVO'],
            'alerts?' => ['@Alert']
        ];

        $schemas['Contact'] = [
            'personName?' => 'string',
            'tollFreePhoneNumber?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'companyName?' => 'string',
            'pagerNumber?' => 'string',
            'faxNumber?' => 'string',
            'title?' => 'string'
        ];

        $schemas['ContactAndAddress'] = [
            'contact?' => '@Contact_1',
            'address?' => '@Address_1'
        ];

        $schemas['ContactAndAddress_1'] = [
            'contact?' => '@Contact_2',
            'address?' => '@Address_1'
        ];

        $schemas['Contact_1'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['Contact_2'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['ContentRecord'] = [
            'itemNumber?' => 'string',
            'receivedQuantity?' => 'int',
            'description?' => 'string',
            'partNumber?' => 'string'
        ];

        $schemas['CreateOpenshipmentOutputVO'] = [
            'asynchronousProcessingResultsDetail?' => 'string',
            'jobId?' => 'string',
            'transactionShipments?' => ['@TransactionOpenShipmentOutputVO_1'],
            'alerts?' => ['@Alert']
        ];

        $schemas['CurrencyExchangeRate'] = [
            'rate?' => 'number',
            'fromCurrency?' => 'string',
            'intoCurrency?' => 'string'
        ];

        $schemas['CustomerImageUsage'] = [
            'id?' => ['@type' => 'string', '@enum' => ['IMAGE_1', 'IMAGE_2', 'IMAGE_3', 'IMAGE_4', 'IMAGE_5']],
            'type?' => ['@type' => 'string', '@enum' => ['SIGNATURE']],
            'providedImageType?' => ['@type' => 'string', '@enum' => ['LETTER_HEAD', 'SIGNATURE']]
        ];

        $schemas['CustomerReference'] = [
            'customerReferenceType?' => ['@type' => 'string', '@enum' => ['INVOICE_NUMBER', 'P_O_NUMBER']],
            'value?' => 'string'
        ];

        $schemas['CustomerReference_1'] = [
            'customerReferenceType?' => ['@type' => 'string', '@enum' => ['CUSTOMER_REFERENCE', 'DEPARTMENT_NUMBER', 'INVOICE_NUMBER', 'P_O_NUMBER', 'INTRACOUNTRY_REGULATORY_REFERENCE', 'RMA_ASSOCIATION']],
            'value?' => 'string'
        ];

        $schemas['CustomerSpecifiedLabelDetail'] = [
            'maskedData?' => [['@type' => 'string', '@enum' => ['CUSTOMS_VALUE', 'DIMENSIONS', 'DUTIES_AND_TAXES_PAYOR_ACCOUNT_NUMBER', 'INSURED_VALUE', 'SECONDARY_BARCODE', 'SHIPPER_INFORMATION', 'TERMS_AND_CONDITIONS', 'TOTAL_WEIGHT', 'TRANSPORTATION_CHARGES_PAYOR_ACCOUNT_NUMBER']]],
            'regulatoryLabels?' => ['@RegulatoryLabelContentDetail'],
            'additionalLabels?' => ['@AdditionalLabelsDetail'],
            'docTabContent?' => '@DocTabContent'
        ];

        $schemas['CustomsClearanceDetail'] = [
            'regulatoryControls?' => [['@type' => 'string', '@enum' => ['FOOD_OR_PERISHABLE', 'USMCA', 'NOT_APPLICABLE_FOR_LOW_VALUE_CUSTOMS_EXCEPTIONS', 'NOT_IN_FREE_CIRCULATION']]],
            'brokers?' => ['@BrokerDetail'],
            'commercialInvoice' => '@CommercialInvoice',
            'freightOnValue?' => ['@type' => 'string', '@enum' => ['CARRIER_RISK', 'OWN_RISK']],
            'dutiesPayment?' => '@Payment_1',
            'commodities' => ['@Commodity'],
            'isDocumentOnly?' => 'bool',
            'recipientCustomsId?' => '@RecipientCustomsId',
            'customsOption?' => '@CustomsOptionDetail',
            'importerOfRecord?' => '@Party_1',
            'generatedDocumentLocale?' => 'string',
            'exportDetail?' => '@ExportDetail',
            'totalCustomsValue?' => '@Money',
            'partiesToTransactionAreRelated?' => 'bool',
            'declarationStatementDetail?' => '@CustomsDeclarationStatementDetail',
            'insuranceCharge?' => '@Money'
        ];

        $schemas['CustomsDeclarationStatementDetail'] = [
            'usmcaLowValueStatementDetail' => '@UsmcaLowValueStatementDetail'
        ];

        $schemas['CustomsOptionDetail'] = [
            'description?' => 'string',
            'type?' => ['@type' => 'string', '@enum' => ['COURTESY_RETURN_LABEL', 'EXHIBITION_TRADE_SHOW', 'FAULTY_ITEM', 'FOLLOWING_REPAIR', 'FOR_REPAIR', 'ITEM_FOR_LOAN', 'OTHER', 'REJECTED', 'REPLACEMENT', 'TRIAL']]
        ];

        $schemas['DangerousGoodsDetail'] = [
            'accessibility?' => ['@type' => 'string', '@enum' => ['ACCESSIBLE', 'INACCESSIBLE']],
            'cargoAircraftOnly?' => 'bool',
            'regulation?' => ['@type' => 'string', '@enum' => ['ADR', 'DOT', 'IATA', 'ORMD']],
            'options?' => [['@type' => 'string', '@enum' => ['HAZARDOUS_MATERIALS', 'BATTERY', 'ORM_D', 'REPORTABLE_QUANTITIES', 'SMALL_QUANTITY_EXCEPTION', 'LIMITED_QUANTITIES_COMMODITIES']]]
        ];

        $schemas['DeleteOpenShipmentOutputVO'] = [
            'deletedOpenshipment?' => 'bool',
            'alerts?' => ['@Alert']
        ];

        $schemas['DeletePackagesFromOpenShipmentOutputVO'] = [
            'deletedPackagesFromOpenshipment?' => 'bool',
            'alerts?' => ['@Alert']
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetail'] = [
            'recipient?' => '@DeliveryOnInvoiceAcceptanceDetail_recipient'
        ];

        $schemas['DestinationControlDetail'] = [
            'endUser?' => 'string',
            'statementTypes' => ['@type' => 'string', '@enum' => ['DEPARTMENT_OF_COMMERCE', 'DEPARTMENT_OF_STATE']],
            'destinationCountries?' => ['string']
        ];

        $schemas['Dimensions'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['DocTabContent'] = [
            'docTabContentType?' => ['@type' => 'string', '@enum' => ['BARCODED', 'CUSTOM', 'MINIMUM', 'STANDARD', 'ZONE001']],
            'zone001?' => '@DocTabContentZone001',
            'barcoded?' => '@DocTabContentBarcoded'
        ];

        $schemas['DocTabContentBarcoded'] = [
            'symbology?' => ['@type' => 'string', '@enum' => ['CODABAR', 'CODE128', 'CODE128_WIDEBAR', 'CODE128B', 'CODE128C', 'CODE39', 'CODE93', 'I2OF5', 'MANUAL', 'PDF417', 'POSTNET', 'QR_CODE', 'UCC128']],
            'specification?' => '@DocTabZoneSpecification'
        ];

        $schemas['DocTabContentZone001'] = [
            'docTabZoneSpecifications?' => ['@DocTabZoneSpecification']
        ];

        $schemas['DocTabZoneSpecification'] = [
            'zoneNumber?' => 'int',
            'header?' => 'string',
            'dataField?' => 'string',
            'literalValue?' => 'string',
            'justification?' => ['@type' => 'string', '@enum' => ['LEFT', 'RIGHT']]
        ];

        $schemas['DocumentFormatOptionsRequested'] = [
            'options?' => [['@type' => 'string', '@enum' => ['SHIPPING_LABEL_FIRST', 'SHIPPING_LABEL_LAST', 'SUPPRESS_ADDITIONAL_LANGUAGES']]]
        ];

        $schemas['DocumentGenerationDetail'] = [
            'letterhead?' => 'string',
            'electronicSignature?' => 'string',
            'minimumCopiesRequired?' => 'int',
            'type?' => 'string'
        ];

        $schemas['DocumentRequirementsDetail'] = [
            'requiredDocuments?' => ['string'],
            'prohibitedDocuments?' => ['string'],
            'generationDetails?' => ['@DocumentGenerationDetail']
        ];

        $schemas['EMailNotificationDetail'] = [
            'aggregationType?' => ['@type' => 'string', '@enum' => ['PER_PACKAGE', 'PER_SHIPMENT']],
            'emailNotificationRecipients?' => ['@EmailNotificationRecipient'],
            'personalMessage?' => 'string'
        ];

        $schemas['ETDDetail'] = [
            'attributes?' => [['@type' => 'string', '@enum' => ['POST_SHIPMENT_UPLOAD_REQUESTED']]],
            'attachedDocuments?' => ['@UploadDocumentReferenceDetail'],
            'requestedDocumentTypes?' => [['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'CUSTOM_PACKAGE_DOCUMENT', 'CUSTOM_SHIPMENT_DOCUMENT', 'CUSTOMER_SPECIFIED_LABELS', 'EXPORT_DECLARATION', 'GENERAL_AGENCY_AGREEMENT', 'LABEL', 'USMCA_CERTIFICATION_OF_ORIGIN', 'OP_900', 'PENDING_SHIPMENT_EMAIL_NOTIFICATION', 'PRO_FORMA_INVOICE', 'RETURN_INSTRUCTIONS', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']]]
        ];

        $schemas['EmailDispositionDetail'] = [
            'emailAddress?' => 'string',
            'type?' => 'string',
            'recipientType?' => 'string'
        ];

        $schemas['EmailLabelDetail'] = [
            'recipients?' => ['@EmailRecipient'],
            'message?' => 'string'
        ];

        $schemas['EmailNotificationRecipient'] = [
            'name?' => 'string',
            'emailNotificationRecipientType' => ['@type' => 'string', '@enum' => ['BROKER', 'OTHER', 'RECIPIENT', 'SHIPPER', 'THIRD_PARTY', 'OTHER1', 'OTHER2']],
            'emailAddress?' => 'string',
            'notificationFormatType?' => ['@type' => 'string', '@enum' => ['HTML', 'TEXT']],
            'notificationType?' => ['@type' => 'string', '@enum' => ['EMAIL']],
            'locale?' => 'string',
            'notificationEventType?' => [['@type' => 'string', '@enum' => ['ON_DELIVERY', 'ON_EXCEPTION', 'ON_SHIPMENT', 'ON_TENDER', 'ON_ESTIMATED_DELIVERY', 'ON_BILL_OF_LADING', 'ON_PICKUP_DRIVER_ARRIVED', 'ON_PICKUP_DRIVER_ASSIGNED', 'ON_PICKUP_DRIVER_DEPARTED', 'ON_PICKUP_DRIVER_EN_ROUTE']]]
        ];

        $schemas['EmailOptionsRequested'] = [
            'options?' => [['@type' => 'string', '@enum' => ['PRODUCE_PAPERLESS_SHIPPING_FORMAT', 'SUPPRESS_ADDITIONAL_LANGUAGES', 'SUPPRESS_ACCESS_EMAILS']]]
        ];

        $schemas['EmailRecipient'] = [
            'emailAddress' => 'string',
            'optionsRequested?' => '@EmailOptionsRequested',
            'role' => ['@type' => 'string', '@enum' => ['SHIPMENT_COMPLETOR', 'SHIPMENT_INITIATOR']],
            'locale?' => 'string'
        ];

        $schemas['ExportDetail'] = [
            'destinationControlDetail?' => '@DestinationControlDetail',
            'b13AFilingOption?' => ['@type' => 'string', '@enum' => ['NOT_REQUIRED', 'MANUALLY_ATTACHED', 'FILED_ELECTRONICALLY', 'SUMMARY_REPORTING', 'FEDEX_TO_STAMP']],
            'exportComplianceStatement?' => 'string',
            'permitNumber?' => 'string'
        ];

        $schemas['ExpressFreightDetail'] = [
            'bookingConfirmationNumber?' => 'int',
            'shippersLoadAndCount?' => 'int',
            'packingListEnclosed?' => 'bool'
        ];

        $schemas['FullSchema_RetrieveOpenShipment'] = [
            'index' => 'string',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version',
            'trackingId?' => 'mixed'
        ];

        $schemas['FullSchema_addPackagesToOpenShipment'] = [
            'index' => 'string',
            'openShipmentAction?' => '@OpenShipmentAction_1',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version',
            'requestedPackageLineItems' => ['@RequestedPackageLineItem']
        ];

        $schemas['FullSchema_confirmOpenShipment'] = [
            'labelResponseOptions' => '@LABELRESPONSEOPTIONS',
            'edtRequestType?' => ['@type' => 'string', '@enum' => ['ALL', 'NONE']],
            'labelSpecification' => '@LabelSpecification',
            'index' => 'string',
            'shippingDocumentSpecification?' => '@ShippingDocumentSpecification',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version',
            'openShipmentAction?' => ['@type' => 'string', '@enum' => ['PROVIDE_DOCUMENTS_INCREMENTALLY']]
        ];

        $schemas['FullSchema_createOpenShipment'] = [
            'requestedShipment' => '@OpenshipmentRequestedShipment',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version',
            'openShipmentAction?' => '@OpenShipAction',
            'index?' => 'string'
        ];

        $schemas['FullSchema_deleteOpenShipment'] = [
            'index' => 'string',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version'
        ];

        $schemas['FullSchema_deletePackagesFromOpenShipment'] = [
            'index' => 'string',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version',
            'trackingIds' => ['@TrackingId']
        ];

        $schemas['FullSchema_getOpenShipmentResults'] = [
            'resultMethodType' => ['@type' => 'string', '@enum' => ['CREATE', 'MODIFY', 'CONFIRM']],
            'jobId' => 'string',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version'
        ];

        $schemas['FullSchema_modifyOpenShipment'] = [
            'index' => 'string',
            'requestedShipment' => '@OpenshipmentRequestedShipment',
            'openShipmentAction?' => '@OpenShipmentAction_1',
            'version?' => '@Version',
            'accountNumber' => '@AccountNumber'
        ];

        $schemas['FullSchema_modifyPackageInOpenShipment'] = [
            'requestedPackageLineItem' => '@RequestedPackageLineItem',
            'index' => 'string',
            'openShipmentAction?' => '@OpenShipmentAction_1',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version',
            'trackingId' => '@TrackingId'
        ];

        $schemas['FullSchema_retrievePackageInOpenShipment'] = [
            'index' => 'string',
            'accountNumber' => '@AccountNumber',
            'trackingId' => '@TrackingId',
            'version?' => '@Version'
        ];

        $schemas['GeneralAgencyAgreementDetail'] = [
            'documentFormat?' => '@ShippingDocumentFormat'
        ];

        $schemas['GetOpenShipmentResultsOutputVO'] = [
            'transactionShipments?' => ['@TransactionShipmentOutputVO'],
            'alerts?' => ['@Alert']
        ];

        $schemas['HazardousCommodityContent001'] = [
            'quantity?' => '@HazardousCommodityQuantityDetail',
            'innerReceptacles?' => ['@HazardousCommodityInnerReceptacleDetail01'],
            'options?' => '@HazardousCommodityOptionDetail01',
            'description?' => '@HazardousCommodityDescription01'
        ];

        $schemas['HazardousCommodityDescription01'] = [
            'sequenceNumber?' => 'int',
            'processingOptions?' => [['@type' => 'string', '@enum' => ['INCLUDE_SPECIAL_PROVISIONS']]],
            'subsidiaryClasses?' => ['string'],
            'labelText?' => 'string',
            'technicalName?' => 'string',
            'packingDetails?' => '@HazardousCommodityPackingDetail01',
            'authorization?' => 'string',
            'reportableQuantity' => 'bool',
            'percentage?' => 'number',
            'id?' => 'string',
            'packingGroup' => ['@type' => 'string', '@enum' => ['DEFAULT', 'I', 'II', 'III']],
            'properShippingName?' => 'string',
            'hazardClass?' => 'string'
        ];

        $schemas['HazardousCommodityInnerReceptacleDetail01'] = [
            'quantity?' => '@HazardousCommodityQuantityDetail002'
        ];

        $schemas['HazardousCommodityOptionDetail01'] = [
            'labelTextOption?' => ['@type' => 'string', '@enum' => ['APPEND', 'OVERRIDE', 'STANDARD']],
            'customerSuppliedLabelText?' => 'string'
        ];

        $schemas['HazardousCommodityPackingDetail01'] = [
            'packingInstructions?' => 'string',
            'cargoAircraftOnly' => 'bool'
        ];

        $schemas['HazardousCommodityQuantityDetail'] = [
            'quantityType' => ['@type' => 'string', '@enum' => ['GROSS', 'NET']],
            'amount' => 'number',
            'units?' => 'string'
        ];

        $schemas['HazardousCommodityQuantityDetail002'] = [
            'quantityType' => ['@type' => 'string', '@enum' => ['GROSS', 'NET']],
            'amount' => 'number',
            'units?' => 'string'
        ];

        $schemas['HoldAtLocationDetail'] = [
            'locationId' => 'string',
            'locationContactAndAddress?' => '@ContactAndAddress',
            'locationType?' => ['@type' => 'string', '@enum' => ['FEDEX_AUTHORIZED_SHIP_CENTER', 'FEDEX_OFFICE', 'FEDEX_SELF_SERVICE_LOCATION', 'FEDEX_STAFFED', 'RETAIL_ALLICANCE_LOCATION', 'FEDEX_GROUND_TERMINAL', 'FEDEX_ONSITE']]
        ];

        $schemas['HomeDeliveryPremiumDetail'] = [
            'phoneNumber?' => '@PhoneNumber1',
            'deliveryDate?' => 'string',
            'homedeliveryPremiumType?' => ['@type' => 'string', '@enum' => ['APPOINTMENT', 'DATE_CERTAIN', 'EVENING']]
        ];

        $schemas['InternationalControlledExportDetail'] = [
            'licenseOrPermitExpirationDate?' => 'string',
            'licenseOrPermitNumber?' => 'string',
            'entryNumber?' => 'string',
            'foreignTradeZoneCode?' => 'string',
            'type' => ['@type' => 'string', '@enum' => ['DEA_036', 'DEA_236', 'DSP_05', 'DSP_61', 'DSP_73', 'DSP_85', 'DSP_LICENSE_AGREEMENT', 'WAREHOUSE_WITHDRAWAL']]
        ];

        $schemas['InternationalTrafficInArmsRegulationsDetail'] = [
            'licenseOrExemptionNumber' => 'string'
        ];

        $schemas['JustContactAndAddress'] = [
            'address?' => '@Address',
            'contact?' => '@Contact'
        ];

        $schemas['LABELRESPONSEOPTIONS'] = ['@type' => 'string', '@enum' => ['URL_ONLY', 'LABEL']];

        $schemas['LabelResponseVO'] = [
            'contentKey?' => 'string',
            'copiesToPrint?' => 'int',
            'contentType?' => ['@type' => 'string', '@enum' => ['LABEL', 'BILL_OF_LADING', 'GAA_FORM', 'HAZMAT_LABEL', 'END_OF_DAY_HAZMAT_REPORT', 'MANIFEST_REPORT', 'MULTIWEIGHT_REPORT', 'MERGED_LABEL_DOCUMENTS', 'AUXILIARY', 'RETURN_INSTRUCTIONS', 'ACCEPTANCE_LABEL', 'COMMERCIAL_INVOICE', 'PROFORMA_INVOICE', 'USMCA_CERTIFICATION_OF_ORIGIN', 'CERTIFICATE_OF_ORIGIN', 'MERGED_LABELS_ONLY']],
            'trackingNumber?' => 'string',
            'docType?' => 'string',
            'alerts?' => ['@Alert'],
            'encodedLabel?' => 'string',
            'url?' => 'string'
        ];

        $schemas['LabelSpecification'] = [
            'labelFormatType?' => ['@type' => 'string', '@enum' => ['COMMON2D', 'LABEL_DATA_ONLY']],
            'labelOrder?' => ['@type' => 'string', '@enum' => ['SHIPPING_LABEL_FIRST', 'SHIPPING_LABEL_LAST']],
            'customerSpecifiedDetail?' => '@CustomerSpecifiedLabelDetail',
            'printedLabelOrigin?' => '@ContactAndAddress',
            'labelStockType' => ['@type' => 'string', '@enum' => ['PAPER_4X6', 'PAPER_4X675', 'PAPER_4X8', 'PAPER_4X9', 'PAPER_7X475', 'PAPER_85X11_BOTTOM_HALF_LABEL', 'PAPER_85X11_TOP_HALF_LABEL', 'PAPER_LETTER', 'STOCK_4X85_TRAILING_DOC_TAB', 'STOCK_4X105_TRAILING_DOC_TAB', 'STOCK_4X675_LEADING_DOC_TAB', 'STOCK_4X8', 'STOCK_4X9_LEADING_DOC_TAB', 'STOCK_4X6', 'STOCK_4X675_TRAILING_DOC_TAB', 'STOCK_4X9_TRAILING_DOC_TAB', 'STOCK_4X675', 'STOCK_4X9']],
            'labelRotation?' => ['@type' => 'string', '@enum' => ['LEFT', 'RIGHT', 'UPSIDE_DOWN', 'NONE']],
            'imageType' => ['@type' => 'string', '@enum' => ['ZPLII', 'EPL2', 'PDF', 'PNG']],
            'labelPrintingOrientation?' => ['@type' => 'string', '@enum' => ['BOTTOM_EDGE_OF_TEXT_FIRST', 'TOP_EDGE_OF_TEXT_FIRST']],
            'returnedDispositionDetail?' => 'bool',
            'resolution?' => 'int'
        ];

        $schemas['LicenseOrPermitDetail'] = [
            'number?' => 'string',
            'effectiveDate?' => 'string',
            'expirationDate?' => 'string'
        ];

        $schemas['MasterTrackingId'] = [
            'formId?' => 'string',
            'trackingIdType?' => 'string',
            'uspsApplicationId?' => 'string',
            'trackingNumber?' => 'string'
        ];

        $schemas['Message'] = [
            'code?' => 'string',
            'text?' => 'string',
            'parameters?' => ['@MessageParameter'],
            'localizedText?' => 'string'
        ];

        $schemas['MessageParameter'] = [
            'id?' => 'string',
            'value?' => 'string'
        ];

        $schemas['ModifyOpenShipmentOutputVO'] = [
            'asynchronousProcessingResultsDetail?' => 'string',
            'jobId?' => 'string',
            'transactionShipments?' => ['@TransactionOpenShipmentOutputVO'],
            'alerts?' => ['@Alert']
        ];

        $schemas['ModifyPackageInOpenShipmentOutputVO'] = [
            'transactionShipments?' => ['@TransactionShipmentOutputVO'],
            'alerts?' => ['@Alert']
        ];

        $schemas['Money'] = [
            'amount?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['NetExplosiveDetail'] = [
            'amount?' => 'number',
            'units?' => 'string',
            'type?' => 'string'
        ];

        $schemas['Op900Detail'] = [
            'customerImageUsages?' => ['@CustomerImageUsage'],
            'signatureName?' => 'string',
            'documentFormat?' => '@ShippingDocumentFormat'
        ];

        $schemas['OpenShipAction'] = ['@type' => 'string', '@enum' => ['STRONG_VALIDATION', 'WEAK_VALIDATION', 'CREATE_PACKAGE', 'PROVIDE_DOCUMENTS_INCREMENTALLY']];

        $schemas['OpenShipmentAction_1'] = ['@type' => 'string', '@enum' => ['STRONG_VALIDATION', 'WEAK_VALIDATION', 'CREATE_PACKAGE']];

        $schemas['OpenShipmentSpecialServicesRequested'] = [
            'specialServiceTypes?' => ['string'],
            'etdDetail?' => '@ETDDetail',
            'deliveryOnInvoiceAcceptanceDetail?' => '@DeliveryOnInvoiceAcceptanceDetail',
            'internationalTrafficInArmsRegulationsDetail?' => '@InternationalTrafficInArmsRegulationsDetail',
            'holdAtLocationDetail?' => '@HoldAtLocationDetail',
            'shipmentCODDetail?' => '@ShipmentCODDetail',
            'shipmentDryIceDetail?' => '@ShipmentDryIceDetail_1',
            'internationalControlledExportDetail?' => '@InternationalControlledExportDetail',
            'homeDeliveryPremiumDetail?' => '@HomeDeliveryPremiumDetail'
        ];

        $schemas['OpenshipmentRequestedShipment'] = [
            'shipDatestamp?' => 'string',
            'pickupType' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'serviceType' => 'string',
            'packagingType' => 'string',
            'totalWeight?' => 'number',
            'shipper' => '@ShipperParty',
            'recipients' => ['@RecipientsParty'],
            'soldTo?' => '@SoldToParty',
            'origin?' => '@ContactAndAddress_1',
            'shippingChargesPayment' => '@Payment',
            'shipmentSpecialServices?' => '@OpenShipmentSpecialServicesRequested',
            'emailNotificationDetail?' => '@EMailNotificationDetail',
            'expressFreightDetail?' => '@ExpressFreightDetail',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'customsClearanceDetail?' => '@CustomsClearanceDetail',
            'smartPostInfoDetail?' => '@SmartPostInfoDetail',
            'blockInsightVisibility?' => 'bool',
            'rateRequestType?' => [['@type' => 'string', '@enum' => ['LIST', 'NONE', 'INCENTIVE', 'ACCOUNT', 'PREFERRED', 'RETAIL']]],
            'preferredCurrency?' => 'string',
            'requestedPackageLineItems' => ['@RequestedPackageLineItem'],
            'labelSpecification' => '@LabelSpecification'
        ];

        $schemas['OperationalInstructions'] = [
            'number?' => 'int',
            'content?' => 'string'
        ];

        $schemas['PackageBarcodes'] = [
            'binaryBarcodes?' => ['@BinaryBarcode'],
            'stringBarcodes?' => ['@StringBarcode']
        ];

        $schemas['PackageCODDetail'] = [
            'codCollectionAmount?' => '@Money'
        ];

        $schemas['PackageOperationalDetail'] = [
            'astraHandlingText?' => 'string',
            'barcodes?' => '@PackageBarcodes',
            'operationalInstructions?' => ['@OperationalInstructions']
        ];

        $schemas['PackageRateDetail'] = [
            'ratedWeightMethod?' => 'string',
            'totalFreightDiscounts?' => 'number',
            'totalTaxes?' => 'number',
            'minimumChargeType?' => ['@type' => 'string', '@enum' => ['CUSTOMER', 'CUSTOMER_FREIGHT_WEIGHT', 'EARNED_DISCOUNT', 'MIXED', 'RATE_SCALE']],
            'baseCharge?' => 'number',
            'totalRebates?' => 'number',
            'rateType?' => 'string',
            'billingWeight?' => '@Weight',
            'netFreight?' => 'number',
            'surcharges?' => ['@Surcharge'],
            'totalSurcharges?' => 'number',
            'netFedExCharge?' => 'number',
            'netCharge?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['PackageRating'] = [
            'effectiveNetDiscount?' => 'number',
            'actualRateType?' => 'string',
            'packageRateDetails?' => ['@PackageRateDetail']
        ];

        $schemas['PackageSpecialServicesRequested'] = [
            'specialServiceTypes?' => ['string'],
            'signatureOptionType?' => ['@type' => 'string', '@enum' => ['SERVICE_DEFAULT', 'NO_SIGNATURE_REQUIRED', 'INDIRECT', 'DIRECT', 'ADULT']],
            'priorityAlertDetail?' => '@PriorityAlertDetail',
            'signatureOptionDetail?' => '@SignatureOptionDetail',
            'alcoholDetail?' => '@AlcoholDetail',
            'dangerousGoodsDetail?' => '@DangerousGoodsDetail',
            'packageCODDetail?' => '@PackageCODDetail',
            'pieceCountVerificationBoxCount?' => 'int',
            'batteryDetails?' => ['@BatteryDetail'],
            'dryIceWeight?' => '@Weight_Without_Link',
            'standaloneBatteryDetails?' => ['@StandaloneBatteryDetails']
        ];

        $schemas['PartyAccountNumber'] = [
            'value?' => 'string'
        ];

        $schemas['PartyAccountNumber_1'] = [
            'value?' => 'string'
        ];

        $schemas['PartyAddress'] = [
            'streetLines' => ['string'],
            'city' => 'string',
            'stateOrProvinceCode' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['PartyAddress_1'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => 'bool',
            'geographicCoordinates?' => 'string'
        ];

        $schemas['PartyContact'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneExtension?' => 'string',
            'phoneNumber' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['PartyContact_1'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneExtension?' => 'string',
            'phoneNumber?' => 'string',
            'companyName?' => 'string',
            'faxNumber?' => 'string'
        ];

        $schemas['PartyContact_2'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneExtension?' => 'string',
            'phoneNumber' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['Party_1'] = [
            'address?' => '@PartyAddress',
            'contact?' => '@PartyContact',
            'accountNumber?' => '@PartyAccountNumber',
            'tins?' => ['@TaxpayerIdentification']
        ];

        $schemas['Party_2'] = [
            'address?' => '@Address_1',
            'contact?' => '@Contact_1',
            'accountNumber?' => '@PartyAccountNumber_1',
            'tins?' => ['@TaxpayerIdentification']
        ];

        $schemas['Party_3'] = [
            'address?' => '@PartyAddress_1',
            'contact?' => '@PartyContact_1',
            'tins?' => ['@TaxpayerIdentification']
        ];

        $schemas['Payment'] = [
            'paymentType' => ['@type' => 'string', '@enum' => ['SENDER', 'RECIPIENT', 'THIRD_PARTY', 'COLLECT']],
            'payor?' => '@Payor'
        ];

        $schemas['Payment_1'] = [
            'payor?' => '@Payor_1',
            'billingDetails?' => '@BillingDetails',
            'paymentType?' => ['@type' => 'string', '@enum' => ['SENDER', 'RECIPIENT', 'THIRD_PARTY', 'COLLECT']]
        ];

        $schemas['Payor'] = [
            'responsibleParty' => '@ResponsiblePartyParty'
        ];

        $schemas['Payor_1'] = [
            'responsibleParty?' => '@Party_2'
        ];

        $schemas['PendingShipmentAccessDetail'] = [
            'accessorDetails?' => ['@PendingShipmentAccessorDetail']
        ];

        $schemas['PendingShipmentAccessorDetail'] = [
            'password?' => 'string',
            'role?' => 'string',
            'emailLabelUrl?' => 'string',
            'userId?' => 'string'
        ];

        $schemas['PendingShipmentDetail'] = [
            'pendingShipmentType' => ['@type' => 'string', '@enum' => ['EMAIL']],
            'processingOptions?' => '@PendingShipmentProcessingOptionsRequested',
            'recommendedDocumentSpecification?' => '@RecommendedDocumentSpecification',
            'emailLabelDetail' => '@EmailLabelDetail',
            'attachedDocuments?' => ['@UploadDocumentReferenceDetail_1'],
            'expirationTimeStamp?' => 'string'
        ];

        $schemas['PendingShipmentProcessingOptionsRequested'] = [
            'options?' => [['@type' => 'string', '@enum' => ['ALLOW_MODIFICATIONS']]]
        ];

        $schemas['PhoneNumber1'] = [
            'areaCode?' => 'string',
            'localNumber?' => 'string',
            'extension?' => 'string',
            'personalIdentificationNumber?' => 'string'
        ];

        $schemas['PieceResponse'] = [
            'netChargeAmount?' => 'number',
            'transactionDetails?' => ['@TransactionDetailVO'],
            'packageDocuments?' => ['@LabelResponseVO'],
            'acceptanceTrackingNumber?' => 'string',
            'serviceCategory?' => ['@type' => 'string', '@enum' => ['EXPRESS', 'GROUND', 'EXPRESS_FREIGHT', 'FREIGHT', 'SMARTPOST', 'EXPRESS_PARCEL', 'NULL']],
            'listCustomerTotalCharge?' => 'string',
            'deliveryTimestamp?' => 'string',
            'trackingIdType?' => 'string',
            'additionalChargesDiscount?' => 'number',
            'netListRateAmount?' => 'number',
            'baseRateAmount?' => 'number',
            'packageSequenceNumber?' => 'int',
            'netDiscountAmount?' => 'number',
            'codcollectionAmount?' => 'number',
            'masterTrackingNumber?' => 'string',
            'acceptanceType?' => 'string',
            'trackingNumber?' => 'string',
            'successful?' => 'bool',
            'customerReferences?' => ['@CustomerReference']
        ];

        $schemas['PieceResponse_1'] = [
            'netChargeAmount?' => 'number',
            'transactionDetails?' => ['@TransactionDetailVO'],
            'packageDocuments?' => ['@LabelResponseVO'],
            'acceptanceTrackingNumber?' => 'string',
            'serviceCategory?' => ['@type' => 'string', '@enum' => ['EXPRESS', 'GROUND', 'EXPRESS_FREIGHT', 'FREIGHT', 'SMARTPOST', 'EXPRESS_PARCEL', 'NULL']],
            'listCustomerTotalCharge?' => 'string',
            'deliveryTimestamp?' => 'string',
            'trackingIdType?' => 'string',
            'additionalChargesDiscount?' => 'number',
            'netListRateAmount?' => 'number',
            'baseRateAmount?' => 'number',
            'packageSequenceNumber?' => 'int',
            'netDiscountAmount?' => 'number',
            'codcollectionAmount?' => 'number',
            'masterTrackingNumber?' => 'string',
            'acceptanceType?' => 'string',
            'trackingNumber?' => 'string',
            'successful?' => 'bool',
            'customerReferences?' => ['@CustomerReference']
        ];

        $schemas['PriorityAlertDetail'] = [
            'enhancementTypes?' => ['string'],
            'content?' => ['string']
        ];

        $schemas['ProductName'] = [
            'type?' => 'string',
            'encoding?' => 'string',
            'value?' => 'string'
        ];

        $schemas['RateDiscount'] = [
            'amount?' => 'number',
            'rateDiscountType?' => 'string',
            'percent?' => 'number',
            'description?' => 'string'
        ];

        $schemas['RecipientCustomsId'] = [
            'type?' => ['@type' => 'string', '@enum' => ['COMPANY', 'INDIVIDUAL', 'PASSPORT']],
            'value?' => 'string'
        ];

        $schemas['RecipientsParty'] = [
            'address' => '@PartyAddress',
            'contact' => '@PartyContact',
            'tins?' => ['@TaxpayerIdentification'],
            'deliveryInstructions?' => 'string'
        ];

        $schemas['RecommendedDocumentSpecification'] = [
            'types' => [['@type' => 'string', '@enum' => ['ANTIQUE_STATEMENT_EUROPEAN_UNION', 'ANTIQUE_STATEMENT_UNITED_STATES', 'ASSEMBLER_DECLARATION', 'BEARING_WORKSHEET', 'CERTIFICATE_OF_SHIPMENTS_TO_SYRIA', 'COMMERCIAL_INVOICE_FOR_THE_CARIBBEAN_COMMON_MARKET', 'CONIFEROUS_SOLID_WOOD_PACKAGING_MATERIAL_TO_THE_PEOPLES_REPUBLIC_OF_CHINA', 'DECLARATION_FOR_FREE_ENTRY_OF_RETURNED_AMERICAN_PRODUCTS', 'DECLARATION_OF_BIOLOGICAL_STANDARDS', 'DECLARATION_OF_IMPORTED_ELECTRONIC_PRODUCTS_SUBJECT_TO_RADIATION_CONTROL_STANDARD', 'ELECTRONIC_INTEGRATED_CIRCUIT_WORKSHEET', 'FILM_AND_VIDEO_CERTIFICATE', 'INTERIM_FOOTWEAR_INVOICE', 'USMCA_CERTIFICATION_OF_ORIGIN_CANADA_ENGLISH', 'USMCA_CERTIFICATION_OF_ORIGIN_CANADA_FRENCH', 'USMCA_CERTIFICATION_OF_ORIGIN_SPANISH', 'USMCA_CERTIFICATION_OF_ORIGIN_UNITED_STATES', 'PACKING_LIST', 'PRINTED_CIRCUIT_BOARD_WORKSHEET', 'REPAIRED_WATCH_BREAKOUT_WORKSHEET', 'STATEMENT_REGARDING_THE_IMPORT_OF_RADIO_FREQUENCY_DEVICES', 'TOXIC_SUBSTANCES_CONTROL_ACT', 'UNITED_STATES_CARIBBEAN_BASIN_TRADE_PARTNERSHIP_ACT_CERTIFICATE_OF_ORIGIN_TEXTILES', 'UNITED_STATES_CARIBBEAN_BASIN_TRADE_PARTNERSHIP_ACT_CERTIFICATE_OF_ORIGIN_NON_TEXTILES', 'UNITED_STATES_NEW_WATCH_WORKSHEET', 'UNITED_STATES_WATCH_REPAIR_DECLARATION']]]
        ];

        $schemas['RegulatoryAdvisoryDetail'] = [
            'commodityClarifications?' => ['@CommodityClarificationDetail'],
            'prohibitions?' => ['@RegulatoryProhibition']
        ];

        $schemas['RegulatoryLabelContentDetail'] = [
            'generationOptions?' => ['@type' => 'string', '@enum' => ['CONTENT_ON_SHIPPING_LABEL_PREFERRED', 'CONTENT_ON_SUPPLEMENTAL_LABEL_ONLY', 'CONTENT_ON_SHIPPING_LABEL_ONLY']],
            'type?' => ['@type' => 'string', '@enum' => ['ALCOHOL_SHIPMENT_LABEL']]
        ];

        $schemas['RegulatoryProhibition'] = [
            'derivedHarmonizedCode?' => 'string',
            'advisory?' => '@Message',
            'commodityIndex?' => 'int',
            'source?' => 'string',
            'categories?' => ['string'],
            'type?' => 'string',
            'waiver?' => '@RegulatoryWaiver',
            'status?' => 'string'
        ];

        $schemas['RegulatoryWaiver'] = [
            'advisories?' => ['@Message'],
            'description?' => 'string',
            'id?' => 'string'
        ];

        $schemas['RequestedPackageLineItem'] = [
            'sequenceNumber?' => 'string',
            'subPackagingType?' => 'string',
            'customerReferences?' => ['@CustomerReference_1'],
            'declaredValue?' => '@Money',
            'weight' => '@Weight_With_Link',
            'dimensions?' => '@Dimensions',
            'groupPackageCount?' => 'int',
            'itemDescriptionForClearance?' => 'string',
            'contentRecord?' => ['@ContentRecord'],
            'itemDescription?' => 'string',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'packageSpecialServices?' => '@PackageSpecialServicesRequested'
        ];

        $schemas['RequestedShipment'] = [
            'shipDatestamp?' => 'string',
            'totalDeclaredValue?' => '@Money',
            'shipper' => '@ShipperParty',
            'recipients' => ['@RecipientsParty'],
            'recipientLocationNumber?' => 'string',
            'pickupType' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'serviceType' => 'string',
            'packagingType' => 'string',
            'totalWeight?' => 'number',
            'origin?' => '@ContactAndAddress_1',
            'shippingChargesPayment' => '@Payment',
            'shipmentSpecialServices?' => '@ShipmentSpecialServicesRequested',
            'emailNotificationDetail?' => '@ShipShipmentEMailNotificationDetail',
            'expressFreightDetail?' => '@ExpressFreightDetail',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'customsClearanceDetail?' => '@CustomsClearanceDetail',
            'smartPostInfoDetail?' => '@SmartPostInfoDetail',
            'blockInsightVisibility?' => 'bool',
            'labelSpecification' => '@LabelSpecification',
            'shippingDocumentSpecification?' => '@ShippingDocumentSpecification',
            'rateRequestType?' => [['@type' => 'string', '@enum' => ['LIST', 'NONE', 'PREFERRED', 'ACCOUNT', 'INCENTIVE', 'RETAIL']]],
            'preferredCurrency?' => 'string',
            'totalPackageCount?' => 'int',
            'masterTrackingId?' => '@MasterTrackingId',
            'requestedPackageLineItems' => ['@RequestedPackageLineItem']
        ];

        $schemas['ResponsiblePartyParty'] = [
            'address?' => '@PartyAddress',
            'contact?' => '@PartyContact_2',
            'accountNumber?' => '@PartyAccountNumber'
        ];

        $schemas['RetrieveDateRange'] = [
            'begins?' => 'string',
            'ends?' => 'string'
        ];

        $schemas['RetrieveOpenShipmentOutputVO'] = [
            'requestedShipment?' => '@RequestedShipment',
            'alerts?' => ['@Alert']
        ];

        $schemas['RetrievePackageInOpenShipmentOutputVO'] = [
            'requestedpackageLineItem?' => '@RequestedPackageLineItem',
            'alerts?' => ['@Alert']
        ];

        $schemas['ReturnAssociationDetail'] = [
            'shipDatestamp?' => 'string',
            'trackingNumber?' => 'string'
        ];

        $schemas['ReturnEmailDetail'] = [
            'merchantPhoneNumber' => 'string',
            'allowedSpecialService?' => [['@type' => 'string', '@enum' => ['SATURDAY_DELIVERY', 'SATURDAY_PICKUP']]]
        ];

        $schemas['ReturnInstructionsDetail'] = [
            'customText?' => 'string',
            'documentFormat?' => '@ReturnShippingDocumentFormat'
        ];

        $schemas['ReturnMerchandiseAuthorization'] = [
            'reason?' => 'string'
        ];

        $schemas['ReturnShipmentDetail'] = [
            'returnEmailDetail?' => '@ReturnEmailDetail',
            'rma?' => '@ReturnMerchandiseAuthorization',
            'returnAssociationDetail?' => '@ReturnAssociationDetail',
            'returnType' => ['@type' => 'string', '@enum' => ['PENDING', 'PRINT_RETURN_LABEL']]
        ];

        $schemas['ReturnShippingDocumentFormat'] = [
            'provideInstructions?' => 'bool',
            'optionsRequested?' => '@DocumentFormatOptionsRequested',
            'stockType?' => ['@type' => 'string', '@enum' => ['PAPER_LETTER']],
            'dispositions?' => ['@ShippingDocumentDispositionDetail'],
            'locale?' => 'string',
            'docType?' => ['@type' => 'string', '@enum' => ['PNG', 'PDF']]
        ];

        $schemas['SHPCResponseVO_AddPackagesToOpenShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_AddPackagesToOpenShipment'
        ];

        $schemas['SHPCResponseVO_ConfirmOpenShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_ConfirmOpenShipment'
        ];

        $schemas['SHPCResponseVO_CreateOpenShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_CreateOpenshipmentOutputVO'
        ];

        $schemas['SHPCResponseVO_DeleteOpenShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_DeleteOpenShipment'
        ];

        $schemas['SHPCResponseVO_DeletePackages'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_DeletePackages'
        ];

        $schemas['SHPCResponseVO_GetOpenShipmentResults'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_GetOpenShipmentResults'
        ];

        $schemas['SHPCResponseVO_ModifyOpenShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_ModifyOpenShipment'
        ];

        $schemas['SHPCResponseVO_ModifyPackageInOpenShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_ModifyPackageInOpenShipment'
        ];

        $schemas['SHPCResponseVO_RetrieveOpenShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_RetrieveOpenShipment'
        ];

        $schemas['SHPCResponseVO_RetrievePackageInOpenShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_RetrievePackageInOpenShipment'
        ];

        $schemas['ServiceDescription'] = [
            'serviceType?' => 'string',
            'code?' => 'string',
            'names?' => ['@ProductName'],
            'operatingOrgCodes?' => ['string'],
            'astraDescription?' => 'string',
            'description?' => 'string',
            'serviceId?' => 'string',
            'serviceCategory?' => 'string'
        ];

        $schemas['ShipShipmentEMailNotificationDetail'] = [
            'aggregationType?' => ['@type' => 'string', '@enum' => ['PER_PACKAGE', 'PER_SHIPMENT']],
            'emailNotificationRecipients?' => ['@ShipShipmentEmailNotificationRecipient'],
            'personalMessage?' => 'string'
        ];

        $schemas['ShipShipmentEmailNotificationRecipient'] = [
            'name?' => 'string',
            'emailNotificationRecipientType' => ['@type' => 'string', '@enum' => ['BROKER', 'OTHER', 'RECIPIENT', 'SHIPPER', 'THIRD_PARTY', 'OTHER1', 'OTHER2']],
            'emailAddress' => 'string',
            'notificationFormatType?' => ['@type' => 'string', '@enum' => ['HTML', 'TEXT']],
            'notificationType?' => ['@type' => 'string', '@enum' => ['EMAIL']],
            'locale?' => 'string',
            'notificationEventType?' => [['@type' => 'string', '@enum' => ['ON_DELIVERY', 'ON_EXCEPTION', 'ON_SHIPMENT', 'ON_TENDER', 'ON_ESTIMATED_DELIVERY', 'ON_PICKUP', 'ON_PICKUP_DRIVER_ARRIVED', 'ON_PICKUP_DRIVER_ASSIGNED', 'ON_PICKUP_DRIVER_DEPARTED', 'ON_PICKUP_DRIVER_EN_ROUTE']]]
        ];

        $schemas['ShipmentAdvisoryDetails'] = [
            'regulatoryAdvisory?' => '@RegulatoryAdvisoryDetail'
        ];

        $schemas['ShipmentCODDetail'] = [
            'addTransportationChargesDetail?' => '@CODTransportationChargesDetail',
            'codRecipient?' => '@Party_1',
            'remitToName?' => 'string',
            'codCollectionType' => ['@type' => 'string', '@enum' => ['ANY', 'CASH', 'GUARANTEED_FUNDS', 'COMPANY_CHECK', 'PERSONAL_CHECK']],
            'financialInstitutionContactAndAddress?' => '@ContactAndAddress',
            'codCollectionAmount?' => '@Money',
            'returnReferenceIndicatorType?' => ['@type' => 'string', '@enum' => ['INVOICE', 'PO', 'REFERENCE', 'TRACKING']],
            'shipmentCodAmount?' => '@Money'
        ];

        $schemas['ShipmentDryIceDetail'] = [
            'totalWeight' => '@Weight',
            'packageCount' => 'int',
            'processingOptions?' => '@ShipmentDryIceProcessingOptionsRequested'
        ];

        $schemas['ShipmentDryIceDetail_1'] = [
            'totalWeight?' => '@Weight_Without_Link',
            'packageCount?' => 'int'
        ];

        $schemas['ShipmentDryIceProcessingOptionsRequested'] = [
            'options?' => ['string']
        ];

        $schemas['ShipmentLegRateDetail'] = [
            'rateZone?' => 'string',
            'pricingCode?' => 'string',
            'taxes?' => ['@Tax'],
            'totalDimWeight?' => '@Weight',
            'totalRebates?' => 'number',
            'fuelSurchargePercent?' => 'number',
            'currencyExchangeRate?' => '@CurrencyExchangeRate',
            'dimDivisor?' => 'int',
            'rateType?' => 'string',
            'legDestinationLocationId?' => 'string',
            'dimDivisorType?' => 'string',
            'totalBaseCharge?' => 'number',
            'ratedWeightMethod?' => 'string',
            'totalFreightDiscounts?' => 'number',
            'totalTaxes?' => 'number',
            'minimumChargeType?' => 'string',
            'totalDutiesAndTaxes?' => 'number',
            'totalNetFreight?' => 'number',
            'totalNetFedExCharge?' => 'number',
            'surcharges?' => ['@Surcharge'],
            'totalSurcharges?' => 'number',
            'totalBillingWeight?' => '@Weight',
            'freightDiscounts?' => ['@RateDiscount'],
            'rateScale?' => 'string',
            'totalNetCharge?' => 'number',
            'totalNetChargeWithDutiesAndTaxes?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['ShipmentOperationalDetail'] = [
            'originServiceArea?' => 'string',
            'serviceCode?' => 'string',
            'airportId?' => 'string',
            'postalCode?' => 'string',
            'scac?' => 'string',
            'deliveryDay?' => 'string',
            'originLocationId?' => 'string',
            'countryCode?' => 'string',
            'astraDescription?' => 'string',
            'originLocationNumber?' => 'int',
            'deliveryDate?' => 'string',
            'deliveryEligibilities?' => ['string'],
            'ineligibleForMoneyBackGuarantee?' => 'bool',
            'maximumTransitTime?' => 'string',
            'destinationLocationStateOrProvinceCode?' => 'string',
            'astraPlannedServiceLevel?' => 'string',
            'destinationLocationId?' => 'string',
            'transitTime?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'destinationLocationNumber?' => 'int',
            'packagingCode?' => 'string',
            'commitDate?' => 'string',
            'publishedDeliveryTime?' => 'string',
            'ursaSuffixCode?' => 'string',
            'ursaPrefixCode?' => 'string',
            'destinationServiceArea?' => 'string',
            'commitDay?' => 'string',
            'customTransitTime?' => 'string'
        ];

        $schemas['ShipmentRateDetail'] = [
            'rateZone?' => 'string',
            'ratedWeightMethod?' => 'string',
            'totalDutiesTaxesAndFees?' => 'number',
            'pricingCode?' => 'string',
            'totalFreightDiscounts?' => 'number',
            'totalTaxes?' => 'number',
            'totalDutiesAndTaxes?' => 'number',
            'totalAncillaryFeesAndTaxes?' => 'number',
            'taxes?' => ['@Tax'],
            'totalRebates?' => 'number',
            'fuelSurchargePercent?' => 'number',
            'currencyExchangeRate?' => '@CurrencyExchangeRate',
            'totalNetFreight?' => 'number',
            'totalNetFedExCharge?' => 'number',
            'shipmentLegRateDetails?' => ['@ShipmentLegRateDetail'],
            'dimDivisor?' => 'int',
            'rateType?' => 'string',
            'surcharges?' => ['@Surcharge'],
            'totalSurcharges?' => 'number',
            'totalBillingWeight?' => '@Weight',
            'freightDiscounts?' => ['@RateDiscount'],
            'rateScale?' => 'string',
            'totalNetCharge?' => 'number',
            'totalBaseCharge?' => 'number',
            'totalNetChargeWithDutiesAndTaxes?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['ShipmentRating'] = [
            'actualRateType?' => 'string',
            'shipmentRateDetails?' => ['@ShipmentRateDetail']
        ];

        $schemas['ShipmentSpecialServicesRequested'] = [
            'specialServiceTypes?' => ['string'],
            'etdDetail?' => '@ETDDetail',
            'returnShipmentDetail?' => '@ReturnShipmentDetail',
            'deliveryOnInvoiceAcceptanceDetail?' => '@DeliveryOnInvoiceAcceptanceDetail',
            'internationalTrafficInArmsRegulationsDetail?' => '@InternationalTrafficInArmsRegulationsDetail',
            'pendingShipmentDetail?' => '@PendingShipmentDetail',
            'holdAtLocationDetail?' => '@HoldAtLocationDetail',
            'shipmentCODDetail?' => '@ShipmentCODDetail',
            'shipmentDryIceDetail?' => '@ShipmentDryIceDetail_1',
            'internationalControlledExportDetail?' => '@InternationalControlledExportDetail',
            'homeDeliveryPremiumDetail?' => '@HomeDeliveryPremiumDetail'
        ];

        $schemas['ShipperParty'] = [
            'address' => '@PartyAddress',
            'contact' => '@PartyContact',
            'tins?' => ['@TaxpayerIdentification']
        ];

        $schemas['ShippingDocumentDispositionDetail'] = [
            'eMailDetail?' => '@ShippingDocumentEmailDetail',
            'dispositionType?' => ['@type' => 'string', '@enum' => ['CONFIRMED', 'DEFERRED_QUEUED', 'DEFERRED_RETURNED', 'DEFERRED_STORED', 'EMAILED', 'QUEUED', 'RETURNED', 'STORED']]
        ];

        $schemas['ShippingDocumentEmailDetail'] = [
            'eMailRecipients' => ['@ShippingDocumentEmailRecipient'],
            'locale?' => 'string',
            'grouping?' => ['@type' => 'string', '@enum' => ['BY_RECIPIENT', 'NONE']]
        ];

        $schemas['ShippingDocumentEmailRecipient'] = [
            'emailAddress?' => 'string',
            'recipientType' => ['@type' => 'string', '@enum' => ['BROKER', 'OTHER', 'RECIPIENT', 'SHIPPER', 'THIRD_PARTY', 'OTHER1', 'OTHER2']]
        ];

        $schemas['ShippingDocumentFormat'] = [
            'provideInstructions?' => 'bool',
            'optionsRequested?' => '@DocumentFormatOptionsRequested',
            'stockType?' => ['@type' => 'string', '@enum' => ['PAPER_LETTER']],
            'dispositions?' => ['@ShippingDocumentDispositionDetail'],
            'locale?' => 'string',
            'docType?' => ['@type' => 'string', '@enum' => ['PDF']]
        ];

        $schemas['ShippingDocumentSpecification'] = [
            'generalAgencyAgreementDetail?' => '@GeneralAgencyAgreementDetail',
            'returnInstructionsDetail?' => '@ReturnInstructionsDetail',
            'op900Detail?' => '@Op900Detail',
            'usmcaCertificationOfOriginDetail?' => '@UsmcaCertificationOfOriginDetail',
            'usmcaCommercialInvoiceCertificationOfOriginDetail?' => '@UsmcaCommercialInvoiceCertificationOfOriginDetail',
            'shippingDocumentTypes?' => [['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'CUSTOM_PACKAGE_DOCUMENT', 'CUSTOM_SHIPMENT_DOCUMENT', 'CUSTOMER_SPECIFIED_LABELS', 'EXPORT_DECLARATION', 'GENERAL_AGENCY_AGREEMENT', 'LABEL', 'USMCA_CERTIFICATION_OF_ORIGIN', 'OP_900', 'PENDING_SHIPMENT_EMAIL_NOTIFICATION', 'PRO_FORMA_INVOICE', 'RETURN_INSTRUCTIONS', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']]],
            'certificateOfOrigin?' => '@CertificateOfOriginDetail',
            'commercialInvoiceDetail?' => '@CommercialInvoiceDetail'
        ];

        $schemas['SignatureOptionDetail'] = [
            'signatureReleaseNumber?' => 'string'
        ];

        $schemas['SmartPostInfoDetail'] = [
            'ancillaryEndorsement?' => ['@type' => 'string', '@enum' => ['ADDRESS_CORRECTION', 'CARRIER_LEAVE_IF_NO_RESPONSE', 'CHANGE_SERVICE', 'FORWARDING_SERVICE', 'RETURN_SERVICE']],
            'hubId' => 'string',
            'indicia' => ['@type' => 'string', '@enum' => ['MEDIA_MAIL', 'PARCEL_RETURN', 'PARCEL_SELECT', 'PRESORTED_BOUND_PRINTED_MATTER', 'PRESORTED_STANDARD']],
            'specialServices?' => ['@type' => 'string', '@enum' => ['USPS_DELIVERY_CONFIRMATION']]
        ];

        $schemas['SoldToParty'] = [
            'address?' => '@PartyAddress',
            'contact?' => '@PartyContact_2',
            'tins?' => ['@TaxpayerIdentification'],
            'accountNumber?' => '@AccountNumber'
        ];

        $schemas['StandaloneBatteryDetails'] = [
            'batteryMaterialType?' => ['@type' => 'string', '@enum' => ['LITHIUM_METAL', 'LITHIUM_ION']]
        ];

        $schemas['StringBarcode'] = [
            'type?' => 'string',
            'value?' => 'string'
        ];

        $schemas['SuggestedCommodityDetail'] = [
            'description?' => 'string',
            'harmonizedCode?' => 'string'
        ];

        $schemas['Surcharge'] = [
            'amount?' => 'mixed',
            'surchargeType?' => 'string',
            'level?' => 'string',
            'description?' => 'string'
        ];

        $schemas['Tax'] = [
            'amount?' => 'number',
            'level?' => 'string',
            'description?' => 'string',
            'type?' => 'string'
        ];

        $schemas['TaxpayerIdentification'] = [
            'number?' => 'string',
            'tinType?' => ['@type' => 'string', '@enum' => ['PERSONAL_NATIONAL', 'PERSONAL_STATE', 'FEDERAL', 'BUSINESS_NATIONAL', 'BUSINESS_STATE', 'BUSINESS_UNION']],
            'usage?' => 'string',
            'effectiveDate?' => 'string',
            'expirationDate?' => 'string'
        ];

        $schemas['TrackingId'] = [
            'formId?' => 'int',
            'trackingIdType?' => 'string',
            'uspsApplicationId?' => 'int',
            'trackingNumber?' => 'string'
        ];

        $schemas['TransactionDetailVO'] = [
            'transactionDetails?' => 'string',
            'transactionId?' => 'string'
        ];

        $schemas['TransactionOpenShipmentOutputVO'] = [
            'serviceType?' => 'string',
            'shipDatestamp?' => 'string',
            'serviceCategory?' => 'string',
            'pieceResponses?' => ['@PieceResponse'],
            'serviceName?' => 'string',
            'alerts?' => ['@Alert'],
            'completedShipmentDetail?' => '@CompletedShipmentDetail',
            'shipmentAdvisoryDetails?' => '@ShipmentAdvisoryDetails',
            'masterTrackingNumber?' => 'string'
        ];

        $schemas['TransactionOpenShipmentOutputVO_1'] = [
            'serviceType?' => 'string',
            'shipDatestamp?' => 'string',
            'serviceCategory?' => 'string',
            'pieceResponses?' => ['@PieceResponse_1'],
            'serviceName?' => 'string',
            'alerts?' => ['@Alert'],
            'completedShipmentDetail?' => '@CompletedShipmentDetail',
            'shipmentAdvisoryDetails?' => '@ShipmentAdvisoryDetails',
            'masterTrackingNumber?' => 'string'
        ];

        $schemas['TransactionShipmentOutputVO'] = [
            'serviceType?' => 'string',
            'shipDatestamp?' => 'string',
            'serviceCategory?' => 'string',
            'shipmentDocuments?' => ['@LabelResponseVO'],
            'pieceResponses?' => ['@PieceResponse'],
            'serviceName?' => 'string',
            'alerts?' => ['@Alert'],
            'completedShipmentDetail?' => '@CompletedShipmentDetail',
            'shipmentAdvisoryDetails?' => '@ShipmentAdvisoryDetails',
            'masterTrackingNumber?' => 'string'
        ];

        $schemas['TransactionShipmentOutputVO_1'] = [
            'serviceType?' => 'string',
            'shipDatestamp?' => 'string',
            'serviceCategory?' => 'string',
            'shipmentDocuments?' => ['@LabelResponseVO'],
            'pieceResponses?' => ['@PieceResponse_1'],
            'serviceName?' => 'string',
            'alerts?' => ['@Alert'],
            'completedShipmentDetail?' => '@CompletedShipmentDetail',
            'shipmentAdvisoryDetails?' => '@ShipmentAdvisoryDetails',
            'masterTrackingNumber?' => 'string'
        ];

        $schemas['UploadDocumentReferenceDetail'] = [
            'documentType?' => ['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'ETD_LABEL', 'USMCA_CERTIFICATION_OF_ORIGIN', 'OTHER', 'PRO_FORMA_INVOICE', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']],
            'documentReference?' => 'string',
            'description?' => 'string',
            'documentId?' => 'string'
        ];

        $schemas['UploadDocumentReferenceDetail_1'] = [
            'documentType?' => ['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'ETD_LABEL', 'USMCA_CERTIFICATION_OF_ORIGIN', 'NET_RATE_SHEET', 'OTHER', 'PRO_FORMA_INVOICE', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']],
            'documentReference?' => 'string',
            'description?' => 'string',
            'documentId?' => 'string'
        ];

        $schemas['UsmcaCertificationOfOriginDetail'] = [
            'customerImageUsages?' => ['@CustomerImageUsage'],
            'documentFormat?' => '@ShippingDocumentFormat',
            'certifierSpecification?' => ['@type' => 'string', '@enum' => ['EXPORTER', 'IMPORTER', 'PRODUCER']],
            'importerSpecification?' => ['@type' => 'string', '@enum' => ['UNKNOWN', 'VARIOUS']],
            'producerSpecification?' => ['@type' => 'string', '@enum' => ['AVAILABLE_UPON_REQUEST', 'VARIOUS', 'SAME_AS_EXPORTER']],
            'producer?' => '@Party_3',
            'blanketPeriod?' => '@RetrieveDateRange',
            'certifierJobTitle?' => 'string'
        ];

        $schemas['UsmcaCommercialInvoiceCertificationOfOriginDetail'] = [
            'customerImageUsages?' => ['@CustomerImageUsage'],
            'documentFormat?' => '@ShippingDocumentFormat',
            'certifierSpecification?' => ['@type' => 'string', '@enum' => ['EXPORTER', 'IMPORTER', 'PRODUCER']],
            'importerSpecification?' => ['@type' => 'string', '@enum' => ['UNKNOWN', 'VARIOUS']],
            'producerSpecification?' => ['@type' => 'string', '@enum' => ['AVAILABLE_UPON_REQUEST', 'VARIOUS', 'SAME_AS_EXPORTER']],
            'producer?' => '@Party_3',
            'certifierJobTitle?' => 'string'
        ];

        $schemas['UsmcaDetail'] = [
            'originCriterion?' => ['@type' => 'string', '@enum' => ['A', 'B', 'C', 'D', 'E']]
        ];

        $schemas['UsmcaLowValueStatementDetail'] = [
            'countryOfOriginLowValueDocumentRequested?' => 'bool',
            'customsRole' => ['@type' => 'string', '@enum' => ['EXPORTER', 'IMPORTER']]
        ];

        $schemas['ValidatedHazardousCommodityContent'] = [
            'quantity?' => '@HazardousCommodityQuantityDetail',
            'options?' => '@HazardousCommodityContent001',
            'description?' => '@ValidatedHazardousCommodityDescription',
            'netExplosiveDetail?' => '@NetExplosiveDetail',
            'massPoints?' => 'number'
        ];

        $schemas['ValidatedHazardousCommodityDescription'] = [
            'sequenceNumber?' => 'int',
            'packingInstructions?' => 'string',
            'subsidiaryClasses?' => ['string'],
            'labelText?' => 'string',
            'tunnelRestrictionCode?' => 'string',
            'specialProvisions?' => 'string',
            'properShippingNameAndDescription?' => 'string',
            'technicalName?' => 'string',
            'symbols?' => 'string',
            'authorization?' => 'string',
            'attributes?' => ['string'],
            'id?' => 'string',
            'packingGroup?' => 'string',
            'properShippingName?' => 'string',
            'hazardClass?' => 'string'
        ];

        $schemas['ValidatedHazardousContainer'] = [
            'qvalue?' => 'number',
            'hazardousCommodities?' => ['@ValidatedHazardousCommodityContent']
        ];

        $schemas['VariableHandlingChargeDetail'] = [
            'rateType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'ACTUAL', 'CURRENT', 'CUSTOM', 'LIST', 'INCENTIVE', 'PREFERRED', 'PREFERRED_INCENTIVE', 'PREFERRED_CURRENCY']],
            'percentValue?' => 'number',
            'rateLevelType?' => ['@type' => 'string', '@enum' => ['BUNDLED_RATE', 'INDIVIDUAL_PACKAGE_RATE']],
            'fixedValue?' => '@VariableHandlingChargeDetail_fixedValue',
            'rateElementBasis?' => ['@type' => 'string', '@enum' => ['NET_CHARGE', 'NET_FREIGHT', 'BASE_CHARGE', 'NET_CHARGE_EXCLUDING_TAXES']]
        ];

        $schemas['Version'] = [
            'major?' => 'int',
            'minor?' => 'int',
            'patch?' => 'int'
        ];

        $schemas['Weight'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG']],
            'value' => 'number'
        ];

        $schemas['Weight_With_Link'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => 'number'
        ];

        $schemas['Weight_Without_Link'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => 'number'
        ];

        $schemas['BrokerDetail_broker'] = [
            'address' => '@PartyAddress',
            'contact' => '@PartyContact',
            'accountNumber?' => '@PartyAccountNumber',
            'tins?' => ['@TaxpayerIdentification']
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetail_recipient_address'] = [
            'streetLines' => ['string'],
            'city' => 'string',
            'stateOrProvinceCode' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetail_recipient_contact'] = [
            'personName' => 'string',
            'emailAddress?' => 'string',
            'phoneExtension?' => 'string',
            'phoneNumber' => 'string',
            'companyName' => 'string'
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetail_recipient'] = [
            'address' => '@DeliveryOnInvoiceAcceptanceDetail_recipient_address',
            'contact' => '@DeliveryOnInvoiceAcceptanceDetail_recipient_contact',
            'tins?' => ['@TaxpayerIdentification'],
            'deliveryInstructions?' => 'string'
        ];

        $schemas['VariableHandlingChargeDetail_fixedValue'] = [
            'amount' => 'number',
            'currency' => 'string'
        ];

        return $schemas;
    }

    /**
     * Get the definitions of the top level of all the requests and responses: the roots of ROOTS with the same fields
     * and the same required fields, where a field that holds an object is only checked to be an object, and a field that
     * holds a list is only checked to be a list. The values that are text, numbers or booleans, and the enums of the top
     * level, are checked as in definitions()
     * @return array
     */
    public static function shallowDefinitions() : array
    {
        $full = self::definitions();
        $shallow = [];

        foreach (self::ROOTS as $root) {
            foreach ($full[$root] as $key => $node) {
                $shallow[$root][$key] = self::shallow($node, $full);
            }
        }

        return $shallow;
    }

    /**
     * The shallow form of the definition of a field
     * @param mixed $node The definition of the field in definitions()
     * @param array $full The definitions
     * @return mixed
     */
    private static function shallow($node, array $full)
    {
        if (is_string($node)) {
            // a reference to another definition stands for what that definition is; a union is any text of the file
            return (0 === strpos($node, '@')) && (false === strpos($node, '|')) ? self::shallow($full[substr($node, 1)], $full) : $node;
        }

        if (isset($node['@type'])) {
            return $node;
        }

        // a list definition has one entry, an object definition has named fields
        return (array_keys($node) === [0]) ? 'array' : 'object';
    }
}
