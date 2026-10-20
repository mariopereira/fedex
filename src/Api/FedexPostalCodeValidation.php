<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexPostalCodeValidationException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Postal Code Validation API: validates postal codes and service commitments (country/v1/postal/validate)
 */
class FedexPostalCodeValidation extends AbstractFedexApi
{
    /**
     * Postal Code Validation API endpoint
     * @var string
     */
    const ENDPOINT = 'country/v1/postal/validate';

    /**
     * Codes of the FedEx operating companies accepted by the API
     * @var string[]
     */
    public const CARRIER_CODES = ['FDXE', 'FDXG', 'FXSP', 'FDXC', 'FXCC'];

    /**
     * API major version, or null to leave it out of the request
     * @var int|null
     */
    protected ?int $majorVersion = 1;

    /**
     * API minor version, or null to leave it out of the request
     * @var int|null
     */
    protected ?int $minorVersion = 1;

    /**
     * API patch number, or null to leave it out of the request
     * @var int|null
     */
    protected ?int $patch = 1;

    /**
     * Initialize the object
     * @param FedexAuth $auth Fedex Authorization class, to fetch tokens
     * @param string $locale Locale to use (defaults to en_US)
     * @param int $timeout Timeout in seconds
     * @param int $connectTimeout Connect timeout in seconds
     * @param int|null $majorVersion API major version (1, the value of the example of the schema, unless it is given).
     * Null leaves it out of the request
     * @param int|null $minorVersion API minor version (1, as above). Null leaves it out of the request
     * @param int|null $patch API patch number (1, as above). Null leaves it out of the request
     * @param FedexClient|null $client Client to use. When it is supplied the locale and the timeouts above are not
     * used: they have to be configured on that client
     */
    public function __construct(
        FedexAuth $auth,
        string $locale = 'en_US',
        int $timeout = 30,
        int $connectTimeout = 10,
        ?int $majorVersion = 1,
        ?int $minorVersion = 1,
        ?int $patch = 1,
        ?FedexClient $client = null
    )
    {
        parent::__construct($auth, $locale, $timeout, $connectTimeout, $client);

        $this->majorVersion = $majorVersion;
        $this->minorVersion = $minorVersion;
        $this->patch = $patch;
    }

