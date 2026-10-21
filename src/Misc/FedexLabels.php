<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Misc;

use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexShipException;

/**
 * Helpers for the labels and the other documents that FedEx returns in the response of a shipment: find them, decode
 * them and save them.
 *
 * FedEx returns each document already in the format that was asked for in labelSpecification.imageType (PDF, PNG,
 * ZPLII, EPL2), encoded as text in the field encodedLabel. Nothing is converted here: decoding gives the bytes of that
 * format. The schema does not state the encoding of the field; it is treated as standard Base64, and anything that is
 * not valid Base64 is refused.
 */
final class FedexLabels
{
    /**
     * Scope of a document that belongs to the whole shipment (an invoice, for example)
     * @var string
     */
    public const SCOPE_SHIPMENT = 'shipment';

    /**
     * Scope of a document that belongs to one package (the label, for example)
     * @var string
     */
    public const SCOPE_PACKAGE = 'package';

    /**
     * Signature of a PNG file
     * @var string
     */
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * Number of bytes at the start of a file where a PDF header may appear
     * @var int
     */
    private const PDF_HEADER_WINDOW = 1024;

    /**
     * Formats whose content can be checked
     * @var string[]
     */
    private const CHECKABLE_FORMATS = ['PDF', 'PNG', 'ZPL', 'ZPLII'];

    /**
     * Find the documents of a shipment response (create shipment, or the async results). They are at
     * output.transactionShipments[].shipmentDocuments[] and output.transactionShipments[].pieceResponses[].packageDocuments[].
     * What is missing in the response is skipped, so a response without documents gives an empty list
     * @param \stdClass $response The response returned by FedexShip
     * @return array[] The documents, each one an array with the keys: scope (SCOPE_SHIPMENT or SCOPE_PACKAGE),
     * transactionIndex, pieceIndex (null for a shipment document), trackingNumber, contentType, docType,
     * copiesToPrint, contentKey, url, encodedLabel (each null when FedEx did not send it) and hasContent (true when
     * encodedLabel is a text that is not empty)
     */
    public static function documents(\stdClass $response) : array
    {
        $data = FedexUtils::objectToArray($response);
        $found = [];

        foreach (self::items(self::child($data, 'output'), 'transactionShipments') as $transactionIndex => $shipment) {
            foreach (self::items($shipment, 'shipmentDocuments') as $document) {
                $found[] = self::describe($document, self::SCOPE_SHIPMENT, $transactionIndex, null);
            }

            foreach (self::items($shipment, 'pieceResponses') as $pieceIndex => $piece) {
                foreach (self::items($piece, 'packageDocuments') as $document) {
                    $found[] = self::describe($document, self::SCOPE_PACKAGE, $transactionIndex, $pieceIndex);
                }
            }
        }

        return $found;
    }

    /**
     * Decode a document: the bytes of the label, in the format that was asked for
     * @param array|\stdClass $document A document of the response, or one of the arrays returned by documents()
     * @param bool $verifyFormat True to check that the bytes look like the format of the document (see isFormat())
     * @param string|null $format The format to check against, when it is not the docType of the document
     * @return string The bytes of the document
     * @throws FedexShipException When the document has no content, the content is not valid Base64, or the check of
     * the format fails
     */
    public static function decode($document, bool $verifyFormat = false, ?string $format = null) : string
    {
        $document = self::asArray($document);
        $encoded = $document['encodedLabel'] ?? null;

        if (!is_string($encoded) || ('' === $encoded)) {
            $url = $document['url'] ?? null;

            throw new FedexShipException(
                (is_string($url) && ('' !== $url))
                    ? 'The document has no encodedLabel, only a url: download it from there'
                    : 'The document has no encodedLabel',
                FedexException::EC_BAD_RESULT_RETURNED
            );
        }

        $bytes = base64_decode($encoded, true);

        if ((false === $bytes) || ('' === $bytes)) {
            throw new FedexShipException('The encodedLabel of the document is not valid Base64', FedexException::EC_BAD_RESULT_RETURNED);
        }

        if ($verifyFormat) {
            $format = $format ?? ($document['docType'] ?? null);

            if (!is_string($format) || ('' === trim($format))) {
                throw new FedexShipException(
                    'The document has no docType to check the format against: pass the format',
                    FedexException::EC_BAD_PARAMETER
                );
            }

            if (!self::isFormat($bytes, $format)) {
                throw new FedexShipException(
                    'The content of the document does not look like ' . strtoupper(trim($format)),
                    FedexException::EC_BAD_RESULT_RETURNED
                );
            }
        }

        return $bytes;
    }

