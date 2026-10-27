<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api;

use Mariopereira\Fedex\Api\Schemas\FedexTradeDocumentsSchemas;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexTradeDocumentsException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;

/**
 * Trade Documents Upload API: uploads the customs documents of an electronic trade documents (ETD) shipment, before or after
 * it is shipped, and the images (letterhead and signature) used in the documents that FedEx generates
 * (schemas/upload-documents.json).
 *
 * This API has its own hosts (SANDBOX_URL and PRODUCTION_URL), not the ones of the other APIs; the token is the same.
 * Three operations send the files as a multipart/form-data request, two send them encoded in Base64 in a JSON request.
 *
 * Every upload stores the files in FedEx, so it is never repeated by this library, except when FedEx answers that the token is
 * not valid, which means that nothing was processed. If an exception is thrown after it was sent (a timeout, a network
 * failure), the files may have been stored: check it before sending them again
 */
class FedexTradeDocuments extends AbstractFedexApi
{
    /**
     * Host of the sandbox of this API, as in its documentation
     * @var string
     */
    const SANDBOX_URL = 'https://documentapitest.prod.fedex.com/sandbox/';

    /**
     * Host of the production of this API, as in its documentation
     * @var string
     */
    const PRODUCTION_URL = 'https://documentapi.prod.fedex.com/';

    /**
     * Upload Document endpoint (multipart)
     * @var string
     */
    const UPLOAD_ENDPOINT = 'documents/v1/etds/upload';

    /**
     * Upload Multiple Documents endpoint (multipart)
     * @var string
     */
    const MULTIUPLOAD_ENDPOINT = 'documents/v1/etds/multiupload';

    /**
     * Upload Images endpoint (multipart)
     * @var string
     */
    const IMAGE_ENDPOINT = 'documents/v1/lhsimages/upload';

    /**
     * Upload Encoded Image endpoint (JSON)
     * @var string
     */
    const ENCODED_IMAGE_ENDPOINT = 'documents/v1/lhsimages/encodedupload';

    /**
     * Upload Multiple Encoded Documents endpoint (JSON)
     * @var string
     */
    const ENCODED_MULTIUPLOAD_ENDPOINT = 'documents/v1/etds/encodedmultiupload';

    /**
     * The most files that can be uploaded at once (maxItems of the metadata of the files in the schema)
     * @var int
     */
    const MAX_FILES = 5;

