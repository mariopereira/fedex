<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Misc;

class FedexConstants
{
    /**
     * Url of the sandbox server
     * @var string
     */
    const SANDBOX_URL = 'https://apis-sandbox.fedex.com/';

    /**
     * Url of the production server
     * @var string
     */
    const PRODUCTION_URL ='https://apis.fedex.com/';

    /**
     * Grant type: client_credentials - needs client id and secret
     * @var string
     */
    const GRANT_TYPE_CLIENT_CREDENTIALS = 'client_credentials';

    /**
     * Grant type: pc_credentials - needs client id and secret and child key and secret
     * @var string
     */
    const GRANT_TYPE_PC_CREDENTIALS = 'client_pc_credentials';

    /**
     * Grant type: csp_credentials - needs client id and secret and child key and secret
     * @var string
     */
    const GRANT_TYPE_CSP_CREDENTIALS = 'csp_credentials';

    /**
     * Check if the grant type is valid
     * @param string $grantType
     * @return bool
     */
    public static function isValidGrantType(string $grantType) : bool
    {
        $validTypes = [
            self::GRANT_TYPE_CLIENT_CREDENTIALS,
            self::GRANT_TYPE_PC_CREDENTIALS,
            self::GRANT_TYPE_CSP_CREDENTIALS
        ];

        return in_array($grantType, $validTypes);
    }

    /**
     * Check if the grant type needs the child key and secret.
     * Assumes that the grant type is valid
     * @param string $grantType
     * @return bool
     */
    public static function grantNeedsChildAuth(string $grantType) : bool
    {
        return ($grantType !== self::GRANT_TYPE_CLIENT_CREDENTIALS);
    }

    /**
     * Get the url to call
     * @param bool $sandbox Are we in sandbox mode?
     * @return string
     */
    public static function getUrl(bool $sandbox) : string
    {
        return $sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    const HTTP_CODES = [
        // information
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        // successfull responses
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // redirection messages
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Unused',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // client error responses
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a teapot",
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Too Large',
        451 => 'Unavailable For Legal Reasons',
        // server error responses
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required'
    ];

    /**
     * Return the text for an HTTP status code
     * @param int $httpStatus HTTP status code
     * @param bool $addStatusToResult Add the HTTP status code to the returned text
     * @return string
     */
    public static function httpStatusText(int $httpStatus, bool $addStatusToResult = false) : string
    {
        $text = $addStatusToResult ? ($httpStatus . ': ') : '';

        if (isset(self::HTTP_CODES[$httpStatus])) {
            $text .= self::HTTP_CODES[$httpStatus];
        } else {
            $text .= 'Uknown HTTP status code';

            if (!$addStatusToResult) {
                $text .= ': ' . $httpStatus;
            }
        }

        return $text;
    }
}
