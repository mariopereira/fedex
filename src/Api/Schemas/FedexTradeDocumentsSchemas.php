<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the requests and responses of the Trade Documents Upload API operations that FedexTradeDocuments implements,
 * generated from schemas/upload-documents.json and written for SchemaValidator. The multipart requests are checked part by part:
 * the definitions are the ones of their JSON parts (the files are not checked by a definition)
 */
final class FedexTradeDocumentsSchemas
{
    /**
     * Name of the definition of the document part of an Upload Document request (multipart)
     * @var string
     */
    const UPLOAD_DOCUMENT = 'ETDUploadRequest';

    /**
     * Name of the definition of the Upload Document response
     * @var string
     */
    const UPLOAD_RESPONSE = 'DocumentResponseOutputVO';

    /**
     * Name of the definition of the documentInformation part of an Upload Multiple Documents request (multipart)
     * @var string
     */
    const MULTIUPLOAD_DOCUMENT_INFORMATION = 'MultiETDUploadRequest';

    /**
     * Name of the definition of the Upload Multiple Documents response
     * @var string
     */
    const MULTIUPLOAD_RESPONSE = 'MultiBasePreResponse';

    /**
     * Name of the definition of the document part of an Upload Images request (multipart)
     * @var string
     */
    const IMAGE_DOCUMENT = 'Document';

    /**
     * Name of the definition of the rules part of an Upload Images request (multipart)
     * @var string
     */
    const IMAGE_RULES = 'Document_rules';

    /**
     * Name of the definition of the Upload Images response
     * @var string
     */
    const IMAGE_RESPONSE = 'ImageUploadServiceOutputVO';

    /**
     * Name of the definition of the Upload Encoded Image request (JSON)
     * @var string
     */
    const ENCODED_IMAGE_REQUEST = 'FullSchema_ImageUploadServiceInputVO_1';

    /**
     * Name of the definition of the Upload Encoded Image response
     * @var string
     */
    const ENCODED_IMAGE_RESPONSE = 'ImageUploadServiceOutputVO_1';

    /**
     * Name of the definition of the Upload Multiple Encoded Documents request (JSON)
     * @var string
     */
    const ENCODED_MULTIUPLOAD_REQUEST = 'FullSchema_Base64MultiDocumentRequest';

    /**
     * Name of the definition of the Upload Multiple Encoded Documents response
     * @var string
     */
    const ENCODED_MULTIUPLOAD_RESPONSE = 'MultiBasePreResponse_1';

