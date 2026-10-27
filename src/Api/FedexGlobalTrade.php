<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexGlobalTradeSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexGlobalTradeException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Global Trade API: retrieves the regulatory documents that a shipment needs, and the advisories that apply to it, before it is
 * shipped (globaltrade/v1).
 *
 * The operation only asks FedEx and changes nothing, so a request that failed because of a timeout, or a busy server, can be
 * repeated (see FedexClient::setRetryPolicy())
 */
class FedexGlobalTrade extends AbstractFedexApi
{
    /**
     * Retrieve Regulatory Details endpoint (POST)
     * @var string
     */
    const ENDPOINT = 'globaltrade/v1/shipments/regulatorydetails/retrieve';

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
     * Create an empty Retrieve Regulatory Details request: the fields that the schema requires, named as in
     * schemas/global-trade.json (the country codes of the origin and the destination, and the carrier). Any other field of the
     * schema can be added (serviceType, shipDate, totalWeight, totalCommodityValue, customsClearanceDetail, ...). Fields set to
     * null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newRequest() : array
    {
        return [
            'originAddress' => [
                'countryCode' => null
            ],
            'destinationAddress' => [
                'countryCode' => null
            ],
            'carrierCode' => null
        ];
    }

    /**
     * Retrieve the regulatory documents and the advisories that apply to a shipment.
     * The request is checked before it is sent: the types and the allowed values of the fields are the ones of
     * schemas/global-trade.json (a field that is not in the schema is sent as it is, and FedEx decides about it; the types are
     * loose, as in the examples of FedEx: a text can be a number or a boolean, a number can be a text that is a number or blank,
     * and a boolean can be the text true or false in any case, or 0 or 1) and the required fields must be present (the value of
     * a weight is not required, because the examples of FedEx leave it out). What the schema cannot express is checked by FedEx.
     * Fields set to null and empty lists are left out. Nothing is changed in FedEx, so the request can be repeated
     * @param array|\stdClass $request The request, as returned by newRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexGlobalTradeException
     */
    public function retrieveRegulatoryDetails($request) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexGlobalTradeException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = (new SchemaValidator(true, FedexGlobalTradeSchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . FedexGlobalTradeSchemas::REQUEST)) {
            throw new FedexGlobalTradeException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $response = $this->client->request('POST', self::ENDPOINT, $request, [], [], FedexUtils::uuidV4(), true);

        return $this->validateAndParse($response);
    }

    /**
     * Retrieve the regulatory documents and the advisories for a shipment between two addresses
     * @param array $originAddress The address of the origin: countryCode, and optionally postalCode and stateOrProvinceCode
     * @param array $destinationAddress The address of the destination, as the one of the origin
     * @param string $carrierCode The carrier (FDXE, FDXG, ...)
     * @param array $options Any other field of the request (serviceType, shipDate, totalWeight, totalCommodityValue,
     * customsClearanceDetail, serviceOptionType, includeURLReferences, consolidationType, consolidationRole, version), named as
     * in the schema. originAddress, destinationAddress and carrierCode cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexGlobalTradeException
     */
    public function retrieveRegulatoryDetailsFor(array $originAddress, array $destinationAddress, string $carrierCode, array $options = []) : \stdClass
    {
        foreach (['originAddress', 'destinationAddress', 'carrierCode'] as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexGlobalTradeException(
                    "The option '{$field}' cannot be used with " . __FUNCTION__ . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        $options['originAddress'] = $originAddress;
        $options['destinationAddress'] = $destinationAddress;
        $options['carrierCode'] = $carrierCode;

        return $this->retrieveRegulatoryDetails($options);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx answered is
     * lost
     * @param mixed $response The response
     * @return \stdClass
     * @throws FedexGlobalTradeException
     */
    protected function validateAndParse($response) : \stdClass
    {
        if (!($response instanceof \stdClass)) {
            throw new FedexGlobalTradeException(
                'Invalid response',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexGlobalTradeSchemas::definitions());

        if (!$validator->validate($response, '@' . FedexGlobalTradeSchemas::RESPONSE)) {
            throw new FedexGlobalTradeException(
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
