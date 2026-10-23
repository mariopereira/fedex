<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexDutyAndTaxSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexDutyAndTaxException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Estimated Duty and Tax API: estimates the duties, the taxes and the fees of a shipment, from its commodities, its origin
 * and its destination (dutytax/v1/estimate).
 *
 * An estimate changes nothing in FedEx, so a request that failed because of a timeout, or a busy server, can be repeated
 * (see FedexClient::setRetryPolicy())
 */
class FedexDutyAndTax extends AbstractFedexApi
{
    /**
     * Duties Taxes Estimates endpoint (POST)
     * @var string
     */
    const ENDPOINT = 'dutytax/v1/estimate';

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
     * Create an empty estimate request: the fields that the API requires, named as in schemas/dutiestaxes.json. The list of
     * commodities is empty and each entry of it is made like newCommodity(). Any other field of the schema can be added
     * (shipmentPurpose, serviceType, shippingCost and preferredCurrency, and the postal code and the state or province
     * code of the shipper and of the recipient). Fields set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newRequest() : array
    {
        return [
            'shipper' => [
                'countryCode' => null
            ],
            'recipient' => [
                'countryCode' => null
            ],
            'commodities' => []
        ];
    }

    /**
     * Create an empty commodity, for the list of commodities of the request: the fields that the API requires, named as in
     * schemas/dutiestaxes.json. Any other field of the schema can be added (commodityName, countryOfManufacture and
     * harmonizedCode). Fields set to null are left out when the request is sent
     * @return array
     */
    public static function newCommodity() : array
    {
        return [
            'description' => null,
            'quantity' => null,
            'unitPrice' => [
                'amount' => null,
                'currencyCode' => null
            ]
        ];
    }

    /**
     * Estimate the duties, the taxes and the fees of a shipment.
     * The request is checked before it is sent: the fields, their types, their allowed values and their lengths are the
     * ones of schemas/dutiestaxes.json (a field that is not in the schema is sent as it is, and FedEx decides about it; the types are loose, as in the examples of FedEx: a text can be a number or a boolean, a number can be a text that is a number or blank, and a boolean can be the text true or false in any case, or 0 or 1), the required fields must be present,
     * and the country codes of the shipper and the recipient must be two letters. What the schema cannot express (the state or
     * province code, which is required for Canada and Brazil) is checked by FedEx. Fields set to null and empty lists are left
     * out. Nothing is changed in FedEx, so the request can be repeated
     * @param array|\stdClass $request The request, as returned by newRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexDutyAndTaxException
     */
    public function estimate($request) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexDutyAndTaxException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = (new SchemaValidator(true, FedexDutyAndTaxSchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . FedexDutyAndTaxSchemas::REQUEST)) {
            throw new FedexDutyAndTaxException(
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
     * Estimate the duties, the taxes and the fees of a shipment, from the countries and the commodities
     * @param string $shipperCountryCode The country of the shipper, two letters
     * @param string $recipientCountryCode The country of the recipient, two letters
     * @param array $commodities The commodities: each one an array (or an object) made like newCommodity(). From the
     * schema, a list with at least one is required
     * @param array $options Any other field of the request (shipmentPurpose, serviceType, shippingCost and
     * preferredCurrency), named as in the schema. shipper, recipient and commodities cannot be used: they are set by this
     * method. For the postal code or the state of the shipper or the recipient use estimate()
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexDutyAndTaxException
     */
    public function estimateForCommodities(string $shipperCountryCode, string $recipientCountryCode, array $commodities, array $options = []) : \stdClass
    {
        foreach (['shipper', 'recipient', 'commodities'] as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexDutyAndTaxException(
                    "The option '{$field}' cannot be used with " . __FUNCTION__ . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        $options['shipper'] = ['countryCode' => $shipperCountryCode];
        $options['recipient'] = ['countryCode' => $recipientCountryCode];
        $options['commodities'] = FedexUtils::objectToArray($commodities);

        return $this->estimate($options);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx
     * answered is lost
     * @param mixed $response The response
     * @return \stdClass
     * @throws FedexDutyAndTaxException
     */
    protected function validateAndParse($response) : \stdClass
    {
        if (!($response instanceof \stdClass)) {
            throw new FedexDutyAndTaxException(
                'Invalid response',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexDutyAndTaxSchemas::definitions());

        if (!$validator->validate($response, '@' . FedexDutyAndTaxSchemas::RESPONSE)) {
            throw new FedexDutyAndTaxException(
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