    /**
     * Set the API version to call. The version is optional in the schema: a number that is null is left out of the
     * request, and when all three are null the request has no version at all
     * @param int|null $majorVersion API major version
     * @param int|null $minorVersion API minor version
     * @param int|null $patch API patch number
     * @return FedexPostalCodeValidation
     * @throws FedexPostalCodeValidationException
     */
    public function setAPIversion(?int $majorVersion, ?int $minorVersion, ?int $patch) : FedexPostalCodeValidation
    {
        if ((($majorVersion ?? 0) < 0) || (($minorVersion ?? 0) < 0) || (($patch ?? 0) < 0)) {
            throw new FedexPostalCodeValidationException(
                'Negative numbers are invalid',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $this->majorVersion = $majorVersion;
        $this->minorVersion = $minorVersion;
        $this->patch = $patch;

        return $this;
    }

    /**
     * Validate a postal code
     * @param string $carrierCode Four letter code of a FedEx operating company, one of the CARRIER_CODES (upper or lower case)
     * @param string $countryCode Two letter country code
     * @param string $stateOrProvinceCode State or province code
     * @param string $postalCode Postal code to validate
     * @param string $shipDate Date which the package will be shipped in the format YYYY-MM-DD. Should not be the current date or 10 days after current date
     * @param string|null $routingCode Routing code (optional)
     * @param bool|null $checkForMismatch Check for State/Province code and postal code mismatch (optional)
     * @param string|null $city Name of the city. Required when the country code is AU (Australia), optional otherwise
     * @return \stdClass
     */
    public function validate(
        string $carrierCode,
        string $countryCode,
        string $stateOrProvinceCode,
        string $postalCode,
        string $shipDate,
        ?string $routingCode = null,
        ?bool $checkForMismatch = null,
        ?string $city = null
    )
    {
        $this->checkParameters(
            $carrierCode,
            $countryCode,
            $stateOrProvinceCode,
            $postalCode,
            $shipDate,
            $routingCode,
            $checkForMismatch,
            $city
        );

        $body = $this->body(
            $carrierCode,
            $countryCode,
            $stateOrProvinceCode,
            $postalCode,
            $shipDate,
            $routingCode,
            $checkForMismatch,
            $city
        );

        $customerTransactionId = FedexUtils::uuidV4();

        $response = $this->client->request('POST', self::ENDPOINT, $body, [], [], $customerTransactionId);
        $data = $this->validateAndParse($response);

        return $data;
    }

    /**
     * Validate and adjust parameters
     * @param string $carrierCode
     * @param string $countryCode
     * @param string $stateOrProvinceCode
     * @param string $postalCode
     * @param string $shipDate
     * @param string|null $routingCode
     * @param bool|null $checkForMismatch
     * @param string|null $city
     * @return void
     * @throws FedexPostalCodeValidationException
     */
    protected function checkParameters(
        string &$carrierCode,
        string &$countryCode,
        string &$stateOrProvinceCode,
        string &$postalCode,
        string &$shipDate,
        ?string &$routingCode = null,
        ?bool &$checkForMismatch = null,
        ?string &$city = null
    )
    {
        $carrierCode = strtoupper(trim($carrierCode));

        if ('' === $carrierCode) {
            throw new FedexPostalCodeValidationException(
                'Missing carrier code',
                FedexException::EC_MISSING_PARAMETER
            );
        } elseif (!in_array($carrierCode, self::CARRIER_CODES, true)) {
            throw new FedexPostalCodeValidationException(
                'Invalid carrier code (valid codes: ' . implode(', ', self::CARRIER_CODES) . ')',
                FedexException::EC_BAD_PARAMETER
            );
        }

        if (empty($countryCode)) {
            throw new FedexPostalCodeValidationException(
                'Missing country code',
                FedexException::EC_MISSING_PARAMETER
            );
        }

        if (empty($postalCode)) {
            throw new FedexPostalCodeValidationException(
                'Missing postal code',
                FedexException::EC_MISSING_PARAMETER
            );
        }

        if (empty($shipDate)) {
            throw new FedexPostalCodeValidationException(
                'Missing ship date',
                FedexException::EC_MISSING_PARAMETER
            );
        } elseif (!FedexUtils::isDateValid($shipDate, 'Y-m-d')) {
            throw new FedexPostalCodeValidationException(
                'Ship date must be a valid YYYY-MM-DD date',
                FedexException::EC_BAD_PARAMETER
            );
        }

        if (empty($routingCode)) {
            $routingCode = null;
        }

        if (!is_null($city)) {
            $city = trim($city);

            if ('' === $city) {
                $city = null;
            }
        }

        if (is_null($city) && ('AU' === strtoupper(trim($countryCode)))) {
            throw new FedexPostalCodeValidationException(
                'Missing city (required when the country code is AU)',
                FedexException::EC_MISSING_PARAMETER
            );
        }
    }

    /**
     * Create the payload to send
     * @param string $carrierCode
     * @param string $countryCode
     * @param string $stateOrProvinceCode
     * @param string $postalCode
     * @param string $shipDate
     * @param string|null $routingCode
     * @param bool|null $checkForMismatch
     * @param string|null $city
     * @return \stdClass
     */
    protected function body(
        string $carrierCode,
        string $countryCode,
        string $stateOrProvinceCode,
        string $postalCode,
        string $shipDate,
        ?string $routingCode = null,
        ?bool $checkForMismatch = null,
        ?string $city = null
    )
    {
        $body = (object) [
            'carrierCode' => $carrierCode,
            'countryCode' => $countryCode,
            'stateOrProvinceCode' => $stateOrProvinceCode,
            'postalCode' => $postalCode,
            'shipDate' => $shipDate
        ];

        if (!is_null($routingCode)) {
            $body->routingCode = $routingCode;
        }

        if (!is_null($checkForMismatch)) {
            $body->checkForMismatch = $checkForMismatch;
        }

        if (!is_null($city)) {
            $body->city = $city;
        }

        // the version is optional in the schema: only the numbers that are set are sent
        $version = array_filter([
            'major' => $this->majorVersion,
            'minor' => $this->minorVersion,
            'patch' => $this->patch
        ], function ($number) {
            return !is_null($number);
        });

        if ($version !== []) {
            $body->version = (object) $version;
        }

        return $body;
    }

    /**
     * Validate the response received from FedEx
     * @param \stdClass $response
     * @return \stdClass
     * @throws FedexPostalCodeValidationException
     */
    protected function validateAndParse($response) : \stdClass
    {
        if (!($response instanceof \stdClass)) {
            throw new FedexPostalCodeValidationException(
                'Invalid response',
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, $this->responseSchemas());

        if (!$validator->validate($response, '@Body')) {
            throw new FedexPostalCodeValidationException(
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

    /**
     * Get the schemas used to validate a response
     * @return array
     */
    protected function responseSchemas() : array
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
