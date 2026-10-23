<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the requests and responses of the Service Availability API operations that FedexServiceAvailability
 * implements, generated from schemas/service-availability.json and written for SchemaValidator
 */
final class FedexServiceAvailabilitySchemas
{
    /**
     * Name of the definition of the Retrieve Services and Transit Times request
     * @var string
     */
    const TRANSIT_TIMES_REQUEST = 'Full_Schema_TransitTime';

    /**
     * Name of the definition of the Retrieve Services and Transit Times response
     * @var string
     */
    const TRANSIT_TIMES_RESPONSE = 'TransitTimeOutputVO';

    /**
     * Name of the definition of the Retrieve Services and Packaging Options request
     * @var string
     */
    const PACKAGE_OPTIONS_REQUEST = 'Full_Schema_Package_And_Service_Options';

    /**
     * Name of the definition of the Retrieve Services and Packaging Options response
     * @var string
     */
    const PACKAGE_OPTIONS_RESPONSE = 'CmdcResponseVO';

    /**
     * Name of the definition of the Retrieve Special Service Options request
     * @var string
     */
    const SPECIAL_OPTIONS_REQUEST = 'Full_Schema_Special_Service_Options';

    /**
     * Name of the definition of the Retrieve Special Service Options response
     * @var string
     */
    const SPECIAL_OPTIONS_RESPONSE = 'AvailabilitycxsResponseVO';

