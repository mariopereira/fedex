<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexEndOfDayCloseSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexEndOfDayCloseException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Ship End of Day Close API: closes the FedEx Ground shipments of the day, which returns the Ground Manifest that has to be
 * printed before the shipments are tendered to FedEx, and reprints the reports of a close (ship/v1/endofday).
 *
 * The close uploads and closes the shipments in FedEx, so it is never repeated by this library, except when FedEx answers that
 * the token is not valid, which means that nothing was processed. If an exception is thrown after it was sent (a timeout, a
 * network failure), the close may have been done: check it before sending it again. The reprint only returns reports that FedEx
 * already generated, so a reprint that failed because of a timeout, or a busy server, can be repeated (see
 * FedexClient::setRetryPolicy())
 */
class FedexEndOfDayClose extends AbstractFedexApi
{
    /**
     * Ground End of Day Close (PUT) and Reprint End of the day (POST) endpoint. The trailing slash is in the path of the schema
     * @var string
     */
    const ENDPOINT = 'ship/v1/endofday/';

    /**
     * The request type of a close
     * @var string
     */
    const CLOSE_REQUEST_TYPE = 'GCDR';

    /**
     * The request type of a reprint
     * @var string
     */
    const REPRINT_REQUEST_TYPE = 'RGCDR';

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
     * Create a Ground End of Day Close request with the key inputs that the documentation lists: the request type (GCDR) and the
     * ground service category (GROUND) are filled in, the account number and the close date (YYYY-MM-DD) are to be filled in.
     * The schema requires none of them. Any other field of the schema can be added (closeDocumentSpecification, ...). Fields set
     * to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newCloseRequest() : array
    {
        return [
            'closeReqType' => self::CLOSE_REQUEST_TYPE,
            'groundServiceCategory' => 'GROUND',
            'accountNumber' => [
                'value' => null
            ],
            'closeDate' => null
        ];
    }

    /**
     * Create a Reprint End of the day request with the key inputs that the documentation lists: the request type (RGCDR), the
     * ground service category (GROUND) and the reprint option (BY_SHIP_DATE) are filled in, the account number and the close date
     * (YYYY-MM-DD) are to be filled in. To reprint by tracking number, set reprintOption to BY_TRACKING_NUMBER and add
     * trackingNumber. The schema requires none of them. Fields set to null and empty lists are left out when the request is sent
     * @return array
     */
    public static function newReprintRequest() : array
    {
        return [
            'closeReqType' => self::REPRINT_REQUEST_TYPE,
            'groundServiceCategory' => 'GROUND',
            'reprintOption' => 'BY_SHIP_DATE',
            'accountNumber' => [
                'value' => null
            ],
            'closeDate' => null
        ];
    }

    /**
     * Close the FedEx Ground shipments of the day. THIS CLOSES THE SHIPMENTS IN FEDEX: it is a real operation.
     * The request is checked before it is sent: the types and the allowed values of the fields are the ones of schemas/close.json
     * (a field that is not in the schema is sent as it is, and FedEx decides about it; the types are loose, as in the examples of
     * FedEx: a text can be a number or a boolean, a number can be a text that is a number or blank, and a boolean can be the text
     * true or false in any case, or 0 or 1). The schema requires no field: what it cannot express is checked by FedEx. Fields set
     * to null and empty lists are left out.
     * The request is never repeated by this library, except when FedEx answers that the token is not valid, which means that
     * nothing was processed. If an exception is thrown after the request was sent (a timeout, a network failure), the close may
     * have been done: check it before sending the request again
     * @param array|\stdClass $request The request, as returned by newCloseRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema (the Ground Manifest is in
     * output->closeDocuments)
     * @throws FedexEndOfDayCloseException
     */
    public function closeGround($request) : \stdClass
    {
        return $this->send('PUT', FedexEndOfDayCloseSchemas::CLOSE_REQUEST, FedexEndOfDayCloseSchemas::CLOSE_RESPONSE, $request, true);
    }

    /**
     * Close the FedEx Ground shipments of the day for an account
     * @param string $accountNumber The account number
     * @param string $closeDate The date of the close (YYYY-MM-DD)
     * @param array $options Any other field of the request (closeDocumentSpecification, version, ...), named as in the schema.
     * closeReqType, groundServiceCategory, accountNumber and closeDate cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexEndOfDayCloseException
     */
    public function closeGroundFor(string $accountNumber, string $closeDate, array $options = []) : \stdClass
    {
        $this->refuseReserved(__FUNCTION__, $options, ['closeReqType', 'groundServiceCategory', 'accountNumber', 'closeDate']);

        $options['closeReqType'] = self::CLOSE_REQUEST_TYPE;
        $options['groundServiceCategory'] = 'GROUND';
        $options['accountNumber'] = ['value' => $accountNumber];
        $options['closeDate'] = $closeDate;

        return $this->closeGround($options);
    }

