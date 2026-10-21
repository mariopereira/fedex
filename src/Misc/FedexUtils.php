<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Misc;

/**
 * Constants and helper functions shared by the package
 */
class FedexUtils
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

    /**
     * HTTP status codes and their descriptions
     * @var array
     */
    const HTTP_CODES = [
        // information
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        // successful responses
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
            $text .= 'Unknown HTTP status code';

            if (!$addStatusToResult) {
                $text .= ': ' . $httpStatus;
            }
        }

        return $text;
    }

    /**
     * Convert an object to an array, recursively
     * @param mixed $data Data to convert
     * @return mixed If data is an array or an object, then an array is returned, otherwise the original value is returned
     */
    public static function objectToArray($data)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_array($data)) {
            return array_map([__CLASS__, __FUNCTION__], $data);
        }

        return $data;
    }

    /**
     * Convert an array to an object, recursively.
     * Empty arrays and arrays with at least one non-numeric key are converted to stdClass;
     * arrays with only numeric keys are kept as arrays
     * @param mixed $data Data to convert
     * @return mixed If data is an array, an array or a stdClass is returned (with its elements converted), otherwise the original value is returned
     */
    public static function arrayToObject($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        if (!count($data)) {
            return new \stdClass();
        }

        $data = array_map([__CLASS__, __FUNCTION__], $data);

        foreach (array_keys($data) as $key) {
            if (is_string($key)) {
                return (object) $data;
            }
        }

        return $data;
    }

    /**
     * Get the list of errors from a decoded API response
     * @param mixed $data Decoded response
     * @return array The errors, as a list; empty if there are none
     */
    public static function extractErrors($data) : array
    {
        $errors = is_object($data) ? ($data->errors ?? null) : null;

        if (is_array($errors)) {
            return $errors;
        }

        // A single error sent without a list
        return (is_string($errors) || is_object($errors)) ? [$errors] : [];
    }

    /**
     * Get a text field from a decoded API response
     * @param mixed $data Decoded response
     * @param string $field Name of the field
     * @return string|null Null if the field is missing or is not a string or a number
     */
    public static function extractString($data, string $field) : ?string
    {
        $value = is_object($data) ? ($data->$field ?? null) : null;

        return (is_string($value) || is_int($value) || is_float($value)) ? (string) $value : null;
    }

    /**
     * Get an integer field from a decoded API response
     * @param mixed $data Decoded response
     * @param string $field Name of the field
     * @return int|null Null if the field is missing or is not an integer. A string holding an integer is accepted,
     * with an optional sign and optional white space around it
     */
    public static function extractInt($data, string $field) : ?int
    {
        $value = is_object($data) ? ($data->$field ?? null) : null;

        if (is_int($value)) {
            return $value;
        }

        // optional white space, an optional sign, digits without leading zeros, optional white space
        if (is_string($value) && preg_match('/^[ \t\r\x0B\n]*([+-]?)(0|[1-9][0-9]*)[ \t\r\x0B\n]*$/D', $value, $m)) {
            $text = (('-' === $m[1]) && ('0' !== $m[2])) ? '-' . $m[2] : $m[2];
            $int = (int) $text;

            // a number that does not fit in an integer is saturated by the cast, so the text no longer matches
            return ((string) $int === $text) ? $int : null;
        }

        return null;
    }

    /**
     * Release a cURL handle.
     * Needed before PHP 8.0, where the handle is a resource. From PHP 8.0 the handle is freed automatically
     * and curl_close() has no effect (it is deprecated since PHP 8.5)
     * @param resource|object $curl cURL handle: a resource before PHP 8.0, a CurlHandle object from PHP 8.0
     * @return void
     */
    public static function closeCurl($curl) : void
    {
        if (PHP_VERSION_ID < 80000) {
            curl_close($curl);
        }
    }

    /**
     * Remove the null values and the empty arrays from a value, at any depth.
     * The lists are renumbered, so that they are still lists
     * @param mixed $value Value to clean
     * @return mixed The cleaned value. Null if the value is null or if nothing is left of an array
     */
    public static function pruneEmpty($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = ([] !== $value) && (array_keys($value) === range(0, count($value) - 1));
        $result = [];

        foreach ($value as $key => $item) {
            $item = self::pruneEmpty($item);

            if (is_null($item)) {
                continue;
            }

            if ($isList) {
                $result[] = $item;
            } else {
                $result[$key] = $item;
            }
        }

        return count($result) ? $result : null;
    }

    /**
     * Validate if a date string is valid in the specified format
     * @param string $date Date string
     * @param string $format Date format
     * @return bool
     */
    public static function isDateValid(string $date, string $format) : bool
    {
        $dt = \DateTime::createFromformat($format, $date);

        if (false === $dt) {
            return false;
        }

        return $dt->format($format) === $date;
    }

    /**
     * Read the value of a Retry-After header: a number of seconds, or an HTTP date (any of the three formats that the
     * HTTP standard, RFC 9110, requires to be accepted). Anything else is not a valid value
     * @param string|null $value The value of the header
     * @param int|null $now The current time as a Unix timestamp, null for the current time
     * @return int|null The seconds to wait (0 when the date is in the past), null when there is no valid value
     */
    public static function parseRetryAfter(?string $value, ?int $now = null) : ?int
    {
        $value = trim((string) $value, " \t");

        if ('' === $value) {
            return null;
        }

        if (preg_match('/^[0-9]{1,10}$/D', $value)) {
            return (int) $value;
        }

        $formats = [
            'D, d M Y H:i:s \G\M\T',
            'l, d-M-y H:i:s \G\M\T',
            'D M j H:i:s Y'
        ];
        $utc = new \DateTimeZone('UTC');

        foreach ($formats as $format) {
            // The date of the last format has two spaces before a day of one digit
            $date = \DateTimeImmutable::createFromFormat($format, preg_replace('/ {2,}/', ' ', $value), $utc);

            if ((false !== $date) && ($date->format($format) === preg_replace('/ {2,}/', ' ', $value))) {
                return max(0, $date->getTimestamp() - ($now ?? time()));
            }
        }

        return null;
    }

    /**
     * Generate a random UUID v4
     * @return string
     */
    public static function uuidV4() : string
    {
        $bytes = random_bytes(16);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant 10xx

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
