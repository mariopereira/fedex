<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexTradeDocuments;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexTradeDocumentsException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use Mariopereira\Fedex\Tests\Support\TradeDocumentsSpec as Spec;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Trade Documents Upload API: what the five uploads send (three multipart requests and two JSON requests), to the
 * hosts of this API, how they check the parts, the files and the responses, and that none of them is repeated (they all store
 * data in FedEx)
 */
final class FedexTradeDocumentsTest extends TestCase
{
    use BatchAssertions;
    use LooseScalars;

    /**
     * The note of the messages of the responses that are not valid
     */
    private const NOTE = ' (the operation may have been done by FedEx: check it before repeating it)';

    /**
     * A file: its name, its content (with a line break and a zero byte, sent as they are) and its media type
     */
    private const FILE = ['invoice.pdf', "%PDF-1.4\r\n\0end", 'application/pdf'];

    /**
     * The transport of the last object made by api()
     * @var FakeTransport
     */
    private $transport;

    /**
     * The authorization of the last object made by api()
     * @var StubAuth
     */
    private $auth;

    /**
     * A FedexTradeDocuments over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexTradeDocuments
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexTradeDocuments
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(201, '{"customerTransactionId":"C1"}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexTradeDocuments($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The five operations: the label of the spec, the path, the JSON parts (name => label of the root in the spec; none for
     * a JSON request) and the name of the file parts (null for a JSON request)
     * @return array
     */
    public function operationsProvider(): array
    {
        return [
            'upload' => ['upload', 'documents/v1/etds/upload', ['document' => 'upload.document'], 'attachment'],
            'multiupload' => ['multiupload', 'documents/v1/etds/multiupload', ['documentInformation' => 'multiupload.documentInformation'], 'fileAttachments'],
            'image' => ['image', 'documents/v1/lhsimages/upload', ['document' => 'image.document', 'rules' => 'image.rules'], 'attachment'],
            'encodedimage' => ['encodedimage', 'documents/v1/lhsimages/encodedupload', ['' => 'encodedimage.request'], null],
            'encodedmultiupload' => ['encodedmultiupload', 'documents/v1/etds/encodedmultiupload', ['' => 'encodedmultiupload.request'], null],
        ];
    }

    /**
     * Call an operation with its JSON parts (or its JSON request) and, for a multipart one, the file
     * @param FedexTradeDocuments $api The object
     * @param string $label The label of the operation
     * @param array $parts The parts, by name ('' for the request of a JSON operation)
     * @return \stdClass
     */
    private static function call(FedexTradeDocuments $api, string $label, array $parts): \stdClass
    {
        [$name, $content, $type] = self::FILE;

        switch ($label) {
            case 'upload':
                return $api->uploadDocument($parts['document'], $name, $content, $type);
            case 'multiupload':
                return $api->uploadDocuments($parts['documentInformation'], [self::FILE]);
            case 'image':
                return $api->uploadImage($parts['document'], $parts['rules'], $name, $content, $type);
            case 'encodedimage':
                return $api->uploadEncodedImage($parts['']);
            default:
                return $api->uploadEncodedDocuments($parts['']);
        }
    }

    /**
     * The generated instance of every part of an operation
     * @param array $roots The JSON parts (name => label of the root in the spec)
     * @return array The parts, by name
     */
    private static function generated(array $roots): array
    {
        $parts = [];

        foreach ($roots as $name => $root) {
            $parts[$name] = Spec::instance($root)[0];
        }

        return $parts;
    }

    /**
     * The parts of a multipart body, in order: name, file name (null for a text part), media type (null for a text part) and
     * content
     * @param array $call The call recorded by the transport
     * @return array
     */
    private static function partsOf(array $call): array
    {
        $header = array_values(preg_grep('/^Content-Type: /', $call['headers']))[0];
        $boundary = substr($header, strpos($header, 'boundary=') + 9);
        $chunks = explode('--' . $boundary, $call['body']);
        $parts = [];

        foreach (array_slice($chunks, 1, -1) as $chunk) {
            [$head, $content] = explode("\r\n\r\n", substr($chunk, 2), 2);
            preg_match('/name="([^"]*)"(?:; filename="([^"]*)")?/', $head, $m);
            preg_match('/\r\nContent-Type: (.*)$/', $head, $t);
            $parts[] = [$m[1], $m[2] ?? null, $t[1] ?? null, substr($content, 0, -2)];
        }

        return $parts;
    }

