<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

/**
 * Definitions of the requests and responses of the Ship API operations that FedexShip implements (create shipment,
 * validate shipment, cancel shipment, create tag, cancel tag and retrieve async ship), generated from
 * schemas/ship.json and written for SchemaValidator
 */
final class FedexShipSchemas
{
    /**
     * Name of the definition of the Create Shipment request
     * @var string
     */
    const CREATE_REQUEST = 'Full_Schema_Ship';

    /**
     * Name of the definition of the Create Shipment response
     * @var string
     */
    const CREATE_RESPONSE = 'SHPCResponseVO_ShipShipment';

    /**
     * Name of the definition of the Validate Shipment request
     * @var string
     */
    const VALIDATE_REQUEST = 'FullSchema_VerifyShipment';

    /**
     * Name of the definition of the Validate Shipment response
     * @var string
     */
    const VALIDATE_RESPONSE = 'SHPCResponseVO_Validate';

    /**
     * Name of the definition of the Cancel Shipment request
     * @var string
     */
    const CANCEL_REQUEST = 'Full_Schema_Cancel_Shipment';

    /**
     * Name of the definition of the Cancel Shipment response
     * @var string
     */
    const CANCEL_RESPONSE = 'SHPCResponseVO_CancelShipment';

    /**
     * Name of the definition of the Create Tag request
     * @var string
     */
    const CREATE_TAG_REQUEST = 'Full_Schema_Create_Tag';

    /**
     * Name of the definition of the Create Tag response
     * @var string
     */
    const CREATE_TAG_RESPONSE = 'SHPCResponseVO_CreateTag';

    /**
     * Name of the definition of the Cancel Tag request
     * @var string
     */
    const CANCEL_TAG_REQUEST = 'FullSchema_CancelTag';

    /**
     * Name of the definition of the Cancel Tag response
     * @var string
     */
    const CANCEL_TAG_RESPONSE = 'SHPCResponseVO';

    /**
     * Name of the definition of the Retrieve Async Ship request
     * @var string
     */
    const RESULTS_REQUEST = 'FullSchema_getConfirmedShipmentAsyncResults';

    /**
     * Name of the definition of the Retrieve Async Ship response
     * @var string
     */
    const RESULTS_RESPONSE = 'SHPCResponseVO_GetOpenShipmentResults';

