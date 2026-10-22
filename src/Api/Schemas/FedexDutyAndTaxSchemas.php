<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the request and the response of the Estimated Duty and Tax API operation that FedexDutyAndTax implements,
 * generated from schemas/dutiestaxes.json and written for SchemaValidator
 */
final class FedexDutyAndTaxSchemas
{
    /**
     * Name of the definition of the Duties Taxes Estimates request
     * @var string
     */
    const REQUEST = 'EdtDetailsRequestVO';

    /**
     * Name of the definition of the Duties Taxes Estimates response
     * @var string
     */
    const RESPONSE = 'EdtDetailsResponseVO';

    /**
     * Get the definitions of the request and the response. The names of the definitions, and of their fields, are the ones
     * of schemas/dutiestaxes.json, with one difference, decided with the owner: the country code of the shipper and the
     * country code of the recipient must be two letters (the description of the file says "the two-letter code used to
     * identify a country", and the schema has no length)
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['Commodities'] = [
            'commodityName?' => ['@type' => 'string', '@max' => 256],
            'countryOfManufacture?' => ['@type' => 'string', '@max' => 2],
            'description' => ['@type' => 'string', '@max' => 512],
            'harmonizedCode?' => 'string',
            'quantity' => 'int',
            'unitPrice' => '@UnitPrice'
        ];

        $schemas['CostDetail'] = [
            'amount?' => 'number',
            'currency?' => 'string',
            'description?' => 'string',
            'formula?' => 'string',
            'note?' => 'string'
        ];

        $schemas['EdtDetails'] = [
            'duties?' => ['@CostDetail'],
            'fees?' => ['@CostDetail'],
            'taxes?' => ['@CostDetail'],
            'summary?' => '@Summary'
        ];

        $schemas['EdtDetailsRequestVO'] = [
            'shipper' => '@Shipper',
            'recipient' => '@Recipient',
            'commodities' => ['@Commodities'],
            'shipmentPurpose?' => ['@type' => 'string', '@enum' => ['DOCUMENTS', 'FOR_RESALE', 'NOT_FOR_RESALE', 'GIFT', 'RETURN']],
            'serviceType?' => 'string',
            'shippingCost?' => '@ShippingCost',
            'preferredCurrency?' => 'string'
        ];

        $schemas['EdtDetailsResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@Output'
        ];

        $schemas['Output'] = [
            'edtDetails?' => ['@EdtDetails']
        ];

        $schemas['Recipient'] = [
            'countryCode' => ['@type' => 'string', '@pattern' => '/^[A-Za-z]{2}$/D'],
            'postalCode?' => 'string',
            'stateOrProvinceCode?' => 'string'
        ];

        $schemas['Shipper'] = [
            'countryCode' => ['@type' => 'string', '@pattern' => '/^[A-Za-z]{2}$/D'],
            'postalCode?' => 'string',
            'stateOrProvinceCode?' => 'string'
        ];

        $schemas['ShippingCost'] = [
            'amount?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['Summary'] = [
            'totalDuty?' => 'number',
            'totalFees?' => 'number',
            'totalShippingCosts?' => 'number',
            'totalTaxes?' => 'number',
            'totalLandedCost?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['UnitPrice'] = [
            'amount' => 'number',
            'currencyCode' => 'string'
        ];

        return $schemas;
    }
}
