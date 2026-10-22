<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

/**
 * Definitions of the requests and responses of the Consolidation API operations that FedexConsolidation implements,
 * generated from schemas/consolidation.json and written for SchemaValidator.
 *
 * There are two sets. definitions() has all of the file, down to the last field. The file contradicts its own sample
 * payloads in the nested objects (3 of its 19 samples pass the whole of it), so that set is only used to report
 * problems (FedexConsolidation::checkRequest() and checkResponse()). shallowDefinitions() is the one that decides
 * whether a request is sent: the top level of each request and response, as the file says it and as every sample of
 * the file has it, with each nested object or list accepted as it comes
 */
final class FedexConsolidationSchemas
{
    /**
     * Name of the definition of the Create Consolidation request
     * @var string
     */
    const CREATE_REQUEST = 'Full_Schema_CreateConsolidation';

    /**
     * Name of the definition of the Create Consolidation response
     * @var string
     */
    const CREATE_RESPONSE = 'CreateConsolidationResponse';

    /**
     * Name of the definition of the Modify Consolidation request
     * @var string
     */
    const MODIFY_REQUEST = 'Full_Schema_ModifyConsolidation';

    /**
     * Name of the definition of the Modify Consolidation response
     * @var string
     */
    const MODIFY_RESPONSE = 'ModifyConsolidationResponse';

    /**
     * Name of the definition of the Create Consolidation Shipment request
     * @var string
     */
    const CREATE_SHIPMENT_REQUEST = 'Full_Schema_CreateConsolidationShipment';

    /**
     * Name of the definition of the Create Consolidation Shipment response
     * @var string
     */
    const CREATE_SHIPMENT_RESPONSE = 'CreateConsolidationShipmentResponse';

    /**
     * Name of the definition of the Consolidation Shipment Results request
     * @var string
     */
    const SHIPMENT_RESULTS_REQUEST = 'ConsolidationShipmentResultsInputVO';

    /**
     * Name of the definition of the Consolidation Shipment Results response
     * @var string
     */
    const SHIPMENT_RESULTS_RESPONSE = 'ConsolidationShipmentResultsResponse';

    /**
     * Name of the definition of the Confirm Consolidations request
     * @var string
     */
    const CONFIRM_REQUEST = 'Full_Schema_ConfirmConsolidation';

    /**
     * Name of the definition of the Confirm Consolidations response
     * @var string
     */
    const CONFIRM_RESPONSE = 'ConfirmConsolidationResponse';

    /**
     * Name of the definition of the Confirm Consolidation Results request
     * @var string
     */
    const CONFIRM_RESULTS_REQUEST = 'Full_Schema_ConfirmConsolidationResults';

    /**
     * Name of the definition of the Confirm Consolidation Results response
     * @var string
     */
    const CONFIRM_RESULTS_RESPONSE = 'ConfirmConsolidationResultsResponse';

    /**
     * Name of the definition of the Retrieve Consolidation request
     * @var string
     */
    const RETRIEVE_REQUEST = 'Full_Schema_RetrieveConsolidation';

    /**
     * Name of the definition of the Retrieve Consolidation response
     * @var string
     */
    const RETRIEVE_RESPONSE = 'RetrieveConsolidationResponse';

    /**
     * Name of the definition of the Delete Consolidation Shipments request
     * @var string
     */
    const DELETE_SHIPMENTS_REQUEST = 'FullSchema_DeleteShipment';

    /**
     * Name of the definition of the Delete Consolidation Shipments response
     * @var string
     */
    const DELETE_SHIPMENTS_RESPONSE = 'SHPCResponseVO';

    /**
     * Name of the definition of the Delete Consolidation request
     * @var string
     */
    const DELETE_REQUEST = 'Full_Schema_deleteConsolidation';

    /**
     * Name of the definition of the Delete Consolidation response
     * @var string
     */
    const DELETE_RESPONSE = 'deleteConsolidationResponse';

    /**
     * The roots of the file that FedexConsolidation implements: the request and the response of each operation
     * @var string[]
     */
    const ROOTS = [
        self::CREATE_REQUEST,
        self::CREATE_RESPONSE,
        self::MODIFY_REQUEST,
        self::MODIFY_RESPONSE,
        self::CREATE_SHIPMENT_REQUEST,
        self::CREATE_SHIPMENT_RESPONSE,
        self::SHIPMENT_RESULTS_REQUEST,
        self::SHIPMENT_RESULTS_RESPONSE,
        self::CONFIRM_REQUEST,
        self::CONFIRM_RESPONSE,
        self::CONFIRM_RESULTS_REQUEST,
        self::CONFIRM_RESULTS_RESPONSE,
        self::RETRIEVE_REQUEST,
        self::RETRIEVE_RESPONSE,
        self::DELETE_SHIPMENTS_REQUEST,
        self::DELETE_SHIPMENTS_RESPONSE,
        self::DELETE_REQUEST,
        self::DELETE_RESPONSE
    ];