    /**
     * Check that some bytes look like a format, by their start (PDF and PNG) or by the ZPL start-of-label command.
     * It is a sanity check, not a validation of the file
     * @param string $bytes The bytes
     * @param string $format PDF, PNG, ZPL or ZPLII, in any case
     * @return bool
     * @throws FedexShipException When the format cannot be checked (EPL2, for example)
     */
    public static function isFormat(string $bytes, string $format) : bool
    {
        $format = strtoupper(trim($format));

        switch ($format) {
            case 'PDF':
                return false !== strpos(substr($bytes, 0, self::PDF_HEADER_WINDOW), '%PDF-');
            case 'PNG':
                return 0 === strncmp($bytes, self::PNG_SIGNATURE, strlen(self::PNG_SIGNATURE));
            case 'ZPL':
            case 'ZPLII':
                return false !== strpos($bytes, '^XA');
        }

        throw new FedexShipException(
            "The format '" . $format . "' cannot be checked (supported: " . implode(', ', self::CHECKABLE_FORMATS) . ')',
            FedexException::EC_BAD_PARAMETER
        );
    }

    /**
     * Decode a document and save it to a file. Nothing is written when the document cannot be decoded
     * @param array|\stdClass $document A document of the response, or one of the arrays returned by documents()
     * @param string $path The file to write
     * @param bool $overwrite True to replace the file when it exists, false to refuse
     * @param bool $verifyFormat True to check that the bytes look like the format of the document (see isFormat())
     * @param string|null $format The format to check against, when it is not the docType of the document
     * @return int The number of bytes written
     * @throws FedexShipException When the document cannot be decoded, the path is empty, the file exists and
     * $overwrite is false, or the file cannot be written
     */
    public static function save($document, string $path, bool $overwrite = false, bool $verifyFormat = false, ?string $format = null) : int
    {
        if ('' === trim($path)) {
            throw new FedexShipException('The path of the file is empty', FedexException::EC_BAD_PARAMETER);
        }

        $bytes = self::decode($document, $verifyFormat, $format);

        // The x mode fails when the file exists, so that a file is never replaced by mistake
        set_error_handler(function () {
            return true;
        });

        try {
            $handle = fopen($path, $overwrite ? 'wb' : 'xb');
            $written = (false === $handle) ? false : fwrite($handle, $bytes);

            if (false !== $handle) {
                fclose($handle);
            }
        } finally {
            restore_error_handler();
        }

        if ($written !== strlen($bytes)) {
            if ((false !== $handle) && !$overwrite) {
                unlink($path);
            }

            throw new FedexShipException(
                (false === $handle)
                    ? ($overwrite ? 'The file cannot be opened for writing' : 'The file cannot be created: it may exist already, or its folder may not')
                    : 'The file could not be written completely',
                FedexException::EC_BAD_PARAMETER
            );
        }

        return $written;
    }

    /**
     * Give a document as an array
     * @param mixed $document An array or an object
     * @return array
     * @throws FedexShipException When it is neither
     */
    private static function asArray($document) : array
    {
        if (is_object($document)) {
            $document = FedexUtils::objectToArray($document);
        }

        if (!is_array($document)) {
            throw new FedexShipException('The document must be an array or an object', FedexException::EC_BAD_PARAMETER);
        }

        return $document;
    }

    /**
     * Get a key of an array when it is an array
     * @param mixed $data The array
     * @param string $key The key
     * @return array The value when it is an array, an empty array otherwise
     */
    private static function child($data, string $key) : array
    {
        return (is_array($data) && isset($data[$key]) && is_array($data[$key])) ? $data[$key] : [];
    }

    /**
     * Get the members of a list that are arrays, keeping their positions
     * @param mixed $data The array that holds the list
     * @param string $key The key of the list
     * @return array[]
     */
    private static function items($data, string $key) : array
    {
        return array_filter(self::child($data, $key), 'is_array');
    }

    /**
     * Describe a document for documents()
     * @param array $document The document of the response
     * @param string $scope SCOPE_SHIPMENT or SCOPE_PACKAGE
     * @param int|string $transactionIndex Position of the shipment in transactionShipments
     * @param int|string|null $pieceIndex Position of the package in pieceResponses, null for a shipment document
     * @return array
     */
    private static function describe(array $document, string $scope, $transactionIndex, $pieceIndex) : array
    {
        $encoded = $document['encodedLabel'] ?? null;

        return [
            'scope' => $scope,
            'transactionIndex' => $transactionIndex,
            'pieceIndex' => $pieceIndex,
            'trackingNumber' => $document['trackingNumber'] ?? null,
            'contentType' => $document['contentType'] ?? null,
            'docType' => $document['docType'] ?? null,
            'copiesToPrint' => $document['copiesToPrint'] ?? null,
            'contentKey' => $document['contentKey'] ?? null,
            'url' => $document['url'] ?? null,
            'encodedLabel' => $encoded,
            'hasContent' => is_string($encoded) && ('' !== $encoded),
        ];
    }
}
