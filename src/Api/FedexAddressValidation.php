<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexAddressValidationSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexAddressValidationException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Address Validation API: validates and resolves addresses (address/v1/addresses/resolve)
 */
class FedexAddressValidation extends AbstractFedexApi
{
    /**
     * Address Validation API endpoint
     * @var string
     */
    const ENDPOINT = 'address/v1/addresses/resolve';

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
     * Create an empty request: every field of the request defined by the API, named as in schemas/address-validation.json.
     * Fields that are not needed can be left as they are: null (and the empty lists) are left out when the request
     * is sent. Add the addresses to addressesToValidate, each one as returned by newAddress()
     * @return array
     */
    public static function newRequest() : array
    {
        return [
            'inEffectAsOfTimestamp' => null,
            'validateAddressControlParameters' => [
                'includeResolutionTokens' => null
            ],
            'addressesToValidate' => []
        ];
    }

    /**
     * Create an empty item of addressesToValidate: an address and the optional client reference id.
     * Fill in the address (the street lines are a list) and append the item to addressesToValidate of the request
     * @return array
     */
    public static function newAddress() : array
    {
        return [
            'address' => [
                'streetLines' => [],
                'city' => null,
                'stateOrProvinceCode' => null,
                'postalCode' => null,
                'countryCode' => null
            ],
            'clientReferenceId' => null
        ];
    }

    /**
     * Validate and resolve addresses, with the full control of the request.
     * The request is checked before it is sent: the fields, their types and their allowed values are the ones of
     * schemas/address-validation.json (a field that is not in the schema is an error), from 1 to 100 addresses are
     * required, each one with its street lines (not empty), city and postal code, and the date of
     * inEffectAsOfTimestamp must be a valid YYYY-MM-DD date. Fields set to null and empty lists are left out
     * @param array|\stdClass $request The request, as returned by newRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexAddressValidationException
     */
    public function resolve($request) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexAddressValidationException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = new SchemaValidator(false, FedexAddressValidationSchemas::requestDefinitions());

        if (!$validator->validate($request, '@' . FedexAddressValidationSchemas::REQUEST)) {
            throw new FedexAddressValidationException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $this->checkRequest($request);

        $customerTransactionId = FedexUtils::uuidV4();

        $response = $this->client->request('POST', self::ENDPOINT, $request, [], [], $customerTransactionId, true);

        return $this->validateAndParse($response);
    }

    /**
     * Validate and resolve one address
     * @param array|\stdClass $address The address, with the fields of the address of the schema: streetLines (a list),
     * city, postalCode, and optionally stateOrProvinceCode (2 characters at most) and countryCode (2 letters)
     * @param array $options Any other field of the request (inEffectAsOfTimestamp, validateAddressControlParameters),
     * named as in the schema. addressesToValidate cannot be used: it is set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexAddressValidationException
     */
    public function validate($address, array $options = []) : \stdClass
    {
        return $this->validateArray([$address], $options);
    }

    /**
     * Validate and resolve several addresses (up to 100) in one request.
     * To send a client reference id with an address, use resolve()
     * @param array $addresses The addresses, each one an array or an object with the fields of the address of the schema
     * @param array $options Any other field of the request (inEffectAsOfTimestamp, validateAddressControlParameters),
     * named as in the schema. addressesToValidate cannot be used: it is set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexAddressValidationException
     */
    public function validateArray(array $addresses, array $options = []) : \stdClass
    {
        if (array_key_exists('addressesToValidate', $options)) {
            throw new FedexAddressValidationException(
                "The option 'addressesToValidate' cannot be used with " . __FUNCTION__ . '(): it is set by the method',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $items = [];

        foreach (array_values($addresses) as $i => $address) {
            if (is_object($address)) {
                $address = FedexUtils::objectToArray($address);
            }

            if (!is_array($address)) {
                throw new FedexAddressValidationException(
                    'Each address must be an array or an object (address ' . ($i + 1) . ')',
                    FedexException::EC_BAD_PARAMETER
                );
            }

            $items[] = ['address' => $address];
        }

        $options['addressesToValidate'] = $items;

        return $this->resolve($options);
    }

    /**
     * Check what the schema cannot express: the street lines must not be empty.
     * Called with a request that already passed the schema, so that every address has its street lines
     * @param array $request The request
     * @return void
     * @throws FedexAddressValidationException
     */
    protected function checkRequest(array $request) : void
    {
        foreach ($request['addressesToValidate'] as $i => $item) {
            foreach ($item['address']['streetLines'] as $line) {
                if ('' === trim($line)) {
                    throw new FedexAddressValidationException(
                        'The street lines cannot be empty (address ' . ($i + 1) . ')',
                        FedexException::EC_BAD_PARAMETER
                    );
                }
            }
        }
    }

    /**
     * Validate the response received from FedEx
     * @param \stdClass $response
     * @return \stdClass
     * @throws FedexAddressValidationException
     */
    protected function validateAndParse($response) : \stdClass
    {
        if (!($response instanceof \stdClass)) {
            throw new FedexAddressValidationException(
                'Invalid response',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexAddressValidationSchemas::responseDefinitions());

        if (!$validator->validate($response, '@' . FedexAddressValidationSchemas::RESPONSE)) {
            throw new FedexAddressValidationException(
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
