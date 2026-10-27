<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the request and the response of the Harmonized System (HS) Code Classification API operation that
 * FedexHarmonizedCode implements, generated from schemas/harmonized-code.json and written for SchemaValidator
 */
final class FedexHarmonizedCodeSchemas
{
    /**
     * Name of the definition of the Search Harmonized Code Data request
     * @var string
     */
    const REQUEST = 'HarmonizedCodeSearchRequestVO';

    /**
     * Name of the definition of the Search Harmonized Code Data response
     * @var string
     */
    const RESPONSE = 'HarmonizedCodeSearchResponseVO';

    /**
     * Get the definitions of the request and the response. The names of the definitions, and of their fields, are the ones of
     * schemas/harmonized-code.json, with these differences, decided with the owner:
     *  - HsClassifyVO requires both destinationCountryCodes and originCountryCode in the file, but the documentation says that
     *    either one is required, and the minimum examples of the file send only one: both are optional, and every commodity has
     *    to have at least one of them (see hasACountry())
     *  - the request requires at least one commodity (hsClassify, which the file does not require): the documentation requires
     *    the description and a country of a commodity
     *  - the responses have at most four subheading options and four tariff alternates in the file: the number is not checked
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['HarmonizedCodeOutputVO'] = [
            'hsClassifications?' => ['@SummaryVO']
        ];

        $schemas['HarmonizedCodeSearchRequestVO'] = [
            'hsClassify' => ['@type' => [['@type' => '@HsClassifyVO', '@check' => [self::class, 'hasACountry']]], '@min' => 1]
        ];

        $schemas['HarmonizedCodeSearchResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@HarmonizedCodeOutputVO'
        ];

        $schemas['HsClassifyVO'] = [
            'commodityName?' => ['@type' => 'string', '@max' => 256],
            'commodityDescription' => ['@type' => 'string', '@max' => 512],
            'destinationCountryCodes?' => [['@type' => 'string', '@max' => 2]],
            'originCountryCode?' => ['@type' => 'string', '@max' => 2],
            'countryOfManufacture?' => ['@type' => 'string', '@max' => 2],
            'productId?' => ['@type' => 'string', '@max' => 100],
            'categories?' => ['string'],
            'material?' => 'string',
            'unitPrice?' => '@UnitPriceVO'
        ];

        $schemas['SubheadingAlternatesVO'] = [
            'name?' => 'string',
            'code?' => 'string',
            'description?' => 'string',
            'confidenceScore?' => 'number',
            'tarriffAlternates?' => ['@TarriffAlternatesVO']
        ];

        $schemas['SummaryVO'] = [
            'countryCode?' => 'string',
            'countryType?' => ['@type' => 'string', '@enum' => ['DESTINATION', 'ORIGIN']],
            'subheadingOptions?' => ['@SubheadingAlternatesVO']
        ];

        $schemas['TarriffAlternatesVO'] = [
            'name?' => 'string',
            'code?' => 'string',
            'description?' => 'string'
        ];

        $schemas['UnitPriceVO'] = [
            'amount?' => 'number',
            'currency?' => 'string'
        ];

        return $schemas;
    }

    /**
     * Check that a commodity of the request has a destination country or an origin country (the check of each item of hsClassify)
     * @param mixed $value The commodity, already known to be an object of the definition HsClassifyVO
     * @return bool|string True when it has one, the reason when it has none
     */
    public static function hasACountry($value)
    {
        $destinations = $value['destinationCountryCodes'] ?? [];
        $origin = $value['originCountryCode'] ?? '';

        if ((is_array($destinations) && $destinations !== []) || ($origin !== '' && $origin !== null)) {
            return true;
        }

        return 'the destination country codes or the origin country code is required';
    }
}