    /**
     * Get the definitions of all the requests and responses. The names of the definitions, and of their fields, are the ones
     * of schemas/service-availability.json, with these differences:
     *  - what the file writes as an allOf of a definition and an overlay that adds required keys (Weight, Dimensions, CodDetail,
     *    ShipmentDimensions, ShipmentDryIceDetail, KeyDisplayTextVO, FedExFreightBillingContactAndAddress and
     *    PackageAndOptionsRequestedShipment) is a definition with the fields of the base and the required keys of both, and what
     *    the file writes inline or as an overlay of a field is a definition of its own, named after the definition and the field
     *    that contain it (a tightened field has the suffix _overlay)
     *  - in the definitions that a request reaches (and so in Weight, Weight1 and Money, which the responses share), a number is
     *    a number or a text that is a number: the sample payloads of the file send weight.value and customsValue.amount as text
     *  - HoldAtLocationDetail requires a field called codDelocationContactAndAddress", with a stray quote, which the file never
     *    defines: it is accepted as an optional field of any type, and locationType is required as in the file
     *  - Address.postalCode and Address.countryCode have a description and no type: they accept any value
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['AccountNumber'] = '@AccountNumber1';

        $schemas['AccountNumber1'] = [
            'value?' => 'string'
        ];

        $schemas['Address'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'mixed',
            'countryCode' => 'mixed',
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

        $schemas['AlcoholDetail'] = [
            'alcoholRecipientType' => ['@type' => 'string', '@enum' => ['LICENSEE', 'CONSUMER']],
            'shipperAgreementType?' => 'string'
        ];

        $schemas['Alert'] = [
            'code?' => 'string',
            'alertType?' => ['@type' => 'string', '@enum' => ['NOTE', 'WARNING']],
            'message?' => 'string'
        ];

        $schemas['AvailabilitycxsResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO'
        ];

        $schemas['BaseProcessOutputVO'] = '@SpecialServicesOptionsOutputVO';

        $schemas['BaseProcessOutputVO_Package'] = '@PackageAndServiceOptionsOutputVO';

        $schemas['BatteryDetail'] = [
            'batteryPackingType?' => ['@type' => 'string', '@enum' => ['CONTAINED_IN_EQUIPMENT', 'PACKED_WITH_EQUIPMENT']],
            'batteryRegulatoryType?' => ['@type' => 'string', '@enum' => ['IATA_SECTION_II']],
            'batteryMaterialType?' => ['@type' => 'string', '@enum' => ['LITHIUM_METAL', 'LITHIUM_ION']]
        ];

        $schemas['CODDetail'] = [
            'codCollectionType?' => ['@type' => 'string', '@enum' => ['COMPANY_CHECK', 'ANY', 'CASH', 'GUARANTEED_FUNDS', 'PERSONAL_CHECK']],
            'codCollectionAmount' => '@Money'
        ];

        $schemas['CleansedAddressandLocationDetail'] = [
            'serviceArea?' => 'string',
            'countryCode?' => 'string',
            'locationId?' => 'string',
            'airportId?' => 'string',
            'postalCode?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'locationNumber?' => 'int'
        ];

        $schemas['CmdcResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_Package'
        ];

        $schemas['CodDetail'] = [
            'codCollectionType?' => ['@type' => 'string', '@enum' => ['COMPANY_CHECK', 'ANY', 'CASH', 'GUARANTEED_FUNDS', 'PERSONAL_CHECK']],
            'codCollectionAmount' => '@Money'
        ];

        $schemas['CodeDisplayTextVO'] = [
            'displayText?' => 'string',
            'code?' => 'string'
        ];

        $schemas['CommitDetail'] = [
            'brokerCommitTimestamp?' => 'string',
            'cutOffTime?' => 'string',
            'commodityName?' => 'string',
            'transitDays?' => '@CommitDetail_transitDays',
            'commitMessageDetails?' => 'string',
            'derivedDestinationDetail?' => '@CleansedAddressandLocationDetail',
            'dateDetail?' => '@CommitDetail_dateDetail'
        ];

        $schemas['CommitDetail_dateDetail'] = [
            'dayOfWeek?' => 'string',
            'time?' => 'string',
            'day?' => 'string'
        ];

        $schemas['CommitDetail_transitDays'] = [
            'description?' => 'string',
            'minimumTransitTime?' => ['@type' => 'string', '@enum' => ['EIGHT_DAYS', 'EIGHTEEN_DAYS', 'ELEVEN_DAYS', 'FIFTEEN_DAYS', 'FIVE_DAYS', 'FOUR_DAYS', 'FOURTEEN_DAYS', 'NINE_DAYS', 'NINETEEN_DAYS', 'ONE_DAY', 'SEVEN_DAYS', 'SEVENTEEN_DAYS', 'SIX_DAYS', 'SIXTEEN_DAYS', 'TEN_DAYS', 'THIRTEEN_DAYS', 'THREE_DAYS', 'TWELVE_DAYS', 'TWENTY_DAYS', 'TWO_DAYS', 'SMARTPOST_TRANSIT_DAYS', 'UNKNOWN']],
            'maximumTransitTime?' => 'string'
        ];

        $schemas['CompanyName'] = [
            'division?' => 'string',
            'companyCd?' => 'string',
            'name?' => 'string',
            'department?' => 'string',
            'storeId?' => 'string'
        ];

        $schemas['Contact'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string'
        ];

        $schemas['Contact1'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['ContactAncillaryDetail'] = [
            'emailAddresses?' => ['@EMailDetail'],
            'prefix?' => 'string',
            'phoneNumberDetails?' => ['@PhoneNumberDetail'],
            'companyName?' => '@CompanyName',
            'title?' => 'string'
        ];

        $schemas['ContactAndAddress'] = [
            'contact?' => '@Contact',
            'contactAncillaryDetail?' => '@ContactAncillaryDetail',
            'address?' => '@Address',
            'addressAncillaryDetail?' => '@AddressAncillaryDetail'
        ];

        $schemas['ContentRecord'] = [
            'itemNumber?' => 'string',
            'receivedQuantity?' => 'int',
            'description?' => 'string',
            'partNumber?' => 'string'
        ];

        $schemas['CoverageAmount'] = '@Money';

        $schemas['CustomerMessage'] = [
            'code?' => 'string',
            'message?' => 'string'
        ];

        $schemas['CustomerReference'] = [
            'customerReferenceType?' => ['@type' => 'string', '@enum' => ['BILL_OF_LADING', 'CUSTOMER_REFERENCE', 'DEPARTMENT_NUMBER', 'INVOICE_NUMBER', 'P_O_NUMBER', 'SHIPMENT_INTEGRITY', 'INTRACOUNTRY_REGULATORY_REFERENCE', 'STORE_NUMBER', 'RMA_ASSOCIATION', 'SHIPPER_ID1', 'SHIPPER_ID2']],
            'value?' => 'string'
        ];

        $schemas['DangerousGoodsDetail'] = [
            'accessibility?' => ['@type' => 'string', '@enum' => ['ACCESSIBLE', 'INACCESSIBLE']],
            'options?' => [['@type' => 'string', '@enum' => ['HAZARDOUS_MATERIALS', 'BATTERY', 'ORM_D', 'REPORTABLE_QUANTITIES', 'SMALL_QUANTITY_EXCEPTION', 'LIMITED_QUANTITIES_COMMODITIES']]]
        ];

        $schemas['Dimensions'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['Dimensions1'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['Distance'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KM', 'MI']],
            'value?' => 'number'
        ];

        $schemas['EMailDetail'] = [
            'emailNotificationFormatType?' => ['@type' => 'string', '@enum' => ['TEXT', 'HTML']],
            'address?' => 'string',
            'permissions?' => 'object'
        ];

        $schemas['FedExFreightAccountNumber'] = '@AccountNumber1';

        $schemas['FedExFreightBillingContactAndAddress'] = [
            'contact' => '@Contact',
            'contactAncillaryDetail?' => '@ContactAncillaryDetail',
            'address' => '@Address',
            'addressAncillaryDetail?' => '@AddressAncillaryDetail'
        ];

        $schemas['FedExFreightBillingContactAndAddress1'] = '@ContactAndAddress';

        $schemas['FixedValue'] = '@Money';

        $schemas['FreightDirectDataDetails'] = [
            'type?' => ['@type' => 'string', '@enum' => ['BASIC', 'BASIC_BY_APPOINTMENT', 'PREMIUM', 'STANDARD']],
            'transportationType?' => ['@type' => 'string', '@enum' => ['DELIVERY', 'PICKUP']],
            'eMailAddress?' => 'string',
            'phoneNumberDetails?' => ['@FreightPhoneNumberDetail']
        ];

        $schemas['FreightDirectDetail'] = [
            'freightDirectDataDetails?' => ['@FreightDirectDataDetails']
        ];

        $schemas['FreightPhoneNumberDetail'] = [
            'phoneNumberType?' => ['@type' => 'string', '@enum' => ['FAX', 'HOME', 'MOBILE', 'PAGER', 'TOLL_FREE', 'WORK']],
            'phoneNumber?' => 'string'
        ];

        $schemas['FreightShipmentDetail'] = [
            'shipmentDimensions?' => '@ShipmentDimensions',
            'alternateBilling?' => ['@type' => 'bool', '@enum' => [true, false]],
            'lineItem?' => ['@FreightShipmentLineItem'],
            'role?' => ['@type' => 'string', '@enum' => ['CONSIGNEE', 'SHIPPER']],
            'clientDiscountPercent?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'fedExFreightBillingContactAndAddress?' => '@FedExFreightBillingContactAndAddress',
            'aliasID?' => 'string',
            'liabilityCoverageDetail?' => '@FreightShipmentLiabilityCoverageDetail',
            'fedExFreightAccountNumber?' => '@FedExFreightAccountNumber',
            'totalHandlingUnits?' => 'int'
        ];

        $schemas['FreightShipmentLiabilityCoverageDetail'] = '@LiabilityCoverageDetail';

        $schemas['FreightShipmentLineItem'] = [
            'pieces?' => 'int',
            'volume?' => '@Volume',
            'handlingUnits?' => 'int',
            'freightClass' => ['@type' => 'string', '@enum' => ['CLASS_050', 'CLASS_055', 'CLASS_060', 'CLASS_065', 'CLASS_070', 'CLASS_077_5', 'CLASS_085', 'CLASS_092_5', 'CLASS_100', 'CLASS_110', 'CLASS_125', 'CLASS_150', 'CLASS_175', 'CLASS_200', 'CLASS_250', 'CLASS_300', 'CLASS_400', 'CLASS_500']],
            'description?' => 'string',
            'weight' => '@Weight_Without_Link',
            'packaging?' => 'string',
            'classProvidedByCustomer?' => 'bool',
            'hazardousMaterials?' => ['@type' => 'string', '@enum' => ['HAZARDOUS_MATERIALS', 'BATTERY', 'ORM_D', 'REPORTABLE_QUANTITIES', 'SMALL_QUANTITY_EXCEPTION', 'LIMITED_QUANTITIES_COMMODITIES']],
            'dimensions?' => '@Dimensions'
        ];

        $schemas['Full_Schema_Package_And_Service_Options'] = [
            'requestedShipment' => '@PackageAndOptionsRequestedShipment',
            'carrierCodes?' => [['@type' => 'string', '@enum' => ['FDXE', 'FDXG', 'FXFR', 'FXSP']]],
            'accountNumber?' => '@AccountNumber1',
            'systemOfMeasureType?' => ['@type' => 'string', '@enum' => ['IMPERIAL', 'METRIC']],
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_Special_Service_Options'] = [
            'requestedShipment' => '@SpecialServicesRequestedShipment',
            'accountNumber?' => '@AccountNumber1',
            'carrierCodes?' => [['@type' => 'string', '@enum' => ['FDXE', 'FDXG', 'FXSP', 'FXFR']]],
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_TransitTime'] = [
            'requestedShipment?' => '@TransitTimeRequestedShipment',
            'carrierCodes?' => [['@type' => 'string', '@enum' => ['FDXE', 'FDXG', 'FXSP']]],
            'version?' => '@Version'
        ];

        $schemas['HoldAtLocationDetail'] = [
            'locationId?' => 'string',
            'locationContactAndAddress?' => '@ContactAndAddress',
            'locationType' => ['@type' => 'string', '@enum' => ['FEDEX_AUTHORIZED_SHIP_CENTER', 'FEDEX_OFFICE', 'FEDEX_SELF_SERVICE_LOCATION', 'FEDEX_STAFFED', 'RETAIL_ALLICANCE_LOCATION', 'FEDEX_GROUND_TERMINAL', 'FEDEX_ONSITE', 'FEDEX_SHIP_AND_GET']],
            'codDelocationContactAndAddress"?' => 'mixed'
        ];

        $schemas['HomeDeliveryPremiumDetail'] = [
            'homedeliveryPremiumType?' => ['@type' => 'string', '@enum' => ['APPOINTMENT', 'DATE_CERTAIN', 'EVENING']]
        ];

        $schemas['InsuredValue'] = '@Money';

        $schemas['InternationalControlledExportDetail'] = [
            'type' => ['@type' => 'string', '@enum' => ['DEA_036', 'DEA_236', 'DEA_486', 'DSP_05', 'DSP_61', 'DSP_73', 'DSP_85', 'DSP_94', 'DSP_LICENSE_AGREEMENT', 'FROM_FOREIGN_TRADE_ZONE', 'WAREHOUSE_WITHDRAWAL']]
        ];

        $schemas['KeyDisplayTextVO'] = [
            'displayText' => 'string',
            'key' => 'string'
        ];

        $schemas['KeyDisplayTextVO1'] = [
            'displayText?' => 'string',
            'key?' => 'string'
        ];

        $schemas['KeyDisplayTextVO_2'] = '@KeyDisplayTextVO1';

        $schemas['LiabilityCoverageDetail'] = [
            'coverageType?' => ['@type' => 'string', '@enum' => ['NEW', 'USED_OR_RECONDITIONED']],
            'coverageAmount?' => '@CoverageAmount'
        ];

        $schemas['LocationDetail'] = [
            'geoPositionalCoordinates?' => '@LocationDetail_geoPositionalCoordinates'
        ];

        $schemas['LocationDetail_geoPositionalCoordinates'] = [
            'latitude?' => 'number',
            'longitude?' => 'number'
        ];

        $schemas['Money'] = [
            'amount?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'currency?' => 'string'
        ];

        $schemas['OptionalServices'] = [
            'serviceType?' => 'string',
            'issEnabled?' => 'bool',
            'signatureOptionsList?' => ['@CodeDisplayTextVO'],
            'packageSpecialServicesList?' => ['@PackageSpecialServiceOption'],
            'returnShipmentList?' => [['@type' => 'string', '@enum' => ['FEDEX_TAG', 'PENDING', 'PRINT_RETURN_LABEL', 'EMAIL_LABEL']]],
            'carrierCode?' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG', 'FXSP', 'FXFR']],
            'batteryOptionList?' => ['@BatteryDetail'],
            'alertList?' => ['@KeyDisplayTextVO'],
            'shipmentSpecialServicesList?' => ['@ShipmentSpecialServiceOption'],
            'alerts?' => ['@Alert']
        ];

        $schemas['PackageAndOptionsRequestedShipment'] = [
            'shipper' => '@Party_Shipper',
            'recipients' => ['@Party'],
            'shipDateStamp?' => 'string',
            'requestedPackageLineItems?' => ['@RequestedPackageLineItem'],
            'freightShipmentDetail?' => '@RequestedShipmentFreightShipmentDetail',
            'specialServicesRequested?' => '@RequestShipmentSpecialServicesRequested',
            'pickupType?' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']]
        ];

        $schemas['PackageAndOptionsRequestedShipment_2'] = [
            'shipper' => '@Party_Shipper',
            'recipients' => ['@Party'],
            'shipDateStamp?' => 'string',
            'requestedPackageLineItems?' => ['@RequestedPackageLineItem'],
            'freightShipmentDetail?' => '@RequestedShipmentFreightShipmentDetail',
            'specialServicesRequested?' => '@RequestShipmentSpecialServicesRequested',
            'pickupType?' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']]
        ];

        $schemas['PackageAndServiceOptionsOutputVO'] = [
            'packageOptions?' => ['@PackageTypeDetail'],
            'oneRate?' => 'bool',
            'advancedRegulatoryPackages?' => ['@KeyDisplayTextVO1'],
            'serviceOptions?' => ['@KeyDisplayTextVO_2'],
            'alerts?' => ['@Alert']
        ];

        $schemas['PackageSpecialServiceOption'] = [
            'customerIntegrationMode?' => 'string',
            'subType?' => 'string',
            'specialServiceType?' => 'string'
        ];

        $schemas['PackageSpecialServicesRequested'] = [
            'signatureOptionType?' => ['@type' => 'string', '@enum' => ['SERVICE_DEFAULT', 'NO_SIGNATURE_REQUIRED', 'INDIRECT', 'DIRECT', 'ADULT']],
            'alcoholDetail?' => '@AlcoholDetail',
            'dangerousGoodsDetail?' => '@DangerousGoodsDetail',
            'pieceCountVerificationBoxCount?' => 'int',
            'batteryDetails?' => ['@BatteryDetail'],
            'specialServiceTypes?' => ['string'],
            'codDetail' => '@PackageSpecialServicesRequestedCodDetail',
            'dryIceWeight?' => '@Weight_Without_Link',
            'standaloneBatteryDetails?' => ['@StandaloneBatteryDetails']
        ];

        $schemas['PackageSpecialServicesRequestedCodDetail'] = '@CodDetail';

        $schemas['PackageTypeDetail'] = [
            'serviceType?' => '@KeyDisplayTextVO',
            'oneRateMaxWeightAllowed?' => '@Weight',
            'maxWeightAllowed?' => '@Weight',
            'rateTypes?' => ['string'],
            'packagingInfoList?' => ['@SubPackageInfo'],
            'packageType?' => '@KeyDisplayTextVO',
            'maxMetricWeightAllowed?' => '@Weight',
            'oneRateMaxMetricWeightAllowed?' => '@Weight',
            'maxDeclaredValue?' => '@Money'
        ];

        $schemas['PackageWeight'] = '@Weight';

        $schemas['ParsedContactAndAddress'] = [
            'address?' => '@Address',
            'contact?' => '@Contact'
        ];

        $schemas['Party'] = [
            'address' => '@Address',
            'contact?' => '@Contact1',
            'accountNumber?' => '@AccountNumber'
        ];

        $schemas['Party_Shipper'] = [
            'address' => '@Address',
            'contact?' => '@Contact',
            'accountNumber?' => '@AccountNumber',
            'productInfo?' => '@ProductInfo'
        ];

        $schemas['PhoneNumber'] = [
            'areaCode?' => 'string',
            'extension?' => 'string',
            'countryCode?' => 'string',
            'personalIdentificationNumber?' => 'string',
            'localNumber?' => 'string'
        ];

        $schemas['PhoneNumberDetail'] = [
            'number?' => '@PhoneNumber',
            'permissions?' => 'object',
            'usage?' => ['@type' => 'string', '@enum' => ['PRIMARY', 'SECONDARY']],
            'type?' => ['@type' => 'string', '@enum' => ['FAX', 'HOME', 'MOBILE', 'PAGER', 'WORK']]
        ];

        $schemas['ProductInfo'] = [
            'customsValueAndWeightSpecifiedAsUnit?' => 'bool',
            'quantity?' => 'int',
            'customsValue?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'weightUnitOfMeasureCode?' => 'string',
            'description?' => 'string',
            'weight?' => '@PackageWeight',
            'sedRequired?' => 'bool',
            'productType?' => ['@type' => 'string', '@enum' => ['DOCUMENTS', 'COMMODITIES']],
            'unitOfMeasureCode?' => 'string',
            'countryOfManufactureCode?' => 'string'
        ];

        $schemas['RequestShipmentSpecialServicesRequested'] = '@ShipmentSpecialServicesRequested';

        $schemas['RequestedPackageLineItem'] = [
            'physicalPackaging?' => 'string',
            'groupPackageCount?' => 'int',
            'itemDescriptionForClearance?' => 'string',
            'customerReferences?' => ['@CustomerReference'],
            'contentRecord?' => '@RequestedPackageLineItemContentRecord',
            'declaredValue?' => '@InsuredValue',
            'weight' => '@Weight_With_Link',
            'dimensions?' => '@Dimensions',
            'packageSpecialServices?' => '@PackageSpecialServicesRequested'
        ];

        $schemas['RequestedPackageLineItemContentRecord'] = '@ContentRecord';

        $schemas['RequestedShipment'] = [
            'shipper' => '@Party',
            'recipient' => '@Party',
            'serviceType?' => 'string',
            'packagingType' => 'string',
            'shipDateStamp?' => 'string',
            'requestedPackageLineItems' => ['@RequestedPackageLineItem'],
            'dropoffType?' => ['@type' => 'string', '@enum' => ['REGULAR_PICKUP', 'REQUEST_COURIER', 'DROP_BOX', 'BUSINESS_SERVICE_CENTER', 'STATION']],
            'variableHandlingChargeDetail?' => '@RequestedShipmentVariableHandlingChargeDetail',
            'freightShipmentDetail?' => '@RequestedShipmentFreightShipmentDetail',
            'specialServicesRequested?' => '@RequestShipmentSpecialServicesRequested',
            'pickupType?' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'shippingDocumentSpecification?' => 'string'
        ];

        $schemas['RequestedShipmentFreightShipmentDetail'] = '@FreightShipmentDetail';

        $schemas['RequestedShipmentVariableHandlingChargeDetail'] = '@VariableHandlingChargeDetail';

        $schemas['ReturnEmailDetail'] = [
            'merchantPhoneNumber?' => 'string',
            'allowedSpecialService?' => [['@type' => 'string', '@enum' => ['SATURDAY_DELIVERY', 'SATURDAY_PICKUP']]]
        ];

        $schemas['ReturnShipmentDetail'] = [
            'returnType' => ['@type' => 'string', '@enum' => ['FEDEX_TAG', 'NET_RETURN', 'PENDING', 'PRINT_RETURN_LABEL', 'EMAIL_LABEL', 'VOICE_CALL_TAG']]
        ];

        $schemas['SaturdayDeliveryDetail'] = [
            'locationContactAndAddress?' => '@ParsedContactAndAddress'
        ];

        $schemas['ShipmentDimensions'] = [
            'length' => 'int',
            'width' => 'int',
            'height' => 'int',
            'units' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['ShipmentDryIceDetail'] = [
            'totalWeight' => '@ShipmentDryIceDetail_totalWeight_overlay',
            'packageCount' => 'int'
        ];

        $schemas['ShipmentDryIceDetail1'] = [
            'totalWeight?' => '@Weight1',
            'packageCount?' => 'int'
        ];

        $schemas['ShipmentDryIceDetail_totalWeight'] = [
            'units' => 'string',
            'value' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['ShipmentSpecialServiceOption'] = [
            'customerIntegrationMode?' => 'string',
            'astraDescription?' => 'string',
            'ltlPaymentTerms?' => [['@type' => 'string', '@enum' => ['PREPAID', 'COLLECT', 'COLLECT_SECTION_7_SIGNED', 'COLLECT_SECTION_9_SIGNED']]],
            'subType?' => 'string',
            'value?' => 'string',
            'specialServiceType?' => 'string'
        ];

        $schemas['ShipmentSpecialServicesRequested'] = [
            'internationalControlledExportDetail?' => '@InternationalControlledExportDetail',
            'returnShipmentDetail?' => '@ReturnShipmentDetail',
            'homeDeliveryPremiumDetail?' => '@HomeDeliveryPremiumDetail',
            'returnEmailDetail?' => '@ReturnEmailDetail',
            'specialServiceTypes?' => ['string'],
            'saturdayDeliveryDetail?' => '@SaturdayDeliveryDetail',
            'holdAtLocationDetail?' => '@HoldAtLocationDetail',
            'codDetail?' => '@CodDetail',
            'shipmentDryIceDetail?' => '@ShipmentDryIceDetail',
            'freightDirectDetail?' => '@FreightDirectDetail'
        ];

        $schemas['SmartPostInfoDetail'] = [
            'ancillaryEndorsement?' => ['@type' => 'string', '@enum' => ['ADDRESS_CORRECTION', 'CARRIER_LEAVE_IF_NO_RESPONSE', 'CHANGE_SERVICE', 'FORWARDING_SERVICE', 'RETURN_SERVICE']],
            'hubId' => 'string',
            'indicia' => ['@type' => 'string', '@enum' => ['MEDIA_MAIL', 'PARCEL_RETURN', 'PARCEL_SELECT', 'PRESORTED_BOUND_PRINTED_MATTER', 'PRESORTED_STANDARD']],
            'specialServices?' => ['@type' => 'string', '@enum' => ['USPS_DELIVERY_CONFIRMATION']]
        ];

        $schemas['SpecialServicesOptionsOutputVO'] = [
            'alerts?' => ['@Alert'],
            'serviceOptionsList?' => ['@OptionalServices']
        ];

        $schemas['SpecialServicesRequestedShipment'] = '@RequestedShipment';

        $schemas['StandaloneBatteryDetails'] = [
            'batteryMaterialType?' => ['@type' => 'string', '@enum' => ['LITHIUM_METAL', 'LITHIUM_ION']]
        ];

        $schemas['SubPackageInfo'] = [
            'dimension?' => 'string',
            'description?' => 'string'
        ];

        $schemas['TransitTime'] = [
            'transitTimeDetails?' => ['@TransitTimeDetail']
        ];

        $schemas['TransitTimeCommodity'] = [
            'description' => 'string',
            'quantity?' => 'int',
            'unitPrice?' => '@TransitTimeCommodity_unitPrice',
            'weight?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_shipmentDryIceDetail_totalWeight',
            'customsValue' => '@TransitTimeCommodity_unitPrice',
            'numberOfPieces' => 'int',
            'countryOfManufacture?' => 'string',
            'quantityUnits?' => 'string',
            'name?' => 'string',
            'harmonizedCode?' => 'string',
            'partNumber?' => 'string'
        ];

        $schemas['TransitTimeCommodity_unitPrice'] = [
            'amount?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'currency?' => 'string'
        ];

        $schemas['TransitTimeDetail'] = [
            'serviceType?' => 'string',
            'customerMessages?' => ['@CustomerMessage'],
            'distance?' => '@Distance',
            'commit?' => '@CommitDetail',
            'destinationLocation?' => '@LocationDetail',
            'serviceName?' => 'string'
        ];

        $schemas['TransitTimeOutput'] = [
            'alerts?' => ['@Alert'],
            'transitTimes?' => ['@TransitTime'],
            'encoded?' => 'bool'
        ];

        $schemas['TransitTimeOutputVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@TransitTimeOutput'
        ];

        $schemas['TransitTimeRecipient'] = [
            'address' => '@TransitTimeRecipient_address'
        ];

        $schemas['TransitTimeRecipient_address'] = [
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['TransitTimeRequestedPackageLineItem'] = [
            'declaredValue?' => '@TransitTimeRequestedPackageLineItem_declaredValue',
            'weight' => '@TransitTimeRequestedPackageLineItem_weight',
            'dimensions?' => '@TransitTimeRequestedPackageLineItem_dimensions',
            'packageSpecialServices?' => '@TransitTimeRequestedPackageLineItem_packageSpecialServices'
        ];

        $schemas['TransitTimeRequestedPackageLineItem_declaredValue'] = [
            'amount?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'currency?' => 'string'
        ];

        $schemas['TransitTimeRequestedPackageLineItem_dimensions'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['TransitTimeRequestedPackageLineItem_packageSpecialServices'] = [
            'specialServiceTypes?' => ['string'],
            'codDetail?' => '@TransitTimeRequestedPackageLineItem_packageSpecialServices_codDetail',
            'dryIceWeight?' => '@TransitTimeRequestedPackageLineItem_packageSpecialServices_dryIceWeight',
            'dangerousGoodsDetail?' => '@TransitTimeRequestedPackageLineItem_packageSpecialServices_dangerousGoodsDetail',
            'alcoholDetail?' => '@TransitTimeRequestedPackageLineItem_packageSpecialServices_alcoholDetail',
            'pieceCountVerificationBoxCount?' => 'int',
            'batteryDetails?' => '@TransitTimeRequestedPackageLineItem_packageSpecialServices_batteryDetails'
        ];

        $schemas['TransitTimeRequestedPackageLineItem_packageSpecialServices_alcoholDetail'] = [
            'alcoholRecipientType' => ['@type' => 'string', '@enum' => ['LICENSEE', 'CONSUMER']],
            'shipperAgreementType?' => 'string'
        ];

        $schemas['TransitTimeRequestedPackageLineItem_packageSpecialServices_batteryDetails'] = [
            'batteryMaterialType?' => ['@type' => 'string', '@enum' => ['LITHIUM_METAL', 'LITHIUM_ION']],
            'batteryPackingType?' => ['@type' => 'string', '@enum' => ['CONTAINED_IN_EQUIPMENT', 'PACKED_WITH_EQUIPMENT']],
            'batteryRegulatoryType?' => ['@type' => 'string', '@enum' => ['IATA_SECTION_II']]
        ];

        $schemas['TransitTimeRequestedPackageLineItem_packageSpecialServices_codDetail'] = [
            'codCollectionAmount?' => '@TransitTimeRequestedPackageLineItem_packageSpecialServices_codDetail_codCollectionAmount'
        ];

        $schemas['TransitTimeRequestedPackageLineItem_packageSpecialServices_codDetail_codCollectionAmount'] = [
            'amount?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'currency?' => 'string'
        ];

        $schemas['TransitTimeRequestedPackageLineItem_packageSpecialServices_dangerousGoodsDetail'] = [
            'accessibility?' => ['@type' => 'string', '@enum' => ['ACCESSIBLE', 'INACCESSIBLE']],
            'options?' => [['@type' => 'string', '@enum' => ['HAZARDOUS_MATERIALS', 'BATTERY', 'ORM_D', 'REPORTABLE_QUANTITIES', 'SMALL_QUANTITY_EXCEPTION', 'LIMITED_QUANTITIES_COMMODITIES']]]
        ];

        $schemas['TransitTimeRequestedPackageLineItem_packageSpecialServices_dryIceWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['TransitTimeRequestedPackageLineItem_weight'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['TransitTimeRequestedShipment'] = [
            'shipper' => '@TransitTimeRequestedShipment_shipper',
            'recipients' => ['@TransitTimeRecipient'],
            'serviceType?' => 'string',
            'packagingType' => 'string',
            'shipDatestamp?' => 'string',
            'pickupType?' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'shippingChargesPayment?' => '@TransitTimeShippingChargesPayment',
            'smartPostInfoDetail?' => '@SmartPostInfoDetail',
            'requestedPackageLineItems' => ['@TransitTimeRequestedPackageLineItem'],
            'shipmentSpecialServices?' => '@TransitTimeRequestedShipment_shipmentSpecialServices',
            'customsClearanceDetail?' => '@TransitTimeRequestedShipment_customsClearanceDetail'
        ];

        $schemas['TransitTimeRequestedShipment_customsClearanceDetail'] = [
            'commodities' => ['@TransitTimeCommodity']
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices'] = [
            'specialServiceTypes?' => ['string'],
            'codDetail?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_codDetail',
            'internationalControlledExportDetail?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_internationalControlledExportDetail',
            'homeDeliveryPremiumDetail?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_homeDeliveryPremiumDetail',
            'holdAtLocationDetail?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_holdAtLocationDetail',
            'shipmentDryIceDetail?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_shipmentDryIceDetail'
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_codDetail'] = [
            'codCollectionAmount?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_codDetail_codCollectionAmount',
            'codCollectionType?' => ['@type' => 'string', '@enum' => ['ANY', 'CASH', 'COMPANY_CHECK', 'GUARANTEED_FUNDS', 'PERSONAL_CHECK']]
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_codDetail_codCollectionAmount'] = [
            'amount?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'currency?' => 'string'
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_holdAtLocationDetail'] = [
            'locationId' => 'string',
            'locationType?' => ['@type' => 'string', '@enum' => ['FEDEX_AUTHORIZED_SHIP_CENTER', 'FEDEX_OFFICE', 'FEDEX_SELF_SERVICE_LOCATION', 'FEDEX_STAFFED', 'RETAIL_ALLICANCE_LOCATION', 'FEDEX_GROUND_TERMINAL', 'FEDEX_ONSITE']],
            'locationContactAndAddress?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_holdAtLocationDetail_locationContactAndAddress'
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_holdAtLocationDetail_locationContactAndAddress'] = [
            'contact?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_holdAtLocationDetail_locationContactAndAddress_contact',
            'address?' => '@TransitTimeShipper'
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_holdAtLocationDetail_locationContactAndAddress_contact'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_homeDeliveryPremiumDetail'] = [
            'homedeliveryPremiumType?' => ['@type' => 'string', '@enum' => ['APPOINTMENT', 'DATE_CERTAIN', 'EVENING']]
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_internationalControlledExportDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['DEA_036', 'DEA_236', 'DEA_486', 'DSP_05', 'DSP_61', 'DSP_73', 'DSP_85', 'DSP_94', 'DSP_LICENSE_AGREEMENT', 'FROM_FOREIGN_TRADE_ZONE', 'WAREHOUSE_WITHDRAWAL']]
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_shipmentDryIceDetail'] = [
            'totalWeight?' => '@TransitTimeRequestedShipment_shipmentSpecialServices_shipmentDryIceDetail_totalWeight',
            'packageCount?' => 'int'
        ];

        $schemas['TransitTimeRequestedShipment_shipmentSpecialServices_shipmentDryIceDetail_totalWeight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['TransitTimeRequestedShipment_shipper'] = [
            'address' => '@TransitTimeShipper'
        ];

        $schemas['TransitTimeShipper'] = [
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['TransitTimeShippingChargesPayment'] = [
            'payor?' => '@TransitTimeShippingChargesPayment_payor',
            'paymentType?' => ['@type' => 'string', '@enum' => ['SENDER', 'RECIPIENT', 'THIRD_PARTY', 'COLLECT']]
        ];

        $schemas['TransitTimeShippingChargesPayment_payor'] = [
            'responsibleParty?' => '@TransitTimeShippingChargesPayment_payor_responsibleParty'
        ];

        $schemas['TransitTimeShippingChargesPayment_payor_responsibleParty'] = [
            'address?' => '@TransitTimeShipper',
            'accountNumber?' => '@TransitTimeShippingChargesPayment_payor_responsibleParty_accountNumber'
        ];

        $schemas['TransitTimeShippingChargesPayment_payor_responsibleParty_accountNumber'] = [
            'value' => 'string'
        ];

        $schemas['VariableHandlingChargeDetail'] = [
            'percentValue?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'fixedValue?' => '@FixedValue',
            'rateElementBasis?' => ['@type' => 'string', '@enum' => ['NET_CHARGE', 'NET_FREIGHT', 'BASE_CHARGE', 'NET_CHARGE_EXCLUDING_TAXES']],
            'rateTypeBasis?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'ACTUAL', 'CURRENT', 'CUSTOM', 'LIST']]
        ];

        $schemas['Version'] = [
            'major?' => 'int',
            'minor?' => 'int',
            'patch?' => 'int'
        ];

        $schemas['Volume'] = '@Volume1';

        $schemas['Volume1'] = [
            'units?' => ['@type' => 'string', '@enum' => ['CUBIC_FT', 'CUBIC_M']],
            'value?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['Weight'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['Weight1'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['Weight_With_Link'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['Weight_Without_Link'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['ShipmentDryIceDetail_totalWeight_overlay'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        return $schemas;
    }

    /**
     * Check that a value is a number or a text that is a number (the check of the number fields of the requests): digits with an
     * optional sign, decimals and exponent, and nothing else, not even spaces
     * @param mixed $value The value, already known to be a number or a text
     * @return bool|string True when it is, the reason when it is not
     */
    public static function numberOrNumericText($value)
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        return (is_string($value) && preg_match('/^[+-]?(\d+(\.\d+)?|\.\d+)([eE][+-]?\d+)?$/D', $value)) ? true : 'not a number or a text that is a number';
    }
}
