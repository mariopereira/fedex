<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexRegulatorySchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexRegulatoryException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Regulatory API: stores the regulatory compliance data of products (CPSC certificates of compliance, EU de minimis product
 * identifiers), which shipments can then reference in commodities[].regulatoryDetails[] (regulatory/v1/profiles).
 *
 * Storing profiles changes data in FedEx, so it is never repeated by this library, except when FedEx answers that the token is
 * not valid, which means that nothing was processed. If an exception is thrown after the request was sent (a timeout, a network
 * failure), the profiles may have been stored: check it before sending the request again
 */
class FedexRegulatory extends AbstractFedexApi
{
    /**
     * Regulatory Profile endpoint (POST)
     * @var string
     */
    const ENDPOINT = 'regulatory/v1/profiles';

    /**
     * The maximum number of profiles in one request
     * @var int
     */
    const MAX_PROFILES = 25;

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
     * Create an empty profile: the fields that the schema requires, named as in schemas/regulatory.json. The regulation (CPSC or
     * EU_DE_MINIMIS), the product id, its type (SKU, PART_NUMBER, REGISTERED_NUMBER, GTIN, UPC, EAN, MPN or OTHER) and the
     * country of import are to be filled in, and details is a list of at least one detail: for CPSC a disclaimMessageSet, a
     * referenceMessageSet or a fullMessageSet; for EU_DE_MINIMIS the manufacturer and merchant product ids. Fields set to null
     * and empty lists are left out when the request is sent
     * @return array
     */
    public static function newProfile() : array
    {
        return [
            'regulationCode' => null,
            'productId' => null,
            'productIdType' => null,
            'countryOfImport' => null,
            'details' => []
        ];
    }

    /**
     * Store the regulatory profiles of products. THIS STORES DATA IN FEDEX.
     * The request is checked before it is sent: the types, the lengths and the allowed values of the fields are the ones of
     * schemas/regulatory.json (a field that is not in the schema is sent as it is, and FedEx decides about it; the types are
     * loose, as in the examples of FedEx: a text can be a number or a boolean, a number can be a text that is a number or blank,
     * and a boolean can be the text true or false in any case, or 0 or 1), from 1 to 25 profiles are required, each one with its
     * required fields and at least one detail. What the schema cannot express (the details that each regulation needs) is checked
     * by FedEx. Fields set to null and empty lists are left out.
     * The request is never repeated by this library, except when FedEx answers that the token is not valid, which means that
     * nothing was processed. If an exception is thrown after the request was sent (a timeout, a network failure), the profiles
     * may have been stored: check it before sending the request again
     * @param array $profiles The profiles, a list of arrays or objects as returned by newProfile() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexRegulatoryException
     */
    public function createProfiles(array $profiles) : \stdClass
    {
        $request = FedexUtils::pruneEmpty(FedexUtils::objectToArray(array_values($profiles))) ?? [];
        $validator = (new SchemaValidator(true, FedexRegulatorySchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . FedexRegulatorySchemas::REQUEST)) {
            throw new FedexRegulatoryException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $response = $this->client->request('POST', self::ENDPOINT, $request, [], [], FedexUtils::uuidV4(), false);

        return $this->validateAndParse($response);
    }

    /**
     * Store the regulatory profile of one product, as createProfiles() does
     * @param array|\stdClass $profile The profile, as returned by newProfile() once filled in
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexRegulatoryException
     */
    public function createProfile($profile) : \stdClass
    {
        if (!is_array($profile) && !is_object($profile)) {
            throw new FedexRegulatoryException(
                'The profile must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        return $this->createProfiles([$profile]);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx answered is
     * lost, and the message says that the profiles may have been stored
     * @param mixed $response The response
     * @return \stdClass
     * @throws FedexRegulatoryException
     */
    protected function validateAndParse($response) : \stdClass
    {
        $note = ' (the operation may have been done by FedEx: check it before repeating it)';

        if (!($response instanceof \stdClass)) {
            throw new FedexRegulatoryException(
                'Invalid response' . $note,
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexRegulatorySchemas::definitions());

        if (!$validator->validate($response, '@' . FedexRegulatorySchemas::RESPONSE)) {
            throw new FedexRegulatoryException(
                'Invalid response received' . $note,
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
