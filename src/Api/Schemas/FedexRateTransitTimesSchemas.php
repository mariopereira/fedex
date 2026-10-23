<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the request and the response of the Rates and Transit Times API operation that FedexRateTransitTimes
 * implements, generated from schemas/rate.json and written for SchemaValidator
 */
final class FedexRateTransitTimesSchemas
{
    /**
     * Name of the definition of the request
     * @var string
     */
    const REQUEST = 'Full_Schema_Quote_Rate';

    /**
     * Name of the definition of the response
     * @var string
     */
    const RESPONSE = 'RatcResponseVO';

    /**
     * Get the definitions of the request and the response. The names of the definitions, and of their fields, are the ones of
     * schemas/rate.json, with these differences:
     *  - what the file writes as an allOf of a definition and an overlay that adds required keys (Money, Weight, Dimensions,
     *    RateAddress, BrokerDetailBroker, BrokerDetailBroker_RateReply and DeliveryOnInvoiceAcceptanceDetailRecipient) is a
     *    definition with the fields of the base and the required keys of both. BrokerDetailBroker.contact exists only in the
     *    overlay of the file, with a description and no type: it accepts any value. The address and the contact of
     *    DeliveryOnInvoiceAcceptanceDetailRecipient are tightened by the overlay, and are definitions of their own, with the
     *    suffix _overlay (the overlay defines streetLines, which the base address does not have: it is a field of the address)
     *  - in the definitions that the request reaches (and so in Money and the other definitions that the response shares with the
     *    request), a number is a number or a text that is a number: the samples of the file send weight.value as text
     *  - in the definitions that the request reaches, postalCode is a text or an integer: fifteen samples of the file send it as
     *    an integer
     *  - the request allows keys that the file does not define (see FedexRateTransitTimes::getRates()): the schema of the file does
     *    not list the keys of the samples for Freight and for Intra Europe
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['AccountNumber'] = [
            'value?' => 'string'
        ];

        $schemas['Address'] = [
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string|int',
            'countryCode?' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['Address_1'] = [
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string|int',
            'countryCode?' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['Address_2'] = [
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string|int',
            'countryCode?' => 'string',
            'residential?' => 'bool'
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

        $schemas['AncillaryFeeAndTax'] = [
            'type?' => ['@type' => 'string', '@enum' => ['CLEARANCE_ENTRY_FEE', 'GOODS_AND_SERVICES_TAX', 'HARMONIZED_SALES_TAX', 'OTHER']],
            'description?' => 'string',
            'amount?' => '@Money_1'
        ];

        $schemas['AncillaryFeesAndTaxes'] = [
            'amount?' => 'number',
            'description?' => 'string',
            'type?' => 'string'
        ];

        $schemas['BaseProcessOutputVO'] = '@RateOutputVO';

        $schemas['BatteryClassificationDetail'] = [
            'material?' => ['@type' => 'string', '@enum' => ['LITHIUM_METAL', 'LITHIUM_ION']],
            'regulatorySubType?' => ['@type' => 'string', '@enum' => ['IATA_SECTION_II']],
            'packing?' => ['@type' => 'string', '@enum' => ['CONTAINED_IN_EQUIPMENT', 'PACKED_WITH_EQUIPMENT']]
        ];

        $schemas['BrokerDetail'] = [
            'broker' => '@BrokerDetailBroker',
            'type' => ['@type' => 'string', '@enum' => ['IMPORT']]
        ];

        $schemas['BrokerDetailBroker'] = [
            'address' => '@Address',
            'accountNumber?' => '@AccountNumber',
            'contact' => 'mixed'
        ];

        $schemas['BrokerDetailBroker_RateReply'] = [
            'address' => '@Address',
            'accountNumber?' => '@AccountNumber'
        ];

        $schemas['BrokerDetail_RateReply'] = [
            'broker' => '@BrokerDetailBroker_RateReply',
            'type' => ['@type' => 'string', '@enum' => ['EXPORT', 'IMPORT']],
            'brokerAddress?' => '@Brokeraddress'
        ];

        $schemas['Brokeraddress'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => 'bool',
            'classification?' => 'string',
            'geographicCoordinates?' => 'string',
            'urbanizationCode?' => 'string',
            'countryName?' => 'string'
        ];

        $schemas['CODTransportationChargesDetail'] = [
            'rateType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'ACTUAL', 'CURRENT', 'CUSTOM', 'LIST', 'INCENTIVE', 'PREFERRED', 'PREFERRED_INCENTIVE', 'PREFERRED_CURRENCY']],
            'rateLevelType?' => ['@type' => 'string', '@enum' => ['BUNDLED_RATE', 'INDIVIDUAL_PACKAGE_RATE']],
            'chargeLevelType?' => ['@type' => 'string', '@enum' => ['CURRENT_PACKAGE', 'SUM_OF_PACKAGES']],
            'chargeType?' => ['@type' => 'string', '@enum' => ['COD_SURCHARGE', 'NET_CHARGE', 'NET_FREIGHT', 'TOTAL_CUSTOMER_CHARGE']]
        ];

        $schemas['CommercialInvoice'] = [
            'shipmentPurpose?' => ['@type' => 'string', '@enum' => ['GIFT', 'NOT_SOLD', 'PERSONAL_EFFECTS', 'REPAIR_AND_RETURN', 'SAMPLE', 'SOLD', 'COMMERCIAL', 'RETURN_AND_REPAIR', 'PERSONAL_USE']]
        ];

        $schemas['Commit'] = '@CommitDetail_1';

        $schemas['CommitDetail_1'] = [
            'daysInTransit?' => ['@type' => 'string', '@enum' => ['EIGHT_DAYS', 'EIGHTEEN_DAYS', 'ELEVEN_DAYS', 'FIFTEEN_DAYS', 'FIVE_DAYS', 'FOUR_DAYS', 'FOURTEEN_DAYS', 'NINE_DAYS', 'NINETEEN_DAYS', 'ONE_DAY', 'SEVEN_DAYS', 'SEVENTEEN_DAYS', 'SIX_DAYS', 'SIXTEEN_DAYS', 'TEN_DAYS', 'THIRTEEN_DAYS', 'THREE_DAYS', 'TWELVE_DAYS', 'TWENTY_DAYS', 'TWO_DAYS', 'SMARTPOST_TRANSIT_DAYS', 'UNKNOWN']],
            'guaranteedType?' => ['@type' => 'string', '@enum' => ['GUARANTEED_MORNING', 'GUARANTEED_CLOSE_OF_BUSINESS']],
            'smartPostCommitTime?' => 'string',
            'dateDetail?' => '@DateDetail',
            'delayDetails?' => '@DelayDetail',
            'saturdayDelivery?' => 'bool',
            'alternativeCommodityNames?' => ['string'],
            'transitDays?' => '@TransitDays',
            'label?' => 'string',
            'commitMessageDetails?' => 'string',
            'commodityName?' => 'string'
        ];

        $schemas['Commodity'] = [
            'description?' => 'string',
            'weight?' => '@Weight_2',
            'quantity?' => 'int',
            'customsValue?' => '@Money',
            'unitPrice?' => '@Money',
            'numberOfPieces?' => 'int',
            'countryOfManufacture?' => 'string',
            'quantityUnits?' => 'string',
            'name?' => 'string',
            'harmonizedCode?' => 'string',
            'partNumber?' => 'string'
        ];

        $schemas['ContactAndAddress'] = [
            'contact?' => '@Contact_2',
            'address?' => '@Address_1'
        ];

        $schemas['Contact_2'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string',
            'companyName?' => 'string'
        ];

        $schemas['CurrencyExchangeRate'] = [
            'fromCurrency?' => 'string',
            'intoCurrency?' => 'string',
            'rate?' => 'number'
        ];

        $schemas['CustomerMessage'] = [
            'code?' => 'string',
            'message?' => 'string'
        ];

        $schemas['CustomsClearanceDetail'] = [
            'brokers?' => ['@BrokerDetail'],
            'commercialInvoice?' => '@CommercialInvoice',
            'freightOnValue?' => ['@type' => 'string', '@enum' => ['CARRIER_RISK', 'OWN_RISK']],
            'dutiesPayment?' => '@Payment',
            'commodities' => ['@Commodity']
        ];

        $schemas['DangerousGoodsContainer'] = [
            'offeror?' => 'string',
            'hazardousCommodities?' => ['@HazardousCommodityContent'],
            'numberOfContainers?' => 'int',
            'containerType?' => 'string',
            'emergencyContactNumber?' => '@PhoneNumber',
            'packaging?' => '@HazardousCommodityPackagingDetail',
            'packingType?' => ['@type' => 'string', '@enum' => ['ALL_PACKED_IN_ONE']],
            'radioactiveContainerClass?' => ['@type' => 'string', '@enum' => ['EXCEPTED_PACKAGE', 'INDUSTRIAL_IP1', 'INDUSTRIAL_IP2', 'INDUSTRIAL_IP3', 'TYPE_A', 'TYPE_B_M', 'TYPE_B_U', 'TYPE_C']]
        ];

        $schemas['DangerousGoodsDetail'] = [
            'accessibility?' => ['@type' => 'string', '@enum' => ['ACCESSIBLE', 'INACCESSIBLE']],
            'options?' => [['@type' => 'string', '@enum' => ['HAZARDOUS_MATERIALS', 'BATTERY', 'ORM_D', 'REPORTABLE_QUANTITIES', 'SMALL_QUANTITY_EXCEPTION', 'LIMITED_QUANTITIES_COMMODITIES']]],
            'containers?' => ['@DangerousGoodsContainer'],
            'regulation?' => ['@type' => 'string', '@enum' => ['ADR', 'DOT', 'IATA', 'ORMD']]
        ];

        $schemas['DateDetail'] = [
            'dayOfWeek?' => 'string',
            'dayFormat?' => 'string'
        ];

        $schemas['DelayDetail'] = [
            'date?' => 'string',
            'dayOfWeek?' => 'string',
            'level?' => 'string',
            'point?' => 'string',
            'type?' => 'string',
            'description?' => 'string'
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetail'] = [
            'recipient?' => '@DeliveryOnInvoiceAcceptanceDetailRecipient'
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetailRecipient'] = [
            'address' => '@DeliveryOnInvoiceAcceptanceDetailRecipient_address_overlay',
            'contact' => '@DeliveryOnInvoiceAcceptanceDetailRecipient_contact_overlay',
            'accountNumber?' => '@AccountNumber'
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetailRecipient_address'] = [
            'streetLines?' => ['string'],
            'countryCode' => 'string'
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetailRecipient_contact'] = [
            'companyName' => 'string',
            'faxNumber?' => 'string',
            'personName' => 'string',
            'phoneNumber' => 'string'
        ];

        $schemas['Dimensions'] = [
            'length' => 'int',
            'width' => 'int',
            'height' => 'int',
            'units' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['Dimensions_1'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['EdtCharge'] = [
            'edtTaxDetail?' => '@EdtTaxDetail',
            'harmonizedCode?' => 'string'
        ];

        $schemas['EdtCommodityTax'] = [
            'harmonizedCode?' => 'string',
            'taxes?' => '@EdtTaxDetail_1',
            'total?' => '@Money_1'
        ];

        $schemas['EdtTaxDetail'] = [
            'edtTaxType?' => 'string',
            'amount?' => 'number',
            'taxableValue?' => 'number',
            'name?' => 'string',
            'description?' => 'string',
            'formula?' => 'string',
            'effectiveDate?' => 'string'
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

        $schemas['Full_Schema_Quote_Rate'] = [
            'accountNumber' => '@AccountNumber',
            'rateRequestControlParameters?' => '@RateRequestControlParameters',
            'requestedShipment' => '@RequestedShipment',
            'processingOptions?' => [['@type' => 'string', '@enum' => ['INCLUDE_PICKUPRATES']]],
            'carrierCodes?' => ['string'],
            'version?' => '@Version'
        ];

        $schemas['HazardousCommodityContent'] = [
            'quantity?' => '@HazardousCommodityQuantityDetail',
            'innerReceptacles?' => ['@HazardousCommodityInnerReceptacleDetail'],
            'options?' => '@HazardousCommodityOptionDetail',
            'description?' => '@HazardousCommodityDescription'
        ];

        $schemas['HazardousCommodityDescription'] = [
            'sequenceNumber?' => 'int',
            'processingOptions?' => [['@type' => 'string', '@enum' => ['INCLUDE_SPECIAL_PROVISIONS']]],
            'subsidiaryClasses?' => ['string'],
            'labelText?' => 'string',
            'technicalName?' => 'string',
            'packingDetails?' => '@HazardousCommodityPackingDetail',
            'authorization?' => 'string',
            'reportableQuantity?' => 'bool',
            'percentage?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'id?' => 'string',
            'packingGroup?' => ['@type' => 'string', '@enum' => ['DEFAULT', 'I', 'II', 'III']],
            'properShippingName?' => 'string',
            'hazardClass?' => 'string'
        ];

        $schemas['HazardousCommodityInnerReceptacleDetail'] = [
            'quantity?' => '@HazardousCommodityQuantityDetail'
        ];

        $schemas['HazardousCommodityOptionDetail'] = [
            'labelTextOption?' => ['@type' => 'string', '@enum' => ['APPEND', 'OVERRIDE', 'STANDARD']],
            'customerSuppliedLabelText?' => 'string'
        ];

        $schemas['HazardousCommodityPackagingDetail'] = [
            'count?' => 'int',
            'units?' => 'string'
        ];

        $schemas['HazardousCommodityPackingDetail'] = [
            'packingInstructions?' => 'string',
            'cargoAircraftOnly?' => 'bool'
        ];

        $schemas['HazardousCommodityQuantityDetail'] = [
            'quantityType?' => ['@type' => 'string', '@enum' => ['GROSS', 'NET']],
            'amount?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'units?' => 'string'
        ];

        $schemas['HoldAtLocationDetail'] = [
            'locationId' => 'string',
            'locationContactAndAddress?' => '@ContactAndAddress',
            'locationType?' => ['@type' => 'string', '@enum' => ['FEDEX_AUTHORIZED_SHIP_CENTER', 'FEDEX_OFFICE', 'FEDEX_SELF_SERVICE_LOCATION', 'FEDEX_STAFFED', 'RETAIL_ALLICANCE_LOCATION', 'FEDEX_GROUND_TERMINAL', 'FEDEX_ONSITE']]
        ];

        $schemas['HomeDeliveryPremiumDetail'] = [
            'shipTimestamp?' => 'string',
            'homedeliveryPremiumType?' => ['@type' => 'string', '@enum' => ['APPOINTMENT', 'DATE_CERTAIN', 'EVENING']]
        ];

        $schemas['InternationalControlledExportDetail'] = [
            'type?' => ['@type' => 'string', '@enum' => ['DEA_036', 'DEA_236', 'DEA_486', 'DSP_05', 'DSP_61', 'DSP_73', 'DSP_85', 'DSP_94', 'DSP_LICENSE_AGREEMENT', 'FROM_FOREIGN_TRADE_ZONE', 'WAREHOUSE_WITHDRAWAL']]
        ];

        $schemas['InternationalTrafficInArmsRegulationsDetail'] = [
            'licenseOrExemptionNumber?' => 'string'
        ];

        $schemas['Money'] = [
            'amount?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'currency?' => 'string'
        ];

        $schemas['Money_1'] = [
            'amount?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'currency?' => 'string'
        ];

        $schemas['OperationalDetail'] = [
            'originLocationIds?' => ['string'],
            'commitDays?' => ['string'],
            'serviceCode?' => 'string',
            'airportId?' => 'string',
            'scac?' => 'string',
            'originServiceAreas?' => ['string'],
            'deliveryDay?' => 'string',
            'originLocationNumbers?' => ['int'],
            'destinationPostalCode?' => 'string',
            'commitDate?' => 'string',
            'astraDescription?' => 'string',
            'deliveryDate?' => 'string',
            'deliveryEligibilities?' => ['string'],
            'ineligibleForMoneyBackGuarantee?' => 'bool',
            'MaximumTransitTime?' => 'string',
            'astraPlannedServiceLevel?' => 'string',
            'destinationLocationIds?' => ['string'],
            'destinationLocationStateOrProvinceCodes?' => ['string'],
            'transitTime?' => 'string',
            'packagingCode?' => 'string',
            'destinationLocationNumbers?' => ['int'],
            'publishedDeliveryTime?' => 'string',
            'countryCodes?' => ['string'],
            'stateOrProvinceCodes?' => ['string'],
            'ursaPrefixCode?' => 'string',
            'ursaSuffixCode?' => 'string',
            'destinationServiceAreas?' => ['string'],
            'originPostalCodes?' => ['string'],
            'customTransitTime?' => 'string'
        ];

        $schemas['PackageCODDetail'] = [
            'codCollectionAmount?' => '@Money_1',
            'codCollectionType?' => ['@type' => 'string', '@enum' => ['ANY', 'CASH', 'COMPANY_CHECK', 'GUARANTEED_FUNDS', 'PERSONAL_CHECK']]
        ];

        $schemas['PackageRateDetail'] = [
            'ratedWeightMethod?' => 'string',
            'totalTaxes?' => 'number',
            'totalFreightDiscounts?' => 'number',
            'baseCharge?' => 'number',
            'totalRebates?' => 'number',
            'rateType?' => 'string',
            'billingWeight?' => '@Weight',
            'netFreight?' => 'number',
            'surcharges?' => ['@Surcharge'],
            'totalSurcharges?' => 'number',
            'netFedExCharge?' => 'number',
            'netCharge?' => 'number',
            'freightDiscounts?' => ['@RateDiscount']
        ];

        $schemas['PackageSpecialServicesRequested'] = [
            'specialServiceTypes?' => ['string'],
            'signatureOptionType?' => ['@type' => 'string', '@enum' => ['SERVICE_DEFAULT', 'NO_SIGNATURE_REQUIRED', 'INDIRECT', 'DIRECT', 'ADULT']],
            'alcoholDetail?' => '@AlcoholDetail',
            'dangerousGoodsDetail?' => '@DangerousGoodsDetail',
            'packageCODDetail?' => '@PackageSpecialServicesRequestedPackageCODDetail',
            'pieceCountVerificationBoxCount?' => 'int',
            'batteryDetails?' => ['@BatteryClassificationDetail'],
            'dryIceWeight?' => '@Weight_1_2',
            'standaloneBatteryDetails?' => ['@StandaloneBatteryDetails']
        ];

        $schemas['PackageSpecialServicesRequestedPackageCODDetail'] = '@PackageCODDetail';

        $schemas['Party'] = [
            'address?' => '@Address',
            'accountNumber?' => '@AccountNumber'
        ];

        $schemas['Party_2'] = [
            'address?' => '@Address_2',
            'contact?' => '@Contact_2',
            'accountNumber?' => '@AccountNumber'
        ];

        $schemas['Payment'] = [
            'payor?' => '@Payor',
            'paymentType?' => ['@type' => 'string', '@enum' => ['SENDER']]
        ];

        $schemas['Payor'] = [
            'responsibleParty?' => '@Payor_responsibleParty'
        ];

        $schemas['PhoneNumber'] = [
            'areaCode' => 'string',
            'extension?' => 'string',
            'countryCode' => 'string',
            'personalIdentificationNumber?' => 'string',
            'localNumber?' => 'string'
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

        $schemas['ProductName'] = [
            'type?' => 'string',
            'encoding?' => 'string',
            'value?' => 'string'
        ];

        $schemas['RatcResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO'
        ];

        $schemas['RateAddress'] = [
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode' => 'string|int',
            'countryCode' => 'string',
            'residential?' => 'bool'
        ];

        $schemas['RateDiscount'] = [
            'amount?' => 'number',
            'name?' => 'string',
            'description?' => 'string',
            'type?' => 'string',
            'percent?' => 'number'
        ];

        $schemas['RateDiscount_1'] = [
            'amount?' => 'number',
            'name?' => 'string',
            'description?' => 'string',
            'type?' => 'string',
            'percent?' => 'number'
        ];

        $schemas['RateDiscount_2'] = [
            'rateDiscountType?' => ['@type' => 'string', '@enum' => ['BONUS', 'COUPON', 'EARNED', 'INCENTIVE', 'OTHER', 'VOLUME']],
            'description?' => 'string',
            'amount?' => '@Money_1',
            'percent?' => 'number'
        ];

        $schemas['RateOutputVO'] = [
            'rateReplyDetails?' => ['@RateReplyDetail'],
            'quoteDate?' => 'string',
            'isEncoded?' => 'bool',
            'alerts?' => ['@Alert']
        ];

        $schemas['RateParty'] = [
            'address' => '@RateAddress'
        ];

        $schemas['RateReplyDetail'] = [
            'serviceType?' => 'string',
            'serviceName?' => 'string',
            'packagingType?' => 'string',
            'customerMessages?' => ['@CustomerMessage'],
            'ratedShipmentDetails?' => ['@RatedShipmentDetail'],
            'operationalDetail?' => '@OperationalDetail',
            'signatureOptionType?' => 'string',
            'serviceDescription?' => '@ServiceDescription',
            'brokerDetail?' => '@BrokerDetail_RateReply',
            'commit?' => '@Commit',
            'serviceSubOptionDetail?' => '@ServiceSubOptionDetail'
        ];

        $schemas['RateRequestControlParameters'] = [
            'returnTransitTimes?' => 'bool',
            'servicesNeededOnRateFailure?' => 'bool',
            'variableOptions?' => ['@type' => 'string', '@enum' => ['SATURDAY_DELIVERY', 'FREIGHT_GUARANTEE', 'SMART_POST_ALLOWED_INDICIA', 'SMARTPOST_HUB_ID']],
            'rateSortOrder?' => ['@type' => 'string', '@enum' => ['COMMITASCENDING', 'SERVICENAMETRADITIONAL', 'COMMITDESCENDING']]
        ];

        $schemas['RatedPackageDetail'] = [
            'effectiveNetDiscount?' => 'number',
            'packageRateDetail?' => '@PackageRateDetail',
            'groupNumber?' => 'int'
        ];

        $schemas['RatedShipmentDetail'] = [
            'rateType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'ACTUAL', 'CURRENT', 'CUSTOM', 'LIST', 'INCENTIVE', 'PREFERRED', 'PREFERRED_INCENTIVE', 'PREFERRED_CURRENCY']],
            'totalNetTransportationAndPickupCharge?' => '@Money',
            'totalNetFedExTransportationAndPickupCharge?' => '@Money',
            'pickupRateDetail?' => '@RatedShipmentDetail_pickupRateDetail',
            'ratedWeightMethod?' => ['@type' => 'string', '@enum' => ['ACTUAL', 'AVERAGE_PACKAGE_WEIGHT_MINIMUM', 'DEFAULT_WEIGHT_APPLIED', 'BALLOON', 'DIM', 'FREIGHT_MINIMUM', 'MIXED', 'OVERSIZE', 'OVERSIZE_1', 'OVERSIZE_2', 'OVERSIZE_3', 'PACKAGING_MINIMUM', 'WEIGHT_BREAK']],
            'totalDutiesTaxesAndFees?' => 'number',
            'totalDiscounts?' => 'number',
            'totalDutiesAndTaxes?' => 'number',
            'variableHandlingCharges?' => '@VariableHandlingCharges',
            'edtCharges?' => ['@EdtCharge'],
            'totalAncillaryFeesAndTaxes?' => 'number',
            'ratedPackages?' => ['@RatedPackageDetail'],
            'totalNetFedExCharge?' => 'number',
            'quoteNumber?' => 'string',
            'shipmentLegRateDetails?' => ['@ShipmentLegRateDetail_1'],
            'freightChargeBasis?' => ['@type' => 'string', '@enum' => ['CWT', 'FLAT', 'MINIMUM']],
            'totalVariableHandlingCharges?' => '@VariableHandlingCharges',
            'totalVatCharge?' => 'number',
            'ancillaryFeesAndTaxes?' => ['@AncillaryFeesAndTaxes'],
            'preferredEdtCharges?' => ['@EdtCharge'],
            'totalNetCharge?' => 'number',
            'totalBaseCharge?' => 'number',
            'totalNetChargeWithDutiesAndTaxes?' => 'number',
            'shipmentRateDetail?' => '@ShipmentRateDetail'
        ];

        $schemas['Rebate'] = [
            'rebateType?' => ['@type' => 'string', '@enum' => ['BONUS', 'EARNED', 'OTHER']],
            'description?' => 'string',
            'amount?' => '@Money_1',
            'percent?' => 'number'
        ];

        $schemas['RequestePackageLineItemDimensions'] = '@Dimensions';

        $schemas['RequestedPackageLineItem'] = [
            'subPackagingType?' => 'string',
            'groupPackageCount?' => 'int',
            'declaredValue?' => '@Money',
            'weight' => '@Weight_2',
            'dimensions?' => '@RequestePackageLineItemDimensions',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'packageSpecialServices?' => '@PackageSpecialServicesRequested'
        ];

        $schemas['RequestedShipment'] = [
            'shipper' => '@RateParty',
            'pickupDetail?' => '@PickupDetail',
            'recipient' => '@RateParty',
            'serviceType?' => 'string',
            'preferredCurrency?' => 'string',
            'rateRequestType?' => [['@type' => 'string', '@enum' => ['LIST', 'INCENTIVE', 'ACCOUNT', 'PREFERRED']]],
            'shipDateStamp?' => 'string',
            'pickupType' => ['@type' => 'string', '@enum' => ['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP']],
            'requestedPackageLineItems' => ['@RequestedPackageLineItem'],
            'documentShipment?' => 'bool',
            'variableHandlingChargeDetail?' => '@VariableHandlingChargeDetail',
            'packagingType?' => 'string',
            'totalPackageCount?' => 'int',
            'totalWeight?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'shipmentSpecialServices?' => '@RequestedShipmentSpecialServicesRequested',
            'customsClearanceDetail?' => '@RequestedShipmentCustomsClearanceDetail',
            'smartPostInfoDetail?' => '@RequestedShipmentSmartPostInfoDetail'
        ];

        $schemas['RequestedShipmentCustomsClearanceDetail'] = '@CustomsClearanceDetail';

        $schemas['RequestedShipmentSmartPostInfoDetail'] = '@SmartPostInfoDetail';

        $schemas['RequestedShipmentSpecialServicesRequested'] = '@ShipmentSpecialServicesRequested';

        $schemas['ServiceDescription'] = [
            'serviceType?' => 'string',
            'code?' => 'string',
            'names?' => ['@ProductName'],
            'astraDescription?' => 'string',
            'description?' => 'string',
            'serviceId?' => 'string',
            'serviceCategory?' => 'string'
        ];

        $schemas['ServiceSubOptionDetail'] = [
            'smartPostIndiciaType?' => ['@type' => 'string', '@enum' => ['MEDIA_MAIL', 'PARCEL_RETURN', 'PARCEL_SELECT', 'PRESORTED_BOUND_PRINTED_MATTER', 'PRESORTED_STANDARD']]
        ];

        $schemas['ShipmentCODDetail'] = [
            'addTransportationChargesDetail?' => '@CODTransportationChargesDetail',
            'codRecipient?' => '@Party_2',
            'remitToName?' => 'string',
            'codCollectionType?' => ['@type' => 'string', '@enum' => ['ANY', 'CASH', 'COMPANY_CHECK', 'GUARANTEED_FUNDS', 'PERSONAL_CHECK']],
            'financialInstitutionContactAndAddress?' => '@ContactAndAddress',
            'returnReferenceIndicatorType?' => ['@type' => 'string', '@enum' => ['INVOICE', 'PO', 'REFERENCE', 'TRACKING']]
        ];

        $schemas['ShipmentDryIceDetail'] = [
            'totalWeight?' => '@Weight_1_2',
            'packageCount?' => 'int'
        ];

        $schemas['ShipmentLegRateDetail_1'] = [
            'discounts?' => ['@RateDiscount_1'],
            'pricingCode?' => 'string',
            'legDescription?' => 'string',
            'surcharges?' => ['@Surcharge_1'],
            'specialRatingApplied?' => ['string'],
            'taxes?' => ['@Tax_1'],
            'rateScale?' => 'string',
            'totalNetCharge?' => 'number',
            'totalBaseCharge?' => 'number',
            'currencyExchangeRate?' => '@CurrencyExchangeRate',
            'totalBillingWeight?' => '@Weight_1',
            'currency?' => 'string'
        ];

        $schemas['ShipmentRateDetail'] = [
            'currencyExchangeRate?' => '@CurrencyExchangeRate',
            'currency?' => 'string',
            'rateZone?' => 'string',
            'ratingBasis?' => ['@type' => 'string', '@enum' => ['SHIPMENT_WEIGHT_BASED', 'FLAT_RATE_PER_PACK', 'PACK_WEIGHT_BASED']],
            'pricingCode?' => 'string',
            'totalFreightDiscount?' => 'number',
            'specialRatingApplied?' => ['string'],
            'totalSurcharges?' => 'number',
            'freightDiscount?' => ['@RateDiscount'],
            'fuelSurchargePercent?' => 'number',
            'totalBillingWeight?' => '@Weight_1',
            'totalDimWeight?' => '@Weight_1',
            'dimDivisor?' => 'int',
            'surCharges?' => ['@Surcharge'],
            'taxes?' => ['@Tax']
        ];

        $schemas['ShipmentSpecialServicesRequested'] = [
            'returnShipmentDetail?' => '@ShipmentSpecialServicesRequested_returnShipmentDetail',
            'deliveryOnInvoiceAcceptanceDetail?' => '@DeliveryOnInvoiceAcceptanceDetail',
            'internationalTrafficInArmsRegulationsDetail?' => '@InternationalTrafficInArmsRegulationsDetail',
            'holdAtLocationDetail?' => '@HoldAtLocationDetail',
            'shipmentCODDetail?' => '@ShipmentSpecialServicesRequestedShipmentCODDetail',
            'shipmentDryIceDetail?' => '@ShipmentDryIceDetail',
            'internationalControlledExportDetail?' => '@InternationalControlledExportDetail',
            'homeDeliveryPremiumDetail?' => '@HomeDeliveryPremiumDetail',
            'specialServiceTypes?' => ['string']
        ];

        $schemas['ShipmentSpecialServicesRequestedShipmentCODDetail'] = '@ShipmentCODDetail';

        $schemas['ShipmentSpecialServicesRequested_returnShipmentDetail'] = [
            'returnType?' => ['@type' => 'string', '@enum' => ['FEDEX_TAG', 'PENDING', 'PRINT_RETURN_LABEL', 'EMAIL_LABEL', 'NET_RETURN', 'VOICE_CALL_TAG']]
        ];

        $schemas['SmartPostInfoDetail'] = [
            'ancillaryEndorsement?' => ['@type' => 'string', '@enum' => ['ADDRESS_CORRECTION', 'CARRIER_LEAVE_IF_NO_RESPONSE', 'CHANGE_SERVICE', 'FORWARDING_SERVICE', 'RETURN_SERVICE']],
            'hubId?' => 'string',
            'indicia?' => ['@type' => 'string', '@enum' => ['MEDIA_MAIL', 'PARCEL_RETURN', 'PARCEL_SELECT', 'PRESORTED_BOUND_PRINTED_MATTER', 'PRESORTED_STANDARD']],
            'specialServices?' => ['@type' => 'string', '@enum' => ['USPS_DELIVERY_CONFIRMATION']]
        ];

        $schemas['StandaloneBatteryDetails'] = [
            'batteryMaterialType?' => ['@type' => 'string', '@enum' => ['LITHIUM_METAL', 'LITHIUM_ION']]
        ];

        $schemas['Surcharge'] = [
            'type?' => 'string',
            'description?' => 'string',
            'amount?' => 'number',
            'level?' => 'string',
            'name?' => 'string'
        ];

        $schemas['Surcharge_1'] = [
            'amount?' => 'number',
            'level?' => 'string',
            'name?' => 'string',
            'description?' => 'string',
            'type?' => 'string'
        ];

        $schemas['Surcharge_2'] = [
            'surchargeType?' => ['@type' => 'string', '@enum' => ['ACCOUNT_NUMBER_PROCESSING_FEE', 'ADDITIONAL_HANDLING', 'ADDRESS_CORRECTION', 'ANCILLARY_FEE', 'APPOINTMENT_DELIVERY', 'BLIND_SHIPMENT', 'BROKER_SELECT_OPTION', 'CANADIAN_DESTINATION', 'CHARGEABLE_PALLET_WEIGHT', 'COD', 'CUT_FLOWERS', 'DANGEROUS_GOODS', 'DELIVERY_AREA', 'DELIVERY_CONFIRMATION', 'DELIVERY_ON_INVOICE_ACCEPTANCE', 'DEMAND', 'DEMAND_ADDITIONAL_HANDLING', 'DEMAND_OVERSIZE', 'DEMAND_RESIDENTIAL_DELIVERY', 'DETENTION', 'DOCUMENTATION_FEE', 'DRY_ICE', 'EMAIL_LABEL', 'ENHANCED_SECURITY', 'EUROPE_FIRST', 'EXCESS_VALUE', 'EXCLUSIVE_USE', 'EXHIBITION', 'EXPEDITED', 'EXPORT', 'EXTRA_LABOR', 'EXTRA_SURFACE_HANDLING_CHARGE', 'EXTREME_LENGTH', 'FEDEX_INTRACOUNTRY_FEES', 'FEDEX_TAG', 'FICE', 'FLATBED', 'FREIGHT_DIRECT_BASIC_BY_APPOINTMENT_DELIVERY', 'FREIGHT_DIRECT_BASIC_BY_APPOINTMENT_PICKUP', 'FREIGHT_DIRECT_BASIC_DELIVERY', 'FREIGHT_DIRECT_BASIC_PICKUP', 'FREIGHT_DIRECT_PREMIUM_DELIVERY', 'FREIGHT_DIRECT_PREMIUM_PICKUP', 'FREIGHT_DIRECT_STANDARD_DELIVERY', 'FREIGHT_DIRECT_STANDARD_PICKUP', 'FREIGHT_GUARANTEE', 'FREIGHT_ON_VALUE', 'FREIGHT_TO_COLLECT', 'FUEL', 'HIGH_COST_SERVICE_AREA_DESTINATION', 'HIGH_COST_SERVICE_AREA_ORIGIN', 'HIGH_DENSITY', 'HOLD_AT_LOCATION', 'HOLIDAY_DELIVERY', 'HOLIDAY_GUARANTEE', 'HOME_DELIVERY_APPOINTMENT', 'HOME_DELIVERY_DATE_CERTAIN', 'HOME_DELIVERY_EVENING', 'INSIDE_DELIVERY', 'INSIDE_PICKUP', 'INSURED_VALUE', 'INTERHAWAII', 'LIFTGATE_DELIVERY', 'LIFTGATE_PICKUP', 'LIMITED_ACCESS_DELIVERY', 'LIMITED_ACCESS_PICKUP', 'MARKING_OR_TAGGING', 'METRO_DELIVERY', 'METRO_PICKUP', 'MONITORING_AND_INTERVENTION', 'NON_BUSINESS_TIME', 'NON_MACHINABLE', 'OFFSHORE', 'ON_CALL_PICKUP', 'ON_DEMAND_CARE', 'OTHER', 'OUT_OF_DELIVERY_AREA', 'OUT_OF_PICKUP_AREA', 'OVER_DIMENSION', 'OVER_LENGTH', 'OVERSIZE', 'OVERWEIGHT', 'PALLET_SHRINKWRAP', 'PALLETS_PROVIDED', 'PEAK', 'PEAK_ADDITIONAL_HANDLING', 'PEAK_OVERSIZE', 'PEAK_RESIDENTIAL_DELIVERY', 'PERMIT', 'PIECE_COUNT_VERIFICATION', 'PORT', 'PRE_DELIVERY_NOTIFICATION', 'PRIORITY_ALERT', 'PROTECTION_FROM_FREEZING', 'REGIONAL_MALL_DELIVERY', 'REGIONAL_MALL_PICKUP', 'REROUTE', 'RESCHEDULE', 'RESIDENTIAL_DELIVERY', 'RESIDENTIAL_PICKUP', 'RETURN_LABEL', 'SATURDAY_DELIVERY', 'SATURDAY_PICKUP', 'SHIPMENT_ASSEMBLY', 'SIGNATURE_OPTION', 'SINGLE_PIECE', 'SORT_AND_SEGREGATE', 'SPECIAL_DELIVERY', 'SPECIAL_EQUIPMENT', 'STORAGE', 'SUNDAY_DELIVERY', 'TARP', 'THIRD_PARTY_BILLING', 'THIRD_PARTY_CONSIGNEE', 'TRANSMART_SERVICE_FEE', 'USPS', 'WEIGHING']],
            'level?' => ['@type' => 'string', '@enum' => ['PACKAGE', 'SHIPMENT']],
            'description?' => 'string',
            'amount?' => '@Money_1'
        ];

        $schemas['Tax'] = [
            'amount?' => 'number',
            'name?' => 'string',
            'description?' => 'string',
            'type?' => 'string'
        ];

        $schemas['Tax_1'] = [
            'amount?' => 'number',
            'name?' => 'string',
            'description?' => 'string',
            'type?' => 'string'
        ];

        $schemas['Tax_2'] = [
            'taxType?' => ['@type' => 'string', '@enum' => ['EXPORT', 'GST', 'HST', 'INTRACOUNTRY', 'OTHER', 'PST', 'SST', 'VAT']],
            'description?' => 'string',
            'amount?' => '@Money_1'
        ];

        $schemas['TransitDays'] = [
            'description?' => 'string',
            'minimumTransitTime?' => ['@type' => 'string', '@enum' => ['EIGHT_DAYS', 'EIGHTEEN_DAYS', 'ELEVEN_DAYS', 'FIFTEEN_DAYS', 'FIVE_DAYS', 'FOUR_DAYS', 'FOURTEEN_DAYS', 'NINE_DAYS', 'NINETEEN_DAYS', 'ONE_DAY', 'SEVEN_DAYS', 'SEVENTEEN_DAYS', 'SIX_DAYS', 'SIXTEEN_DAYS', 'TEN_DAYS', 'THIRTEEN_DAYS', 'THREE_DAYS', 'TWELVE_DAYS', 'TWENTY_DAYS', 'TWO_DAYS', 'SMARTPOST_TRANSIT_DAYS', 'UNKNOWN']],
            'maximumTransitTime?' => 'string'
        ];

        $schemas['VariableHandlingChargeDetail'] = [
            'rateType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'ACTUAL', 'CURRENT', 'CUSTOM', 'LIST', 'INCENTIVE', 'PREFERRED', 'PREFERRED_INCENTIVE', 'PREFERRED_CURRENCY']],
            'percentValue?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']],
            'rateLevelType?' => ['@type' => 'string', '@enum' => ['BUNDLED_RATE', 'INDIVIDUAL_PACKAGE_RATE']],
            'fixedValue?' => '@Money',
            'rateElementBasis' => ['@type' => 'string', '@enum' => ['NET_CHARGE', 'NET_FREIGHT', 'BASE_CHARGE', 'NET_CHARGE_EXCLUDING_TAXES']]
        ];

        $schemas['VariableHandlingCharges'] = [
            'totalCustomerCharge?' => 'number',
            'variableHandlingCharge?' => 'number'
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
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => 'number'
        ];

        $schemas['Weight_1'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'number'
        ];

        $schemas['Weight_1_2'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['Weight_2'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value' => ['@type' => 'number|string', '@check' => [self::class, 'numberOrNumericText']]
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetailRecipient_address_overlay'] = [
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string|int',
            'countryCode' => 'string',
            'residential?' => 'bool',
            'streetLines?' => ['string']
        ];

        $schemas['DeliveryOnInvoiceAcceptanceDetailRecipient_contact_overlay'] = [
            'personName' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string',
            'companyName' => 'string'
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

        $schemas['Payor_responsibleParty'] = [
            'address?' => '@Address_2',
            'contact?' => '@Contact_2',
            'accountNumber' => '@AccountNumber'
        ];

        $schemas['RatedShipmentDetail_pickupRateDetail'] = [
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

        return $schemas;
    }

    /**
     * Check that a value is a number or a text that is a number (the check of the number fields of the request): digits with an
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
