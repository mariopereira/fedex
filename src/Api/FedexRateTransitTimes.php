<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexRateTransitTimesSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexRateTransitTimesException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Rates and Transit Times API: retrieves the rates, and the transit times, of the services that are available for a shipment
 * (rate/v1/rates/quotes).
 *
 * The operation only asks FedEx for a quote and changes nothing, so a request that failed because of a timeout, or a busy server,
 * can be repeated (see FedexClient::setRetryPolicy())
 */
class FedexRateTransitTimes extends AbstractFedexApi
{
    /**
     * Retrieve Rates and Transit Times endpoint (POST)
     * @var string
     */
    const ENDPOINT = 'rate/v1/rates/quotes';

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
     * Create an empty rates request: the fields that the schema requires, named as in schemas/rate.json. The account number is
     * an array with its value, the lists (requestedPackageLineItems) are empty: a package is an array with its weight. Any other
     * field of the schema can be added. Fields set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newRatesRequest() : array
    {
        return [
            'accountNumber' => [
                'value' => null
            ],
            'requestedShipment' => [
                'shipper' => [
                    'address' => [
                        'postalCode' => null,
                        'countryCode' => null
                    ]
                ],
                'recipient' => [
                    'address' => [
                        'postalCode' => null,
                        'countryCode' => null
                    ]
                ],
                'pickupType' => null,
                'requestedPackageLineItems' => []
            ]
        ];
    }

    /**
     * Retrieve the rates, and the transit times, of the services that are available for a shipment.
     * The request is checked before it is sent: the types and the allowed values of the fields are the ones of
     * schemas/rate.json and the required fields must be present (the account number and the customs commodities are not
     * required, because the samples of FedEx leave them out). The types are loose, as in the samples of the schema: a text can be a
     * number (a postal code as an integer), a number can be a text that is a number, and a boolean can be the text true or false.
     * A field that is not in the schema is not an error (the samples of the schema for Freight and for Intra Europe have fields
     * that it does not list), and FedEx decides about it. What the schema cannot express is checked by FedEx. Fields set to
     * null and empty lists are left out. Nothing is changed in FedEx, so the request can be repeated
     * @param array|\stdClass $request The request, as returned by newRatesRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexRateTransitTimesException
     */
    public function getRates($request) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexRateTransitTimesException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = (new SchemaValidator(true, FedexRateTransitTimesSchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . FedexRateTransitTimesSchemas::REQUEST)) {
            throw new FedexRateTransitTimesException(
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
     * Retrieve the rates, and the transit times, for a shipment between two addresses, with one package
     * @param string $accountNumber The FedEx account number
     * @param array $shipperAddress The address of the shipper: postalCode and countryCode, and any other field of the address
     * @param array $recipientAddress The address of the recipient, as the one of the shipper
     * @param float|int|string $weight The weight of the package, a number or a text that is a number
     * @param string $weightUnits The unit of the weight (KG or LB)
     * @param string $packagingType The packaging type
     * @param string $pickupType How the shipment is handed over (CONTACT_FEDEX_TO_SCHEDULE, DROPOFF_AT_FEDEX_LOCATION or
     * USE_SCHEDULED_PICKUP)
     * @param array $options Any other field of the request (rateRequestControlParameters, processingOptions, carrierCodes,
     * version), named as in the schema. accountNumber and requestedShipment cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexRateTransitTimesException
     */
    public function getRatesBetween(
        string $accountNumber,
        array $shipperAddress,
        array $recipientAddress,
        $weight,
        string $weightUnits = 'LB',
        string $packagingType = 'YOUR_PACKAGING',
        string $pickupType = 'DROPOFF_AT_FEDEX_LOCATION',
        array $options = []
    ) : \stdClass
    {
        foreach (['accountNumber', 'requestedShipment'] as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexRateTransitTimesException(
                    "The option '{$field}' cannot be used with getRatesBetween(): it is set by the method",
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }

        $options['accountNumber'] = ['value' => $accountNumber];
        $options['requestedShipment'] = [
            'shipper' => ['address' => $shipperAddress],
            'recipient' => ['address' => $recipientAddress],
            'pickupType' => $pickupType,
            'packagingType' => $packagingType,
            'requestedPackageLineItems' => [['weight' => ['units' => $weightUnits, 'value' => $weight]]]
        ];

        return $this->getRates($options);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx answered is
     * lost
     * @param mixed $response The response
     * @return \stdClass
     * @throws FedexRateTransitTimesException
     */
    protected function validateAndParse($response) : \stdClass
    {
        if (!($response instanceof \stdClass)) {
            throw new FedexRateTransitTimesException(
                'Invalid response',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexRateTransitTimesSchemas::definitions());

        if (!$validator->validate($response, '@' . FedexRateTransitTimesSchemas::RESPONSE)) {
            throw new FedexRateTransitTimesException(
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