    /**
     * Reprint the reports of a close (FedEx keeps them for three days). The request is checked before it is sent, as in
     * closeGround(). Nothing is changed in FedEx, so the request can be repeated
     * @param array|\stdClass $request The request, as returned by newReprintRequest() once filled in
     * @return \stdClass The response, with the same structure as in the schema (the reports are in output->closeDocuments)
     * @throws FedexEndOfDayCloseException
     */
    public function reprintReports($request) : \stdClass
    {
        return $this->send('POST', FedexEndOfDayCloseSchemas::REPRINT_REQUEST, FedexEndOfDayCloseSchemas::REPRINT_RESPONSE, $request, false);
    }

    /**
     * Reprint the reports of the closes of a day, for an account
     * @param string $accountNumber The account number
     * @param string $closeDate The date of the close (YYYY-MM-DD)
     * @param array $options Any other field of the request (closeDocumentSpecification, version, ...), named as in the schema.
     * closeReqType, groundServiceCategory, reprintOption, accountNumber and closeDate cannot be used: they are set by this method
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexEndOfDayCloseException
     */
    public function reprintReportsFor(string $accountNumber, string $closeDate, array $options = []) : \stdClass
    {
        $this->refuseReserved(__FUNCTION__, $options, ['closeReqType', 'groundServiceCategory', 'reprintOption', 'accountNumber', 'closeDate']);

        $options['closeReqType'] = self::REPRINT_REQUEST_TYPE;
        $options['groundServiceCategory'] = 'GROUND';
        $options['reprintOption'] = 'BY_SHIP_DATE';
        $options['accountNumber'] = ['value' => $accountNumber];
        $options['closeDate'] = $closeDate;

        return $this->reprintReports($options);
    }

    /**
     * Refuse the options that a method sets itself
     * @param string $method The name of the method, for the message
     * @param array $options The options of the caller
     * @param string[] $reserved The fields that the method sets
     * @return void
     * @throws FedexEndOfDayCloseException When an option is one that the method sets itself
     */
    protected function refuseReserved(string $method, array $options, array $reserved) : void
    {
        foreach ($reserved as $field) {
            if (array_key_exists($field, $options)) {
                throw new FedexEndOfDayCloseException(
                    "The option '{$field}' cannot be used with " . $method . '(): it is set by the method',
                    FedexException::EC_BAD_PARAMETER
                );
            }
        }
    }

    /**
     * Check a request, send it and check the response
     * @param string $method The HTTP method (PUT to close, POST to reprint)
     * @param string $requestSchema Name of the definition of the request
     * @param string $responseSchema Name of the definition of the response
     * @param array|\stdClass $request The request
     * @param bool $changesData True when the operation changes data in FedEx (it is then never repeated)
     * @return \stdClass The response
     * @throws FedexEndOfDayCloseException
     */
    protected function send(string $method, string $requestSchema, string $responseSchema, $request, bool $changesData) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexEndOfDayCloseException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = (new SchemaValidator(true, FedexEndOfDayCloseSchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . $requestSchema)) {
            throw new FedexEndOfDayCloseException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $response = $this->client->request($method, self::ENDPOINT, $request, [], [], FedexUtils::uuidV4(), !$changesData);

        return $this->validateAndParse($response, $responseSchema, $changesData);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx answered is
     * lost
     * @param mixed $response The response
     * @param string $responseSchema Name of the definition of the response
     * @param bool $changesData True when the operation changes data in FedEx: the message then says that it may have been done
     * @return \stdClass
     * @throws FedexEndOfDayCloseException
     */
    protected function validateAndParse($response, string $responseSchema, bool $changesData) : \stdClass
    {
        $note = $changesData ? ' (the operation may have been done by FedEx: check it before repeating it)' : '';

        if (!($response instanceof \stdClass)) {
            throw new FedexEndOfDayCloseException(
                'Invalid response' . $note,
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexEndOfDayCloseSchemas::definitions());

        if (!$validator->validate($response, '@' . $responseSchema)) {
            throw new FedexEndOfDayCloseException(
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