    /**
     * Get the definitions of all the requests and responses. The names of the definitions, and of their fields, are the ones of
     * schemas/upload-documents.json, with these differences:
     *  - FullSchema-ImageUploadServiceInputVO_1 and FullSchema-Base64MultiDocumentRequest are named with an underscore instead of
     *    the hyphen (the validator does not accept a hyphen in a name)
     *  - Document_1 requires rules and workflowName, which the file never defines and its example does not send: they are
     *    accepted as optional values of any type (decided with the owner)
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['Document'] = [
            'referenceId' => 'string',
            'name' => 'string',
            'contentType' => 'string',
            'meta' => '@Document_meta'
        ];

        $schemas['DocumentMetaOutput'] = [
            'documentType?' => 'string',
            'docId?' => 'string',
            'folderId?' => 'string'
        ];

        $schemas['DocumentResponseOutputVO'] = [
            'output?' => '@DocumentUploadServiceOutputVO',
            'customerTransactionId?' => 'string'
        ];

        $schemas['DocumentUploadServiceOutputVO'] = [
            'meta?' => '@DocumentMetaOutput'
        ];

        $schemas['Document_1'] = [
            'referenceId' => 'string',
            'contentType' => ['@type' => 'string', '@enum' => ['image/png', 'image/gif']],
            'meta' => '@Document_meta_1',
            'rules?' => 'mixed',
            'workflowName?' => 'mixed'
        ];

        $schemas['Document_meta'] = [
            'imageType' => 'string',
            'imageIndex' => 'string'
        ];

        $schemas['Document_meta_1'] = [
            'imageType' => ['@type' => 'string', '@enum' => ['SIGNATURE', 'LETTERHEAD']],
            'imageIndex' => ['@type' => 'string', '@enum' => ['IMAGE_1', 'IMAGE_2', 'IMAGE_3', 'IMAGE_4', 'IMAGE_5']],
            'fileContentBase64' => 'string'
        ];

        $schemas['Document_rules'] = [
            'workflowName' => 'string'
        ];

        $schemas['ETDUploadRequest'] = [
            'workflowName' => ['@type' => 'string', '@enum' => ['ETDPreshipment', 'ETDPostshipment']],
            'carrierCode?' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG']],
            'name' => 'string',
            'contentType' => ['@type' => 'string', '@enum' => ['application/pdf', 'application/x-soffice', 'application/doc', 'text/richtext', 'text/rtf', 'application/x-rtf', 'application/rtf', 'application/msword', 'text/plain', 'image/bmp', 'image/png', 'image/gif', 'image/jpeg', 'image/tiff', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']],
            'meta' => '@MetaObject'
        ];

        $schemas['FullSchema_Base64MultiDocumentRequest'] = [
            'workflowName' => ['@type' => 'string', '@enum' => ['ETDPreshipment', 'ETDPostshipment']],
            'carrierCode' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG']],
            'originCountryCode' => 'string',
            'destinationCountryCode' => 'string',
            'shipmentDate?' => 'string',
            'trackingNumber?' => 'string',
            'metaData' => ['@type' => ['@MultiMetaDataRequest_1'], '@max' => 5]
        ];

        $schemas['FullSchema_ImageUploadServiceInputVO_1'] = [
            'document' => '@Document_1'
        ];

        $schemas['ImageMetaOutputVO'] = [
            'meta?' => '@ImageMetaOutputVO_meta',
            'status?' => 'string',
            'documentReferenceId?' => 'string'
        ];

        $schemas['ImageMetaOutputVO_meta'] = [
            'imageType?' => 'string',
            'imageIndex?' => 'string'
        ];

        $schemas['ImageUploadServiceOutputVO'] = [
            'output?' => '@ImageMetaOutputVO',
            'customerTransactionId?' => 'string'
        ];

        $schemas['ImageUploadServiceOutputVO_1'] = [
            'output?' => '@ImageUploadServiceOutputVO_output',
            'customerTransactionId?' => 'string',
            'transactionId?' => 'string'
        ];

        $schemas['ImageUploadServiceOutputVO_output'] = [
            'meta?' => '@ImageUploadServiceOutputVO_output_meta',
            'documentReferenceId?' => 'string',
            'status?' => ['@type' => 'string', '@enum' => ['SUCCESS', 'FAILURE', 'ERROR']]
        ];

        $schemas['ImageUploadServiceOutputVO_output_meta'] = [
            'imageType?' => 'string',
            'imageIndex?' => 'object'
        ];

        $schemas['MetaObject'] = [
            'shipDocumentType' => ['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'ETD_LABEL', 'USMCA_CERTIFICATION_OF_ORIGIN', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN', 'OTHER', 'PRO_FORMA_INVOICE']],
            'formCode?' => 'string',
            'trackingNumber?' => 'string',
            'shipmentDate?' => 'string',
            'originLocationCode?' => 'string',
            'originCountryCode' => 'string',
            'destinationLocationCode?' => 'string',
            'destinationCountryCode' => 'string'
        ];

        $schemas['MultiBasePreResponse'] = [
            'customerTransactionId?' => 'string',
            'transactionId?' => 'string',
            'output?' => '@MultiBasePreResponse_output'
        ];

        $schemas['MultiBasePreResponse_1'] = [
            'customerTransactionId?' => 'string',
            'transactionId?' => 'string',
            'output?' => '@MultiBasePreResponse_1_output'
        ];

        $schemas['MultiBasePreResponse_1_output'] = [
            'documentResponses?' => ['@MultiDocumentPreResponse']
        ];

        $schemas['MultiBasePreResponse_output'] = [
            'documentResponses?' => ['@MultiDocumentPreResponse']
        ];

        $schemas['MultiDocumentPreResponse'] = [
            'fileReferenceId?' => 'string',
            'metaData?' => '@MultiMetaDataPreResponse'
        ];

        $schemas['MultiETDUploadRequest'] = [
            'workflowName' => ['@type' => 'string', '@enum' => ['ETDPreshipment', 'ETDPostshipment']],
            'carrierCode' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG']],
            'originCountryCode' => 'string',
            'destinationCountryCode' => 'string',
            'shipmentDate?' => 'string',
            'trackingNumber?' => 'string',
            'metaData' => ['@type' => ['@MultiMetaDataRequest'], '@max' => 5]
        ];

        $schemas['MultiMetaDataPreResponse'] = [
            'formCode?' => 'string',
            'documentType?' => 'string',
            'docId?' => 'string',
            'folderId?' => 'string'
        ];

        $schemas['MultiMetaDataRequest'] = [
            'fileName' => 'string',
            'fileReferenceId?' => 'string',
            'contentType' => ['@type' => 'string', '@enum' => ['application/pdf', 'application/x-soffice', 'application/doc', 'text/richtext', 'text/rtf', 'application/x-rtf', 'application/rtf', 'application/msword', 'text/plain', 'image/bmp', 'image/png', 'image/gif', 'image/jpeg', 'image/tiff', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']],
            'formCode?' => 'string',
            'originLocationCode?' => 'string',
            'destinationLocationCode?' => 'string',
            'shipDocumentType' => ['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'ETD_LABEL', 'OTHER', 'PRO_FORMA_INVOICE', 'USMCA_CERTIFICATION_OF_ORIGIN', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']]
        ];

        $schemas['MultiMetaDataRequest_1'] = [
            'fileReferenceId?' => 'string',
            'contentType' => 'string',
            'formCode?' => 'string',
            'originLocationCode?' => 'string',
            'destinationLocationCode?' => 'string',
            'shipDocumentType' => ['@type' => 'string', '@enum' => ['CERTIFICATE_OF_ORIGIN', 'COMMERCIAL_INVOICE', 'ETD_LABEL', 'OTHER', 'PRO_FORMA_INVOICE', 'USMCA_CERTIFICATION_OF_ORIGIN', 'USMCA_COMMERCIAL_INVOICE_CERTIFICATION_OF_ORIGIN']],
            'fileContentBase64' => 'string'
        ];

        return $schemas;
    }
}