    /**
     * The JSON parts that were sent (or the JSON request), decoded, by name
     * @param array $call The call recorded by the transport
     * @return array
     */
    private static function sentParts(array $call): array
    {
        if (strpos(implode("\n", $call['headers']), 'Content-Type: application/json') !== false) {
            return ['' => json_decode($call['body'], true)];
        }

        $sent = [];

        foreach (self::partsOf($call) as [$name, $file, $type, $content]) {
            if ($file === null) {
                $sent[$name] = json_decode($content, true);
            }
        }

        return $sent;
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
     * Describe an exception, for the message of a failed case
     * @param \Throwable|null $e The exception
     * @return string
     */
    private static function describe(?\Throwable $e): string
    {
        return ($e === null) ? 'no exception' : get_class($e) . ' ' . $e->getCode() . ' ' . $e->getMessage() . ($e instanceof FedexException ? ' ' . implode(' | ', array_map('strval', $e->getErrors())) : '');
    }

    /**
     * The class extends the base of the API classes, has its own exception, the hosts of the documentation and the paths of
     * the spec
     * @return void
     */
    public function testTheClassExtendsTheBaseHasItsOwnExceptionTheHostsOfTheDocumentationAndThePathsOfTheSpec(): void
    {
        $this->assertInstanceOf(AbstractFedexApi::class, $this->api());
        $this->assertInstanceOf(FedexException::class, new FedexTradeDocumentsException('x'));
        $this->assertSame('https://documentapitest.prod.fedex.com/sandbox/', FedexTradeDocuments::SANDBOX_URL);
        $this->assertSame('https://documentapi.prod.fedex.com/', FedexTradeDocuments::PRODUCTION_URL);
        $this->assertSame(5, FedexTradeDocuments::MAX_FILES);

        $endpoints = [FedexTradeDocuments::UPLOAD_ENDPOINT, FedexTradeDocuments::IMAGE_ENDPOINT, FedexTradeDocuments::ENCODED_IMAGE_ENDPOINT, FedexTradeDocuments::MULTIUPLOAD_ENDPOINT, FedexTradeDocuments::ENCODED_MULTIUPLOAD_ENDPOINT];
        $this->assertSame(array_keys(Spec::spec()['paths']), array_map(function ($endpoint) {
            return '/' . $endpoint;
        }, $endpoints));
    }

    // ---------------------------------------------------------------- what the operations send

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     * @param string|null $fileParts The name of the file parts
     */
    public function testEachOperationSendsOnePostWithTheGeneratedPartsToTheSandboxHost(string $label, string $path, array $roots, ?string $fileParts): void
    {
        $parts = self::generated($roots);
        $api = $this->api();

        $result = self::call($api, $label, $parts);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame('https://documentapitest.prod.fedex.com/sandbox/' . $path, $calls[0]['url']);
        $this->assertEquals($parts, self::sentParts($calls[0]));
        $this->assertSame('C1', $result->customerTransactionId);

        if ($fileParts === null) {
            return;
        }

        $expected = [];

        foreach ($roots as $name => $root) {
            $expected[] = [$name, null, null];
        }

        $expected[] = [$fileParts, self::FILE[0], self::FILE[2]];
        $found = array_map(function ($part) {
            return array_slice($part, 0, 3);
        }, self::partsOf($calls[0]));

        $this->assertSame($expected, $found, 'the JSON parts, in order, then the file');
        $this->assertSame(self::FILE[1], self::partsOf($calls[0])[count($roots)][3], 'the content of the file as it is');
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     * @param string|null $fileParts The name of the file parts
     */
    public function testEachOperationSendsTheHeadersAndAUuidThatIsDifferentInEveryCall(string $label, string $path, array $roots, ?string $fileParts): void
    {
        $api = $this->api();
        $parts = self::generated($roots);

        self::call($api, $label, $parts);
        self::call($api, $label, $parts);
        $ids = [];

        foreach ($this->transport->calls as $call) {
            $this->assertContains('Authorization: Bearer TOK', $call['headers']);
            $this->assertContains('X-locale: en_US', $call['headers']);
            $this->assertCount(1, preg_grep(($fileParts === null) ? '/^Content-Type: application\/json$/' : '/^Content-Type: multipart\/form-data; boundary=----FedexBoundary[0-9a-f]{32}$/', $call['headers']));
            $found = array_values(preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $call['headers']));
            $this->assertCount(1, $found);
            $ids[] = $found[0];
        }

        $this->assertNotSame($ids[0], $ids[1]);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     */
    public function testTheProductionHostIsUsedWhenTheAuthorizationIsNotTheSandbox(string $label, string $path, array $roots): void
    {
        $api = $this->api([], null, new StubAuth(false, 'client_credentials', 'id', 'secret'));

        self::call($api, $label, self::generated($roots));

        $this->assertSame('https://documentapi.prod.fedex.com/' . $path, $this->transport->calls[0]['url']);
    }

    /**
     * The JSON requests take an object; anything that is not an array or an object is refused
     * @return void
     */
    public function testAJsonRequestThatIsNotAnArrayOrAnObjectIsRefusedAndAnObjectIsTaken(): void
    {
        foreach (['uploadEncodedImage' => 'encodedimage.request', 'uploadEncodedDocuments' => 'encodedmultiupload.request'] as $method => $root) {
            $api = $this->api();

            foreach ([null, 'text', 5, 1.5, true] as $request) {
                $e = self::thrown(function () use ($api, $method, $request) {
                    $api->$method($request);
                });

                $this->assertInstanceOf(FedexTradeDocumentsException::class, $e, var_export($request, true));
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
                $this->assertSame('The request must be an array or an object', $e->getMessage());
            }

            $this->assertSame([], $this->transport->calls);

            $request = Spec::instance($root)[0];
            $api->$method(json_decode(json_encode($request)));

            $this->assertEquals($request, $this->transport->bodyOf(0));
        }
    }

    /**
     * An unknown key is sent, and nulls and empty lists are left out, in the parts and in the JSON requests
     * @return void
     */
    public function testAnUnknownKeyIsSentAndNullAndEmptyListsAreLeftOut(): void
    {
        $document = Spec::instance('upload.document')[0];
        $api = $this->api();

        $sent = $document;
        $sent['carrierCode'] = null;
        $sent['notInTheSchema'] = 1;
        $api->uploadDocument($sent, ...self::FILE);
        unset($document['carrierCode']);

        $this->assertSame($document + ['notInTheSchema' => 1], self::sentParts($this->transport->calls[0])['document']);

        $request = Spec::instance('encodedimage.request')[0];
        $api = $this->api();
        $api->uploadEncodedImage($request + ['notInTheSchema' => 1, 'empty' => []]);

        $this->assertSame($request + ['notInTheSchema' => 1], $this->transport->bodyOf(0));
    }

    /**
     * The templates carry the fields that the schema requires, and are sent once filled in
     * @return void
     */
    public function testTheTemplatesCarryTheRequiredFieldsAndAreSentOnceFilledIn(): void
    {
        $this->assertSame(['workflowName' => null, 'name' => null, 'contentType' => null, 'meta' => ['shipDocumentType' => null, 'originCountryCode' => null, 'destinationCountryCode' => null]], FedexTradeDocuments::newDocument());
        $this->assertSame(['referenceId' => null, 'name' => null, 'contentType' => null, 'meta' => ['imageType' => null, 'imageIndex' => null]], FedexTradeDocuments::newImageDocument());

        $api = $this->api();
        $document = FedexTradeDocuments::newDocument();
        $document['workflowName'] = 'ETDPreshipment';
        $document['name'] = 'invoice.pdf';
        $document['contentType'] = 'application/pdf';
        $document['meta'] = ['shipDocumentType' => 'COMMERCIAL_INVOICE', 'originCountryCode' => 'US', 'destinationCountryCode' => 'IN'];
        $api->uploadDocument($document, ...self::FILE);
        $image = FedexTradeDocuments::newImageDocument();
        $image['referenceId'] = '1234';
        $image['name'] = 'LH2.PNG';
        $image['contentType'] = 'image/png';
        $image['meta'] = ['imageType' => 'SIGNATURE', 'imageIndex' => 'IMAGE_1'];
        $api->uploadImage($image, ['workflowName' => 'LetterheadSignature'], 'LH2.PNG', 'png', 'image/png');

        $this->assertSame(['document' => $document], self::sentParts($this->transport->calls[0]));
        $this->assertSame(['document' => $image, 'rules' => ['workflowName' => 'LetterheadSignature']], self::sentParts($this->transport->calls[1]));

        $e = self::thrown(function () use ($api) {
            $api->uploadDocument(FedexTradeDocuments::newDocument(), ...self::FILE);
        });
        $this->assertInstanceOf(FedexTradeDocumentsException::class, $e, 'the template left empty misses the required fields');
        $this->assertSame('Invalid document', $e->getMessage());
        $this->assertCount(2, $this->transport->calls);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     */
    public function testAValueOfTheWrongTypeOrOutsideAnEnumInAnyLeafOfAnyPartIsRefusedAndNothingIsSent(string $label, string $path, array $roots): void
    {
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($roots as $name => $root) {
            [$instance, $meta] = Spec::instance($root);
            $message = ($name === '') ? 'Invalid request' : "Invalid $name";

            foreach ($meta['leaves'] as $leaf) {
                if (!$leaf['typed']) {
                    continue;
                }

                foreach (array_filter([$wrong[$leaf['type']], $leaf['enum'] !== null ? 'NOT_A_VALUE' : null]) as $value) {
                    $parts = self::generated($roots);
                    $parts[$name] = SpecInstances::setAt($instance, $leaf['path'], $value);
                    $api = $this->api();
                    $e = self::thrown(function () use ($api, $label, $parts) {
                        self::call($api, $label, $parts);
                    });
                    $ok = $e instanceof FedexTradeDocumentsException && $e->getMessage() === $message && $e->getErrors() !== [] && $this->transport->calls === [];
                    $results["$name " . implode('.', $leaf['path']) . ' ' . json_encode($value)] = $ok ? true : self::describe($e);
                }
            }
        }

        $this->assertAll('the values of the wrong type or outside an enum', $results);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     */
    public function testTheScalarsOfALooserTypeAreSentAsTheyAre(string $label, string $path, array $roots): void
    {
        foreach ($roots as $name => $root) {
            [$instance, $meta] = Spec::instance($root);

            $this->assertLooseScalarsAreSent($instance, $meta['leaves'], function (array $part) use ($label, $roots, $name) {
                $parts = self::generated($roots);
                $parts[$name] = $part;
                $api = $this->api();
                self::call($api, $label, $parts);

                return self::sentParts($this->transport->calls[0])[$name];
            });
        }
    }

    // ---------------------------------------------------------------- the files

    /**
     * The upload of several documents sends every file, in order, as fileAttachments
     * @return void
     */
    public function testTheUploadOfSeveralDocumentsSendsEveryFileInOrder(): void
    {
        $api = $this->api();
        $files = [['a.pdf', 'A', 'application/pdf'], ['b.txt', 'B', 'text/plain'], ['c.png', 'C', 'image/png'], ['d.pdf', 'D', 'application/pdf'], ['e.pdf', 'E', 'application/pdf']];

        $api->uploadDocuments(Spec::instance('multiupload.documentInformation')[0], $files);
        $parts = self::partsOf($this->transport->calls[0]);

        $this->assertSame('documentInformation', $parts[0][0]);
        $this->assertSame(array_map(function ($file) {
            return ['fileAttachments', $file[0], $file[2], $file[1]];
        }, $files), array_slice($parts, 1));
    }

    /**
     * No file, more than five, a file that is not a list of three texts, or an empty file, is refused before anything is sent
     * @return void
     */
    public function testTheFilesThatCannotBeSentAreRefusedBeforeAnythingIsSent(): void
    {
        $information = Spec::instance('multiupload.documentInformation')[0];
        $document = Spec::instance('upload.document')[0];
        $image = Spec::instance('image.document')[0];
        $api = $this->api();
        $cases = [
            'no file' => [function () use ($api, $information) {
                $api->uploadDocuments($information, []);
            }, 'From one to 5 files can be uploaded at once'],
            'six files' => [function () use ($api, $information) {
                $api->uploadDocuments($information, array_fill(0, 6, self::FILE));
            }, 'From one to 5 files can be uploaded at once'],
            'a file that is not a list' => [function () use ($api, $information) {
                $api->uploadDocuments($information, [self::FILE, 'a.pdf']);
            }, 'The file 1 must be a list of the file name, the content and the media type, all texts'],
            'a file with two entries' => [function () use ($api, $information) {
                $api->uploadDocuments($information, [['a.pdf', 'x']]);
            }, 'The file 0 must be a list of the file name, the content and the media type, all texts'],
            'a file with four entries' => [function () use ($api, $information) {
                $api->uploadDocuments($information, [['a.pdf', 'x', 'application/pdf', 'extra']]);
            }, 'The file 0 must be a list of the file name, the content and the media type, all texts'],
            'a file with a key instead of an entry' => [function () use ($api, $information) {
                $api->uploadDocuments($information, [['a.pdf', 'x', 'type' => 'text/plain']]);
            }, 'The file 0 must be a list of the file name, the content and the media type, all texts'],
            'a file with a number' => [function () use ($api, $information) {
                $api->uploadDocuments($information, [['a.pdf', 5, 'text/plain']]);
            }, 'The file 0 must be a list of the file name, the content and the media type, all texts'],
            'an empty file of several' => [function () use ($api, $information) {
                $api->uploadDocuments($information, [self::FILE, ['b.pdf', '', 'application/pdf']]);
            }, "The file 'b.pdf' is empty"],
            'an empty document' => [function () use ($api, $document) {
                $api->uploadDocument($document, 'a.pdf', '', 'application/pdf');
            }, "The file 'a.pdf' is empty"],
            'an empty image' => [function () use ($api, $image) {
                $api->uploadImage($image, ['workflowName' => 'LetterheadSignature'], 'a.png', '', 'image/png');
            }, "The file 'a.png' is empty"],
        ];
        $results = [];

        foreach ($cases as $case => [$fn, $message]) {
            $e = self::thrown($fn);
            $ok = $e instanceof FedexTradeDocumentsException && $e->getCode() === FedexException::EC_BAD_PARAMETER && $e->getMessage() === $message;
            $results[$case] = $ok ? true : self::describe($e);
        }

        $this->assertAll('the files that cannot be sent', $results);
        $this->assertSame([], $this->transport->calls);

        $api->uploadDocuments($information, ['x' => self::FILE]);
        $this->assertCount(1, $this->transport->calls, 'the keys of the list of files do not matter');
    }

    /**
     * A file name or a media type that would break the headers of its part is refused by the client before anything is sent
     * @return void
     */
    public function testAFileNameOrAMediaTypeThatWouldBreakTheHeadersIsRefusedBeforeAnythingIsSent(): void
    {
        $api = $this->api();
        $document = Spec::instance('upload.document')[0];

        foreach ([['a".pdf', 'application/pdf'], ["a\r\n.pdf", 'application/pdf'], ['a.pdf', "application/pdf\r\nX: 1"]] as [$name, $type]) {
            $e = self::thrown(function () use ($api, $document, $name, $type) {
                $api->uploadDocument($document, $name, 'x', $type);
            });

            $this->assertInstanceOf(FedexClientException::class, $e);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        }

        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     */
    public function testTheResponseGeneratedFromTheSpecIsAcceptedAndAFieldThatFedexAddsIsTolerated(string $label, string $path, array $roots): void
    {
        $generated = Spec::instance($label . '.response')[0];
        $api = $this->api([], Responses::make(201, json_encode($generated)));

        $this->assertEquals(json_decode(json_encode($generated)), self::call($api, $label, self::generated($roots)));

        $generated['addedByFedex'] = ['x' => 1];
        $api = $this->api([], Responses::make(201, json_encode($generated)));

        $this->assertSame(1, self::call($api, $label, self::generated($roots))->addedByFedex->x);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     */
    public function testAResponseWithAValueOfTheWrongTypeIsRefusedWithTheResponseAndSaysTheUploadMayHaveBeenDone(string $label, string $path, array $roots): void
    {
        [$response, $meta] = Spec::instance($label . '.response');
        $parts = self::generated($roots);
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $copy = SpecInstances::setAt($response, $leaf['path'], $wrong[$leaf['type']]);
            $api = $this->api([], Responses::make(201, json_encode($copy)));
            $e = self::thrown(function () use ($api, $label, $parts) {
                self::call($api, $label, $parts);
            });
            $ok = ($e instanceof FedexTradeDocumentsException)
                && ($e->getCode() === FedexException::EC_BAD_RESULT_RETURNED)
                && ($e->getMessage() === 'Invalid response received' . self::NOTE)
                && ($e->getData() == json_decode(json_encode($copy)))
                && !empty($e->getErrors());
            $results[implode('.', $leaf['path'])] = $ok ? true : self::describe($e);
        }

        $this->assertAll('the responses with a value of the wrong type', $results);

        $api = $this->api([], Responses::make(201, '[1,2]'));
        $e = self::thrown(function () use ($api, $label, $parts) {
            self::call($api, $label, $parts);
        });

        $this->assertInstanceOf(FedexTradeDocumentsException::class, $e);
        $this->assertSame('Invalid response' . self::NOTE, $e->getMessage());
        $this->assertSame([1, 2], $e->getData());
        $this->assertSame([], $e->getErrors());
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     */
    public function testAFailureThatIsTransientIsNeverRepeated(string $label, string $path, array $roots): void
    {
        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], null, null, true);
            $e = self::thrown(function () use ($api, $label, $roots) {
                self::call($api, $label, self::generated($roots));
            });

            $this->assertInstanceOf(FedexClientException::class, $e, "HTTP $status");
            $this->assertCount(1, $this->transport->calls, "HTTP $status is not repeated: the upload stores data in FedEx");
        }
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation
     * @param string $path The path
     * @param array $roots The JSON parts
     */
    public function testA401IsRetriedOnceWithAFreshTokenAndTheSameBodyAndTwo401sInARowStopAfterTwoRequests(string $label, string $path, array $roots): void
    {
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(201, '{"customerTransactionId":"C2"}')]);

        $this->assertSame('C2', self::call($api, $label, self::generated($roots))->customerTransactionId);
        $this->assertCount(2, $this->transport->calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($this->transport->calls[0]['body'], $this->transport->calls[1]['body']);
        $this->assertSame($this->transport->calls[0]['url'], $this->transport->calls[1]['url']);

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}')]);
        $e = self::thrown(function () use ($api, $label, $roots) {
            self::call($api, $label, self::generated($roots));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(401, $e->getStatusCode());
        $this->assertCount(2, $this->transport->calls);
    }

    /**
     * A token that cannot be had sends nothing, a response that is not JSON and a 400 keep what FedEx said
     * @return void
     */
    public function testNoTokenNotJsonAndA400(): void
    {
        $document = Spec::instance('upload.document')[0];

        $auth = StubAuth::make();
        $auth->throwOnToken = new FedexAuthException('token failed', FedexException::EC_UNABLE_TO_AUTHENTICATE, null, 401);
        $api = $this->api([], null, $auth);
        $e = self::thrown(function () use ($api, $document) {
            $api->uploadDocument($document, ...self::FILE);
        });
        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame([], $this->transport->calls);

        $api = $this->api([Responses::make(201, 'not json')]);
        $e = self::thrown(function () use ($api, $document) {
            $api->uploadDocument($document, ...self::FILE);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());

        $api = $this->api([Responses::make(400, '{"transactionId":"624deea6","errors":[{"code":"INVALID.INPUT.EXCEPTION","message":"Invalid input."}]}')]);
        $e = self::thrown(function () use ($api, $document) {
            $api->uploadDocument($document, ...self::FILE);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }
}
