<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

use Mariopereira\Fedex\Misc\FedexUtils;

/**
 * Definitions of the request and the response of the Address Validation API operation that FedexAddressValidation implements,
 * taken from schemas/address-validation.json and written for SchemaValidator. The request and the response have a set each:
 * the request is checked strictly and the response leniently
 */
final class FedexAddressValidationSchemas
{
    /**
     * Name of the definition of the request
     * @var string
     */
    const REQUEST = 'Full_Schema_Validate_Address';

    /**
     * Name of the definition of the response
     * @var string
     */
    const RESPONSE = 'AdvcResponseVO';

    /**
     * Get the definitions used to validate the request, taken from schemas/address-validation.json.
     * Full_Schema_Validate_Address describes the request. The names of the schemas, and of their fields, are the
     * ones of the file. The differences from the file are the checks added to the postal code (a string or an
     * integer, as in the examples of the file), to the state or province code (2 characters at most), to the
     * country code (2 letters), to the date (YYYY-MM-DD) and to the number of addresses (1 to 100)
     * @return array
     */
    public static function requestDefinitions() : array
    {
        $schemas = [];

        $schemas['Full_Schema_Validate_Address'] = [
            'inEffectAsOfTimestamp?' => [
                '@type' => 'string',
                '@pattern' => '/^\\d{4}-\\d{2}-\\d{2}$/D',
                '@check' => fn($s) => FedexUtils::isDateValid($s, 'Y-m-d') ?: 'not a valid date (expected Y-m-d)'
            ],
            'validateAddressControlParameters?' => '@AddressResolutionControlParameters',
            'addressesToValidate' => ['@type' => ['@ResolveContactAndAddress'], '@min' => 1, '@max' => 100]
        ];

        $schemas['AddressResolutionControlParameters'] = [
            'includeResolutionTokens?' => 'bool'
        ];

        $schemas['ResolveContactAndAddress'] = [
            'address' => '@Address',
            'clientReferenceId?' => 'string'
        ];

        $schemas['Address'] = [
            'streetLines' => ['string'],
            'city' => 'string',
            'stateOrProvinceCode?' => ['@type' => 'string', '@max' => 2],
            'postalCode' => 'string|int',
            'countryCode?' => ['@type' => 'string', '@pattern' => '/^[A-Za-z]{2}$/D']
        ];

        return $schemas;
    }

    /**
     * Get the definitions used to validate the response, taken from schemas/address-validation.json.
     * AdvcResponseVO describes the response. The names of the schemas, and of their fields, are the ones of the file.
     * The differences from the file are that the city tokens may also be plain strings (the file declares objects but
     * its own example shows strings), that attributes is only checked to be an object (a free list of key-value
     * pairs) and that customerMessage, which has no schema in the file, accepts any value
     * @return array
     */
    public static function responseDefinitions() : array
    {
        $schemas = [];

        $schemas['AdvcResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO'
        ];

        $schemas['BaseProcessOutputVO'] = '@AddressResolutionOutputVOV3';

        $schemas['AddressResolutionOutputVOV3'] = [
            'resolvedAddresses?' => ['@ResolvedAddress'],
            'alerts?' => ['@Alert']
        ];

        $schemas['ResolvedAddress'] = [
            'streetLinesToken?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'countryCode?' => 'string',
            'customerMessage?' => ['@CustomerMessage'],
            'cityToken?' => ['string|@ResolutionToken'],
            'postalCodeToken?' => '@ResolutionToken',
            'parsedPostalCode?' => '@ParsedPostalCode',
            'classification?' => ['@type' => 'string', '@enum' => ['MIXED', 'UNKNOWN', 'BUSINESS', 'RESIDENTIAL']],
            'postOfficeBox?' => 'bool',
            'normalizedStatusNameDPV?' => 'bool',
            'standardizedStatusNameMatchSource?' => 'string',
            'resolutionMethodName?' => 'string',
            'ruralRouteHighwayContract?' => 'bool',
            'generalDelivery?' => 'bool',
            'attributes?' => 'object'
        ];

        $schemas['Alert'] = [
            'code?' => 'string',
            'message?' => 'string',
            'alertType?' => ['@type' => 'string', '@enum' => ['NOTE', 'WARNING']]
        ];

        $schemas['CustomerMessage'] = 'mixed';

        $schemas['ResolutionToken'] = [
            'changed?' => 'bool',
            'value?' => 'string'
        ];

        $schemas['ParsedPostalCode'] = [
            'base?' => 'string',
            'addOn?' => 'string',
            'deliveryPoint?' => 'string'
        ];

        return $schemas;
    }
}