    /**
     * Get the definitions of all the requests and responses. The names of the definitions, and of their fields, are the
     * ones of schemas/ship.json, with these differences:
     *
     * - FullSchema-VerifyShipment, FullSchema-CancelTag and FullSchema-getConfirmedShipmentAsyncResults are named with an
     * underscore instead of the hyphen (the validator does not accept a hyphen in a name);
     * - what the file writes inline or as an overlay of another definition ("this definition, but these keys are
     * required") is a definition of its own, named after the definition and the field that contain it (for example
     * ClearanceItemDetail_address or BrokerDetail_broker);
     * - CreateTagRequestedShipment lists as required three keys that the file does not define anywhere (shipTimestamp,
     * shippingLabelType and specialServicesRequested): they are accepted as optional values of any type, and are not
     * checked. Its pickupDetail, which the file adds as an overlay, is required.
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['AccountNumber'] = '@PartyAccountNumber';

        $schemas['AdditionalLabelsDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['BROKER', 'CONSIGNEE', 'CUSTOMS', 'DESTINATION', 'DESTINATION_CONTROL_STATEMENT', 'FREIGHT_REFERENCE', 'MANIFEST', 'ORIGIN', 'RECIPIENT', 'SECOND_ADDRESS', 'SHIPPER']],
            'count?' => 'int'
        ];

        $schemas['AdditionalMeasures'] = [
            'quantity?' => 'number',
            'units?' => 'string'
        ];

        $schemas['Address'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
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

        $schemas['Address_RegulatoryReferences'] = [
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

        $schemas['Alert_3P'] = [
            'code?' => 'string',
            'message?' => 'string'
        ];

        $schemas['Alert_3PP'] = [
            'code?' => 'string',
            'message?' => 'string'
        ];

        $schemas['AncillaryFeeAndTax'] = [
            'type?' => ['@type' => 'string', '@enum' => ['CLEARANCE_ENTRY_FEE', 'GOODS_AND_SERVICES_TAX', 'HARMONIZED_SALES_TAX', 'OTHER']],
            'description?' => 'string',
            'amount?' => '@Money_1'
        ];

        $schemas['AsynchronousProcessingOptionType_1'] = ['@type' => 'string', '@enum' => ['SYNCHRONOUS_ONLY', 'ALLOW_ASYNCHRONOUS']];

        $schemas['BaseProcessOutputVO'] = '@CancelTagOuputVO';

        $schemas['BaseProcessOutputVO_CancelShipment'] = '@CancelShipmentOutputVO';

        $schemas['BaseProcessOutputVO_CreateTag'] = '@CreateTagOutputVO';

        $schemas['BaseProcessOutputVO_GetOpenShipmentResults'] = '@GetOpenShipmentResultsOutputVO';

        $schemas['BaseProcessOutputVO_ShipShipment'] = '@ShipShipmentOutputVO';

        $schemas['BaseProcessOutputVO_Validate'] = '@VerifyShipmentOutputVO';

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
            'rateType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'LIST', 'ACTUAL', 'CURRENT', 'CUSTOM']],
            'rateLevelType?' => ['@type' => 'string', '@enum' => ['BUNDLED_RATE', 'INDIVIDUAL_PACKAGE_RATE']],
            'chargeLevelType?' => ['@type' => 'string', '@enum' => ['CURRENT_PACKAGE', 'SUM_OF_PACKAGES']],
            'chargeType?' => ['@type' => 'string', '@enum' => ['COD_SURCHARGE', 'NET_CHARGE', 'NET_FREIGHT', 'TOTAL_CUSTOMER_CHARGE']]
        ];

        $schemas['CancelShipmentOutputVO'] = [
            'cancelledShipment?' => 'bool',
            'cancelledHistory?' => 'bool',
            'message?' => 'string',
            'alerts?' => ['@Alert']
        ];

        $schemas['CancelTagOuputVO'] = [
            'cancelledTag?' => 'bool',
            'successMessage?' => 'string'
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

        $schemas['CommercialInvoice'] = [
            'originatorName?' => 'string',
            'paymentTerms?' => 'string',
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
            'emailNotificationDetail?' => '@ShipEmailDispositionDetail'
        ];

        $schemas['CommercialInvoiceDetail'] = [
            'customerImageUsages?' => ['@CustomerImageUsage'],
            'documentFormat?' => '@ShippingDocumentFormat'
        ];

        $schemas['Commodity'] = [
            'clearanceItemDetail?' => ['@ClearanceItemDetail'],
            'unitPrice?' => '@Money',
            'additionalMeasures?' => ['@AdditionalMeasures'],
            'regulatoryDetails?' => ['@RegulatoryDetail'],
            'numberOfPieces?' => 'int',
            'quantity?' => 'int',
            'quantityUnits?' => 'string',
            'customsValue?' => '@Customs_Money',
            'countryOfManufacture?' => 'string',
            'cIMarksAndNumbers?' => 'string',
            'harmonizedCode?' => 'string',
            'description' => 'string',
            'name?' => 'string',
            'weight?' => '@Weight_4',
            'exportLicenseNumber?' => 'string',
            'exportLicenseExpirationDate?' => 'string',
            'partNumber?' => 'string',
            'purpose?' => ['@type' => 'string', '@enum' => ['BUSINESS', 'CONSUMER']],
            'usmcaDetail?' => '@UsmcaDetail'
        ];

        $schemas['Commodity_1'] = [
            'unitPrice?' => '@Money',
            'additionalMeasures?' => ['@AdditionalMeasures'],
            'numberOfPieces?' => 'int',
            'quantity?' => 'int',
            'quantityUnits?' => 'string',
            'customsValue?' => '@Customs_Money',
            'countryOfManufacture?' => 'string',
            'cIMarksAndNumbers?' => 'string',
            'harmonizedCode?' => 'string',
            'description' => 'string',
            'name?' => 'string',
            'weight?' => '@Weight_4',
            'exportLicenseNumber?' => 'string',
            'exportLicenseExpirationDate?' => 'string',
            'partNumber?' => 'string',
            'purpose?' => ['@type' => 'string', '@enum' => ['BUSINESS', 'CONSUMER']],
            'usmcaDetail?' => '@UsmcaDetail'
        ];

        $schemas['CompletedCreateShipmentDetail'] = [
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
            'shipmentRating?' => '@Create_ShipmentRating',
            'documentRequirements?' => '@DocumentRequirementsDetail',
            'exportComplianceStatement?' => 'string',
            'accessDetail?' => '@PendingShipmentAccessDetail'
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

        $schemas['CompletedTagDetail'] = '@CompletedTagDetail_2';

        $schemas['CompletedTagDetail_2'] = [
            'confirmationNumber' => 'string',
            'location' => 'string',
            'dispatchDate' => 'string'
        ];

        $schemas['Contact'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['ContactAndAddress'] = [
            'contact?' => '@Contact_1',
            'address?' => '@Address_1'
        ];

        $schemas['ContactAndAddress_1'] = [
            'contact?' => '@Contact_2',
            'address?' => '@Address_1'
        ];

        $schemas['ContactAndAddress_Verify'] = [
            'contact?' => '@Contact_verify',
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

        $schemas['Contact_RegulatoryReferences'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneExtension?' => 'string',
            'phoneNumber?' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['Contact_verify'] = [
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

        $schemas['CreateTagOutputVO'] = [
            'masterTrackingNumber?' => 'string',
            'serviceType?' => 'string',
            'shipTimestamp?' => 'string',
            'completedTagDetail?' => '@CompletedTagDetail',
            'alerts?' => ['@Alert']
        ];

        $schemas['CreateTagRequestedShipment'] = [
            'shipDatestamp?' => 'string',
            'totalDeclaredValue?' => '@Money',
            'shipper' => '@ShipperParty',
            'soldTo?' => '@SoldToParty',
            'recipients' => ['@RecipientsParty'],
            'recipientLocationNumber?' => 'string',
            'pickupType' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'serviceType' => 'string',
            'packagingType' => 'string',
            'totalWeight' => 'number',
            'origin?' => '@ContactAndAddress_1',
            'shippingChargesPayment' => '@Payment',
            'shipmentSpecialServices?' => '@ShipmentSpecialServicesRequested_tag',
            'emailNotificationDetail?' => '@ShipShipmentEMailNotificationDetail_tag',
            'expressFreightDetail?' => '@ExpressFreightDetail',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'customsClearanceDetail?' => '@CustomsClearanceDetail_tag',
            'smartPostInfoDetail?' => '@SmartPostInfoDetail',
            'blockInsightVisibility?' => 'bool',
            'labelSpecification' => '@LabelSpecification',
            'shippingDocumentSpecification?' => '@ShippingDocumentSpecification',
            'rateRequestType?' => [['@type' => 'string', '@enum' => ['LIST', 'NONE', 'PREFERRED', 'ACCOUNT', 'INCENTIVE', 'RETAIL']]],
            'preferredCurrency?' => 'string',
            'totalPackageCount?' => 'int',
            'masterTrackingId?' => '@MasterTrackingId',
            'requestedPackageLineItems' => ['@RequestedPackageLineItem'],
            'pickupDetail' => '@CreateTagRequestedShipment_pickupDetail',
            'shipTimestamp?' => 'mixed',
            'shippingLabelType?' => 'mixed',
            'specialServicesRequested?' => 'mixed'
        ];

        $schemas['Create_ShipmentRating'] = [
            'actualRateType?' => 'string',
            'shipmentRateDetails?' => ['@ShipmentRateDetail'],
            'totalNetTransportationAndPickupCharge?' => '@Money_1',
            'totalNetFedExTransportationAndPickupCharge?' => '@Money_1',
            'pickupRateDetail?' => '@Create_ShipmentRating_pickupRateDetail'
        ];

        $schemas['CurrencyExchangeRate'] = [
            'rate?' => 'number',
            'fromCurrency?' => 'string',
            'intoCurrency?' => 'string'
        ];

        $schemas['CustomerImageUsage'] = [
            'id?' => ['@type' => 'string', '@enum' => ['IMAGE_1', 'IMAGE_2', 'IMAGE_3', 'IMAGE_4', 'IMAGE_5']],
            'type?' => ['@type' => 'string', '@enum' => ['SIGNATURE', 'LETTER_HEAD']],
            'providedImageType?' => ['@type' => 'string', '@enum' => ['LETTER_HEAD', 'SIGNATURE']]
        ];

        $schemas['CustomerReference'] = [
            'customerReferenceType?' => ['@type' => 'string', '@enum' => ['CUSTOMER_REFERENCE', 'DEPARTMENT_NUMBER', 'INVOICE_NUMBER', 'P_O_NUMBER', 'INTRACOUNTRY_REGULATORY_REFERENCE', 'RMA_ASSOCIATION']],
            'value?' => 'string'
        ];

        $schemas['CustomerReference_1'] = [
            'customerReferenceType?' => ['@type' => 'string', '@enum' => ['CUSTOMER_REFERENCE', 'DEPARTMENT_NUMBER', 'INVOICE_NUMBER', 'P_O_NUMBER', 'INTRACOUNTRY_REGULATORY_REFERENCE', 'RMA_ASSOCIATION']],
            'value?' => 'string'
        ];

        $schemas['CustomerSpecifiedLabelDetail'] = [
            'maskedData?' => [['@type' => 'string', '@enum' => ['CUSTOMS_VALUE', 'SHIPPER_ACCOUNT_NUMBER', 'DIMENSIONS', 'DUTIES_AND_TAXES_PAYOR_ACCOUNT_NUMBER', 'INSURED_VALUE', 'SECONDARY_BARCODE', 'SHIPPER_INFORMATION', 'TERMS_AND_CONDITIONS', 'TOTAL_WEIGHT', 'TRANSPORTATION_CHARGES_PAYOR_ACCOUNT_NUMBER']]],
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

        $schemas['CustomsClearanceDetail_1'] = [
            'regulatoryControls?' => [['@type' => 'string', '@enum' => ['FOOD_OR_PERISHABLE', 'USMCA', 'NOT_APPLICABLE_FOR_LOW_VALUE_CUSTOMS_EXCEPTIONS', 'NOT_IN_FREE_CIRCULATION']]],
            'brokers?' => ['@BrokerDetail'],
            'commercialInvoice' => '@CommercialInvoice',
            'freightOnValue?' => ['@type' => 'string', '@enum' => ['CARRIER_RISK', 'OWN_RISK']],
            'dutiesPayment?' => '@Payment_1',
            'commodities' => ['@Commodity_1'],
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

        $schemas['CustomsClearanceDetail_tag'] = [
            'regulatoryControls?' => [['@type' => 'string', '@enum' => ['FOOD_OR_PERISHABLE', 'USMCA', 'NOT_APPLICABLE_FOR_LOW_VALUE_CUSTOMS_EXCEPTIONS', 'NOT_IN_FREE_CIRCULATION']]],
            'brokers?' => ['@BrokerDetail'],
            'commercialInvoice' => '@CommercialInvoice',
            'freightOnValue?' => ['@type' => 'string', '@enum' => ['CARRIER_RISK', 'OWN_RISK']],
            'dutiesPayment?' => '@Payment_1_tag',
            'commodities' => ['@Commodity_1'],
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

        $schemas['Customs_Money'] = [
            'amount?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['DangerousGoodsDetail'] = [
            'cargoAircraftOnly?' => 'bool',
            'regulation?' => ['@type' => 'string', '@enum' => ['ADR', 'DOT', 'IATA', 'ORMD']],
            'accessibility?' => ['@type' => 'string', '@enum' => ['ACCESSIBLE', 'INACCESSIBLE']],
            'options?' => [['@type' => 'string', '@enum' => ['HAZARDOUS_MATERIALS', 'BATTERY', 'ORM_D', 'REPORTABLE_QUANTITIES', 'SMALL_QUANTITY_EXCEPTION', 'LIMITED_QUANTITIES_COMMODITIES']]]
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

        $schemas['DisclaimMessageSet'] = [
            'disclaimCode?' => ['@type' => 'string', '@enum' => ['A', 'B'], '@max' => 1],
            'intendedUseCode?' => ['@type' => 'string', '@max' => 16],
            'intendedUseDescription?' => ['@type' => 'string', '@max' => 22]
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

        $schemas['EdtCommodityTax'] = [
            'harmonizedCode?' => 'string',
            'taxes?' => '@EdtTaxDetail_1',
            'total?' => '@Money_1'
        ];

        $schemas['EdtTaxDetail_1'] = [
            'taxType?' => ['@type' => 'string', '@enum' => ['ADDITIONAL_TAXES', 'CONSULAR_INVOICE_FEE', 'CUSTOMS_SURCHARGES', 'DUTY', 'EXCISE_TAX', 'FOREIGN_EXCHANGE_TAX', 'GENERAL_SALES_TAX', 'IMPORT_LICENSE_FEE', 'INTERNAL_ADDITIONAL_TAXES', 'INTERNAL_SENSITIVE_PRODUCTS_TAX', 'OTHER', 'SENSITIVE_PRODUCTS_TAX', 'STAMP_TAX', 'STATISTICAL_TAX', 'TRANSPORT_FACILITIES_TAX']],
            'taxcode?' => 'string',
            'effectiveDate?' => 'string',
            'name?' => 'string',
            'taxableValue?' => '@Money_1',
            'description?' => 'string',
            'formula?' => 'string',
            'amount?' => '@Money_1',
            'taxRates?' => ['@EdtTaxDetail_1_taxRates_item'],
            'appliedPreferentialTradeAgreement?' => '@EdtTaxDetail_1_appliedPreferentialTradeAgreement'
        ];

        $schemas['EmailLabelDetail'] = [
            'recipients?' => ['@EmailRecipient'],
            'message?' => 'string'
        ];

        $schemas['EmailNotificationRecipient'] = [
            'name?' => 'string',
            'emailNotificationRecipientType' => ['@type' => 'string', '@enum' => ['BROKER', 'OTHER', 'RECIPIENT', 'SHIPPER', 'THIRD_PARTY']],
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
            'bookingConfirmationNumber?' => 'string',
            'shippersLoadAndCount?' => 'int',
            'packingListEnclosed?' => 'bool'
        ];

        $schemas['FullSchema_CancelTag'] = [
            'accountNumber' => '@ShipmentAccountNumber',
            'serviceType' => 'string',
            'trackingNumber?' => 'string',
            'version?' => '@Version',
            'completedTagDetail' => '@CompletedTagDetail_2'
        ];

        $schemas['FullSchema_VerifyShipment'] = [
            'requestedShipment' => '@RequestedShipmentVerify',
            'accountNumber?' => '@ShipperAccountNumber',
            'version?' => '@Version'
        ];

        $schemas['FullSchema_getConfirmedShipmentAsyncResults'] = [
            'accountNumber' => '@AccountNumber',
            'jobId' => 'string',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_Cancel_Shipment'] = [
            'accountNumber' => '@ShipperAccountNumber',
            'emailShipment?' => 'bool',
            'senderCountryCode?' => 'string',
            'deletionControl?' => ['@type' => 'string', '@enum' => ['DELETE_ALL_PACKAGES']],
            'trackingNumber' => 'string',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_Create_Tag'] = [
            'requestedShipment' => '@CreateTagRequestedShipment',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_Ship'] = [
            'mergeLabelDocOption?' => ['@type' => 'string', '@enum' => ['NONE', 'LABELS_AND_DOCS', 'LABELS_ONLY']],
            'requestedShipment' => '@RequestedShipment_1',
            'labelResponseOptions' => '@LABELRESPONSEOPTIONS',
            'accountNumber' => '@ShipperAccountNumber',
            'shipAction?' => '@OpenShipmentAction',
            'processingOptionType?' => '@AsynchronousProcessingOptionType_1',
            'oneLabelAtATime?' => 'bool',
            'version?' => '@Version',
            'processingOptions?' => [['@type' => 'string', '@enum' => ['INCLUDE_PICKUPRATES']]]
        ];

        $schemas['GeneralAgencyAgreementDetail'] = [
            'documentFormat?' => '@ShippingDocumentFormat'
        ];

        $schemas['GetOpenShipmentResultsOutputVO'] = [
            'transactionShipments?' => ['@TransactionShipmentOutputVO'],
            'alerts?' => ['@Alert']
        ];

        $schemas['HazardousCommodityOptionDetail'] = [
            'labelTextOption?' => ['@type' => 'string', '@enum' => ['APPEND', 'OVERRIDE', 'STANDARD']],
            'customerSuppliedLabelText?' => 'string'
        ];

        $schemas['HazardousCommodityQuantityDetail'] = [
            'quantityType' => ['@type' => 'string', '@enum' => ['GROSS', 'NET']],
            'amount' => 'number',
            'units?' => 'string',
            'value?' => 'number'
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
            'type' => ['@type' => 'string', '@enum' => ['DEA_036', 'DEA_236', 'DSP_05', 'DSP_61', 'DSP_73', 'DSP_85', 'DSP_LICENSE_AGREEMENT', 'WAREHOUSE_WITHDRAWAL', 'FROM_FOREIGN_TRADE_ZONE', 'DEA_486', 'DSP_94']]
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
            'contentType?' => ['@type' => 'string', '@enum' => ['LABEL', 'BILL_OF_LADING', 'GAA_FORM', 'HAZMAT_LABEL', 'END_OF_DAY_HAZMAT_REPORT', 'MANIFEST_REPORT', 'MULTIWEIGHT_REPORT', 'MERGED_LABEL_DOCUMENTS', 'AUXILIARY', 'RETURN_INSTRUCTIONS', 'ACCEPTANCE_LABEL', 'COMMERCIAL_INVOICE', 'PROFORMA_INVOICE', 'USMCA_CERTIFICATION_OF_ORIGIN', 'CERTIFICATE_OF_ORIGIN', 'MERGED_LABELS_ONLY', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']],
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
            'labelStockType' => ['@type' => 'string', '@enum' => ['PAPER_4X6', 'STOCK_4X675', 'PAPER_4X675', 'PAPER_4X8', 'PAPER_4X9', 'PAPER_7X475', 'PAPER_85X11_BOTTOM_HALF_LABEL', 'PAPER_85X11_TOP_HALF_LABEL', 'PAPER_LETTER', 'STOCK_4X675_LEADING_DOC_TAB', 'STOCK_4X8', 'STOCK_4X9_LEADING_DOC_TAB', 'STOCK_4X6', 'STOCK_4X675_TRAILING_DOC_TAB', 'STOCK_4X9_TRAILING_DOC_TAB', 'STOCK_4X9', 'STOCK_4X85_TRAILING_DOC_TAB', 'STOCK_4X105_TRAILING_DOC_TAB']],
            'labelRotation?' => ['@type' => 'string', '@enum' => ['LEFT', 'RIGHT', 'UPSIDE_DOWN', 'NONE']],
            'imageType' => ['@type' => 'string', '@enum' => ['ZPLII', 'EPL2', 'PDF', 'PNG']],
            'labelPrintingOrientation?' => ['@type' => 'string', '@enum' => ['BOTTOM_EDGE_OF_TEXT_FIRST', 'TOP_EDGE_OF_TEXT_FIRST']],
            'returnedDispositionDetail?' => 'string',
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

        $schemas['Money'] = [
            'amount?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['Money_1'] = [
            'currency?' => 'string',
            'value' => ['@type' => 'string', '@enum' => ['CUSTOMS_VALUE', 'INSURED_VALUE']]
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

        $schemas['OpenShipmentAction'] = ['@type' => 'string', '@enum' => ['CONFIRM', 'TRANSFER']];

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
            'minimumChargeType?' => 'string',
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
            'dryIceWeight?' => '@Weight_3',
            'standaloneBatteryDetails?' => ['@StandaloneBatteryDetails']
        ];

        $schemas['PartyAccountNumber'] = [
            'value?' => 'string'
        ];

        $schemas['PartyAccountNumber_1'] = [
            'value?' => 'string'
        ];

        $schemas['PartyAccountNumber_2'] = [
            'value?' => 'string'
        ];

        $schemas['PartyAccountNumber_tag'] = [
            'value?' => 'string'
        ];

        $schemas['PartyAddress'] = [
            'streetLines?' => ['string'],
            'city?' => ['@type' => 'string', '@max' => 35],
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
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

        $schemas['PartyAddress_2'] = [
            'streetLines' => ['string'],
            'city' => ['@type' => 'string', '@max' => 35],
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
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

        $schemas['Party_1'] = [
            'address?' => '@PartyAddress',
            'contact' => '@PartyContact',
            'accountNumber?' => '@PartyAccountNumber',
            'tins?' => ['@TaxpayerIdentification']
        ];

        $schemas['Party_2'] = [
            'address?' => '@Address_1',
            'contact?' => '@Contact_1',
            'accountNumber?' => '@PartyAccountNumber_1',
            'tins?' => ['@TaxpayerIdentification']
        ];

        $schemas['Party_2_tag'] = [
            'address?' => '@Address_1',
            'contact?' => '@Contact_1',
            'accountNumber?' => '@PartyAccountNumber_tag',
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

        $schemas['Payment_1_tag'] = [
            'payor?' => '@Payor_1_tag',
            'billingDetails?' => '@BillingDetails',
            'paymentType?' => ['@type' => 'string', '@enum' => ['SENDER', 'RECIPIENT', 'THIRD_PARTY', 'COLLECT']]
        ];

        $schemas['Payor'] = [
            'responsibleParty' => '@ResponsiblePartyParty'
        ];

        $schemas['Payor_1'] = [
            'responsibleParty?' => '@Party_2'
        ];

        $schemas['Payor_1_tag'] = [
            'responsibleParty?' => '@Party_2_tag'
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

        $schemas['PickupDetail'] = [
            'readyPickupDateTime?' => 'string',
            'latestPickupDateTime?' => 'string',
            'courierInstructions?' => 'string',
            'requestType?' => '@PickupRequestType',
            'requestSource?' => '@PickupRequestSourceType'
        ];

        $schemas['PickupRequestSourceType'] = ['@type' => 'string', '@enum' => ['AUTOMATION', 'CUSTOMER_SERVICE']];

        $schemas['PickupRequestType'] = ['@type' => 'string', '@enum' => ['FUTURE_DAY', 'SAME_DAY']];

        $schemas['PieceResponse'] = [
            'netChargeAmount?' => 'number',
            'packageDocuments?' => ['@LabelResponseVO'],
            'acceptanceTrackingNumber?' => 'string',
            'serviceCategory?' => ['@type' => 'string', '@enum' => ['EXPRESS', 'GROUND', 'EXPRESS_FREIGHT', 'FREIGHT', 'SMARTPOST', 'EXPRESS_PARCEL', 'NULL']],
            'listCustomerTotalCharge?' => 'string',
            'deliveryTimestamp?' => 'string',
            'trackingIdType?' => 'string',
            'additionalChargesDiscount?' => 'number',
            'listRateAmount?' => 'number',
            'baseRateAmount?' => 'number',
            'packageSequenceNumber?' => 'int',
            'netDiscountAmount?' => 'number',
            'codCollectionAmount?' => 'number',
            'masterTrackingNumber?' => 'string',
            'acceptanceType?' => 'string',
            'trackingNumber?' => 'string',
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

        $schemas['RateDiscount_2'] = [
            'rateDiscountType?' => ['@type' => 'string', '@enum' => ['BONUS', 'COUPON', 'EARNED', 'INCENTIVE', 'OTHER', 'VOLUME']],
            'description?' => 'string',
            'amount?' => '@Money_1',
            'percent?' => 'number'
        ];

        $schemas['Rebate'] = [
            'rebateType?' => ['@type' => 'string', '@enum' => ['BONUS', 'EARNED', 'OTHER']],
            'description?' => 'string',
            'amount?' => '@Money_1',
            'percent?' => 'number'
        ];

        $schemas['RecipientCustomsId'] = [
            'type?' => ['@type' => 'string', '@enum' => ['COMPANY', 'INDIVIDUAL', 'PASSPORT']],
            'value?' => 'string'
        ];

        $schemas['RecipientsParty'] = [
            'address' => '@PartyAddress_2',
            'contact' => '@PartyContact',
            'tins?' => ['@TaxpayerIdentification'],
            'deliveryInstructions?' => 'string'
        ];

        $schemas['RecommendedDocumentSpecification'] = [
            'types' => [['@type' => 'string', '@enum' => ['ANTIQUE_STATEMENT_EUROPEAN_UNION', 'ANTIQUE_STATEMENT_UNITED_STATES', 'ASSEMBLER_DECLARATION', 'BEARING_WORKSHEET', 'CERTIFICATE_OF_SHIPMENTS_TO_SYRIA', 'COMMERCIAL_INVOICE_FOR_THE_CARIBBEAN_COMMON_MARKET', 'CONIFEROUS_SOLID_WOOD_PACKAGING_MATERIAL_TO_THE_PEOPLES_REPUBLIC_OF_CHINA', 'DECLARATION_FOR_FREE_ENTRY_OF_RETURNED_AMERICAN_PRODUCTS', 'DECLARATION_OF_BIOLOGICAL_STANDARDS', 'DECLARATION_OF_IMPORTED_ELECTRONIC_PRODUCTS_SUBJECT_TO_RADIATION_CONTROL_STANDARD', 'ELECTRONIC_INTEGRATED_CIRCUIT_WORKSHEET', 'FILM_AND_VIDEO_CERTIFICATE', 'INTERIM_FOOTWEAR_INVOICE', 'USMCA_CERTIFICATION_OF_ORIGIN_CANADA_ENGLISH', 'USMCA_CERTIFICATION_OF_ORIGIN_CANADA_FRENCH', 'USMCA_CERTIFICATION_OF_ORIGIN_SPANISH', 'USMCA_CERTIFICATION_OF_ORIGIN_UNITED_STATES', 'PACKING_LIST', 'PRINTED_CIRCUIT_BOARD_WORKSHEET', 'REPAIRED_WATCH_BREAKOUT_WORKSHEET', 'STATEMENT_REGARDING_THE_IMPORT_OF_RADIO_FREQUENCY_DEVICES', 'TOXIC_SUBSTANCES_CONTROL_ACT', 'UNITED_STATES_CARIBBEAN_BASIN_TRADE_PARTNERSHIP_ACT_CERTIFICATE_OF_ORIGIN_TEXTILES', 'UNITED_STATES_CARIBBEAN_BASIN_TRADE_PARTNERSHIP_ACT_CERTIFICATE_OF_ORIGIN_NON_TEXTILES', 'UNITED_STATES_NEW_WATCH_WORKSHEET', 'UNITED_STATES_WATCH_REPAIR_DECLARATION']]]
        ];

        $schemas['ReferenceMessageSet'] = [
            'productVersion?' => ['@type' => 'string', '@max' => 29],
            'certifierId?' => ['@type' => 'string', '@max' => 33],
            'registryProductId?' => ['@type' => 'string', '@max' => 19]
        ];

        $schemas['RegulatoryAdvisoryDetail'] = [
            'prohibitions?' => ['@RegulatoryProhibition']
        ];

        $schemas['RegulatoryDetail'] = [
            'regulationCode' => ['@type' => 'string', '@enum' => ['CPSC', 'EU_DE_MINIMIS']],
            'productId' => ['@type' => 'string', '@max' => 70],
            'productIdType' => ['@type' => 'string', '@enum' => ['SKU', 'MODEL_NUMBER', 'SERIAL_NUMBER', 'ALTERNATE_ID', 'GTIN', 'UPC']],
            'details?' => '@RegulatoryDetailInfo'
        ];

        $schemas['RegulatoryDetailInfo'] = [
            'disclaimMessageSet?' => '@DisclaimMessageSet',
            'referenceMessageSet?' => '@ReferenceMessageSet',
            'standardManufacturerProductId?' => ['@type' => 'string', '@max' => 70],
            'nonStandardManufacturerProductId?' => ['@type' => 'string', '@max' => 70],
            'merchantProductId?' => ['@type' => 'string', '@max' => 70]
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
            'sequenceNumber?' => 'int',
            'subPackagingType?' => 'string',
            'customerReferences?' => ['@CustomerReference_1'],
            'declaredValue?' => '@Money',
            'weight' => '@Weight',
            'dimensions?' => '@Dimensions',
            'groupPackageCount?' => 'int',
            'itemDescriptionForClearance?' => 'string',
            'contentRecord?' => ['@ContentRecord'],
            'itemDescription?' => 'string',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'packageSpecialServices?' => '@PackageSpecialServicesRequested'
        ];

        $schemas['RequestedShipmentVerify'] = [
            'shipDatestamp?' => 'string',
            'pickupType' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'serviceType' => 'string',
            'packagingType' => 'string',
            'totalWeight' => 'int',
            'shipper' => '@ShipperParty',
            'recipients' => ['@RecipientsParty'],
            'origin?' => '@ContactAndAddress_Verify',
            'shippingChargesPayment' => '@Payment',
            'shipmentSpecialServices?' => '@RequestedShipmentVerify_shipmentSpecialServices',
            'emailNotificationDetail?' => '@EMailNotificationDetail',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'customsClearanceDetail?' => '@CustomsClearanceDetail_1',
            'smartPostInfoDetail?' => '@SmartPostInfoDetail',
            'expressFreightDetail?' => '@ExpressFreightDetail',
            'blockInsightVisibility?' => 'bool',
            'labelSpecification' => '@LabelSpecification',
            'shippingDocumentSpecification?' => '@ShippingDocumentSpecification',
            'rateRequestType?' => [['@type' => 'string', '@enum' => ['LIST', 'NONE', 'PREFERRED', 'ACCOUNT', 'INCENTIVE', 'RETAIL']]],
            'preferredCurrency?' => 'string',
            'requestedPackageLineItems' => ['@RequestedPackageLineItem']
        ];

        $schemas['RequestedShipmentVerify_shipmentSpecialServices'] = [
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

        $schemas['RequestedShipment_1'] = [
            'shipDatestamp?' => 'string',
            'totalDeclaredValue?' => '@Money',
            'shipper' => '@ShipperParty',
            'regulatoryReferences?' => '@regulatoryReferences',
            'recipients' => ['@RecipientsParty'],
            'pickupDetail?' => '@PickupDetail',
            'soldTo?' => '@SoldToParty',
            'recipientLocationNumber?' => 'string',
            'pickupType' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'serviceType' => 'string',
            'packagingType' => 'string',
            'totalWeight' => 'number',
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
            'variationOptions?' => ['@VariationOptions'],
            'requestedPackageLineItems' => ['@RequestedPackageLineItem']
        ];

        $schemas['ResponsiblePartyParty'] = [
            'address?' => '@PartyAddress',
            'contact?' => '@PartyContact',
            'accountNumber' => '@PartyAccountNumber'
        ];

        $schemas['RetrieveDateRange'] = [
            'begins?' => 'string',
            'ends?' => 'string'
        ];

        $schemas['ReturnAssociationDetail'] = [
            'shipDatestamp?' => 'string',
            'trackingNumber' => 'string'
        ];

        $schemas['ReturnEmailDetail'] = [
            'merchantPhoneNumber' => 'string',
            'allowedSpecialService' => [['@type' => 'string', '@enum' => ['SATURDAY_DELIVERY', 'SATURDAY_PICKUP']]]
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

        $schemas['SHPCResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO'
        ];

        $schemas['SHPCResponseVO_CancelShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_CancelShipment'
        ];

        $schemas['SHPCResponseVO_CreateTag'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_CreateTag'
        ];

        $schemas['SHPCResponseVO_GetOpenShipmentResults'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_GetOpenShipmentResults'
        ];

        $schemas['SHPCResponseVO_ShipShipment'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_ShipShipment'
        ];

        $schemas['SHPCResponseVO_Validate'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_Validate'
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

        $schemas['ShipEmailDispositionDetail'] = [
            'emailAddress?' => 'string',
            'type?' => 'string',
            'recipientType?' => 'string'
        ];

        $schemas['ShipShipmentEMailNotificationDetail'] = [
            'aggregationType?' => ['@type' => 'string', '@enum' => ['PER_PACKAGE', 'PER_SHIPMENT']],
            'emailNotificationRecipients?' => ['@ShipShipmentEmailNotificationRecipient'],
            'personalMessage?' => 'string'
        ];

        $schemas['ShipShipmentEMailNotificationDetail_tag'] = [
            'aggregationType?' => ['@type' => 'string', '@enum' => ['PER_PACKAGE', 'PER_SHIPMENT']],
            'emailNotificationRecipients?' => ['@ShipShipmentEmailNotificationRecipient_tag'],
            'personalMessage?' => 'string'
        ];

        $schemas['ShipShipmentEmailNotificationRecipient'] = [
            'name?' => 'string',
            'emailNotificationRecipientType' => ['@type' => 'string', '@enum' => ['BROKER', 'OTHER', 'RECIPIENT', 'SHIPPER', 'THIRD_PARTY']],
            'emailAddress' => 'string',
            'notificationFormatType?' => ['@type' => 'string', '@enum' => ['HTML', 'TEXT']],
            'notificationType?' => ['@type' => 'string', '@enum' => ['EMAIL']],
            'locale?' => 'string',
            'notificationEventType?' => [['@type' => 'string', '@enum' => ['ON_DELIVERY', 'ON_EXCEPTION', 'ON_SHIPMENT', 'ON_TENDER', 'ON_ESTIMATED_DELIVERY', 'ON_PICKUP', 'ON_PICKUP_DRIVER_ARRIVED', 'ON_PICKUP_DRIVER_ASSIGNED', 'ON_PICKUP_DRIVER_DEPARTED', 'ON_PICKUP_DRIVER_EN_ROUTE']]]
        ];

        $schemas['ShipShipmentEmailNotificationRecipient_tag'] = [
            'name?' => 'string',
            'emailNotificationRecipientType' => ['@type' => 'string', '@enum' => ['BROKER', 'OTHER', 'RECIPIENT', 'SHIPPER', 'THIRD_PARTY']],
            'emailAddress' => 'string',
            'notificationFormatType?' => ['@type' => 'string', '@enum' => ['HTML', 'TEXT']],
            'notificationType?' => ['@type' => 'string', '@enum' => ['EMAIL']],
            'locale?' => 'string',
            'notificationEventType?' => [['@type' => 'string', '@enum' => ['ON_DELIVERY', 'ON_EXCEPTION', 'ON_SHIPMENT', 'ON_TENDER', 'ON_ESTIMATED_DELIVERY', 'ON_PICKUP_DRIVER_ARRIVED', 'ON_PICKUP_DRIVER_ASSIGNED', 'ON_PICKUP_DRIVER_DEPARTED', 'ON_PICKUP_DRIVER_EN_ROUTE']]]
        ];

        $schemas['ShipShipmentOutputVO'] = [
            'transactionShipments?' => ['@TransactionCreateShipmentOutputVO'],
            'alerts?' => ['@Alert'],
            'jobId?' => 'string'
        ];

        $schemas['ShipmentAccountNumber'] = '@PartyAccountNumber_2';

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
            'totalWeight?' => '@Weight',
            'packageCount' => 'int',
            'processingOptions?' => '@ShipmentDryIceProcessingOptionsRequested'
        ];

        $schemas['ShipmentDryIceDetail_1'] = [
            'totalWeight?' => '@Weight_1',
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

        $schemas['ShipmentSpecialServicesRequested_tag'] = [
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

        $schemas['ShipperAccountNumber'] = [
            'value' => 'string'
        ];

        $schemas['ShipperParty'] = [
            'address' => '@PartyAddress_2',
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
            'contact?' => '@PartyContact',
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

        $schemas['Surcharge'] = [
            'amount?' => 'number',
            'surchargeType?' => 'string',
            'level?' => 'string',
            'description?' => 'string'
        ];

        $schemas['Surcharge_2'] = [
            'surchargeType?' => ['@type' => 'string', '@enum' => ['ACCOUNT_NUMBER_PROCESSING_FEE', 'ADDITIONAL_HANDLING', 'ADDRESS_CORRECTION', 'ANCILLARY_FEE', 'APPOINTMENT_DELIVERY', 'BLIND_SHIPMENT', 'BROKER_SELECT_OPTION', 'CANADIAN_DESTINATION', 'CHARGEABLE_PALLET_WEIGHT', 'COD', 'CUT_FLOWERS', 'DANGEROUS_GOODS', 'DELIVERY_AREA', 'DELIVERY_CONFIRMATION', 'DELIVERY_ON_INVOICE_ACCEPTANCE', 'DEMAND', 'DEMAND_ADDITIONAL_HANDLING', 'DEMAND_OVERSIZE', 'DEMAND_RESIDENTIAL_DELIVERY', 'DETENTION', 'DOCUMENTATION_FEE', 'DRY_ICE', 'EMAIL_LABEL', 'ENHANCED_SECURITY', 'EUROPE_FIRST', 'EXCESS_VALUE', 'EXCLUSIVE_USE', 'EXHIBITION', 'EXPEDITED', 'EXPORT', 'EXTRA_LABOR', 'EXTRA_SURFACE_HANDLING_CHARGE', 'EXTREME_LENGTH', 'FEDEX_INTRACOUNTRY_FEES', 'FEDEX_TAG', 'FICE', 'FLATBED', 'FREIGHT_DIRECT_BASIC_BY_APPOINTMENT_DELIVERY', 'FREIGHT_DIRECT_BASIC_BY_APPOINTMENT_PICKUP', 'FREIGHT_DIRECT_BASIC_DELIVERY', 'FREIGHT_DIRECT_BASIC_PICKUP', 'FREIGHT_DIRECT_PREMIUM_DELIVERY', 'FREIGHT_DIRECT_PREMIUM_PICKUP', 'FREIGHT_DIRECT_STANDARD_DELIVERY', 'FREIGHT_DIRECT_STANDARD_PICKUP', 'FREIGHT_GUARANTEE', 'FREIGHT_ON_VALUE', 'FREIGHT_TO_COLLECT', 'FUEL', 'HIGH_COST_SERVICE_AREA_DESTINATION', 'HIGH_COST_SERVICE_AREA_ORIGIN', 'HIGH_DENSITY', 'HOLD_AT_LOCATION', 'HOLIDAY_DELIVERY', 'HOLIDAY_GUARANTEE', 'HOME_DELIVERY_APPOINTMENT', 'HOME_DELIVERY_DATE_CERTAIN', 'HOME_DELIVERY_EVENING', 'INSIDE_DELIVERY', 'INSIDE_PICKUP', 'INSURED_VALUE', 'INTERHAWAII', 'LIFTGATE_DELIVERY', 'LIFTGATE_PICKUP', 'LIMITED_ACCESS_DELIVERY', 'LIMITED_ACCESS_PICKUP', 'MARKING_OR_TAGGING', 'METRO_DELIVERY', 'METRO_PICKUP', 'MONITORING_AND_INTERVENTION', 'NON_BUSINESS_TIME', 'NON_MACHINABLE', 'OFFSHORE', 'ON_CALL_PICKUP', 'ON_DEMAND_CARE', 'OTHER', 'OUT_OF_DELIVERY_AREA', 'OUT_OF_PICKUP_AREA', 'OVER_DIMENSION', 'OVER_LENGTH', 'OVERSIZE', 'OVERWEIGHT', 'PALLET_SHRINKWRAP', 'PALLETS_PROVIDED', 'PEAK', 'PEAK_ADDITIONAL_HANDLING', 'PEAK_OVERSIZE', 'PEAK_RESIDENTIAL_DELIVERY', 'PERMIT', 'PIECE_COUNT_VERIFICATION', 'PORT', 'PRE_DELIVERY_NOTIFICATION', 'PRIORITY_ALERT', 'PROTECTION_FROM_FREEZING', 'REGIONAL_MALL_DELIVERY', 'REGIONAL_MALL_PICKUP', 'REROUTE', 'RESCHEDULE', 'RESIDENTIAL_DELIVERY', 'RESIDENTIAL_PICKUP', 'RETURN_LABEL', 'SATURDAY_DELIVERY', 'SATURDAY_PICKUP', 'SHIPMENT_ASSEMBLY', 'SIGNATURE_OPTION', 'SINGLE_PIECE', 'SORT_AND_SEGREGATE', 'SPECIAL_DELIVERY', 'SPECIAL_EQUIPMENT', 'STORAGE', 'SUNDAY_DELIVERY', 'TARP', 'THIRD_PARTY_BILLING', 'THIRD_PARTY_CONSIGNEE', 'TRANSMART_SERVICE_FEE', 'USPS', 'WEIGHING']],
            'level?' => ['@type' => 'string', '@enum' => ['PACKAGE', 'SHIPMENT']],
            'description?' => 'string',
            'amount?' => '@Money_1'
        ];

        $schemas['Tax'] = [
            'amount?' => 'number',
            'level?' => 'string',
            'description?' => 'string',
            'type?' => 'string'
        ];

        $schemas['Tax_2'] = [
            'taxType?' => ['@type' => 'string', '@enum' => ['EXPORT', 'GST', 'HST', 'INTRACOUNTRY', 'OTHER', 'PST', 'SST', 'VAT']],
            'description?' => 'string',
            'amount?' => '@Money_1'
        ];

        $schemas['TaxpayerIdentification'] = [
            'number' => 'string',
            'tinType' => ['@type' => 'string', '@enum' => ['PERSONAL_NATIONAL', 'PERSONAL_STATE', 'FEDERAL', 'BUSINESS_NATIONAL', 'BUSINESS_STATE', 'BUSINESS_UNION']],
            'usage?' => 'string',
            'effectiveDate?' => 'string',
            'expirationDate?' => 'string'
        ];

        $schemas['TrackingId'] = [
            'formId?' => 'string',
            'trackingIdType?' => 'string',
            'uspsApplicationId?' => 'string',
            'trackingNumber?' => 'string'
        ];

        $schemas['TransactionCreateShipmentOutputVO'] = [
            'serviceType?' => 'string',
            'shipDatestamp?' => 'string',
            'serviceCategory?' => 'string',
            'shipmentDocuments?' => ['@LabelResponseVO'],
            'pieceResponses?' => ['@PieceResponse'],
            'serviceName?' => 'string',
            'alerts?' => ['@Alert_3P|@Alert_3PP'],
            'completedShipmentDetail?' => '@CompletedCreateShipmentDetail',
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
            'alerts?' => ['@Alert_3P|@Alert_3PP'],
            'completedShipmentDetail?' => '@CompletedShipmentDetail',
            'shipmentAdvisoryDetails?' => '@ShipmentAdvisoryDetails',
            'masterTrackingNumber?' => 'string'
        ];

        $schemas['UploadDocumentReferenceDetail'] = [
            'documentType?' => ['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'NET_RATE_SHEET', 'COMMERCIAL_INVOICE', 'ETD_LABEL', 'USMCA_CERTIFICATION_OF_ORIGIN', 'OTHER', 'PRO_FORMA_INVOICE', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']],
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
            'options?' => '@HazardousCommodityOptionDetail',
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
            'QValue?' => 'number',
            'hazardousCommodities?' => ['@ValidatedHazardousCommodityContent']
        ];

        $schemas['VariableHandlingChargeDetail'] = [
            'rateType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'ACTUAL', 'CURRENT', 'CUSTOM', 'LIST', 'INCENTIVE', 'PREFERRED', 'PREFERRED_INCENTIVE', 'PREFERRED_CURRENCY']],
            'percentValue?' => 'number',
            'rateLevelType?' => ['@type' => 'string', '@enum' => ['BUNDLED_RATE', 'INDIVIDUAL_PACKAGE_RATE']],
            'fixedValue?' => '@VariableHandlingChargeDetail_fixedValue',
            'rateElementBasis?' => ['@type' => 'string', '@enum' => ['NET_CHARGE', 'NET_FREIGHT', 'BASE_CHARGE', 'NET_CHARGE_EXCLUDING_TAXES']]
        ];

        $schemas['VariableHandlingCharges_1'] = [
            'variableHandlingCharge?' => '@Money_1',
            'fixedVariableHandlingCharge?' => '@Money_1',
            'percentVariableHandlingCharge?' => '@Money_1',
            'totalCustomerCharge?' => '@Money_1'
        ];

        $schemas['VariationOptions'] = [
            'id?' => 'string',
            'values?' => ['string']
        ];

        $schemas['VerifyShipmentOutputVO'] = [
            'alerts?' => ['@Alert']
        ];

        $schemas['Version'] = [
            'major?' => 'int',
            'minor?' => 'int',
            'patch?' => 'int'
        ];

        $schemas['Weight'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => 'number'
        ];

        $schemas['Weight_1'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'number'
        ];

        $schemas['Weight_3'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => 'number'
        ];

        $schemas['Weight_4'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => 'number'
        ];

        $schemas['regulatoryReferences'] = [
            'regulatoryID?' => ['@type' => 'string', '@enum' => ['IOSS']],
            'responsibleParty?' => ['@type' => 'string', '@enum' => ['SHIPPER']],
            'identifier?' => 'string',
            'registeredParty?' => '@regulatoryReferences_registeredParty'
        ];

        $schemas['regulatoryReferences_registeredParty'] = [
            'address?' => '@Address_RegulatoryReferences',
            'contact?' => '@Contact_RegulatoryReferences'
        ];

        $schemas['BrokerDetail_broker'] = [
            'address' => '@PartyAddress',
            'contact' => '@PartyContact',
            'accountNumber?' => '@PartyAccountNumber',
            'tins?' => ['@TaxpayerIdentification']
        ];

        $schemas['ClearanceItemDetail_contact'] = [
            'companyName?' => 'string'
        ];

        $schemas['ClearanceItemDetail_address'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['CreateTagRequestedShipment_pickupDetail'] = [
            'readyPickupDateTime' => 'string',
            'latestPickupDateTime' => 'string'
        ];

        $schemas['Create_ShipmentRating_pickupRateDetail'] = [
            'rateType?' => ['@type' => 'string', '@enum' => ['INCENTIVE', 'NEGOTIATED', 'PAYOR_ACCOUNT_PACKAGE', 'PAYOR_ACCOUNT_SHIPMENT', 'PAYOR_CUSTOM_PACKAGE', 'PAYOR_CUSTOM_SHIPMENT', 'PAYOR_LIST_PACKAGE', 'PAYOR_LIST_SHIPMENT', 'PAYOR_RETAIL_PACKAGE', 'PAYOR_RETAIL_SHIPMENT', 'PREFERRED_ACCOUNT_PACKAGE', 'PREFERRED_ACCOUNT_SHIPMENT', 'PREFERRED_CUSTOM_PACKAGE', 'PREFERRED_CUSTOM_SHIPMENT', 'PREFERRED_INCENTIVE', 'PREFERRED_LIST_PACKAGE', 'PREFERRED_LIST_SHIPMENT', 'PREFERRED_NEGOTIATED', 'PREFERRED_RETAIL_PACKAGE', 'PREFERRED_RETAIL_SHIPMENT', 'RATED_ACCOUNT_PACKAGE', 'RATED_ACCOUNT_SHIPMENT', 'RATED_CUSTOM_PACKAGE', 'RATED_CUSTOM_SHIPMENT', 'RATED_LIST_PACKAGE', 'RATED_LIST_SHIPMENT', 'RATED_RETAIL_PACKAGE', 'RATED_RETAIL_SHIPMENT', 'UNKNOWN']],
            'rateScale?' => 'string',
            'rateZone?' => 'string',
            'ratingBasis?' => ['@type' => 'string', '@enum' => ['SHIPMENT_WEIGHT_BASED', 'FLAT_RATE_PER_PAK', 'PACK_WEIGHT_BASED', 'UNKNOWN']],
            'pricingCode?' => ['@type' => 'string', '@enum' => ['ACTUAL', 'ALTERNATE', 'BASE', 'HUNDREDWEIGHT', 'HUNDREDWEIGHT_ALTERNATE', 'INTERNATIONAL_DISTRIBUTION', 'INTERNATIONAL_ECONOMY_SERVICE', 'LTL_FREIGHT', 'PACKAGE', 'SHIPMENT', 'SHIPMENT_FIVE_POUND_OPTIONAL', 'SHIPMENT_OPTIONAL', 'SPECIAL', 'UNKNOWN']],
            'minimumChargeType?' => ['@type' => 'string', '@enum' => ['CUSTOMER', 'CUSTOMER_FREIGHT_WEIGHT', 'EARNED_DISCOUNT', 'MIXED', 'RATE_SCALE', 'UNKNOWN']],
            'currencyExchangeRate?' => '@CurrencyExchangeRate',
            'specialRatingApplied?' => [['@type' => 'string', '@enum' => ['FEDEX_ONE_RATE', 'FIXED_FUEL_SURCHARGE', 'IMPORT_PRICING']]],
            'fuelSurchargePercent?' => 'number',
            'pickupBaseChargeDescription?' => ['@type' => 'string', '@enum' => ['Regularly Scheduled Pickup Mon-Fri', 'Automated Pickup Mon-Fri', 'Same Day OC Pickup Mon-Fri (CS)', 'Same Day OC Pickup Mon-Fri (Online)', 'Fut. Day OC Pickup Mon-Fri (CS)', 'Fut. Day OC Pickup Mon-Fri (Online)', 'Regularly Scheduled Pickup Sat', 'Automated Pickup Sat', 'Same Day OC Pickup Sat (CS)', 'Same Day OC Pickup Sat (Online)', 'Fut. Day OC Pickup Sat (CS)', 'Fut. Day OC Pickup Sat (Online)', 'Regularly Scheduled Pickup Sun', 'Automated Pickup Sun', 'Same Day OC Pickup Sun (CS)', 'Same Day OC Pickup Sun (Online)', 'Fut. Day OC Pickup Sun (CS)', 'Fut. Day OC Pickup Sun (Online)', 'Residential On-Call Pickup Surcharge', 'Pickup Area Surcharge', 'Extended Pickup Area Surcharge', 'Remote Pickup Area Surcharge', 'Fuel Surcharge']],
            'totalBaseCharge?' => '@Money_1',
            'totalFreightDiscounts?' => '@Money_1',
            'totalNetFreight?' => '@Money_1',
            'totalSurcharges?' => '@Money_1',
            'totalNetFedExCharge?' => '@Money_1',
            'totalTaxes?' => '@Money_1',
            'totalNetCharge?' => '@Money_1',
            'totalRebates?' => '@Money_1',
            'totalDutiesAndTaxes?' => '@Money_1',
            'totalAncillaryFeesAndTaxes?' => '@Money_1',
            'totalDutiesTaxesAndFees?' => '@Money_1',
            'totalNetChargeWithDutiesAndTaxes?' => '@Money_1',
            'freightDiscounts?' => ['@RateDiscount_2'],
            'rebates?' => ['@Rebate'],
            'surcharges?' => ['@Surcharge_2'],
            'taxes?' => ['@Tax_2'],
            'dutiesAndTaxes?' => ['@EdtCommodityTax'],
            'ancillaryFeesAndTaxes?' => ['@AncillaryFeeAndTax'],
            'variableHandlingCharges?' => '@VariableHandlingCharges_1',
            'totalVariableHandlingCharges?' => '@VariableHandlingCharges_1'
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetail_recipient_address'] = [
            'streetLines' => ['string'],
            'city' => ['@type' => 'string', '@max' => 35],
            'stateOrProvinceCode?' => 'string',
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

        $schemas['EdtTaxDetail_1_taxRates_item'] = [
            'value?' => 'string',
            'currency?' => 'string',
            'quantity?' => 'number',
            'unitOfMeasure?' => 'string'
        ];

        $schemas['EdtTaxDetail_1_appliedPreferentialTradeAgreement'] = [
            'id?' => 'string',
            'name?' => 'string',
            'description?' => 'string'
        ];

        $schemas['VariableHandlingChargeDetail_fixedValue'] = [
            'amount' => 'number',
            'currency' => 'string'
        ];

        return $schemas;
    }
}
