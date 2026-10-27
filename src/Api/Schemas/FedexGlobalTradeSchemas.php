<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the request and the response of the Global Trade API operation that FedexGlobalTrade implements, generated
 * from schemas/global-trade.json and written for SchemaValidator
 */
final class FedexGlobalTradeSchemas
{
    /**
     * Name of the definition of the Retrieve Regulatory Details request
     * @var string
     */
    const REQUEST = 'FullSchema';

    /**
     * Name of the definition of the Retrieve Regulatory Details response
     * @var string
     */
    const RESPONSE = 'GticResponseVO';

    /**
     * Get the definitions of the request and the response. The names of the definitions, and of their fields, are the ones of
     * schemas/global-trade.json, with this difference:
     *  - the value of the weight of a commodity (Weight.value) is optional, although the file requires it: most of the
     *    requests of the collection of FedEx leave it out, and FedEx decides. Weight is also the definition of the total weight
     *    of the request, so its value is optional too
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['Address'] = [
            'countryCode' => 'string',
            'postalCode?' => 'string',
            'stateOrProvinceCode?' => 'string'
        ];

        $schemas['BaseProcessOutputVO_1'] = [
            'userMessages' => ['@RegulatoryMessage'],
            'countryDetails' => '@RegulatoryCountryDetails',
            'cxsalerts?' => ['@CXSAlert_1']
        ];

        $schemas['CXSAlert_1'] = [
            'code?' => 'string',
            'alertType?' => ['@type' => 'string', '@enum' => ['NOTE', 'WARNING']],
            'parameterList?' => ['@Parameter'],
            'message?' => 'string'
        ];

        $schemas['CommercialInvoice'] = [
            'freightCharge?' => '@Money',
            'purpose?' => 'string'
        ];

        $schemas['CommodityVO'] = [
            'quantity?' => 'string',
            'quantityUOM?' => 'string',
            'customsValue?' => '@Money',
            'countryOfManufacture?' => 'string',
            'name?' => 'string',
            'harmonizedCode' => 'string',
            'description?' => 'string',
            'weight?' => '@Weight',
            'commodityId?' => 'int',
            'additionalMeasures?' => '@Measure'
        ];

        $schemas['CustomsClearanceDetailVO'] = [
            'regulatoryControls?' => [['@type' => 'string', '@enum' => ['FOOD_OR_PERISHABLE', 'USMCA', 'NOT_APPLICABLE_FOR_LOW_VALUE_CUSTOMS_EXCEPTIONS', 'NOT_IN_FREE_CIRCULATION']]],
            'insuranceCharges?' => '@Money_1',
            'importerOfRecordAccountNumber?' => '@Party',
            'customsValue' => '@Money',
            'commercialInvoice?' => '@CommercialInvoice',
            'commodities' => ['@CommodityVO'],
            'documentContent?' => ['@type' => 'string', '@enum' => ['DOCUMENT', 'COMMODITY']]
        ];

        $schemas['FullSchema'] = [
            'serviceType?' => 'string',
            'totalCommodityValue?' => '@Money',
            'originAddress' => '@Address',
            'destinationAddress' => '@Address',
            'serviceOptionType?' => [['@type' => 'string', '@enum' => ['FEDEX_ONE_RATE', 'FREIGHT_GUARANTEE', 'SATURDAY_DELIVERY', 'SMART_POST_ALLOWED_INDICIA', 'SMART_POST_HUB_ID']]],
            'customsClearanceDetail?' => '@CustomsClearanceDetailVO',
            'shipDate?' => 'string',
            'carrierCode' => 'string',
            'totalWeight?' => '@Weight',
            'includeURLReferences?' => 'bool',
            'consolidationType?' => 'string',
            'consolidationRole?' => 'string',
            'version?' => '@Version'
        ];

        $schemas['GticResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_1'
        ];

        $schemas['Localization'] = [
            'localization?' => 'string',
            'localeCode?' => 'string'
        ];

        $schemas['LocalizedAttributeDetail'] = [
            'localization?' => '@Localization',
            'name?' => 'string',
            'description?' => 'string'
        ];

        $schemas['Measure'] = [
            'uom' => 'string',
            'quantity' => 'number'
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

        $schemas['MessageParameters'] = [
            'id?' => 'string',
            'value?' => 'string'
        ];

        $schemas['Money'] = [
            'amount?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['Money_1'] = [
            'amount?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['Parameter'] = [
            'value?' => 'string',
            'key?' => 'string'
        ];

        $schemas['Party'] = [
            'accountNumber?' => 'string'
        ];

        $schemas['RegulatoryCountryAdvisory'] = [
            'types?' => 'string',
            'messages?' => ['@Message']
        ];

        $schemas['RegulatoryCountryDetails'] = [
            'shipmentDetails' => '@RegulatoryShipmentDetail',
            'countryCode' => 'string',
            'category' => 'string'
        ];

        $schemas['RegulatoryDocument'] = [
            'userMessage?' => '@RegulatoryMessage',
            'supportType?' => ['@type' => 'string', '@enum' => ['FEDEX_GENERATED', 'FILLABLE', 'PRINT_ONLY', 'SAMPLE']],
            'allowability?' => 'string',
            'fileName?' => 'string',
            'documentType?' => 'string',
            'agencyUrl?' => 'string',
            'documentUrl?' => 'string',
            'id?' => 'string',
            'agencyName?' => 'string',
            'localizedAttributes?' => '@LocalizedAttributeDetail'
        ];

        $schemas['RegulatoryMessage'] = [
            'code?' => 'string',
            'messageParameters?' => ['@MessageParameters'],
            'index?' => 'int',
            'text?' => 'string',
            'localizedText?' => 'string'
        ];

        $schemas['RegulatoryShipmentDetail'] = [
            'regulatoryStatements?' => '@RegulatoryStatement',
            'requiredCommodityUnitsOfMeasure?' => ['string'],
            'level?' => 'string',
            'commodityIndex?' => 'int',
            'regulatoryDocuments?' => '@RegulatoryDocument',
            'regulatoryCountryAdvisories?' => '@RegulatoryCountryAdvisory'
        ];

        $schemas['RegulatoryStatement'] = [
            'userMessage?' => '@RegulatoryMessage',
            'supportType?' => ['@type' => 'string', '@enum' => ['FEDEX_GENERATED', 'FILLABLE', 'PRINT_ONLY', 'SAMPLE']],
            'allowability?' => 'string',
            'fileName?' => 'string',
            'agencyUrl?' => 'string',
            'documentUrl?' => 'string',
            'id?' => 'string',
            'completionType?' => ['@type' => 'string', '@enum' => ['COMPLETE', 'REQUIRES_COMPLETION']],
            'agencyName?' => 'string',
            'localizedAttributes?' => '@LocalizedAttributeDetail',
            'documentIds?' => ['string'],
            'statementUrl?' => 'string'
        ];

        $schemas['Version'] = [
            'major?' => 'int',
            'minor?' => 'int',
            'patch?' => 'int'
        ];

        $schemas['Weight'] = [
            'units' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'number'
        ];

        return $schemas;
    }
}
