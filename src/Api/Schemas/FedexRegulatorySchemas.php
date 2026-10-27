<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the request and the response of the Regulatory API operation that FedexRegulatory implements, generated from
 * schemas/regulatory.json and written for SchemaValidator
 */
final class FedexRegulatorySchemas
{
    /**
     * Name of the definition of the Regulatory Profile request (a list of profiles)
     * @var string
     */
    const REQUEST = 'RegulatoryProfileRequest';

    /**
     * Name of the definition of the Regulatory Profile response
     * @var string
     */
    const RESPONSE = 'RegulatoryProfileResponse';

    /**
     * Get the definitions of the request and the response. The names of the definitions, and of their fields, are the ones of
     * schemas/regulatory.json, with these differences, decided with the owner:
     *  - the request has from 1 to 25 profiles (the file says at most 25), and every profile has at least one detail, as the
     *    documentation says
     *  - three fields are integers with a maximum length in the file (ProductDetails.gtinUpcCode 14, Contact.phoneExtension 5 and
     *    LotDetails.lotNumberQualifier 1): the length is not checked, FedEx checks it (the validator would read it as a maximum
     *    value)
     *  - Contact.phoneNumber has no maximum length (15 in the file): an example of the file sends an international number of 17
     *    characters
     *  - LotDetails.lotNumberQualifier also accepts the texts '1' and '2', as an example of the file sends it
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['Address'] = [
            'streetLines?' => [['@type' => 'string', '@max' => 23]],
            'city?' => ['@type' => 'string', '@max' => 20],
            'stateOrProvinceCode?' => ['@type' => 'string', '@max' => 9],
            'postalCode?' => ['@type' => 'string', '@max' => 9],
            'countryCode?' => ['@type' => 'string', '@max' => 2]
        ];

        $schemas['CitationDetail'] = [
            'testingDetails?' => ['@TestingDetail']
        ];

        $schemas['Contact'] = [
            'personName?' => ['@type' => 'string', '@max' => 32],
            'emailAddress?' => ['@type' => 'string', '@max' => 70],
            'phoneExtension?' => 'int',
            'phoneNumber?' => 'string',
            'companyName?' => ['@type' => 'string', '@max' => 32]
        ];

        $schemas['DisclaimMessageSet'] = [
            'disclaimCode?' => ['@type' => 'string', '@enum' => ['A', 'B'], '@max' => 1],
            'intendedUseCode?' => ['@type' => 'string', '@max' => 16],
            'intendedUseDescription?' => ['@type' => 'string', '@max' => 22]
        ];

        $schemas['EntityDetails'] = [
            'contact?' => '@Contact',
            'address?' => '@Address'
        ];

        $schemas['FullMessageSet'] = [
            'productDetails?' => '@ProductDetails',
            'manufacturerDetails?' => '@ManufacturerDetails',
            'lotDetails?' => '@LotDetails',
            'certifierEntity?' => '@EntityDetails',
            'pointOfContact?' => '@EntityDetails',
            'citationDetails?' => ['@CitationDetail']
        ];

        $schemas['LotDetails'] = [
            'lotNumber?' => ['@type' => 'string', '@max' => 20],
            'lotNumberQualifier?' => ['@type' => 'int', '@enum' => [1, 2, '1', '2']],
            'productionStartDate?' => 'string',
            'productionEndDate?' => 'string'
        ];

        $schemas['ManufacturerDetails'] = [
            'smallBatchManufacturerRegistryId?' => ['@type' => 'string', '@max' => 15],
            'manufactureDate?' => 'string',
            'contact?' => '@Contact',
            'address?' => '@Address'
        ];

        $schemas['ProductDetails'] = [
            'childrensProduct?' => 'bool',
            'skuProductCode?' => ['@type' => 'string', '@max' => 30],
            'gtinUpcCode?' => 'int',
            'gtinUpcQualifierCode?' => ['@type' => 'string', '@enum' => ['SRV', 'AI'], '@max' => 3],
            'serialNumber?' => ['@type' => 'string', '@max' => 30],
            'registeredNumber?' => ['@type' => 'string', '@max' => 30],
            'productName?' => ['@type' => 'string', '@max' => 15],
            'brandName?' => ['@type' => 'string', '@max' => 15],
            'alternateId?' => ['@type' => 'string', '@max' => 30],
            'previousTestDate?' => 'string',
            'modelNumber?' => ['@type' => 'string', '@max' => 30],
            'modelColor?' => ['@type' => 'string', '@max' => 70],
            'modelDescription?' => ['@type' => 'string', '@max' => 70],
            'modelStyle?' => ['@type' => 'string', '@max' => 70],
            'declarationCode?' => ['@type' => 'string', '@enum' => ['CPY', 'CPN'], '@max' => 3]
        ];

        $schemas['ReferenceMessageSet'] = [
            'productVersion?' => ['@type' => 'string', '@max' => 29],
            'certifierId?' => ['@type' => 'string', '@max' => 33],
            'registryProductId?' => ['@type' => 'string', '@max' => 19]
        ];

        $schemas['RegulatoryDetail'] = [
            'disclaimMessageSet?' => '@DisclaimMessageSet',
            'referenceMessageSet?' => '@ReferenceMessageSet',
            'fullMessageSet?' => '@FullMessageSet',
            'standardManufacturerProductId?' => ['@type' => 'string', '@max' => 70],
            'nonStandardManufacturerProductId?' => ['@type' => 'string', '@max' => 70],
            'merchantProductId?' => ['@type' => 'string', '@max' => 70]
        ];

        $schemas['RegulatoryProfileRequest'] = ['@type' => ['@RegulatoryProfileRequest_inner'], '@min' => 1, '@max' => 25];

        $schemas['RegulatoryProfileRequest_inner'] = [
            'regulationCode' => ['@type' => 'string', '@enum' => ['CPSC', 'EU_DE_MINIMIS']],
            'productId' => ['@type' => 'string', '@max' => 70],
            'productIdType' => ['@type' => 'string', '@enum' => ['SKU', 'PART_NUMBER', 'REGISTERED_NUMBER', 'GTIN', 'UPC', 'EAN', 'MPN', 'OTHER']],
            'countryOfImport' => 'string',
            'details' => ['@type' => ['@RegulatoryDetail'], '@min' => 1]
        ];

        $schemas['RegulatoryProfileResponse'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@RegulatoryProfileResponse_output'
        ];

        $schemas['RegulatoryProfileResponse_output'] = [
            'message?' => 'string'
        ];

        $schemas['TestingDetail'] = [
            'citationCode?' => [['@type' => 'string', '@max' => 9]],
            'labType?' => ['@type' => 'string', '@enum' => ['ITL', 'LAB', 'NOL'], '@max' => 3],
            'labTestDate?' => 'string',
            'labId?' => ['@type' => 'string', '@max' => 15],
            'testReportId?' => ['@type' => 'string', '@max' => 30],
            'testReportUrl?' => ['@type' => 'string', '@max' => 70],
            'testReportAccessKey?' => ['@type' => 'string', '@max' => 30],
            'componentPartDescription?' => 'string',
            'contact?' => '@Contact',
            'address?' => '@Address'
        ];

        return $schemas;
    }
}
