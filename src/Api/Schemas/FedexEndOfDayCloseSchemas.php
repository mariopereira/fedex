<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the requests and responses of the Ship End of Day Close API operations that FedexEndOfDayClose implements,
 * generated from schemas/close.json and written for SchemaValidator
 */
final class FedexEndOfDayCloseSchemas
{
    /**
     * Name of the definition of the Ground End Of Day Close request
     * @var string
     */
    const CLOSE_REQUEST = 'FullSchema_GEOD_PUT';

    /**
     * Name of the definition of the Ground End Of Day Close response
     * @var string
     */
    const CLOSE_RESPONSE = 'SHPCResponseVO';

    /**
     * Name of the definition of the Reprint End of the day request
     * @var string
     */
    const REPRINT_REQUEST = 'FullSchema_Reprint_POST';

    /**
     * Name of the definition of the Reprint End of the day response
     * @var string
     */
    const REPRINT_RESPONSE = 'SHPCResponseVO';

    /**
     * Get the definitions of all the requests and responses. The names of the definitions, and of their fields, are the ones of
     * schemas/close.json, without differences. Neither request requires a field in the file: the key inputs that the
     * documentation lists (the close date, the account number, the ground service category, the documents) are checked by FedEx
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['AccountNumber_2'] = '@CloseAccountNumber';

        $schemas['Alert'] = [
            'code?' => 'string',
            'alertType?' => ['@type' => 'string', '@enum' => ['NOTE', 'WARNING']],
            'message?' => 'string'
        ];

        $schemas['CloseAccountNumber'] = [
            'value?' => 'string'
        ];

        $schemas['CloseDocument'] = [
            'type?' => ['@type' => 'string', '@enum' => ['MANIFEST', 'OP_950']],
            'shippingCycle?' => 'string',
            'shippingDocumentDisposition?' => 'string',
            'accessReference?' => 'string',
            'resolution?' => 'int',
            'copiesToPrint?' => 'int',
            'parts?' => ['@ShippingDocumentPart']
        ];

        $schemas['CloseDocumentSpecification'] = [
            'closeDocumentTypes?' => [['@type' => 'string', '@enum' => ['MANIFEST', 'OP_950']]],
            'op950Detail?' => '@Op950Detail'
        ];

        $schemas['EndOfDayCloseOutputVO'] = [
            'alerts?' => ['@Alert'],
            'closeDocuments?' => ['@CloseDocument']
        ];

        $schemas['FullSchema_GEOD_PUT'] = [
            'closeReqType?' => ['@type' => 'string', '@enum' => ['GCDR', 'RGCDR']],
            'accountNumber?' => '@AccountNumber_2',
            'groundServiceCategory?' => ['@type' => 'string', '@enum' => ['GROUND']],
            'closeDate?' => 'string',
            'trackingNumber?' => 'string',
            'reprintOption?' => ['@type' => 'string', '@enum' => ['BY_SHIP_DATE', 'BY_TRACKING_NUMBER']],
            'closeDocumentSpecification?' => '@CloseDocumentSpecification',
            'version?' => '@Version'
        ];

        $schemas['FullSchema_Reprint_POST'] = [
            'closeReqType?' => ['@type' => 'string', '@enum' => ['GCDR', 'RGCDR']],
            'accountNumber?' => '@AccountNumber_2',
            'groundServiceCategory?' => ['@type' => 'string', '@enum' => ['GROUND']],
            'closeDate?' => 'string',
            'trackingNumber?' => 'string',
            'reprintOption?' => ['@type' => 'string', '@enum' => ['BY_SHIP_DATE', 'BY_TRACKING_NUMBER']],
            'closeDocumentSpecification?' => '@CloseDocumentSpecification',
            'version?' => '@Version'
        ];

        $schemas['Op950Detail'] = [
            'format?' => '@Op950Detail_format'
        ];

        $schemas['Op950Detail_format'] = [
            'topOfPageOffset?' => '@Op950Detail_format_topOfPageOffset',
            'imageType?' => ['@type' => 'string', '@enum' => ['DIB', 'DOC', 'DPL', 'EPL2', 'GIF', 'PDF', 'PNG', 'RTF', 'TEXT', 'ZPLII']],
            'stockType?' => ['@type' => 'string', '@enum' => ['OP_900_LG', 'OP_900_LG_B', 'OP_900_LL', 'OP_900_LL_B', 'OP_950', 'PAPER_4_PER_PAGE_PORTRAIT', 'PAPER_4X6', 'PAPER_LETTER', 'STOCK_4X6', 'STOCK_4X6.75_LEADING_DOC_TAB', 'STOCK_4X6.75_TRAILING_DOC_TAB', 'STOCK_4X8', 'STOCK_4X9_LEADING_DOC_TAB', 'STOCK_4X9_TRAILING_DOC_TAB']],
            'locale?' => 'string'
        ];

        $schemas['Op950Detail_format_topOfPageOffset'] = [
            'value?' => 'string',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['SHPCResponseVO'] = [
            'transactionId?' => 'string',
            'output?' => '@EndOfDayCloseOutputVO'
        ];

        $schemas['ShippingDocumentPart'] = [
            'documentPartSequenceNumber?' => 'int',
            'image?' => 'string'
        ];

        $schemas['Version'] = [
            'major?' => 'int',
            'minor?' => 'int',
            'patch?' => 'int'
        ];

        return $schemas;
    }
}