    /**
     * Fedex Authorization class, which tells whether the sandbox or the production host is used
     * @var FedexAuth
     */
    protected FedexAuth $auth;

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
        $this->auth = $auth;
    }

    /**
     * Create the description of a trade document, for uploadDocument(), with the fields that the schema requires: workflowName
     * (ETDPreshipment or ETDPostshipment), name (the file name), contentType (application/pdf, ...) and meta, with
     * shipDocumentType (COMMERCIAL_INVOICE, ...), originCountryCode and destinationCountryCode. Any other field of the schema
     * can be added (carrierCode, meta->trackingNumber, ...). Fields set to null and empty lists are left out when the request is
     * sent
     * @return array
     */
    public static function newDocument() : array
    {
        return [
            'workflowName' => null,
            'name' => null,
            'contentType' => null,
            'meta' => [
                'shipDocumentType' => null,
                'originCountryCode' => null,
                'destinationCountryCode' => null
            ]
        ];
    }

    /**
     * Create the description of an image, for uploadImage(), with the fields that the schema requires: referenceId, name (the
     * file name), contentType (image/png, ...) and meta, with imageType and imageIndex (texts in this schema; the encoded upload
     * lists SIGNATURE or LETTERHEAD, and IMAGE_1 to IMAGE_5). Fields set to null and empty lists are left out when the request
     * is sent
     * @return array
     */
    public static function newImageDocument() : array
    {
        return [
            'referenceId' => null,
            'name' => null,
            'contentType' => null,
            'meta' => [
                'imageType' => null,
                'imageIndex' => null
            ]
        ];
    }

    /**
     * Upload a trade document (commercial invoice, certificate of origin, ...). THIS STORES THE DOCUMENT IN FEDEX.
     * The description is checked before it is sent: the types and the allowed values of the fields are the ones of
     * schemas/upload-documents.json (a field that is not in the schema is sent as it is, and FedEx decides about it; the types
     * are loose, as in the examples of FedEx: a text can be a number or a boolean, a number can be a text that is a number or
     * blank, and a boolean can be the text true or false in any case, or 0 or 1). Fields set to null and empty lists are left
     * out. The request is never repeated by this library, except when FedEx answers that the token is not valid
     * @param array $document The description of the document, as returned by newDocument() once filled in
     * @param string $fileName The name of the file
     * @param string $content The content of the file
     * @param string $contentType The media type of the file (application/pdf, ...)
     * @return \stdClass The response, with the same structure as in the schema (the id of the document is in
     * output->meta->docId)
     * @throws FedexTradeDocumentsException
     */
    public function uploadDocument(array $document, string $fileName, string $content, string $contentType) : \stdClass
    {
        $fields = [
            'document' => $this->encodePart('document', $document, FedexTradeDocumentsSchemas::UPLOAD_DOCUMENT)
        ];
        $files = [$this->filePart('attachment', $fileName, $content, $contentType)];

        return $this->sendMultipart(self::UPLOAD_ENDPOINT, $fields, $files, FedexTradeDocumentsSchemas::UPLOAD_RESPONSE);
    }

    /**
     * Upload up to five trade documents of a shipment at once. THIS STORES THE DOCUMENTS IN FEDEX.
     * The information of the documents is checked before it is sent, as in uploadDocument(). The request is never repeated by
     * this library, except when FedEx answers that the token is not valid
     * @param array $documentInformation The information of the documents: workflowName, carrierCode, originCountryCode,
     * destinationCountryCode and metaData (a list with the description of each file), named as in the schema
     * @param array $files The files, one to five: a list of [file name, content, media type]
     * @return \stdClass The response, with the same structure as in the schema (the ids of the documents are in
     * output->meta)
     * @throws FedexTradeDocumentsException
     */
    public function uploadDocuments(array $documentInformation, array $files) : \stdClass
    {
        if (count($files) < 1 || count($files) > self::MAX_FILES) {
            throw new FedexTradeDocumentsException(
                'From one to ' . self::MAX_FILES . ' files can be uploaded at once',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $parts = [];

        foreach (array_values($files) as $i => $file) {
            if (!is_array($file) || count($file) !== 3 || !isset($file[0], $file[1], $file[2])
                || !is_string($file[0]) || !is_string($file[1]) || !is_string($file[2])
            ) {
                throw new FedexTradeDocumentsException(
                    "The file {$i} must be a list of the file name, the content and the media type, all texts",
                    FedexException::EC_BAD_PARAMETER
                );
            }

            $parts[] = $this->filePart('fileAttachments', $file[0], $file[1], $file[2]);
        }

        $fields = [
            'documentInformation' => $this->encodePart(
                'documentInformation',
                $documentInformation,
                FedexTradeDocumentsSchemas::MULTIUPLOAD_DOCUMENT_INFORMATION
            )
        ];

        return $this->sendMultipart(self::MULTIUPLOAD_ENDPOINT, $fields, $parts, FedexTradeDocumentsSchemas::MULTIUPLOAD_RESPONSE);
    }

    /**
     * Upload an image (a letterhead or a signature) to be used in the documents that FedEx generates. THIS STORES THE IMAGE IN
     * FEDEX. The description and the rules are checked before they are sent, as in uploadDocument(). The request is never
     * repeated by this library, except when FedEx answers that the token is not valid
     * @param array $document The description of the image, as returned by newImageDocument() once filled in
     * @param array $rules The rules of the upload: ['workflowName' => 'LetterheadSignature']
     * @param string $fileName The name of the file
     * @param string $content The content of the file
     * @param string $contentType The media type of the file (image/png, ...)
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexTradeDocumentsException
     */
    public function uploadImage(array $document, array $rules, string $fileName, string $content, string $contentType) : \stdClass
    {
        $fields = [
            'document' => $this->encodePart('document', $document, FedexTradeDocumentsSchemas::IMAGE_DOCUMENT),
            'rules' => $this->encodePart('rules', $rules, FedexTradeDocumentsSchemas::IMAGE_RULES)
        ];
        $files = [$this->filePart('attachment', $fileName, $content, $contentType)];

        return $this->sendMultipart(self::IMAGE_ENDPOINT, $fields, $files, FedexTradeDocumentsSchemas::IMAGE_RESPONSE);
    }

    /**
     * Upload an image (a letterhead or a signature) encoded in Base64, in a JSON request. THIS STORES THE IMAGE IN FEDEX.
     * The request is checked before it is sent, as in uploadDocument(). The request is never repeated by this library, except
     * when FedEx answers that the token is not valid
     * @param array|\stdClass $request The request (document and attachment), named as in the schema
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexTradeDocumentsException
     */
    public function uploadEncodedImage($request) : \stdClass
    {
        return $this->sendJson(
            self::ENCODED_IMAGE_ENDPOINT,
            FedexTradeDocumentsSchemas::ENCODED_IMAGE_REQUEST,
            FedexTradeDocumentsSchemas::ENCODED_IMAGE_RESPONSE,
            $request
        );
    }

    /**
     * Upload up to five trade documents of a shipment, encoded in Base64, in a JSON request. THIS STORES THE DOCUMENTS IN FEDEX.
     * The request is checked before it is sent, as in uploadDocument(). The request is never repeated by this library, except
     * when FedEx answers that the token is not valid
     * @param array|\stdClass $request The request (workflowName, carrierCode, the countries, metaData and the attachments),
     * named as in the schema
     * @return \stdClass The response, with the same structure as in the schema
     * @throws FedexTradeDocumentsException
     */
    public function uploadEncodedDocuments($request) : \stdClass
    {
        return $this->sendJson(
            self::ENCODED_MULTIUPLOAD_ENDPOINT,
            FedexTradeDocumentsSchemas::ENCODED_MULTIUPLOAD_REQUEST,
            FedexTradeDocumentsSchemas::ENCODED_MULTIUPLOAD_RESPONSE,
            $request
        );
    }

    /**
     * Get the url of an endpoint, on the sandbox or the production host of this API
     * @param string $endpoint The endpoint
     * @return string
     */
    protected function url(string $endpoint) : string
    {
        return ($this->auth->inSandbox() ? self::SANDBOX_URL : self::PRODUCTION_URL) . $endpoint;
    }

    /**
     * Check a JSON part of a multipart request and encode it
     * @param string $part The name of the part, for the message
     * @param array $data The content of the part
     * @param string $schema Name of the definition of the part
     * @return string The part, as JSON
     * @throws FedexTradeDocumentsException When the part is not valid
     */
    protected function encodePart(string $part, array $data, string $schema) : string
    {
        $data = FedexUtils::pruneEmpty($data) ?? [];
        $validator = (new SchemaValidator(true, FedexTradeDocumentsSchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($data, '@' . $schema)) {
            throw new FedexTradeDocumentsException(
                "Invalid {$part}",
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $json = json_encode($data);

        if ($json === false) {
            throw new FedexTradeDocumentsException(
                "Unable to encode the {$part} as JSON: " . json_last_error_msg(),
                FedexException::EC_BAD_PARAMETER
            );
        }

        return $json;
    }

    /**
     * Make a file part of a multipart request
     * @param string $name The name of the part
     * @param string $fileName The name of the file
     * @param string $content The content of the file
     * @param string $contentType The media type of the file
     * @return array
     * @throws FedexTradeDocumentsException When the file is empty
     */
    protected function filePart(string $name, string $fileName, string $content, string $contentType) : array
    {
        if ($content === '') {
            throw new FedexTradeDocumentsException(
                "The file '{$fileName}' is empty",
                FedexException::EC_BAD_PARAMETER
            );
        }

        return ['name' => $name, 'filename' => $fileName, 'contentType' => $contentType, 'content' => $content];
    }

    /**
     * Send a multipart request and check the response. The request is never repeated, except after a 401
     * @param string $endpoint The endpoint
     * @param array $fields The text parts
     * @param array $files The file parts
     * @param string $responseSchema Name of the definition of the response
     * @return \stdClass The response
     * @throws FedexTradeDocumentsException
     */
    protected function sendMultipart(string $endpoint, array $fields, array $files, string $responseSchema) : \stdClass
    {
        $response = $this->client->requestMultipart('POST', $this->url($endpoint), $fields, $files, [], FedexUtils::uuidV4(), false);

        return $this->validateAndParse($response, $responseSchema);
    }

    /**
     * Check a JSON request, send it and check the response. The request is never repeated, except after a 401
     * @param string $endpoint The endpoint
     * @param string $requestSchema Name of the definition of the request
     * @param string $responseSchema Name of the definition of the response
     * @param array|\stdClass $request The request
     * @return \stdClass The response
     * @throws FedexTradeDocumentsException
     */
    protected function sendJson(string $endpoint, string $requestSchema, string $responseSchema, $request) : \stdClass
    {
        if (is_object($request)) {
            $request = FedexUtils::objectToArray($request);
        }

        if (!is_array($request)) {
            throw new FedexTradeDocumentsException(
                'The request must be an array or an object',
                FedexException::EC_BAD_PARAMETER
            );
        }

        $request = FedexUtils::pruneEmpty($request) ?? [];
        $validator = (new SchemaValidator(true, FedexTradeDocumentsSchemas::definitions()))->setLooseScalars(true);

        if (!$validator->validate($request, '@' . $requestSchema)) {
            throw new FedexTradeDocumentsException(
                'Invalid request',
                FedexException::EC_BAD_PARAMETER,
                null,
                0,
                null,
                $validator->getErrors()
            );
        }

        $response = $this->client->request('POST', $this->url($endpoint), $request, [], [], FedexUtils::uuidV4(), false);

        return $this->validateAndParse($response, $responseSchema);
    }

    /**
     * Validate the response received from FedEx. The response is given to the exception, so that nothing that FedEx answered is
     * lost. As every operation stores data, the message says that it may have been done
     * @param mixed $response The response
     * @param string $responseSchema Name of the definition of the response
     * @return \stdClass
     * @throws FedexTradeDocumentsException
     */
    protected function validateAndParse($response, string $responseSchema) : \stdClass
    {
        $note = ' (the operation may have been done by FedEx: check it before repeating it)';

        if (!($response instanceof \stdClass)) {
            throw new FedexTradeDocumentsException(
                'Invalid response' . $note,
                FedexException::EC_BAD_RESULT_RETURNED,
                null,
                0,
                null,
                [],
                $response
            );
        }

        $validator = new SchemaValidator(true, FedexTradeDocumentsSchemas::definitions());

        if (!$validator->validate($response, '@' . $responseSchema)) {
            throw new FedexTradeDocumentsException(
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
