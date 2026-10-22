<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the response of the Postal Code Validation API operation that FedexPostalCodeValidation implements, written for
 * SchemaValidator
 */
final class FedexPostalCodeValidationSchemas
{
    /**
     * Name of the definition of the response
     * @var string
     */
    const RESPONSE = 'Body';

    /**
     * Get the definitions used to validate a response (the request is checked by FedexPostalCodeValidation itself)
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['Body'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@Output'
        ];

        $schemas['Output'] = [
            'countryCode?' => 'string',
            'cityFirstInitials?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'alerts?' => ['@Alert'],
            'locationDescriptions?' => ['@LocationDescription'],
            'cleanedPostalCode?' => 'string'
        ];

        $schemas['Alert'] = [
            'code?' => 'string',
            'alertType?' => [
                '@type' => 'string',
                '@enum' => ['NOTE', 'WARNING']
            ],
            'parameterList?' => ['@Parameters'],
            'message?' => 'string'
        ];

        $schemas['Parameters'] = [
            'value?' => 'string',
            'key?' => 'string'
        ];

        $schemas['LocationDescription'] = [
            'locationId' => 'string',
            'locationNumber' => 'string',
            'serviceArea' => 'string',
            'airportId' => 'string'
        ];

        return $schemas;
    }
}
