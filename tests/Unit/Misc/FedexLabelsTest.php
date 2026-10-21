<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Misc;

use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexShipException;
use Mariopereira\Fedex\Misc\FedexLabels;
use Mariopereira\Fedex\Tests\Support\ShipSpec as Spec;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the helpers for the labels and documents of a shipment response
 */
final class FedexLabelsTest extends TestCase
{
    /**
     * A folder for the files that the tests save, made by tempDir() and removed after each test
     * @var string|null
     */
    private $dir;

    /**
     * Remove the folder of the files that were saved
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->dir !== null && is_dir($this->dir)) {
            foreach (scandir($this->dir) as $file) {
                if ($file !== '.' && $file !== '..') {
                    unlink($this->dir . DIRECTORY_SEPARATOR . $file);
                }
            }

            rmdir($this->dir);
        }

        $this->dir = null;
    }

    /**
     * Make an empty folder in the temporary folder of the system
     * @return string
     */
    private function tempDir(): string
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fedex_labels_' . bin2hex(random_bytes(6));
        mkdir($this->dir);

        return $this->dir;
    }

    /**
     * A response made from an array, as the client gives it
     * @param array $data The response
     * @return \stdClass
     */
    private static function response(array $data): \stdClass
    {
        return json_decode(json_encode($data));
    }

    /**
     * A document of a response
     * @param string $bytes The content of the label, which is encoded here
     * @param array $extra Other fields of the document
     * @return array
     */
    private static function doc(string $bytes, array $extra = []): array
    {
        return array_merge(['encodedLabel' => base64_encode($bytes), 'docType' => 'PDF'], $extra);
    }

    /**
     * Run something and give the exception it throws
     * @param callable $fn What to run
     * @return \Throwable|null
     */
    private static function thrown(callable $fn): ?\Throwable
    {
        try {
            $fn();

            return null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * Count the keys with a name in an array, at any depth
     * @param mixed $data The data
     * @param string $key The name
     * @return int
     */
    private static function countKeys($data, string $key): int
    {
        $count = 0;

        if (is_array($data)) {
            foreach ($data as $k => $value) {
                $count += (($k === $key) ? 1 : 0) + self::countKeys($value, $key);
            }
        }

        return $count;
    }

    // ---------------------------------------------------------------- documents()

    /**
     * The documents of both scopes are found with their positions and fields
     * @return void
     */
    public function testTheDocumentsOfBothScopesAreFoundWithTheirPositionsAndFields(): void
    {
        $response = self::response(['transactionId' => 'T', 'output' => ['transactionShipments' => [
            [
                'shipmentDocuments' => [self::doc('INVOICE', ['contentType' => 'COMMERCIAL_INVOICE', 'copiesToPrint' => 3, 'contentKey' => 'K0', 'url' => 'https://example.test/0'])],
                'pieceResponses' => [
                    ['packageDocuments' => [self::doc('A', ['trackingNumber' => '111', 'contentType' => 'LABEL']), self::doc('B', ['trackingNumber' => '111'])]],
                    ['packageDocuments' => [self::doc('C', ['trackingNumber' => '222'])]],
                ],
            ],
            ['shipmentDocuments' => [['contentType' => 'MANIFEST_REPORT']]],
        ]]]);

        $documents = FedexLabels::documents($response);

        $this->assertCount(5, $documents);
        $this->assertSame(
            ['scope' => 'shipment', 'transactionIndex' => 0, 'pieceIndex' => null, 'trackingNumber' => null, 'contentType' => 'COMMERCIAL_INVOICE', 'docType' => 'PDF', 'copiesToPrint' => 3, 'contentKey' => 'K0', 'url' => 'https://example.test/0', 'encodedLabel' => base64_encode('INVOICE'), 'hasContent' => true],
            $documents[0]
        );
        $this->assertSame(['package', 0, 0, '111', 'LABEL', base64_encode('A')], [$documents[1]['scope'], $documents[1]['transactionIndex'], $documents[1]['pieceIndex'], $documents[1]['trackingNumber'], $documents[1]['contentType'], $documents[1]['encodedLabel']]);
        $this->assertSame(['package', 0, 0, '111', null], [$documents[2]['scope'], $documents[2]['transactionIndex'], $documents[2]['pieceIndex'], $documents[2]['trackingNumber'], $documents[2]['contentType']]);
        $this->assertSame(['package', 0, 1, '222'], [$documents[3]['scope'], $documents[3]['transactionIndex'], $documents[3]['pieceIndex'], $documents[3]['trackingNumber']]);
        $this->assertSame(['shipment', 1, null, false, null], [$documents[4]['scope'], $documents[4]['transactionIndex'], $documents[4]['pieceIndex'], $documents[4]['hasContent'], $documents[4]['encodedLabel']]);
        $this->assertSame('MANIFEST_REPORT', $documents[4]['contentType']);
    }

    /**
     * What is missing or malformed in the response is skipped
     * @return void
     */
    public function testWhatIsMissingOrMalformedInTheResponseIsSkipped(): void
    {
        $empty = [
            '{}',
            '{"output":{}}',
            '{"output":[]}',
            '{"output":"text"}',
            '{"output":{"transactionShipments":[]}}',
            '{"output":{"transactionShipments":"text"}}',
            '{"output":{"transactionShipments":[null,"x",5]}}',
            '{"output":{"transactionShipments":[{}]}}',
            '{"output":{"transactionShipments":[{"shipmentDocuments":"x","pieceResponses":"y"}]}}',
            '{"output":{"transactionShipments":[{"pieceResponses":[{}, null, {"packageDocuments":7}]}]}}',
            '{"output":{"transactionShipments":[{"shipmentDocuments":[null,1,"x"]}]}}',
        ];

        foreach ($empty as $json) {
            $this->assertSame([], FedexLabels::documents(json_decode($json)), $json);
        }

        $documents = FedexLabels::documents(json_decode('{"output":{"transactionShipments":[null,{"pieceResponses":[null,{"packageDocuments":[null,{}]}]}]}}'));

        $this->assertCount(1, $documents);
        $this->assertSame([1, 1], [$documents[0]['transactionIndex'], $documents[0]['pieceIndex']], 'the positions are the ones of the response, not of the list that is left');
        $this->assertSame([null, null, null, null, null, null, false], [$documents[0]['trackingNumber'], $documents[0]['contentType'], $documents[0]['docType'], $documents[0]['copiesToPrint'], $documents[0]['contentKey'], $documents[0]['url'], $documents[0]['hasContent']]);
    }

    /**
     * An encodedLabel that is not text has no content
     * @return void
     */
    public function testAnEncodedLabelThatIsNotTextHasNoContent(): void
    {
        foreach ([null, '', 0, 12, true, []] as $value) {
            $documents = FedexLabels::documents(self::response(['output' => ['transactionShipments' => [['shipmentDocuments' => [['encodedLabel' => $value]]]]]]));

            $this->assertCount(1, $documents);
            $this->assertFalse($documents[0]['hasContent'], var_export($value, true));
        }
    }

    /**
     * The documents found in the responses generated from the spec are all of those that they have
     * @return void
     */
    public function testTheDocumentsFoundInTheResponsesGeneratedFromTheSpecAreAllOfThoseThatTheyHave(): void
    {
        foreach (['create.response', 'results.response'] as $label) {
            $instance = Spec::instance($label)[0];
            $expected = self::countKeys($instance, 'encodedLabel');
            $documents = FedexLabels::documents(self::response($instance));

            $this->assertGreaterThan(0, $expected, $label);
            $this->assertCount($expected, $documents, $label);
            $this->assertCount(
                $expected,
                array_filter($documents, function (array $d) {
                    return $d['encodedLabel'] !== null;
                }),
                $label
            );
        }
    }

    // ---------------------------------------------------------------- decode()

    /**
     * Decoding gives back the bytes, whatever they are
     * @return void
     */
    public function testDecodingGivesBackTheBytesWhateverTheyAre(): void
    {
        $all = implode('', array_map('chr', range(0, 255)));

        foreach (['x', "%PDF-1.4\n\x00\xff\x00", $all, str_repeat($all, 50), "^XA^FO50,50^FDHello^FS^XZ"] as $bytes) {
            $this->assertSame($bytes, FedexLabels::decode(self::doc($bytes)));
            $this->assertSame($bytes, FedexLabels::decode((object) self::doc($bytes)));
        }
    }

    /**
     * A document returned by documents() can be decoded
     * @return void
     */
    public function testADocumentReturnedByDocumentsCanBeDecoded(): void
    {
        $response = self::response(['output' => ['transactionShipments' => [['pieceResponses' => [['packageDocuments' => [self::doc('THE LABEL')]]]]]]]);

        $this->assertSame('THE LABEL', FedexLabels::decode(FedexLabels::documents($response)[0]));
    }

    /**
     * Line breaks in the encoded text are tolerated
     * @return void
     */
    public function testLineBreaksInTheEncodedTextAreTolerated(): void
    {
        $bytes = random_bytes(500);

        $this->assertSame($bytes, FedexLabels::decode(['encodedLabel' => chunk_split(base64_encode($bytes), 76, "\r\n")]));
        $this->assertSame($bytes, FedexLabels::decode(['encodedLabel' => chunk_split(base64_encode($bytes), 64, "\n")]));
    }

    /**
     * A document without content is refused, and a url is pointed out
     * @return void
     */
    public function testADocumentWithoutContentIsRefusedAndAUrlIsPointedOut(): void
    {
        foreach ([[], ['encodedLabel' => null], ['encodedLabel' => ''], ['encodedLabel' => 123], ['encodedLabel' => ['x']], ['docType' => 'PDF']] as $document) {
            $e = self::thrown(function () use ($document) {
                FedexLabels::decode($document);
            });

            $this->assertInstanceOf(FedexShipException::class, $e, json_encode($document));
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('The document has no encodedLabel', $e->getMessage());
        }

        $e = self::thrown(function () {
            FedexLabels::decode(['url' => 'https://example.test/secret-token', 'encodedLabel' => '']);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame('The document has no encodedLabel, only a url: download it from there', $e->getMessage());
        $this->assertStringNotContainsString('secret-token', $e->getMessage(), 'the url is not repeated in the message');
    }

    /**
     * Content that is not valid Base64 is refused without repeating it
     * @return void
     */
    public function testContentThatIsNotValidBase64IsRefusedWithoutRepeatingIt(): void
    {
        foreach (['SECRET*LABEL!', 'QUJD$', 'QU JD*', '   ', "\n", '=', 'a'] as $encoded) {
            $e = self::thrown(function () use ($encoded) {
                FedexLabels::decode(['encodedLabel' => $encoded]);
            });

            $this->assertInstanceOf(FedexShipException::class, $e, var_export($encoded, true));
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('The encodedLabel of the document is not valid Base64', $e->getMessage());
        }
    }

    /**
     * Something that is neither an array nor an object is refused
     * @return void
     */
    public function testSomethingThatIsNeitherAnArrayNorAnObjectIsRefused(): void
    {
        foreach ([null, 'text', 5, true] as $document) {
            $e = self::thrown(function () use ($document) {
                FedexLabels::decode($document);
            });

            $this->assertInstanceOf(FedexShipException::class, $e, var_export($document, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The document must be an array or an object', $e->getMessage());
        }
    }

    // ---------------------------------------------------------------- the format check

    /**
     * The formats are recognised by their content
     * @return void
     */
    public function testTheFormatsAreRecognisedByTheirContent(): void
    {
        $png = "\x89PNG\r\n\x1a\n" . random_bytes(20);

        $this->assertTrue(FedexLabels::isFormat("%PDF-1.7\n...", 'PDF'));
        $this->assertTrue(FedexLabels::isFormat(str_repeat('x', 1000) . '%PDF-1.7', 'pdf'), 'a header within the first 1024 bytes');
        $this->assertFalse(FedexLabels::isFormat(str_repeat('x', 1100) . '%PDF-1.7', 'PDF'), 'a header after the first 1024 bytes');
        $this->assertFalse(FedexLabels::isFormat('<html>%PDF</html>', 'PDF'));
        $this->assertFalse(FedexLabels::isFormat('', 'PDF'));

        $this->assertTrue(FedexLabels::isFormat($png, 'PNG'));
        $this->assertTrue(FedexLabels::isFormat($png, ' png '));
        $this->assertFalse(FedexLabels::isFormat('x' . $png, 'PNG'), 'the signature must be at the start');
        $this->assertFalse(FedexLabels::isFormat("\x89PNG", 'PNG'));
        $this->assertFalse(FedexLabels::isFormat('%PDF-1.7', 'PNG'));

        foreach (['ZPL', 'ZPLII', 'zplii', ' Zpl '] as $format) {
            $this->assertTrue(FedexLabels::isFormat('^XA^FDx^FS^XZ', $format), $format);
            $this->assertTrue(FedexLabels::isFormat("~DGR:X.GRF,1,1,00\n^XA^XZ", $format), "$format with a command before the label");
            $this->assertFalse(FedexLabels::isFormat('%PDF-1.7', $format), $format);
            $this->assertFalse(FedexLabels::isFormat('^xa', $format), "$format is case sensitive");
        }
    }

    /**
     * A format that cannot be checked is refused
     * @return void
     */
    public function testAFormatThatCannotBeCheckedIsRefused(): void
    {
        foreach (['EPL2', 'epl2', 'GIF', '', '  '] as $format) {
            $e = self::thrown(function () use ($format) {
                FedexLabels::isFormat('bytes', $format);
            });

            $this->assertInstanceOf(FedexShipException::class, $e, "'$format'");
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertStringContainsString('cannot be checked (supported: PDF, PNG, ZPL, ZPLII)', $e->getMessage());
        }
    }

    /**
     * The check is only made when it is asked for, against the docType or against the format given
     * @return void
     */
    public function testTheCheckIsOnlyMadeWhenItIsAskedForAgainstTheDocTypeOrTheFormatGiven(): void
    {
        $zpl = self::doc('^XA^XZ', ['docType' => 'PDF']);

        $this->assertSame('^XA^XZ', FedexLabels::decode($zpl), 'no check by default');
        $this->assertSame('^XA^XZ', FedexLabels::decode($zpl, false, 'PNG'), 'a format without the check is ignored');
        $this->assertSame('%PDF-1.4', FedexLabels::decode(self::doc('%PDF-1.4'), true), 'the docType of the document');
        $this->assertSame('^XA^XZ', FedexLabels::decode($zpl, true, 'ZPLII'), 'the format given wins over the docType');

        $e = self::thrown(function () use ($zpl) {
            FedexLabels::decode($zpl, true);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertSame('The content of the document does not look like PDF', $e->getMessage());

        $e = self::thrown(function () {
            FedexLabels::decode(self::doc('%PDF-1.4'), true, 'zplii');
        });

        $this->assertSame('The content of the document does not look like ZPLII', $e->getMessage());
    }

    /**
     * Without a docType or a format there is nothing to check against
     * @return void
     */
    public function testWithoutADocTypeOrAFormatThereIsNothingToCheckAgainst(): void
    {
        foreach ([['encodedLabel' => base64_encode('x')], ['encodedLabel' => base64_encode('x'), 'docType' => ''], ['encodedLabel' => base64_encode('x'), 'docType' => '  '], ['encodedLabel' => base64_encode('x'), 'docType' => 5]] as $document) {
            $e = self::thrown(function () use ($document) {
                FedexLabels::decode($document, true);
            });

            $this->assertInstanceOf(FedexShipException::class, $e, json_encode($document));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The document has no docType to check the format against: pass the format', $e->getMessage());
        }

        $e = self::thrown(function () {
            FedexLabels::decode(['encodedLabel' => base64_encode('x'), 'docType' => 'EPL2'], true);
        });

        $this->assertInstanceOf(FedexShipException::class, $e, 'a docType that cannot be checked is refused when the check is asked for');
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame('x', FedexLabels::decode(['encodedLabel' => base64_encode('x'), 'docType' => 'EPL2']), 'and ignored when it is not');
    }

    // ---------------------------------------------------------------- save()

    /**
     * A document is saved with its bytes and the number of bytes is returned
     * @return void
     */
    public function testADocumentIsSavedWithItsBytesAndTheNumberOfBytesIsReturned(): void
    {
        $path = $this->tempDir() . DIRECTORY_SEPARATOR . 'label.pdf';
        $bytes = "%PDF-1.4\n" . random_bytes(300);

        $this->assertSame(strlen($bytes), FedexLabels::save(self::doc($bytes), $path));
        $this->assertSame($bytes, file_get_contents($path));
    }

    /**
     * A file that exists is only replaced when it is asked for
     * @return void
     */
    public function testAFileThatExistsIsOnlyReplacedWhenItIsAskedFor(): void
    {
        $path = $this->tempDir() . DIRECTORY_SEPARATOR . 'label.pdf';
        file_put_contents($path, 'OLD');

        $e = self::thrown(function () use ($path) {
            FedexLabels::save(self::doc('NEW'), $path);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertStringStartsWith('The file cannot be created', $e->getMessage());
        $this->assertSame('OLD', file_get_contents($path), 'the file is untouched');

        $this->assertSame(3, FedexLabels::save(self::doc('NEW'), $path, true));
        $this->assertSame('NEW', file_get_contents($path));

        $this->assertSame(2, FedexLabels::save(self::doc('XY'), $path, true), 'a shorter file does not keep the end of the old one');
        $this->assertSame('XY', file_get_contents($path));
    }

    /**
     * An empty path or a folder that does not exist is refused and nothing is written
     * @return void
     */
    public function testAnEmptyPathOrAFolderThatDoesNotExistIsRefusedAndNothingIsWritten(): void
    {
        $dir = $this->tempDir();

        foreach (['', '   '] as $path) {
            $e = self::thrown(function () use ($path) {
                FedexLabels::save(self::doc('x'), $path);
            });

            $this->assertInstanceOf(FedexShipException::class, $e);
            $this->assertSame('The path of the file is empty', $e->getMessage());
        }

        foreach ([false, true] as $overwrite) {
            $e = self::thrown(function () use ($dir, $overwrite) {
                FedexLabels::save(self::doc('x'), $dir . DIRECTORY_SEPARATOR . 'missing' . DIRECTORY_SEPARATOR . 'label.pdf', $overwrite);
            });

            $this->assertInstanceOf(FedexShipException::class, $e);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        }

        $this->assertSame(['.', '..'], scandir($dir));
    }

    /**
     * A document that cannot be decoded or fails the check is not saved
     * @return void
     */
    public function testADocumentThatCannotBeDecodedOrFailsTheCheckIsNotSaved(): void
    {
        $dir = $this->tempDir();
        $path = $dir . DIRECTORY_SEPARATOR . 'label.pdf';

        foreach ([[['encodedLabel' => 'not*base64'], false], [[], false], [self::doc('^XA^XZ'), true]] as [$document, $verify]) {
            $e = self::thrown(function () use ($document, $path, $verify) {
                FedexLabels::save($document, $path, false, $verify);
            });

            $this->assertInstanceOf(FedexShipException::class, $e, json_encode($document));
            $this->assertFileDoesNotExist($path);
        }

        $e = self::thrown(function () use ($path) {
            FedexLabels::save('not a document', $path);
        });

        $this->assertSame('The document must be an array or an object', $e->getMessage());
        $this->assertFileDoesNotExist($path);

        file_put_contents($path, 'OLD');
        self::thrown(function () use ($path) {
            FedexLabels::save(['encodedLabel' => 'not*base64'], $path, true);
        });

        $this->assertSame('OLD', file_get_contents($path), 'a file that is to be replaced is kept when the document is bad');
    }

    /**
     * The check can be asked for when saving
     * @return void
     */
    public function testTheCheckCanBeAskedForWhenSaving(): void
    {
        $dir = $this->tempDir();

        $this->assertSame(6, FedexLabels::save(self::doc('^XA^XZ', ['docType' => 'ZPLII']), $dir . DIRECTORY_SEPARATOR . 'a.zpl', false, true));
        $this->assertSame(6, FedexLabels::save(self::doc('^XA^XZ'), $dir . DIRECTORY_SEPARATOR . 'b.zpl', false, true, 'ZPL'), 'the format given');
        $this->assertSame(6, FedexLabels::save(self::doc('^XA^XZ'), $dir . DIRECTORY_SEPARATOR . 'c.pdf'), 'no check when it is not asked for');
        $this->assertSame('^XA^XZ', file_get_contents($dir . DIRECTORY_SEPARATOR . 'a.zpl'));
    }
}