    /**
     * Get the definitions of all the requests and responses, down to the last field. The names of the definitions, and
     * of their fields, are the ones of schemas/consolidation.json, with these differences:
     *  - FullSchema-DeleteShipment is named FullSchema_DeleteShipment (the validator does not accept a hyphen in a name)
     *  - what the file writes inline or as an overlay of another definition ("this definition, but these keys are
     *    required") is a definition of its own, named after the definition and the field that contain it
     *  - RequestedConsolidationShipment requires a field called recipient, which the file never defines (the field is
     *    recipients, and it is not required there): recipient is accepted as an optional field of any type
     *  - Label.type and ShippingDocument.type have an enum of one value, the whole list of values joined in a text: they
     *    are checked as text
     *  - the items of DistributionLocations.inclusionSpecifications are the allOf of CarrierCode and ServiceCategory,
     *    which are both plain text: they are text
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['AccountNumber'] = '@PartyAccountNumber';

        $schemas['AccountNumber_1'] = '@PartyAccountNumber_2';

        $schemas['AccountNumber_2'] = '@PartyAccountNumber_2';

        $schemas['Address'] = [
            'streetLines' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'residential?' => ['@type' => 'bool', '@enum' => [true, false]]
        ];

        $schemas['AddressAncillaryDetail'] = [
            'locationInCity?' => 'string',
            'suite?' => 'string',
            'addressVerificationOverrideReason?' => 'string',
            'locationInProperty?' => 'string',
            'addtionalDescriptions?' => 'string',
            'department?' => 'string',
            'roomFloor?' => 'string',
            'crossStreet?' => 'string',
            'building?' => 'string',
            'apartment?' => 'string',
            'room?' => 'string'
        ];

        $schemas['Address_1'] = [
            'streetLines' => ['string'],
            'city' => ['@type' => 'string', '@max' => 35],
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['Address_1_1'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['Address_2'] = [
            'streetLines?' => ['string'],
            'city?' => ['@type' => 'string', '@max' => 35],
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

        $schemas['AncillaryFeeAndTax'] = [
            'type?' => ['@type' => 'string', '@enum' => ['CLEARANCE_ENTRY_FEE', 'GOODS_AND_SERVICES_TAX', 'HARMONIZED_SALES_TAX', 'OTHER']],
            'description?' => 'string',
            'amount?' => '@Money_1'
        ];

        $schemas['AssociatedFreightLineItemDetail'] = [
            'id' => 'string'
        ];

        $schemas['AssociatedTrackingDetails'] = [
            'trackingIds?' => ['@TrackingIds'],
            'type?' => ['@type' => 'string', '@enum' => ['COD_AND_DELIVERY_ON_INVOICE_ACCEPTANCE_RETURN', 'COD_RETURN', 'CONSOLIDATION_DOCUMENTS', 'CONSOLIDATION_MASTER', 'DELIVERY_ON_INVOICE_ACCEPTANCE_RETURN']]
        ];

        $schemas['BaseProcessOutputVO_ConfirmConsolidationResults'] = [
            'jobId?' => 'string',
            'processingOptionType?' => 'string',
            'transactionConsolidations?' => ['@TransactionConsolidationOutputVO'],
            'alerts?' => ['@Alert']
        ];

        $schemas['BaseProcessOutputVO_ConsolidationShipmentResults'] = [
            'transactionShipments?' => ['@TransactionConsolidationShipmentResultsOutputVO'],
            'alerts?' => ['@Alert']
        ];

        $schemas['BaseProcessOutputVO_RetrieveConsolidation'] = [
            'retrievedConsolidation?' => '@RetrievedConsolidation',
            'alerts?' => ['@Alert']
        ];

        $schemas['BatteryDetail'] = [
            'batteryPackingType?' => ['@type' => 'string', '@enum' => ['CONTAINED_IN_EQUIPMENT', 'PACKED_WITH_EQUIPMENT']],
            'batteryRegulatoryType?' => ['@type' => 'string', '@enum' => ['IATA_SECTION_II']],
            'batteryMaterialType?' => ['@type' => 'string', '@enum' => ['LITHIUM_METAL', 'LITHIUM_ION']]
        ];

        $schemas['BinaryBarcode'] = [
            'type?' => 'string',
            'value?' => ['string']
        ];

        $schemas['BrokerDetail'] = [
            'broker?' => '@BrokerDetail_broker',
            'type?' => 'string'
        ];

        $schemas['BrokerDetail_1'] = [
            'broker?' => '@BrokerDetail_1_broker',
            'type?' => 'string'
        ];

        $schemas['CarrierCode'] = 'string';

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

        $schemas['Commodity'] = [
            'name?' => 'string',
            'numberOfPieces' => 'int',
            'description' => 'string',
            'cIMarksAndNumbers?' => 'string',
            'countryOfManufacture?' => 'string',
            'weight?' => '@Weight',
            'quantity?' => 'int',
            'quantityUnits?' => 'string',
            'unitPrice?' => '@Money',
            'customsValue?' => '@Money',
            'commodityId?' => 'string',
            'clearanceItemDetail?' => ['@ClearanceItemDetail']
        ];

        $schemas['CommodityClarificationDetail'] = [
            'commodityIndex?' => 'int',
            'suggestions?' => ['@SuggestedCommodityDetail']
        ];

        $schemas['Commodity_1'] = [
            'name' => 'string',
            'numberOfPieces' => 'int',
            'description' => 'string',
            'countryOfManufacture?' => 'string',
            'weight?' => '@Weight',
            'quantity?' => 'int',
            'quantityUnits?' => 'string',
            'unitPrice?' => '@Money_2',
            'customsValue?' => '@Money_2',
            'commodityId?' => 'string',
            'clearanceItemDetail?' => ['@ClearanceItemDetail']
        ];

        $schemas['CompletedCodDetail'] = [
            'codCollectionAmount?' => '@Money'
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
            'holdingLocation?' => '@CompletedHoldAtLocationDetail_holdingLocation'
        ];

        $schemas['CompletedHoldAtLocationDetail_holdingLocation'] = [
            'address?' => '@Address',
            'contact?' => '@Contact'
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

        $schemas['ConfirmConsolidationOutputVO'] = [
            'processingOptionType?' => 'string',
            'jobId?' => 'string',
            'completedConsolidationDetail?' => '@ConfirmConsolidationOutputVO_completedConsolidationDetail',
            'alerts?' => ['@Alert']
        ];

        $schemas['ConfirmConsolidationOutputVO_completedConsolidationDetail'] = [
            'consolidationShipments?' => '@ConsolidationShipments'
        ];

        $schemas['ConfirmConsolidationResponse'] = [
            'output?' => '@ConfirmConsolidationOutputVO'
        ];

        $schemas['ConfirmConsolidationResultsResponse'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_ConfirmConsolidationResults'
        ];

        $schemas['ConsolidationCompletedShipmentDetail'] = [
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
            'accessDetail?' => '@PendingShipmentAccessDetail',
            'shipmentConsolidationDetail?' => '@ShipmentConsolidationDetail',
            'completedCodDetail?' => '@CompletedCodDetail'
        ];

        $schemas['ConsolidationCustomsDeclarationStatementDetail'] = [
            'types?' => ['string']
        ];

        $schemas['ConsolidationDataSources'] = [
            'consolidationDataType?' => ['@type' => 'string', '@enum' => ['TOTAL_CUSTOMS_CODE', 'TOTAL_FREIGHT_CHARGES', 'TOTAL_HANDLING_COSTS', 'TOTAL_INSURANCE_CHARGES', 'TOTAL_INSURED_VALUE', 'TOTAL_PACKING_COSTS', 'TOTAL_TAXES_OR_MISCELLANEOUS_CHARGES']],
            'consolidationDataSourceType?' => ['@type' => 'string', '@enum' => ['ACCUMULATED', 'CLIENT']]
        ];

        $schemas['ConsolidationDataSources_1'] = ['@ConsolidationDataSources_1_inner'];

        $schemas['ConsolidationDataSources_1_inner'] = [
            'consolidationDataType?' => '@consolidationDataType',
            'consolidationDataSourceType?' => '@consolidationDataSourceType'
        ];

        $schemas['ConsolidationDocumentSpec'] = [
            'consolidationDocumentTypes?' => ['string'],
            'consolidatedCommercialInvoiceDetail?' => '@ConsolidationDocumentSpec_consolidatedCommercialInvoiceDetail',
            'customsPackingListDetail?' => '@ConsolidationDocumentSpec_customsPackingListDetail',
            'crnReportDetail?' => '@ConsolidationDocumentSpec_crnReportDetail',
            'condensedCrnReportDetail?' => '@ConsolidationDocumentSpec_condensedCrnReportDetail',
            'consolidatedCustomsLinehaulReportDetail?' => '@ConsolidationDocumentSpec_consolidatedCustomsLinehaulReportDetail',
            'consolidatedPartyReportDetail?' => '@ConsolidationDocumentSpec_consolidatedPartyReportDetail',
            'consolidatedSoldToSummaryReportDetail?' => '@ConsolidationDocumentSpec_consolidatedSoldToSummaryReportDetail',
            'commoditiesByTrackingNumberDetail?' => '@ConsolidationDocumentSpec_consolidatedSoldToSummaryReportDetail',
            'customDocumentDetails?' => ['@Formats']
        ];

        $schemas['ConsolidationDocumentSpec_1'] = [
            'consolidationDocumentTypes?' => ['string'],
            'consolidatedCommercialInvoiceDetail?' => '@ConsolidationDocumentSpec_1_consolidatedCommercialInvoiceDetail',
            'customsPackingListDetail?' => '@ConsolidationDocumentSpec_1_customsPackingListDetail',
            'crnReportDetail?' => '@ConsolidationDocumentSpec_1_crnReportDetail',
            'condensedCrnReportDetail?' => '@ConsolidationDocumentSpec_1_condensedCrnReportDetail',
            'consolidatedCustomsLinehaulReportDetail?' => '@ConsolidationDocumentSpec_1_consolidatedCustomsLinehaulReportDetail',
            'consolidatedPartyReportDetail?' => '@ConsolidationDocumentSpec_1_consolidatedPartyReportDetail',
            'consolidatedSoldToSummaryReportDetail?' => '@ConsolidationDocumentSpec_1_consolidatedSoldToSummaryReportDetail',
            'commoditiesByTrackingNumberDetail?' => '@ConsolidationDocumentSpec_1_consolidatedSoldToSummaryReportDetail',
            'customDocumentDetails?' => ['@Formats_1']
        ];

        $schemas['ConsolidationDocumentSpec_1_condensedCrnReportDetail'] = [
            'format?' => '@Formats_1'
        ];

        $schemas['ConsolidationDocumentSpec_1_consolidatedCommercialInvoiceDetail'] = [
            'format?' => '@Formats_1'
        ];

        $schemas['ConsolidationDocumentSpec_1_consolidatedCustomsLinehaulReportDetail'] = [
            'format?' => '@Formats_1'
        ];

        $schemas['ConsolidationDocumentSpec_1_consolidatedPartyReportDetail'] = [
            'format?' => '@Formats_1'
        ];

        $schemas['ConsolidationDocumentSpec_1_consolidatedSoldToSummaryReportDetail'] = [
            'format?' => '@Formats_1'
        ];

        $schemas['ConsolidationDocumentSpec_1_crnReportDetail'] = [
            'format?' => '@Formats_1'
        ];

        $schemas['ConsolidationDocumentSpec_1_customsPackingListDetail'] = [
            'format?' => '@Formats_1'
        ];

        $schemas['ConsolidationDocumentSpec_condensedCrnReportDetail'] = [
            'format?' => '@Formats'
        ];

        $schemas['ConsolidationDocumentSpec_consolidatedCommercialInvoiceDetail'] = [
            'format?' => '@Formats'
        ];

        $schemas['ConsolidationDocumentSpec_consolidatedCustomsLinehaulReportDetail'] = [
            'format?' => '@Formats'
        ];

        $schemas['ConsolidationDocumentSpec_consolidatedPartyReportDetail'] = [
            'format?' => '@Formats'
        ];

        $schemas['ConsolidationDocumentSpec_consolidatedSoldToSummaryReportDetail'] = [
            'format?' => '@Formats'
        ];

        $schemas['ConsolidationDocumentSpec_crnReportDetail'] = [
            'format?' => '@Formats'
        ];

        $schemas['ConsolidationDocumentSpec_customsPackingListDetail'] = [
            'format?' => '@Formats'
        ];

        $schemas['ConsolidationKey'] = [
            'date' => 'string',
            'index' => 'string',
            'type' => ['@type' => 'string', '@enum' => ['INTERNATIONAL_DISTRIBUTION_FREIGHT', 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'INTERNATIONAL_GROUND_DISTRIBUTION', 'TRANSBORDER_DISTRIBUTION', 'INTERNATIONAL_PRIORITY_DISTRIBUTION']]
        ];

        $schemas['ConsolidationProcessingOptionsRequested'] = [
            'options?' => [['@type' => 'string', '@enum' => ['REQUIRE_PRE_EPIC_BARCODE']]]
        ];

        $schemas['ConsolidationShipmentResultsInputVO'] = [
            'jobId' => 'string',
            'accountNumber' => '@PartyAccountNumber',
            'version?' => '@Version'
        ];

        $schemas['ConsolidationShipmentResultsResponse'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_ConsolidationShipmentResults'
        ];

        $schemas['ConsolidationShipments'] = [
            'shipmentRoleType?' => ['@type' => 'string', '@enum' => ['CONSOLIDATION_DOCUMENTS_SHIPMENT', 'CRN_SHIPMENT', 'MASTER_AIRWAYBILL_SHIPMENT']],
            'consolidationRequestedShipments?' => '@ConsolidationShipments_consolidationRequestedShipments',
            'completedShipmentDetail?' => '@ConsolidationCompletedShipmentDetail'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments'] = [
            'shipDatestamp?' => 'string',
            'dropOffType?' => ['@type' => 'string', '@enum' => ['BUSINESS_SERVICE_CENTER', 'DROP_BOX', 'REGULAR_PICKUP', 'REQUEST_COURIER', 'STATION']],
            'recipientLocationNumber?' => 'string',
            'serviceType?' => 'string',
            'packagingType?' => 'string',
            'totalWeight?' => '@Weight',
            'totalDimensions?' => '@Dimensions',
            'shipper?' => '@ShipperParty',
            'origin?' => '@Origin',
            'recipient?' => '@SoldToParty',
            'processingOptionsRequested?' => '@ConsolidationShipments_consolidationRequestedShipments_processingOptionsRequested',
            'consolidationDetail?' => '@ConsolidationShipments_consolidationRequestedShipments_consolidationDetail',
            'customsClearanceDetail?' => '@CustomsClearanceDetail',
            'blockinsightVisibility?' => 'bool',
            'labelSpecification?' => '@ConsolidationShipments_consolidationRequestedShipments_labelSpecification',
            'rateRequestTypes?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'CUSTOM', 'INCENTIVE', 'LIST', 'NONE', 'PREFERRED', 'RATED', 'RETAIL']],
            'edtRequestTypes?' => ['@type' => 'string', '@enum' => ['ALL', 'NONE']],
            'requestedPackageLineItems?' => '@ConsolidationShipments_consolidationRequestedShipments_requestedPackageLineItems',
            'completedShipmentDetail?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail'] = [
            'usDomestic?' => 'bool',
            'carrierCode?' => ['@type' => 'string', '@enum' => ['FDXC', 'FDXE', 'FDXG', 'FDXO', 'FXCC', 'FXFR', 'FXSP']],
            'masterTrackingId?' => '@TrackingIds',
            'serviceDescription?' => '@ServiceDescription',
            'packageDescription?' => '@PackageDescription',
            'specialServiceDescription?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_specialServiceDescription',
            'operationalDetail?' => '@ShipmentOperationalDetail',
            'consolidationDetail?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_consolidationDetail',
            'exportComplianceStatement?' => 'string',
            'documentRequirements?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_documentRequirements',
            'completedPackageDetail?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_completedPackageDetail'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_completedPackageDetail'] = [
            'sequenceNumber?' => 'int',
            'trackingId?' => '@TrackingIds',
            'groupNumber?' => 'int',
            'operationalDetail?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_completedPackageDetail_operationalDetail',
            'label?' => '@Label',
            'packageDocuments?' => ['@ShippingDocument'],
            'signatureOptions?' => ['@type' => 'string', '@enum' => ['ADULT', 'DIRECT', 'INDIRECT', 'NO_SIGNATURE_REQUIRED', 'SERVICE_DEFAULT']]
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_completedPackageDetail_operationalDetail'] = [
            'operationalInstructions?' => '@OperationalInstructions',
            'barcodes?' => '@PackageBarcodes'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_consolidationDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['INTERNATIONAL_DISTRIBUTION_FREIGHT', 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'INTERNATIONAL_GROUND_DISTRIBUTION', 'INTERNATIONAL_PRIORITY_DISTRIBUTION', 'TRANSBORDER_DISTRIBUTION']],
            'role?' => ['@type' => 'string', '@enum' => ['CONSOLIDATION_DOCUMENTS_SHIPMENT', 'CRN_SHIPMENT', 'MASTER_AIRWAYBILL_SHIPMENT']]
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_documentRequirements'] = [
            'requiredDocuments?' => ['@type' => 'string', '@enum' => ['AIR_WAYBILL', 'CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'COMMERCIAL_OR_PRO_FORMA_INVOICE', 'COMMERCIAL_OR_PRO_FORMA_INVOICE_OR_USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN', 'PRO_FORMA_INVOICE']],
            'generationDetails?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_documentRequirements_generationDetails'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_documentRequirements_generationDetails'] = [
            'type?' => ['@type' => 'string', '@enum' => ['AIR_WAYBILL', 'CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'PRO_FORMA_INVOICE', 'USMCA_CERTIFICATION_OF_ORIGIN', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']],
            'minimumCopiesRequired?' => 'int'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_specialServiceDescription'] = [
            'identifier?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_specialServiceDescription_identifier',
            'names?' => '@ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_specialServiceDescription_names'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_specialServiceDescription_identifier'] = [
            'id?' => 'string',
            'type?' => 'string',
            'code?' => 'string'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_completedShipmentDetail_specialServiceDescription_names'] = [
            'type?' => 'string',
            'encoding?' => 'string',
            'value?' => 'string'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_consolidationDetail'] = [
            'relationshipTimeStamp?' => 'string',
            'type?' => ['@type' => 'string', '@enum' => ['INTERNATIONAL_DISTRIBUTION_FREIGHT', 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'INTERNATIONAL_GROUND_DISTRIBUTION', 'INTERNATIONAL_PRIORITY_DISTRIBUTION', 'TRANSBORDER_DISTRIBUTION']],
            'role?' => ['@type' => 'string', '@enum' => ['CONSOLIDATION_DOCUMENTS_SHIPMENT', 'CRN_SHIPMENT', 'MASTER_AIRWAYBILL_SHIPMENT']],
            'internationalDistribtuionDetail?' => '@ConsolidationShipments_consolidationRequestedShipments_consolidationDetail_internationalDistribtuionDetail',
            'transborderDistributionDetail?' => '@TransborderDistributionDetail'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_consolidationDetail_internationalDistribtuionDetail'] = [
            'summaryDetail?' => '@ConsolidationShipments_consolidationRequestedShipments_consolidationDetail_internationalDistribtuionDetail_summaryDetail'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_consolidationDetail_internationalDistribtuionDetail_summaryDetail'] = [
            'totalWeight?' => '@ConsolidationShipments_consolidationRequestedShipments_consolidationDetail_internationalDistribtuionDetail_summaryDetail_totalWeight',
            'totalPackageCount?' => 'int',
            'totalUniqueAddressCount?' => 'int',
            'totalCustomsValue?' => '@ConsolidationShipments_consolidationRequestedShipments_consolidationDetail_internationalDistribtuionDetail_summaryDetail_totalCustomsValue'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_consolidationDetail_internationalDistribtuionDetail_summaryDetail_totalCustomsValue'] = [
            'currency?' => 'string',
            'amount?' => 'string'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_consolidationDetail_internationalDistribtuionDetail_summaryDetail_totalWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'string'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_labelSpecification'] = [
            'labelFormatType?' => ['@type' => 'string', '@enum' => ['COMMON2D', 'ERROR', 'LABEL_DATA_ONLY', 'MAILROOM', 'NO_LABEL', 'OPERATIONAL_LABEL', 'PRE_COMMON2D']],
            'labelStockType?' => ['@type' => 'string', '@enum' => ['PAPER_4X6', 'PAPER_4X675', 'PAPER_4X8', 'PAPER_4X9', 'PAPER_7X475', 'PAPER_85X11_BOTTOM_HALF_LABEL', 'PAPER_85X11_TOP_HALF_LABEL', 'PAPER_LETTER', 'STOCK_4X675_LEADING_DOC_TAB', 'STOCK_4X8', 'STOCK_4X9_LEADING_DOC_TAB', 'STOCK_4X6', 'STOCK_4X675_TRAILING_DOC_TAB', 'STOCK_4X9_TRAILING_DOC_TAB', 'STOCK_4X675', 'STOCK_4X9', 'STOCK_4X8.5_TRAILING_DOC_TAB', 'STOCK_4X10.5_TRAILING_DOC_TAB']],
            'imageType?' => ['@type' => 'string', '@enum' => ['ZPLII', 'EPL2', 'PDF', 'PNG']],
            'labelPrintingOrientation?' => ['@type' => 'string', '@enum' => ['BOTTOM_EDGE_OF_TEXT_FIRST', 'TOP_EDGE_OF_TEXT_FIRST']]
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_processingOptionsRequested'] = [
            'options?' => ['@type' => 'string', '@enum' => ['ALLOW_PREVIOUS_SHIP_DATE', 'BYPASS_REGULATORY_VALIDATION', 'CONVENIENCE_MULTIPLE_PACKAGES', 'CUSTOM_TRANSIT_TIME', 'FUTURE_DAY_SHIPMENT', 'IATA_CLASS_AND_AIRCRAFT_ONLY', 'MANUAL_AIRBILL', 'NO_COMMITMENT_DATE_REQUESTED', 'PACKAGE_LEVEL_COMMODITIES', 'PRE_EIG_PROCESSING', 'PRE_ETD_ENHANCEMENTS', 'PRE_FXF2020_HANDLING_UNIT_DETAILS', 'PRE_GPR', 'PRE_GRAPE2_SHIPMENT', 'PRE_MULTIPLIER_PROCESSING', 'REQUEST_LEGACY_GROUND_ECONOMY_LABEL', 'REQUEST_LEGACY_SMARTPOST_LABEL', 'REQUIRE_PRE_EPIC_BARCODE']]
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_requestedPackageLineItems'] = [
            'sequenceNumber?' => 'string',
            'groupNumber?' => 'string',
            'groupPackageCount?' => 'string',
            'trackingId?' => '@ConsolidationShipments_consolidationRequestedShipments_requestedPackageLineItems_trackingId',
            'weights?' => '@Weight',
            'dimensions?' => '@Dimensions',
            'customerReferences?' => '@ConsolidationShipments_consolidationRequestedShipments_requestedPackageLineItems_customerReferences'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_requestedPackageLineItems_customerReferences'] = [
            'customerReferenceType?' => ['@type' => 'string', '@enum' => ['BILL_OF_LADING', 'CUSTOMER_REFERENCE', 'DEPARTMENT_NUMBER', 'ELECTRONIC_PRODUCT_CODE', 'INTRACOUNTRY_REGULATORY_REFERENCE', 'INVOICE_NUMBER', 'P_O_NUMBER', 'PACKING_SLIP_NUMBER', 'RMA_ASSOCIATION', 'SHIPMENT_INTEGRITY', 'STORE_NUMBER']],
            'value?' => 'string'
        ];

        $schemas['ConsolidationShipments_consolidationRequestedShipments_requestedPackageLineItems_trackingId'] = [
            'trackindIdType?' => ['@type' => 'string', '@enum' => ['EXPRESS', 'FEDEX', 'FREIGHT', 'GROUND', 'INTERNAL', 'UNKNOWN', 'USPS']],
            'formId?' => 'string',
            'trackingNumber?' => 'string'
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

        $schemas['ContactAncillaryDetail_1'] = [
            'emailAddresses?' => ['@EMailDetail_1']
        ];

        $schemas['ContactAncillaryDetail_1_1'] = [
            'emailAddresses?' => ['@EMailDetail_1']
        ];

        $schemas['ContactAndAddress'] = [
            'contact?' => '@Contact_1',
            'contactAncillaryDetail?' => '@ContactAncillaryDetail_1',
            'address?' => '@Address_1',
            'addressAncillaryDetail?' => '@AddressAncillaryDetail'
        ];

        $schemas['ContactAndAddress_1'] = [
            'contact?' => '@Contact_1_1',
            'contactAncillaryDetail?' => '@ContactAncillaryDetail_1_1',
            'address?' => '@Address_1_1',
            'addressAncillaryDetail?' => '@AddressAncillaryDetail'
        ];

        $schemas['Contact_1'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneExtension?' => 'string',
            'phoneNumber?' => 'string',
            'faxNumber?' => 'string',
            'companyName?' => 'string',
            'stateTaxId?' => 'string',
            'fedralTaxId?' => 'string'
        ];

        $schemas['Contact_1_1'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string',
            'companyName?' => 'string',
            'stateTaxId?' => 'string',
            'fedralTaxId?' => 'string'
        ];

        $schemas['ContentRecord'] = [
            'itemNumber?' => 'string',
            'receivedQuantity?' => 'int',
            'description?' => 'string',
            'partNumber?' => 'string'
        ];

        $schemas['CreateConsolidationCompletedShipmentDetail'] = [
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
            'accessDetail?' => '@PendingShipmentAccessDetail',
            'shipmentConsolidationDetail?' => '@ShipmentConsolidationDetail',
            'completedCodDetail?' => '@CompletedCodDetail'
        ];

        $schemas['CreateConsolidationOutputVO'] = [
            'trackingIds?' => ['@TrackingIds'],
            'consolidationKey?' => '@ConsolidationKey',
            'alerts?' => ['@Alert']
        ];

        $schemas['CreateConsolidationResponse'] = [
            'customerTransactionId?' => 'string',
            'transactionId?' => 'string',
            'output?' => '@CreateConsolidationOutputVO'
        ];

        $schemas['CreateConsolidationShipmentOutputVO_Details'] = [
            'transactionShipments?' => ['@TransactionCreateConsolidationShipmentResultsOutputVO']
        ];

        $schemas['CreateConsolidationShipmentResponse'] = [
            'customerTransactionId?' => 'string',
            'transactionId?' => 'string',
            'output?' => '@CreateConsolidationShipmentOutputVO_Details'
        ];

        $schemas['CurrencyExchangeRate'] = [
            'rate?' => 'number',
            'fromCurrency?' => 'string',
            'intoCurrency?' => 'string'
        ];

        $schemas['CustomerReference'] = [
            'customerReferenceType?' => ['@type' => 'string', '@enum' => ['BILL_OF_LADING', 'CUSTOMER_REFERENCE', 'DEPARTMENT_NUMBER', 'INVOICE_NUMBER', 'P_O_NUMBER', 'SHIPMENT_INTEGRITY', 'INTRACOUNTRY_REGULATORY_REFERENCE', 'STORE_NUMBER', 'RMA_ASSOCIATION', 'SHIPPER_ID1', 'SHIPPER_ID2']],
            'value?' => 'string'
        ];

        $schemas['CustomerReferences'] = [
            'customerReferenceType?' => ['@type' => 'string', '@enum' => ['BILL_OF_LANDING', 'CUSTOMER_REFERENCE', 'DEPARTMENT_NUMBER', 'ELECTRONIC_PRODUCT_CODE', 'INTRACOUNTRY_REGULATORY_REFERENCE', 'INVOICE_NUMBER', 'P_O_NUMBER', 'PACKING_SLIP_NUMBER', 'RMA_ASSOCIATION', 'SHIPMENT_INTEGRITY', 'STORE_NUMBER']],
            'value?' => 'string'
        ];

        $schemas['CustomerReferences_1'] = ['@CustomerReferences_1_inner'];

        $schemas['CustomerReferences_1_inner'] = [
            'customerReferenceType?' => '@customerReferenceType',
            'value?' => '@value'
        ];

        $schemas['CustomerSpecifiedLabelDetail'] = [
            'maskedData?' => [['@type' => 'string', '@enum' => ['CUSTOMS_VALUE', 'DIMENSIONS', 'DUTIES_AND_TAXES_PAYOR_ACCOUNT_NUMBER', 'FREIGHT_PAYOR_ACCOUNT_NUMBER', 'INSURED_VALUE', 'PACKAGE_SEQUENCE_AND_COUNT', 'SECONDARY_BARCODE', 'SHIPPER_ACCOUNT_NUMBER', 'SHIPPER_INFORMATION', 'SUPPLEMENTAL_LABEL_DOC_TAB', 'TERMS_AND_CONDITIONS', 'TOTAL_WEIGHT', 'TRANSPORTATION_CHARGES_PAYOR_ACCOUNT_NUMBER']]],
            'regulatoryLabels?' => ['@RegulatoryLabelContentDetail'],
            'docTabContent?' => '@DocTabContent'
        ];

        $schemas['CustomerSpecifiedLabelDetail_1'] = [
            'maskedData?' => [['@type' => 'string', '@enum' => ['CUSTOMS_VALUE', 'DIMENSIONS', 'DUTIES_AND_TAXES_PAYOR_ACCOUNT_NUMBER', 'FREIGHT_PAYOR_ACCOUNT_NUMBER', 'INSURED_VALUE', 'PACKAGE_SEQUENCE_AND_COUNT', 'SECONDARY_BARCODE', 'SHIPPER_ACCOUNT_NUMBER', 'SHIPPER_INFORMATION', 'SUPPLEMENTAL_LABEL_DOC_TAB', 'TERMS_AND_CONDITIONS', 'TOTAL_WEIGHT', 'TRANSPORTATION_CHARGES_PAYOR_ACCOUNT_NUMBER']]],
            'regulatoryLabels?' => ['@RegulatoryLabelContentDetail'],
            'docTabContent?' => '@DocTabContent_1'
        ];

        $schemas['CustomsClearanceDetail'] = [
            'customsOption?' => '@CustomsOptionDetail',
            'importerOfRecord?' => '@ImporterOfRecord',
            'recipientCustomsId?' => '@RecipientCustomsId',
            'dutiesPayment?' => '@Payment1',
            'documentContent?' => ['@type' => 'string', '@enum' => ['DOCUMENTS_ONLY', 'NON_DOCUMENTS', 'DERIVED']],
            'totalCustomsValue?' => '@Money',
            'regulatoryControls?' => [['@type' => 'string', '@enum' => ['FOOD_OR_PERISHABLE', 'USMCA', 'NOT_APPLICABLE_FOR_LOW_VALUE_CUSTOMS_EXCEPTIONS', 'NOT_IN_FREE_CIRCULATION']]],
            'insuranceCharges?' => '@Money',
            'declarationStatementDetail?' => '@ConsolidationCustomsDeclarationStatementDetail',
            'commercialInvoice?' => '@CustomsClearanceDetail_commercialInvoice',
            'exportDetail?' => '@ExportDetail',
            'commodities?' => ['@Commodity'],
            'brokers?' => ['@BrokerDetail'],
            'clearanceBrokerage?' => ['@type' => 'string', '@enum' => ['BROKER_INCLUSIVE', 'BROKER_INCLUSIVE_NON_RESIDENT_IMPORTER', 'BROKER_SELECT', 'BROKER_SELECT_NON_RESIDENT_IMPORTER', 'BROKER_UNASSIGNED']],
            'freightOnValue?' => ['@type' => 'string', '@enum' => ['CARRIER_RISK', 'OWN_RISK']]
        ];

        $schemas['CustomsClearanceDetail_1'] = [
            'customsOption?' => '@CustomsOptionDetail_1',
            'importerOfRecord?' => '@ImporterOfRecord_1',
            'recipientCustomsId?' => '@RecipientCustomsId',
            'dutiesPayment?' => '@Payment_1',
            'documentContent?' => ['@type' => 'string', '@enum' => ['DOCUMENTS_ONLY', 'NON_DOCUMENTS', 'DERIVED']],
            'totalCustomsValue?' => '@Money_2',
            'regulatoryControls?' => [['@type' => 'string', '@enum' => ['FOOD_OR_PERISHABLE', 'USMCA', 'NOT_APPLICABLE_FOR_LOW_VALUE_CUSTOMS_EXCEPTIONS', 'NOT_IN_FREE_CIRCULATION']]],
            'insuranceCharges?' => '@Money_2',
            'declarationStatementDetail?' => '@ConsolidationCustomsDeclarationStatementDetail',
            'commercialInvoice?' => '@CustomsClearanceDetail_1_commercialInvoice',
            'exportDetail?' => '@ExportDetail_1',
            'commodities?' => ['@Commodity_1'],
            'brokers?' => ['@BrokerDetail_1'],
            'clearanceBrokerage?' => ['@type' => 'string', '@enum' => ['BROKER_INCLUSIVE', 'BROKER_INCLUSIVE_NON_RESIDENT_IMPORTER', 'BROKER_SELECT', 'BROKER_SELECT_NON_RESIDENT_IMPORTER', 'BROKER_UNASSIGNED']],
            'freightOnValue?' => ['@type' => 'string', '@enum' => ['CARRIER_RISK', 'OWN_RISK']]
        ];

        $schemas['CustomsClearanceDetail_1_commercialInvoice'] = [
            'termsOfSale?' => 'string',
            'comments?' => ['string'],
            'shipmentPurpose?' => ['@type' => 'string', '@enum' => ['GIFT', 'NOT_SOLD', 'PERSONAL_EFFECTS', 'REPAIR_AND_RETURN', 'SAMPLE', 'SOLD']],
            'freightCharge?' => '@FreightChargeMoney_1',
            'taxesOrMiscellaneousCharge?' => '@TaxesOrMiscellaneousCharges_1',
            'taxesOrMiscellaneousChargeType?' => ['@type' => 'string', '@enum' => ['COMMISSIONS', 'DISCOUNTS', 'HANDLING_FEES', 'OTHER', 'ROYALTIES_AND_LICENSE_FEES', 'TAXES']],
            'packingCosts?' => '@PackingCostCharges_1',
            'handlingCosts?' => '@HandlingCostCharges_1',
            'specialInstructions?' => 'string',
            'declarationStatement?' => 'string',
            'paymentTerms?' => 'string',
            'purposeOfShipmentDescription?' => 'string',
            'customerReferences?' => '@CustomerReferences_1',
            'originatorName?' => 'string'
        ];

        $schemas['CustomsClearanceDetail_commercialInvoice'] = [
            'termsOfSale?' => 'string',
            'comments?' => ['string'],
            'shipmentPurpose?' => ['@type' => 'string', '@enum' => ['GIFT', 'NOT_SOLD', 'PERSONAL_EFFECTS', 'REPAIR_AND_RETURN', 'SAMPLE', 'SOLD']],
            'freightCharge?' => '@FreightChargeMoney',
            'taxesOrMiscellaneousCharge?' => '@TaxesOrMiscellaneousCharges',
            'taxesOrMiscellaneousChargeType?' => ['@type' => 'string', '@enum' => ['COMMISSIONS', 'DISCOUNTS', 'HANDLING_FEES', 'OTHER', 'ROYALTIES_AND_LICENSE_FEES', 'TAXES']],
            'packingCosts?' => '@PackingCostCharges',
            'handlingCosts?' => '@HandlingCostCharges',
            'specialInstructions?' => 'string',
            'declarationStatement?' => 'string',
            'paymentTerms?' => 'string',
            'purposeOfShipmentDescription?' => ['@type' => 'string', '@enum' => ['GIFT', 'NOT_SOLD', 'PERSONAL_EFFECTS', 'REPAIR_AND_RETURN', 'SAMPLE', 'SOLD']],
            'customerReferences?' => '@CustomerReferences',
            'originatorName?' => 'string'
        ];

        $schemas['CustomsOptionDetail'] = [
            'description?' => 'string',
            'type?' => ['@type' => 'string', '@enum' => ['COURTESY_RETURN_LABEL', 'EXHIBITION_TRADE_SHOW', 'FAULTY_ITEM', 'FOLLOWING_REPAIR', 'FOR_REPAIR', 'ITEM_FOR_LOAN', 'OTHER', 'REJECTED', 'REPLACEMENT', 'TRIAL']]
        ];

        $schemas['CustomsOptionDetail_1'] = [
            'description?' => 'string',
            'type?' => ['@type' => 'string', '@enum' => ['COURTESY_RETURN_LABEL', 'EXHIBITION_TRADE_SHOW', 'FAULTY_ITEM', 'FOLLOWING_REPAIR', 'FOR_REPAIR', 'ITEM_FOR_LOAN', 'OTHER', 'REJECTED', 'REPLACEMENT', 'TRIAL']]
        ];

        $schemas['DangerousGoodsDetail'] = [
            'trackingNumber?' => 'string',
            'accessibility?' => ['@type' => 'string', '@enum' => ['ACCESSIBLE', 'INACCESSIBLE']],
            'cargoAircraftOnly?' => ['@type' => 'bool', '@enum' => [true, false]]
        ];

        $schemas['DeleteShipmentOutputVO'] = [
            'cancelledShipment?' => 'bool',
            'message?' => 'string',
            'alerts?' => ['@Alert']
        ];

        $schemas['DestinationControlDetail'] = [
            'endUser?' => 'string',
            'statementTypes' => ['@type' => 'string', '@enum' => ['DEPARTMENT_OF_COMMERCE', 'DEPARTMENT_OF_STATE']],
            'destinationCountries?' => ['string']
        ];

        $schemas['DestinationControlDetail_1'] = [
            'endUser?' => 'string',
            'statementTypes' => ['@type' => 'string', '@enum' => ['DEPARTMENT_OF_COMMERCE', 'DEPARTMENT_OF_STATE', 'DEPARTMENT_OF_COMMERCE_AND_DEPARTMENT_OF_STATE']],
            'destinationCountries?' => ['string']
        ];

        $schemas['Dimensions'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['DistributionLocations'] = [
            'type?' => ['@type' => 'string', '@enum' => ['CUSTOMER_SPECIFIED', 'FEDEX_EXPRESS_FREIGHT_RAMP', 'FEDEX_EXPRESS_STATION', 'FEDEX_GROUND_TERMINAL']],
            'locationNumber?' => 'int',
            'id?' => 'string',
            'locationId?' => 'string',
            'locationContactAndAddress?' => '@ContactAndAddress',
            'clearanceNumber?' => 'string',
            'inclusionSpecifications?' => ['@CarrierCode']
        ];

        $schemas['DistributionLocations_1'] = [
            'type?' => ['@type' => 'string', '@enum' => ['CUSTOMER_SPECIFIED', 'FEDEX_EXPRESS_FREIGHT_RAMP', 'FEDEX_EXPRESS_STATION', 'FEDEX_GROUND_TERMINAL']],
            'locationNumber?' => 'int',
            'id?' => 'string',
            'locationId?' => 'string',
            'locationContactAndAddress?' => '@ContactAndAddress_1',
            'clearanceNumber?' => 'string',
            'inclusionSpecifications?' => ['@CarrierCode']
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

        $schemas['DocTabContentBarcoded_1'] = [
            'symbology?' => ['@type' => 'string', '@enum' => ['CODABAR', 'CODE128', 'CODE128_WIDEBAR', 'CODE128B', 'CODE128C', 'CODE39', 'CODE93', 'I2OF5', 'MANUAL', 'PDF417', 'POSTNET', 'QR_CODE', 'UCC128']],
            'specification?' => '@DocTabZoneSpecification_1'
        ];

        $schemas['DocTabContentZone001'] = [
            'docTabZoneSpecifications?' => ['@DocTabZoneSpecification']
        ];

        $schemas['DocTabContentZone001_1'] = [
            'docTabZoneSpecifications?' => ['@DocTabZoneSpecification_1']
        ];

        $schemas['DocTabContent_1'] = [
            'docTabContentType?' => ['@type' => 'string', '@enum' => ['BARCODED', 'CUSTOM', 'MINIMUM', 'STANDARD', 'ZONE001']],
            'zone001?' => '@DocTabContentZone001_1',
            'barcoded?' => '@DocTabContentBarcoded_1'
        ];

        $schemas['DocTabZoneSpecification'] = [
            'zoneNumber?' => 'int',
            'header?' => 'string',
            'dataField?' => 'string',
            'literalValue?' => 'string',
            'justification?' => ['@type' => 'string', '@enum' => ['LEFT', 'RIGHT']]
        ];

        $schemas['DocTabZoneSpecification_1'] = [
            'zoneNumber?' => 'int',
            'header?' => 'string',
            'dataField?' => 'string',
            'literalValue?' => 'string',
            'justification?' => ['@type' => 'string', '@enum' => ['LEFT', 'RIGHT']]
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

        $schemas['EMailDetail_1'] = [
            'emailNotificationFormatType?' => ['@type' => 'string', '@enum' => ['SMS_TEXT_MESSAGE', 'TEXT', 'HTML']],
            'address?' => 'string',
            'permissions?' => 'object'
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

        $schemas['EmailNotificationDetail'] = [
            'aggregationType?' => ['@type' => 'string', '@enum' => ['PER_PACKAGE', 'PER_SHIPMENT']],
            'emailNotificationRecipients?' => ['@EmailNotificationRecipient'],
            'personalMessage?' => 'string'
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

        $schemas['ExportDetail'] = [
            'destinationControlDetail?' => '@DestinationControlDetail',
            'b13AFilingOption' => ['@type' => 'string', '@enum' => ['NOT_REQUIRED', 'MANUALLY_ATTACHED', 'FILED_ELECTRONICALLY', 'SUMMARY_REPORTING', 'FEDEX_TO_STAMP']],
            'exportComplianceStatement?' => 'string',
            'permitNumber?' => 'string'
        ];

        $schemas['ExportDetail_1'] = [
            'destinationControlDetail?' => '@DestinationControlDetail_1',
            'b13AFilingOption' => ['@type' => 'string', '@enum' => ['NOT_REQUIRED', 'MANUALLY_ATTACHED', 'FILED_ELECTRONICALLY', 'SUMMARY_REPORTING', 'FEDEX_TO_STAMP']],
            'exportComplianceStatement?' => 'string',
            'permitNumber' => 'string'
        ];

        $schemas['Formats'] = [
            'docType?' => ['@type' => 'string', '@enum' => ['PDF', 'PNG']],
            'stockType?' => ['@type' => 'string', '@enum' => ['PAPER_LETTER']]
        ];

        $schemas['Formats_1'] = [
            'docType?' => ['@type' => 'string', '@enum' => ['ZPLII', 'EPL2', 'PDF', 'PNG', 'DIB', 'DOC', 'DPL', 'GIF', 'RTF', 'TEXT']],
            'stockType?' => ['@type' => 'string', '@enum' => ['OP_900_LG', 'OP_900_LG_B', 'OP_900_LL', 'OP_900_LL_B', 'OP_950_PAPER_4_PER_PAGE_PORTRAIT', 'PAPER_4X6', 'PAPER_LETTER', 'STOCK_4X6', 'STOCK_4X675_LEADING_DOC_TAB', 'STOCK_4X675_TRAILING_DOC_TAB', 'STOCK_4X8', 'STOCK_4X9_LEADING_DOC_TAB', 'STOCK_4X9_TRAILING_DOC_TAB']]
        ];

        $schemas['FreightChargeMoney'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['FreightChargeMoney_1'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['FullSchema_DeleteShipment'] = [
            'accountNumber' => '@AccountNumber',
            'deletionControl?' => ['@type' => 'string', '@enum' => ['DELETE_ALL_PACKAGES', 'DELETE_ONE_PACKAGE']],
            'trackingNumber' => 'string',
            'resourceKey?' => 'string',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_ConfirmConsolidation'] = [
            'edtRequestType?' => ['@type' => 'string', '@enum' => ['ALL', 'NONE']],
            'rateRequestType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'CUSTOM', 'INCENTIVE', 'LIST', 'NONE', 'PREFERRED', 'RATED', 'RETAIL']],
            'labelSpecification' => '@LabelSpecification',
            'consolidationKey' => '@ConsolidationKey',
            'processingOptionType' => ['@type' => 'string', '@enum' => ['ALLOW_ASYNCHRONOUS']],
            'accountNumber' => '@AccountNumber',
            'consolidationDocumentSpecification?' => '@ConsolidationDocumentSpec',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_ConfirmConsolidationResults'] = [
            'accountNumber' => '@AccountNumber',
            'jobId' => 'string',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_CreateConsolidation'] = [
            'consolidationIndex?' => 'string',
            'requestedConsolidation' => '@RequestedConsolidation',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_CreateConsolidationShipment'] = [
            'accountNumber' => 'object',
            'consolidationKey' => '@ConsolidationKey',
            'processingOptionType?' => ['@type' => 'string', '@enum' => ['ALLOW_ASYNCHRONOUS']],
            'index?' => 'string',
            'shipAction?' => ['@type' => 'string', '@enum' => ['CONFIRM', 'TRANSFER']],
            'requestedShipment' => '@RequestedConsolidationShipment',
            'processingOptions?' => [['@type' => 'string', '@enum' => ['INCLUDE_PICKUPRATES']]],
            'labelResponseOptions?' => '@LableResponseOptions',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_ModifyConsolidation'] = [
            'consolidationKey' => '@ConsolidationKey',
            'requestedConsolidation' => '@RequestedConsolidation',
            'accountNumber' => '@AccountNumber',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_RetrieveConsolidation'] = [
            'accountNumber' => '@AccountNumber_2',
            'consolidationKey' => '@ConsolidationKey',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_deleteConsolidation'] = [
            'accountNumber' => '@AccountNumber',
            'consolidationKey' => '@ConsolidationKey',
            'trackingId' => '@TrackingIds',
            'version?' => '@Version'
        ];

        $schemas['HandlingCostCharges'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['HandlingCostCharges_1'] = [
            'amount?' => 'number',
            'currency' => 'string'
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
            'reportableQuantity' => ['@type' => 'bool', '@enum' => [true, false]],
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
            'cargoAircraftOnly' => ['@type' => 'bool', '@enum' => [true, false]]
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
            'locationId' => 'string'
        ];

        $schemas['ImporterOfRecord'] = '@Party_1';

        $schemas['ImporterOfRecord_1'] = [
            'address' => '@PartyAddress_1',
            'contact' => '@PartyContact_1',
            'accountNumber?' => '@PartyAccountNumber_2'
        ];

        $schemas['InternationalControlledExportDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['DEA_036', 'DEA_236', 'DEA_486', 'DSP_05', 'DSP_61', 'DSP_73', 'DSP_85', 'DSP_94', 'DSP_LICENSE_AGREEMENT', 'FROM_FOREIGN_TRADE_ZONE', 'WAREHOUSE_WITHDRAWAL']],
            'foreignTradeZoneCode?' => 'string',
            'entryNumber?' => 'string',
            'licenseOrPermitNumber?' => 'string',
            'licenseOrPermitExpirationDate?' => 'string'
        ];

        $schemas['InternationalDistributionDetail'] = [
            'dropOffType?' => ['@type' => 'string', '@enum' => ['BUSINESS_SERVICE_CENTER', 'DROP_BOX', 'REGULAR_PICKUP', 'REQUEST_PICKUP', 'STATION']],
            'totalDimensions' => '@Dimensions',
            'totalInsuredValue?' => '@TotalInsuredMoney',
            'unitSystem?' => ['@type' => 'string', '@enum' => ['ENGLISH', 'METRIC']],
            'declarationCurrencies' => ['@Money_1'],
            'clearanceFacilityLocationId' => 'string'
        ];

        $schemas['InternationalDistributionDetail_1'] = [
            'dropOffType?' => ['@type' => 'string', '@enum' => ['BUSINESS_SERVICE_CENTER', 'DROP_BOX', 'REGULAR_PICKUP', 'REQUEST_PICKUP', 'STATION']],
            'totalDimensions' => '@Dimensions',
            'totalInsuredValue?' => '@TotalInsuredMoney_1',
            'unitSystem?' => ['@type' => 'string', '@enum' => ['ENGLISH', 'METRIC']],
            'declarationCurrencies' => ['@Money_1_1'],
            'clearanceFacilityLocationId' => 'string'
        ];

        $schemas['Label'] = [
            'type?' => 'string',
            'shippingDocumentDisposition?' => ['@type' => 'string', '@enum' => ['CONFIRMED', 'DEFERRED_QUEUED', 'DEFERRED_RETURNED', 'DEFERRED_STORED', 'EMAILED', 'QUEUED', 'RETURNED', 'STORED']],
            'imageType?' => ['@type' => 'string', '@enum' => ['DIB', 'DOC', 'DPL', 'EPL2', 'GIF', 'PDF', 'PNG', 'RTF', 'TEXT', 'ZPLII']],
            'resolution?' => 'int',
            'copiesToPrint?' => 'int',
            'parts?' => '@Label_parts'
        ];

        $schemas['LabelResponseVO'] = [
            'copiesToPrint?' => 'int',
            'contentType?' => ['@type' => 'string', '@enum' => ['LABEL', 'BILL_OF_LADING', 'GAA_FORM', 'HAZMAT_LABEL', 'END_OF_DAY_HAZMAT_REPORT', 'MANIFEST_REPORT', 'MULTIWEIGHT_REPORT', 'MERGED_LABEL_DOCUMENTS', 'AUXILIARY', 'RETURN_INSTRUCTIONS', 'ACCEPTANCE_LABEL', 'COMMERCIAL_INVOICE', 'PROFORMA_INVOICE', 'USMCA_CERTIFICATION_OF_ORIGIN', 'CERTIFICATE_OF_ORIGIN', 'MERGED_LABELS_ONLY']],
            'trackingNumber?' => 'string',
            'docType?' => 'string',
            'encodedLabel?' => 'string',
            'url?' => 'string'
        ];

        $schemas['LabelSpecification'] = [
            'labelFormatType?' => ['@type' => 'string', '@enum' => ['COMMON2D', 'ERROR', 'LABEL_DATA_ONLY', 'MAILROOM', 'NO_LABEL', 'OPERATIONAL_LABEL', 'PRE_COMMON2D']],
            'labelStockType?' => ['@type' => 'string', '@enum' => ['PAPER_4X6', 'PAPER_4X675', 'PAPER_4X8', 'PAPER_4X9', 'PAPER_7X475', 'PAPER_85X11_BOTTOM_HALF_LABEL', 'PAPER_85X11_TOP_HALF_LABEL', 'PAPER_LETTER', 'STOCK_4X675_LEADING_DOC_TAB', 'STOCK_4X8', 'STOCK_4X9_LEADING_DOC_TAB', 'STOCK_4X6', 'STOCK_4X675_TRAILING_DOC_TAB', 'STOCK_4X9_TRAILING_DOC_TAB', 'STOCK_4X675', 'STOCK_4X9', 'STOCK_4X8.5_TRAILING_DOC_TAB', 'STOCK_4X10.5_TRAILING_DOC_TAB']],
            'imageType?' => ['@type' => 'string', '@enum' => ['ZPLII', 'EPL2', 'PDF', 'PNG']],
            'processingOptionsRequested?' => '@LabelSpecification_processingOptionsRequested',
            'dispositions?' => ['@ShippingDocumentDispositionDetail'],
            'labelPrintingOrientation?' => ['@type' => 'string', '@enum' => ['BOTTOM_EDGE_OF_TEXT_FIRST', 'TOP_EDGE_OF_TEXT_FIRST']],
            'labelRotation?' => ['@type' => 'string', '@enum' => ['LEFT', 'RIGHT', 'UPSIDE_DOWN', 'NONE']],
            'labelOrder?' => ['@type' => 'string', '@enum' => ['SHIPPING_LABEL_FIRST', 'SHIPPING_LABEL_LAST']],
            'printedLabelOrigin' => '@ContactAndAddress',
            'customerSpecifiedDetail?' => '@CustomerSpecifiedLabelDetail',
            'resolution?' => 'int'
        ];

        $schemas['LabelSpecification_1'] = [
            'labelFormatType?' => ['@type' => 'string', '@enum' => ['COMMON2D', 'ERROR', 'LABEL_DATA_ONLY', 'MAILROOM', 'NO_LABEL', 'OPERATIONAL_LABEL', 'PRE_COMMON2D']],
            'labelStockType?' => ['@type' => 'string', '@enum' => ['PAPER_4X6', 'PAPER_4X675', 'PAPER_4X8', 'PAPER_4X9', 'PAPER_7X475', 'PAPER_85X11_BOTTOM_HALF_LABEL', 'PAPER_85X11_TOP_HALF_LABEL', 'PAPER_LETTER', 'STOCK_4X675_LEADING_DOC_TAB', 'STOCK_4X8', 'STOCK_4X9_LEADING_DOC_TAB', 'STOCK_4X6', 'STOCK_4X675_TRAILING_DOC_TAB', 'STOCK_4X9_TRAILING_DOC_TAB', 'STOCK_4X675', 'STOCK_4X9', 'STOCK_4X8.5_TRAILING_DOC_TAB', 'STOCK_4X10.5_TRAILING_DOC_TAB']],
            'imageType?' => ['@type' => 'string', '@enum' => ['ZPLII', 'EPL2', 'PDF', 'PNG', 'DIB', 'DOC', 'DPL', 'GIF', 'RTF', 'TEXT']],
            'processingOptionsRequested?' => '@LabelSpecification_processingOptionsRequested',
            'dispositions?' => ['@ShippingDocumentDispositionDetail_1'],
            'resolution?' => 'int',
            'labelPrintingOrientation?' => ['@type' => 'string', '@enum' => ['BOTTOM_EDGE_OF_TEXT_FIRST', 'TOP_EDGE_OF_TEXT_FIRST']],
            'labelRotation?' => ['@type' => 'string', '@enum' => ['LEFT', 'RIGHT', 'UPSIDE_DOWN', 'NONE']],
            'labelOrder?' => ['@type' => 'string', '@enum' => ['SHIPPING_LABEL_FIRST', 'SHIPPING_LABEL_LAST']],
            'printedLabelOrigin' => '@ContactAndAddress_1',
            'customerSpecifiedDetail?' => '@CustomerSpecifiedLabelDetail_1'
        ];

        $schemas['LabelSpecification_processingOptionsRequested'] = [
            'options?' => ['@type' => 'string', '@enum' => ['INCLUDE_DOC_TAB_ON_ALL_LABELS', 'PRE_EACI_LABEL']]
        ];

        $schemas['Label_parts'] = [
            'documentPartSequenceNumber?' => 'int',
            'image?' => 'mixed'
        ];

        $schemas['LableResponseOptions'] = ['@type' => 'string', '@enum' => ['URL_ONLY', 'LABEL']];

        $schemas['LicenseOrPermitDetail'] = [
            'number?' => 'string',
            'effectiveDate?' => 'string',
            'expirationDate?' => 'string'
        ];

        $schemas['Locale'] = [
            'country?' => 'string',
            'language?' => 'string'
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

        $schemas['ModifyConsolidationOutputVO'] = [
            'modifyConsolidation?' => '@RetrievedConsolidation'
        ];

        $schemas['ModifyConsolidationResponse'] = [
            'customerTransactionId?' => 'string',
            'transactionId?' => 'string',
            'output?' => '@ModifyConsolidationOutputVO'
        ];

        $schemas['Money'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['Money_1'] = [
            'currency?' => 'string',
            'value' => ['@type' => 'string', '@enum' => ['CUSTOMS_VALUE', 'INSURED_VALUE']]
        ];

        $schemas['Money_1_1'] = [
            'currency?' => 'string',
            'value' => ['@type' => 'string', '@enum' => ['CUSTOMS_VALUE', 'INSURED_VALUE']]
        ];

        $schemas['Money_2'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['NetExplosiveDetail'] = [
            'amount?' => 'number',
            'units?' => 'string',
            'type?' => 'string'
        ];

        $schemas['OperationalInstructions'] = [
            'number?' => 'int',
            'content?' => 'string'
        ];

        $schemas['Origin'] = [
            'address?' => '@PartyAddress',
            'contact?' => '@PartyContact'
        ];

        $schemas['Origin_1'] = [
            'address?' => '@PartyAddress_1',
            'contact?' => '@PartyContact_1'
        ];

        $schemas['PackageBarcodes'] = [
            'binaryBarcodes?' => ['@BinaryBarcode'],
            'stringBarcodes?' => ['@StringBarcode']
        ];

        $schemas['PackageCODDetail'] = [
            'codCollectionAmount?' => '@Money'
        ];

        $schemas['PackageDescription'] = [
            'packagingType?' => 'string',
            'code?' => 'string',
            'names?' => ['@ProductName'],
            'astraDescription?' => 'string',
            'description?' => 'string'
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
            'priorityAlertDetail?' => '@PriorityAlertDetail',
            'signatureOptionType?' => ['@type' => 'string', '@enum' => ['SERVICE_DEFAULT', 'NO_SIGNATURE_REQUIRED', 'INDIRECT', 'DIRECT', 'ADULT']],
            'signatureOptionDetail?' => '@SignatureOptionDetail',
            'alcoholDetail?' => '@AlcoholDetail',
            'dangerousGoodsDetail?' => '@DangerousGoodsDetail',
            'packageCODDetail?' => '@PackageCODDetail',
            'pieceCountVerificationBoxCount?' => 'int',
            'batteryDetails?' => ['@BatteryDetail'],
            'dryIceWeight?' => '@Weight'
        ];

        $schemas['PackingCostCharges'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['PackingCostCharges_1'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['Party'] = [
            'address?' => '@Address_1',
            'contact?' => '@Contact_1',
            'accountNumber' => '@PartyAccountNumber_1'
        ];

        $schemas['Party1'] = [
            'address?' => '@Address_2',
            'contact?' => '@Contact_1',
            'accountNumber?' => '@PartyAccountNumber_1'
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

        $schemas['PartyAddress'] = [
            'streetLines' => ['string'],
            'city' => ['@type' => 'string', '@max' => 35],
            'stateOrProvinceCode?' => 'string',
            'postalCode' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['PartyAddress_1'] = [
            'streetLines' => ['string'],
            'city' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode' => 'string',
            'countryCode' => 'string'
        ];

        $schemas['PartyAddress_soldTo'] = [
            'streetLines' => ['string'],
            'city' => ['@type' => 'string', '@max' => 35],
            'stateOrProvinceCode' => 'string',
            'postalCode' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['PartyAddress_soldTo_1'] = [
            'streetLines' => ['string'],
            'city' => 'string',
            'stateOrProvinceCode' => 'string',
            'postalCode' => 'string',
            'countryCode' => 'string',
            'residential?' => ['@type' => 'bool', '@enum' => [true, false]]
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
            'phoneNumber' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['PartyContact_soldTo'] = [
            'personName' => 'string',
            'emailAddress?' => 'string',
            'phoneExtension?' => 'string',
            'phoneNumber' => 'string',
            'companyName' => 'string'
        ];

        $schemas['PartyContact_soldTo_1'] = [
            'personName' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber' => 'string',
            'companyName' => 'string'
        ];

        $schemas['Party_1'] = [
            'address?' => '@PartyAddress',
            'contact?' => '@PartyContact',
            'accountNumber?' => '@PartyAccountNumber',
            'tins?' => ['@TaxpayerIdentification'],
            'deliveryInstructions?' => 'string'
        ];

        $schemas['Party_1_1'] = [
            'address?' => '@PartyAddress_1',
            'contact?' => '@PartyContact_1',
            'accountNumber?' => '@PartyAccountNumber_2',
            'tins?' => ['@TaxpayerIdentification_1']
        ];

        $schemas['Party_2'] = [
            'address' => '@Address_1_1',
            'contact?' => '@Contact_1_1',
            'accountNumber?' => '@PartyAccountNumber_2'
        ];

        $schemas['Payment'] = [
            'payor?' => '@Payor',
            'paymentType?' => ['@type' => 'string', '@enum' => ['SENDER', 'RECIPIENT', 'THIRD_PARTY']]
        ];

        $schemas['Payment1'] = [
            'payor?' => '@Payor1',
            'paymentType?' => ['@type' => 'string', '@enum' => ['SENDER', 'THIRD_PARTY']]
        ];

        $schemas['Payment_1'] = [
            'payor?' => '@Payor_1',
            'paymentType?' => ['@type' => 'string', '@enum' => ['SENDER', 'RECIPIENT', 'THIRD_PARTY', 'BILL_ANONYMOUSLY', 'COLLECT', 'CASH', 'ACCOUNT', 'CONSIGNEE']]
        ];

        $schemas['Payor'] = [
            'responsibleParty' => '@Party'
        ];

        $schemas['Payor1'] = [
            'responsibleParty?' => '@Party1'
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

        $schemas['PickupDetail'] = [
            'readyDateTime?' => 'string',
            'latestPickupDateTime?' => 'string',
            'courierInstructions?' => 'string',
            'requestType?' => '@PickupRequestType',
            'requestSource?' => '@PickupRequestSourceType'
        ];

        $schemas['PickupRequestSourceType'] = ['@type' => 'string', '@enum' => ['AUTOMATION', 'CUSTOMER_SERVICE']];

        $schemas['PickupRequestType'] = ['@type' => 'string', '@enum' => ['FUTURE_DAY', 'SAME_DAY']];

        $schemas['PieceResponse'] = [
            'packageDocuments?' => ['@PieceResponses_1']
        ];

        $schemas['PieceResponses_1'] = [
            'masterTrackingNumber?' => 'string',
            'trackingNumber?' => 'string',
            'additionalChargesDiscount?' => 'number',
            'netRateAmount?' => 'number',
            'netChargeAmount?' => 'number',
            'netDiscountAmount?' => 'number',
            'packageDocuments?' => ['@LabelResponseVO'],
            'customerReferences?' => ['@CustomerReference'],
            'codcollectionAmount?' => 'number',
            'baseRateAmount?' => 'number'
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

        $schemas['RecipientParty'] = [
            'address?' => '@PartyAddress',
            'contact?' => '@PartyContact'
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

        $schemas['RequestedConsolidation'] = [
            'consolidationType' => ['@type' => 'string', '@enum' => ['INTERNATIONAL_DISTRIBUTION_FREIGHT', 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'INTERNATIONAL_GROUND_DISTRIBUTION', 'TRANSBORDER_DISTRIBUTION', 'INTERNATIONAL_PRIORITY_DISTRIBUTION']],
            'shipDatestamp?' => 'string',
            'shipper' => '@ShipperParty_1',
            'origin?' => '@Origin_1',
            'soldTo?' => '@SoldToParty_1',
            'bookingNumber?' => 'string',
            'distributionLocation?' => '@DistributionLocations_1',
            'consolidationDataSources?' => '@ConsolidationDataSources_1',
            'customerReferences?' => '@CustomerReferences_1',
            'labelSpecification?' => '@LabelSpecification_1',
            'internationalDistributionDetail' => '@InternationalDistributionDetail_1',
            'transborderDistributionDetail?' => '@TransborderDistributionDetail_1',
            'customsClearanceDetail' => '@CustomsClearanceDetail_1',
            'consolidationDocumentSpecification?' => '@ConsolidationDocumentSpec_1',
            'shippingChargesPayment?' => '@Payment_1',
            'description?' => 'string',
            'specialServicesRequested?' => '@RequestedConsolidation_specialServicesRequested',
            'processingOption?' => '@ConsolidationProcessingOptionsRequested',
            'crnShippingDocumentTypes?' => '@RequestedShippingDocumentType'
        ];

        $schemas['RequestedConsolidationShipment'] = [
            'specialServicesRequested?' => '@RequestedConsolidationShipment_specialServicesRequested',
            'shipDatestamp' => 'string',
            'dropOffType?' => ['@type' => 'string', '@enum' => ['BUSINESS_SERVICE_CENTER', 'DROP_BOX', 'REGULAR_PICKUP', 'REQUEST_COURIER', 'STATION']],
            'pickupType' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'serviceType' => ['@type' => 'string', '@enum' => ['INTERNATIONAL_DISTRIBUTION_FREIGHT', 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'INTERNATIONAL_GROUND_DISTRIBUTION', 'INTERNATIONAL_PRIORITY_DISTRIBUTION', 'TRANSBORDER_DISTRIBUTION']],
            'packagingType' => 'string',
            'totalWeight?' => '@Weight',
            'shipper' => '@ShipperParty',
            'recipients?' => ['@RecipientParty'],
            'pickupDetail?' => '@PickupDetail',
            'emailNotificationDetail?' => '@EmailNotificationDetail',
            'shippingChargesPayment' => '@Payment',
            'processingOption?' => '@RequestedConsolidationShipment_processingOption',
            'customsClearanceDetail?' => '@CustomsClearanceDetail',
            'labelSpecification' => '@LabelSpecification',
            'rateRequestTypes?' => ['@type' => 'string', '@enum' => ['LIST', 'NONE', 'PREFERRED']],
            'packageCount' => 'int',
            'requestedPackageLineItems' => ['@RequestedPackageLineItem'],
            'recipient?' => 'mixed'
        ];

        $schemas['RequestedConsolidationShipment_processingOption'] = [
            'options?' => ['@type' => 'string', '@enum' => ['ALLOW_PREVIOUS_SHIP_DATE', 'BYPASS_REGULATORY_VALIDATION', 'CONVENIENCE_MULTIPLE_PACKAGES', 'CUSTOM_TRANSIT_TIME', 'FUTURE_DAY_SHIPMENT', 'IATA_CLASS_AND_AIRCRAFT_ONLY', 'MANUAL_AIRBILL', 'NO_COMMITMENT_DATE_REQUESTED', 'PACKAGE_LEVEL_COMMODITIES', 'PRE_EIG_PROCESSING', 'PRE_ETD_ENHANCEMENTS', 'PRE_FXF2020_HANDLING_UNIT_DETAILS', 'PRE_GPR', 'PRE_GRAPE2_SHIPMENT', 'PRE_MULTIPLIER_PROCESSING', 'REQUEST_LEGACY_GROUND_ECONOMY_LABEL', 'REQUEST_LEGACY_SMARTPOST_LABEL', 'REQUIRE_PRE_EPIC_BARCODEx']]
        ];

        $schemas['RequestedConsolidationShipment_specialServicesRequested'] = [
            'holdAtLocationDetail?' => '@HoldAtLocationDetail'
        ];

        $schemas['RequestedConsolidation_specialServicesRequested'] = [
            'specialServiceTypes?' => ['@type' => 'string', '@enum' => ['BROKER_SELECT_OPTION', 'PRIORITY_ALERT', 'SATURDAY_PICKUP']],
            'priorityAlertDetail?' => '@PriorityAlertDetail'
        ];

        $schemas['RequestedPackageLineItem'] = [
            'sequenceNumber?' => 'int',
            'subPackagingType?' => 'string',
            'customerReferences?' => ['@CustomerReferences'],
            'declaredValue?' => '@Money',
            'weight' => '@Weight',
            'dimensions?' => '@Dimensions',
            'physicalPackaging?' => 'string',
            'groupPackageCount?' => 'int',
            'itemDescriptionForClearance?' => 'string',
            'contentRecord?' => ['@ContentRecord'],
            'itemDescription?' => 'string',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'packageSpecialServices?' => '@PackageSpecialServicesRequested',
            'associatedFreightLineItems?' => ['@AssociatedFreightLineItemDetail'],
            'commodities?' => ['@Commodity']
        ];

        $schemas['RequestedShippingDocumentType'] = ['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'CUSTOM_PACKAGE_DOCUMENT', 'CUSTOM_SHIPMENT_DOCUMENT', 'CUSTOMER_SPECIFIED_LABELS', 'EXPORT_DECLARATION', 'DANGEROUS_GOODS_SHIPPERS_DECLARATION', 'FREIGHT_ADDRESS_LABEL', 'GENERAL_AGENCY_AGREEMENT', 'LABEL', 'USMCA_CERTIFICATION_OF_ORIGIN', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN', 'OP_900', 'PENDING_SHIPMENT_EMAIL_NOTIFICATION', 'PRO_FORMA_INVOICE', 'RETURN_INSTRUCTIONS', 'FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING', 'VICS_BILL_OF_LADING']];

        $schemas['RetrieveConsolidationResponse'] = [
            'customerTransactionId?' => 'string',
            'transactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_RetrieveConsolidation'
        ];

        $schemas['RetrievedConsolidation'] = [
            'consolidationType?' => ['@type' => 'string', '@enum' => ['INTERNATIONAL_DISTRIBUTION_FREIGHT', 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'INTERNATIONAL_GROUND_DISTRIBUTION', 'TRANSBORDER_DISTRIBUTION', 'INTERNATIONAL_PRIORITY_DISTRIBUTION']],
            'shipDatestamp?' => 'string',
            'trackingIds?' => ['@TrackingIds'],
            'shipper?' => '@ShipperParty',
            'origin?' => '@Origin',
            'soldTo?' => '@SoldToParty',
            'bookingNumber?' => 'string',
            'consolidationDataSources?' => '@ConsolidationDataSources',
            'customerReferences?' => ['@CustomerReferences'],
            'labelSpecification?' => '@LabelSpecification',
            'internationalDistributionDetail?' => '@InternationalDistributionDetail',
            'transborderDistributionDetail?' => '@TransborderDistributionDetail',
            'customsClearanceDetail?' => '@CustomsClearanceDetail',
            'consolidationDocumentSpecification?' => '@ConsolidationDocumentSpec',
            'distributionLocations?' => '@DistributionLocations',
            'shippingChargesPayment?' => '@Payment',
            'specialServicesRequested?' => '@SpecialServicesRequested'
        ];

        $schemas['SHPCResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@DeleteShipmentOutputVO'
        ];

        $schemas['ServiceCategory'] = 'string';

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

        $schemas['ShipmentAdvisoryDetails'] = [
            'regulatoryAdvisory?' => '@RegulatoryAdvisoryDetail'
        ];

        $schemas['ShipmentConsolidationDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['INTERNATIONAL_DISTRIBUTION_FREIGHT', 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'INTERNATIONAL_GROUND_DISTRIBUTION', 'INTERNATIONAL_PRIORITY_DISTRIBUTION', 'TRANSBORDER_DISTRIBUTION']],
            'role?' => ['@type' => 'string', '@enum' => ['CONSOLIDATION_DOCUMENTS_SHIPMENT', 'CRN_SHIPMENT', 'MASTER_AIRWAYBILL_SHIPMENT']],
            'associatedTrackingIds?' => ['@AssociatedTrackingDetails']
        ];

        $schemas['ShipmentDryIceDetail'] = [
            'totalWeight' => '@Weight',
            'packageCount' => 'int',
            'processingOptions?' => '@ShipmentDryIceProcessingOptionsRequested'
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
            'shipmentRateDetails?' => ['@ShipmentRateDetail'],
            'totalNetTransportationAndPickupCharge?' => '@Money_1',
            'totalNetFedExTransportationAndPickupCharge?' => '@Money_1',
            'pickupRateDetail?' => '@ShipmentRating_pickupRateDetail'
        ];

        $schemas['ShipperParty'] = [
            'address' => '@PartyAddress',
            'contact' => '@PartyContact',
            'tins?' => ['@TaxpayerIdentification'],
            'accountNumber?' => '@AccountNumber'
        ];

        $schemas['ShipperParty_1'] = [
            'address' => '@PartyAddress_1',
            'contact' => '@PartyContact_1',
            'tins?' => ['@TaxpayerIdentification_1'],
            'accountNumber?' => '@AccountNumber_1'
        ];

        $schemas['ShippingDocument'] = [
            'type?' => 'string',
            'shippingDocumentDisposition?' => ['@type' => 'string', '@enum' => ['CONFIRMED', 'DEFERRED_QUEUED', 'DEFERRED_RETURNED', 'DEFERRED_STORED', 'EMAILED', 'QUEUED', 'RETURNED', 'STORED']],
            'imageType?' => ['@type' => 'string', '@enum' => ['DIB', 'DOC', 'DPL', 'EPL2', 'GIF', 'PDF', 'PNG', 'RTF', 'TEXT', 'ZPLII']],
            'resolution?' => 'int',
            'copiesToPrint?' => 'int',
            'parts?' => ['@ShippingDocument_parts']
        ];

        $schemas['ShippingDocumentDispositionDetail'] = [
            'eMailDetail?' => '@ShippingDocumentEmailDetail',
            'dispositionType?' => ['@type' => 'string', '@enum' => ['CONFIRMED', 'DEFERRED_QUEUED', 'DEFERRED_RETURNED', 'DEFERRED_STORED', 'EMAILED', 'QUEUED', 'RETURNED', 'STORED']],
            'grouping?' => ['@type' => 'string', '@enum' => ['CONSOLIDATED_BY_DOCUMENT_TYPE', 'CONSOLIDATED_BY_IMAGE_TYPE', 'INDIVIDUAL']],
            'storageDetail?' => '@ShippingDocumentStorageDetail',
            'printDetail?' => '@ShippingDocumentPrintDetail'
        ];

        $schemas['ShippingDocumentDispositionDetail_1'] = [
            'eMailDetail?' => '@ShippingDocumentEmailDetail_1',
            'dispositionType?' => ['@type' => 'string', '@enum' => ['CONFIRMED', 'DEFERRED_QUEUED', 'DEFERRED_RETURNED', 'DEFERRED_STORED', 'EMAILED', 'QUEUED', 'RETURNED', 'STORED']],
            'grouping?' => ['@type' => 'string', '@enum' => ['CONSOLIDATED_BY_DOCUMENT_TYPE', 'CONSOLIDATED_BY_IMAGE_TYPE', 'INDIVIDUAL']],
            'storageDetail?' => '@ShippingDocumentStorageDetail',
            'printDetail?' => '@ShippingDocumentPrintDetail'
        ];

        $schemas['ShippingDocumentEmailDetail'] = [
            'eMailRecipients' => ['@ShippingDocumentEmailRecipient'],
            'locale?' => '@Locale',
            'grouping?' => ['@type' => 'string', '@enum' => ['BY_RECIPIENT', 'NONE']]
        ];

        $schemas['ShippingDocumentEmailDetail_1'] = [
            'eMailRecipients' => ['@ShippingDocumentEmailRecipient'],
            'locale?' => '@Locale',
            'grouping?' => ['@type' => 'string', '@enum' => ['BY_RECIPIENT', 'NONE']]
        ];

        $schemas['ShippingDocumentEmailRecipient'] = [
            'emailAddress?' => 'string',
            'recipientType' => ['@type' => 'string', '@enum' => ['BROKER', 'OTHER', 'RECIPIENT', 'SHIPPER', 'THRID_PARTY', 'OTHER1', 'OTHER2']]
        ];

        $schemas['ShippingDocumentPrintDetail'] = [
            'printerId?' => 'string'
        ];

        $schemas['ShippingDocumentStorageDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['ASYNC_SERVICE', 'LOCAL_FILE_SYSTEM']],
            'filePath?' => 'string',
            'fileNaming?' => ['@type' => 'string', '@enum' => ['FAST', 'LEGACY_FXRS']],
            'fileSuffix?' => 'string'
        ];

        $schemas['ShippingDocument_parts'] = [
            'documentPartSequenceNumber?' => 'int',
            'image?' => 'string'
        ];

        $schemas['SignatureOptionDetail'] = [
            'signatureReleaseNumber?' => 'string'
        ];

        $schemas['SoldToParty'] = [
            'address?' => '@PartyAddress_soldTo',
            'contact?' => '@PartyContact_soldTo',
            'tins?' => ['@TaxpayerIdentification'],
            'accountNumber?' => '@AccountNumber'
        ];

        $schemas['SoldToParty_1'] = [
            'address?' => '@PartyAddress_soldTo_1',
            'contact?' => '@PartyContact_soldTo_1',
            'tins?' => ['@TaxpayerIdentification_1'],
            'accountNumber?' => '@AccountNumber_1'
        ];

        $schemas['SpecialServicesRequested'] = [
            'specialServiceTypes?' => ['@type' => 'string', '@enum' => ['BROKER_SELECT_OPTION', 'PRIORITY_ALERT', 'SATURDAY_PICKUP', 'INTERNATIONAL_CONTROLLED_EXPORT_SERVICE']],
            'priorityAlertDetail?' => '@PriorityAlertDetail',
            'internationalControlledExportDetail?' => '@InternationalControlledExportDetail'
        ];

        $schemas['StringBarcode'] = [
            'type?' => 'string',
            'value?' => 'string'
        ];

        $schemas['SuggestedCommodityDetail'] = [
            'description?' => 'string',
            'harmonizedCode?' => 'string'
        ];

        $schemas['SummaryDetail'] = [
            'expressPacakgeCount?' => 'int',
            'expressTotalPackageWeight?' => '@SummaryDetail_expressTotalPackageWeight',
            'expressTotalPackageAdjustedWeight?' => '@SummaryDetail_expressTotalPackageAdjustedWeight',
            'expressFreightPackageCount?' => 'int',
            'expressTotalFreightWeight?' => '@SummaryDetail_expressTotalFreightWeight',
            'expressTotalFreightAdjustedWeight?' => '@SummaryDetail_expressTotalFreightAdjustedWeight',
            'groundPackageCount?' => 'int',
            'groundTotalPackageWeight?' => '@SummaryDetail_groundTotalPackageWeight',
            'groundTotalPackageAdjustedWeight?' => '@SummaryDetail_groundTotalPackageAdjustedWeight',
            'numberOfUnconsolidatedCommodities?' => 'int'
        ];

        $schemas['SummaryDetail_expressTotalFreightAdjustedWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'int'
        ];

        $schemas['SummaryDetail_expressTotalFreightWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'int'
        ];

        $schemas['SummaryDetail_expressTotalPackageAdjustedWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'int'
        ];

        $schemas['SummaryDetail_expressTotalPackageWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'int'
        ];

        $schemas['SummaryDetail_groundTotalPackageAdjustedWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'int'
        ];

        $schemas['SummaryDetail_groundTotalPackageWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'int'
        ];

        $schemas['Surcharge'] = [
            'amount?' => '@Money',
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

        $schemas['TaxesOrMiscellaneousCharges'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['TaxesOrMiscellaneousCharges_1'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['TaxpayerIdentification'] = [
            'number?' => 'string',
            'tinType?' => ['@type' => 'string', '@enum' => ['PERSONAL_NATIONAL', 'PERSONAL_STATE', 'FEDERAL', 'BUSINESS_NATIONAL', 'BUSINESS_STATE', 'BUSINESS_UNION']],
            'usage?' => 'string',
            'effectiveDate?' => 'string',
            'expirationDate?' => 'string'
        ];

        $schemas['TaxpayerIdentification_1'] = [
            'number' => 'string',
            'tinType?' => ['@type' => 'string', '@enum' => ['PERSONAL_NATIONAL', 'PERSONAL_STATE', 'BUSINESS_NATIONAL', 'BUSINESS_STATE', 'BUSINESS_UNION', 'FEDERAL']],
            'usage?' => 'string',
            'effectiveDate?' => 'string',
            'expirationDate?' => 'string'
        ];

        $schemas['Tins'] = [
            'tinType?' => ['@type' => 'string', '@enum' => ['BUSINESS_NATIONAL', 'BUSINESS_STATE', 'BUSINESS_UNION', 'PERSONAL_NATIONAL', 'PERSONAL_STATE']],
            'number?' => 'string',
            'usage?' => 'string',
            'effectiveDate?' => 'string',
            'expirationDate?' => 'string'
        ];

        $schemas['TotalInsuredMoney'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['TotalInsuredMoney_1'] = [
            'amount?' => 'number',
            'currency' => 'string'
        ];

        $schemas['TrackingId'] = [
            'formId?' => 'string',
            'trackingIdType?' => 'string',
            'uspsApplicationId?' => 'string',
            'trackingNumber?' => 'string'
        ];

        $schemas['TrackingIds'] = [
            'trackingIdType' => 'string',
            'formId' => 'string',
            'trackingNumber' => 'string'
        ];

        $schemas['TransactionConsolidationOutputVO'] = [
            'completedConsolidationDetail?' => '@TransactionConsolidationOutputVO_completedConsolidationDetail',
            'pieceResponses?' => ['@PieceResponse'],
            'shipmentDocuments?' => ['@LabelResponseVO']
        ];

        $schemas['TransactionConsolidationOutputVO_completedConsolidationDetail'] = [
            'consolidationShipments?' => '@ConsolidationShipments',
            'consolidationDocumentTypes?' => '@ConsolidationDocumentSpec'
        ];

        $schemas['TransactionConsolidationShipmentResultsOutputVO'] = [
            'serviceType?' => 'string',
            'shipDatestamp?' => 'string',
            'serviceCategory?' => 'string',
            'shipmentDocuments?' => ['@LabelResponseVO'],
            'pieceResponses?' => ['@PieceResponse'],
            'serviceName?' => 'string',
            'completedShipmentDetail?' => '@ConsolidationCompletedShipmentDetail',
            'shipmentAdvisoryDetails?' => '@ShipmentAdvisoryDetails',
            'masterTrackingNumber?' => 'string'
        ];

        $schemas['TransactionCreateConsolidationShipmentResultsOutputVO'] = [
            'serviceType?' => 'string',
            'shipDatestamp?' => 'string',
            'serviceCategory?' => 'string',
            'shipmentDocuments?' => ['@LabelResponseVO'],
            'pieceResponses?' => ['@PieceResponse'],
            'serviceName?' => 'string',
            'completedShipmentDetail?' => '@CreateConsolidationCompletedShipmentDetail',
            'shipmentAdvisoryDetails?' => '@ShipmentAdvisoryDetails',
            'masterTrackingNumber?' => 'string'
        ];

        $schemas['TransborderDistributionDetail'] = [
            'specialServicesRequested?' => '@TransborderDistributionDetail_specialServicesRequested',
            'routing?' => ['@type' => 'string', '@enum' => ['BY_METER', 'BY_ORIGIN', 'CUSTOMER_SPECIFIED']],
            'summaryDetail?' => '@SummaryDetail'
        ];

        $schemas['TransborderDistributionDetail_1'] = [
            'specialServicesRequested?' => '@TransborderDistributionDetail_1_specialServicesRequested',
            'routing?' => ['@type' => 'string', '@enum' => ['BY_METER', 'BY_ORIGIN', 'CUSTOMER_SPECIFIED']],
            'summaryDetail?' => '@SummaryDetail'
        ];

        $schemas['TransborderDistributionDetail_1_specialServicesRequested'] = [
            'specialServiceTypes?' => ['@type' => 'string', '@enum' => ['FEDEX_LTL']],
            'transborderDistributionLtlDetail?' => '@TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail'
        ];

        $schemas['TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail'] = [
            'payment?' => '@TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment',
            'ltlScacCode?' => 'string'
        ];

        $schemas['TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment'] = [
            'paymentType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'CASH', 'COLLECT', 'CREDIT_CARD', 'EPAYMENT', 'RECIPIENT', 'SENDER', 'THIRD_PARTY']],
            'payor?' => '@TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_payor',
            'ePaymentDetail?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_ePaymentDetail',
            'creditCard?' => '@TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard',
            'creditCardTransactionDetail?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCardTransactionDetail',
            'amount?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_amount'
        ];

        $schemas['TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard'] = [
            'number?' => 'string',
            'creditCardType?' => ['@type' => 'string', '@enum' => ['AMEX', 'DANKORT', 'DINERS', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA']],
            'expirationDate?' => 'string',
            'lastAuthenticationByFedexDate?' => 'string',
            'verificationCode?' => 'string',
            'creditCardHolder?' => '@TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder'
        ];

        $schemas['TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder'] = [
            'contact?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder_contact',
            'address?' => '@TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_address'
        ];

        $schemas['TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_payor'] = [
            'responsibleParty?' => '@TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty',
            'associatedAccounts?' => ['@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_associatedAccounts']
        ];

        $schemas['TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty'] = [
            'accountNumber?' => 'string',
            'tins?' => ['@Tins'],
            'contact?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_contact',
            'address?' => '@TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_address'
        ];

        $schemas['TransborderDistributionDetail_1_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_address'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => ['@type' => 'bool', '@enum' => [true, false]],
            'urbanizationCode?' => 'string',
            'countryName?' => 'string',
            'geographicCoordinates?' => 'string',
            'classification?' => ['@type' => 'string', '@enum' => ['BUSINESS', 'MIXED', 'RESIDENTIAL', 'UNCONFIRMED_BUSINESS', 'UNCONFIRMED_RESIDENCE', 'UNKOWN']]
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested'] = [
            'specialServiceTypes?' => ['@type' => 'string', '@enum' => ['FEDEX_LTL']],
            'transborderDistributionLtlDetail?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail'] = [
            'payment?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment',
            'ltlScacCode?' => 'string'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment'] = [
            'paymentType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'CASH', 'COLLECT', 'CREDIT_CARD', 'EPAYMENT', 'RECIPIENT', 'SENDER', 'THIRD_PARTY']],
            'payor?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor',
            'ePaymentDetail?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_ePaymentDetail',
            'creditCard?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard',
            'creditCardTransactionDetail?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCardTransactionDetail',
            'amount?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_amount'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_amount'] = [
            'currency?' => 'string',
            'amount?' => 'number'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard'] = [
            'number?' => 'string',
            'creditCardType?' => ['@type' => 'string', '@enum' => ['AMEX', 'DANKORT', 'DINERS', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA']],
            'expirationDate?' => 'string',
            'lastAuthenticationByFedexDate?' => 'string',
            'verificationCode?' => 'string',
            'creditCardHolder?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCardTransactionDetail'] = [
            'authorizationId?' => 'string',
            'authorizationType?' => ['@type' => 'string', '@enum' => ['AUTHORIZE_NON_ACCOUNT', 'AUTHORIZE_WITH_ACCOUNT', 'VERIFY_WITH_ACCOUNT']],
            'settlementScheduleType?' => ['@type' => 'string', '@enum' => ['SETTLE_IMMEDIATELY', 'SETTLE_NEXT_DAY', 'SETTLE_ON_DELIVERY']],
            'fraudDetectionDetail?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCardTransactionDetail_fraudDetectionDetail',
            'payorAuthenticationCode?' => 'string',
            'payorAuthenticationSessionId?' => 'string',
            'attributesDetail?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCardTransactionDetail_attributesDetail'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCardTransactionDetail_attributesDetail'] = [
            'types?' => [['@type' => 'string', '@enum' => ['ORIGINATED_BY_AUTHORIZED_PERSONNEL', 'ORIGINATED_BY_UNAUTHORIZED_PERSONNEL']]]
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCardTransactionDetail_fraudDetectionDetail'] = [
            'ipAddress?' => 'string',
            'clientCookiesEnabled?' => 'bool',
            'devicePrint?' => 'string'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder'] = [
            'contact?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder_contact',
            'address?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_address'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder_contact'] = [
            'personName?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder_contact_personName',
            'phoneNumberCountryCode?' => 'string',
            'phoneNumberAreaCode?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'companyName?' => 'string',
            'pagerNumber?' => 'string',
            'faxNumber?' => 'string',
            'title?' => 'string'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_creditCard_creditCardHolder_contact_personName'] = [
            'prefix?' => 'string',
            'title?' => 'string',
            'firstName?' => 'string',
            'middleName?' => 'string',
            'lastName?' => 'string',
            'suffix?' => 'string'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_ePaymentDetail'] = [
            'epaymentProcessor?' => ['@type' => 'string', '@enum' => ['RETAIL_SINGLE_POINT_OF_SALE']],
            'ePaymentMode?' => ['@type' => 'string', '@enum' => ['APPLE_PAY', 'CASH', 'CHECK', 'CREDIT_CARD', 'GOOGLE_PAY', 'PAYPAL']],
            'creditCardType?' => ['@type' => 'string', '@enum' => ['AMEX', 'DANKORT', 'DINERS', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA']],
            'creditCardExpirationDate?' => 'string',
            'amount?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_ePaymentDetail_amount'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_ePaymentDetail_amount'] = [
            'currency?' => 'string',
            'amount?' => 'number'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor'] = [
            'responsibleParty?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty',
            'associatedAccounts?' => ['@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_associatedAccounts']
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_associatedAccounts'] = [
            'type?' => ['@type' => 'string', '@enum' => ['FEDEX_EXPRESS', 'FEDEX_FREIGHT', 'FEDEX_GROUND', 'FEDEX_OFFICE']],
            'accountNumber?' => 'string'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty'] = [
            'accountNumber?' => 'string',
            'tins?' => ['@Tins'],
            'contact?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_contact',
            'address?' => '@TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_address'
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_address'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => ['@type' => 'bool', '@enum' => [true, false]],
            'urbanizationCode?' => 'string',
            'countryName?' => 'string',
            'geographicCoordinates?' => 'string',
            'classification?' => ['@type' => 'string', '@enum' => ['BUSINESS', 'MIXED', 'RESIDENTIAL', 'UNCONFIRMED_BUSINESS', 'UNCONFIRMED_RESIDENCE', 'UNKOWN']]
        ];

        $schemas['TransborderDistributionDetail_specialServicesRequested_transborderDistributionLtlDetail_payment_payor_responsibleParty_contact'] = [
            'contactId?' => 'string',
            'personName?' => 'string',
            'tollFreePhoneNumber?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'companyName?' => 'string',
            'faxNumber?' => 'string',
            'pagerNumber?' => 'string',
            'title?' => 'string'
        ];

        $schemas['UploadDocumentReferenceDetail'] = [
            'documentId?' => 'string'
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

        $schemas['VariableHandlingCharges_1'] = [
            'variableHandlingCharge?' => '@Money_1',
            'fixedVariableHandlingCharge?' => '@Money_1',
            'percentVariableHandlingCharge?' => '@Money_1',
            'totalCustomerCharge?' => '@Money_1'
        ];

        $schemas['Version'] = [
            'major?' => 'int',
            'minor?' => 'int',
            'patch?' => 'int'
        ];

        $schemas['Weight'] = [
            'units' => 'string',
            'value' => 'number'
        ];

        $schemas['consolidationDataSourceType'] = ['@type' => 'string', '@enum' => ['ACCUMULATED', 'CLIENT']];

        $schemas['consolidationDataType'] = ['@type' => 'string', '@enum' => ['TOTAL_CUSTOMS_CODE', 'TOTAL_FREIGHT_CHARGES', 'TOTAL_HANDLING_COSTS', 'TOTAL_INSURANCE_CHARGES', 'TOTAL_INSURED_VALUE', 'TOTAL_PACKING_COSTS', 'TOTAL_TAXES_OR_MISCELLANEOUS_CHARGES']];

        $schemas['customerReferenceType'] = ['@type' => 'string', '@enum' => ['BILL_OF_LANDING', 'CUSTOMER_REFERENCE', 'DEPARTMENT_NUMBER', 'ELECTRONIC_PRODUCT_CODE', 'INTRACOUNTRY_REGULATORY_REFERENCE', 'INVOICE_NUMBER', 'P_O_NUMBER', 'PACKING_SLIP_NUMBER', 'RMA_ASSOCIATION', 'SHIPMENT_INTEGRITY', 'STORE_NUMBER']];

        $schemas['deleteConsolidationOutputVO'] = [
            'alerts?' => ['@Alert']
        ];

        $schemas['deleteConsolidationResponse'] = [
            'customerTransactionId?' => 'string',
            'transactionId?' => 'string',
            'output?' => '@deleteConsolidationOutputVO'
        ];

        $schemas['value'] = 'string';

        $schemas['BrokerDetail_broker'] = [
            'address' => '@PartyAddress',
            'contact' => '@PartyContact',
            'accountNumber?' => '@PartyAccountNumber',
            'tins?' => ['@TaxpayerIdentification'],
            'deliveryInstructions?' => 'string'
        ];

        $schemas['BrokerDetail_1_broker'] = [
            'address' => '@PartyAddress_1',
            'contact' => '@PartyContact_1',
            'accountNumber?' => '@PartyAccountNumber_2',
            'tins?' => ['@TaxpayerIdentification_1']
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

        $schemas['ShipmentRating_pickupRateDetail'] = [
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
