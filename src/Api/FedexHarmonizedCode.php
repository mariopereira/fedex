<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexHarmonizedCodeSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexHarmonizedCodeException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Harmonized System (HS) Code Classification API: suggests the HS codes of commodities, from their description and their
 * destination or origin country (commodity/v2/hscodes). The search is powered by artificial intelligence, and its results are
 * suggestions only.
 *
 * The operation only asks FedEx and changes nothing, so a request that failed because of a timeout, or a busy server, can be
 * repeated (see FedexClient::setRetryPolicy())
 */
class FedexHarmonizedCode extends AbstractFedexApi
{
    /**
     * Search Harmonized Code Data endpoint (POST)
     * @var string
     */
    const ENDPOINT = 'commodity/v2/hscodes/search';

    /**
     * Initialize the object
     * @param FedexAuth $auth Fedex Authorization class, to fetch tokens
     * @param string $locale Locale to use (defaults to en_US)
     * @param int $timeout Timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @param FedexClient|null $client Client to use. When it is supplied the locale and the timeouts above are not
     * used: they have to be configured on that client
     */
    public function __construct(
        FedexAuth $auth,
        string $locale = 'en_US',
        int $timeout = 30,
        int $connectTimeout = 10,
        ?FedexClient $client = null
    )
    {
        parent::__construct($auth, $locale, $timeout, $connectTimeout, $client);
    }

    /**
     * Create an empty Search Harmonized Code Data request: the list of the commodities (hsClassify) is empty, and each commodity
     * is an array as returned by newClassification(). At least one commodity is required
     * @return array
     */
    public static function newRequest() : array
    {
        return [
            'hsClassify' => []
        ];
    }

    /**
     * Create an empty commodity of the request: its description, and its destination countries and its origin country (at least
     * one of the two is required: the empty one is left out when the request is sent). Any other field of the schema can be added
     * (commodityName, countryOfManufacture, productId, categories, material, unitPrice)
     * @return array
     */
    public static function newClassification() : array
    {
        return [
            'commodityDescription' => null,
            'destinationCountryCodes' => [],
            'originCountryCode' => null
        ];
    }

    /**
     * Search the HS codes of commodities.
     * The request is checked before it is sent: the types, the lengths and the allowed values of the fields are the ones of
     * schemas/harmonized-code.json (a field that is not in the schema is sent as it is, and FedEx decides about it; the types are
     * loose, as in the examples of FedEx: a text can be a number or a boolean, a number can be a text that is a number or blank,
     * and a boolean can be the text true or false in any case, or 0 or 1). At least one commodity is required, and every commodity
     * needs its description and a destination country or an origin country (or both), as the documentation says. What the schema
     * cannot express is checked by FedEx. Fields set to null and empty lists are left out. Nothing is changed in FedEx, so the
     * request can be repeated
     * @param array|\stdClass $request The request, as returned by newRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexHarmonizedCodeException
     */
    public function searchHarmonizedCodes($request) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexHarmonizedCodeException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = (new SchemaValidator(true, FedexHarmonizedCodeSchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . FedexHarmonizedCodeSchemas::REQUEST)) {
            throw new FedexHarmonizedCodeException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        // x-content-type is required by the schema, besides the Content-Type that the client sends
        $response = $this->client->request('POST', self::ENDPOINT, $request, [], ['x-content-type' => 'application/json'], FedexUtils::uuidV4(), true);

        return $this->validateAndParse($response);
    }

    /**
     * Search the HS codes of one commodity
     * @param string $commodityDescription The description of the commodity: the more detailed, the better the results
     * @param string[] $destinationCountryCodes The destination countries (two letters each), none to leave them out
     * @param string|null $originCountryCode The origin country (two letters), null to leave it out. At least one country, of
     * destination or of origin, is required
     * @param array $options Any other field of the commodity (commodityName, countryOfManufacture, productId, categories,
     * material, unitPrice), named as in the schema. commodityDescription, destinationCountryCodes and originCountryCode cannot be
     * used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexHarmonizedCodeException
     */
    public function searchFor(string $commodityDescription, array $destinationCountryCodes = [], ?string $originCountryCode = null, array $options = []) : \stdClass
    {
        foreach (['commodityDescription', 'destinationCountryCodes', 'originCountryCode'] as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexHarmonizedCodeException(
                    "The option '{$field}' cannot be used with " . __FUNCTION__ . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        $options['commodityDescription'] = $commodityDescription;
        $options['destinationCountryCodes'] = array_values($destinationCountryCodes);
        $options['originCountryCode'] = $originCountryCode;

        return $this->searchHarmonizedCodes(['hsClassify' => [$options]]);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx answered is
     * lost
     * @param mixed $response The response
     * @return \stdClass
     * @throws FedexHarmonizedCodeException
     */
    protected function validateAndParse($response) : \stdClass
    {
        if (!($response instanceof \stdClass)) {
            throw new FedexHarmonizedCodeException(
                'Invalid response',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexHarmonizedCodeSchemas::definitions());

        if (!$validator->validate($response, '@' . FedexHarmonizedCodeSchemas::RESPONSE)) {
            throw new FedexHarmonizedCodeException(
                'Invalid response received',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                $validator->getErrors(),
                $response
            );
        }

        return $response;
    }
}
