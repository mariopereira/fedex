<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexTradeDocumentsSchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\TradeDocumentsSpec as Spec;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Trade Documents Upload API. They are compared with schemas/upload-documents.json, which is
 * read here independently of the code that generated them, and an instance of each root is generated from that file and broken
 * one field at a time. The differences from the file are the two hyphenated names, written with an underscore, and the two
 * fields that Document_1 requires and never defines, which are optional fields of any type
 */
final class FedexTradeDocumentsSchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The constant of the class of each root, by label
     */
    private const CONSTANTS = [
        'upload.document' => 'UPLOAD_DOCUMENT', 'upload.response' => 'UPLOAD_RESPONSE',
        'multiupload.documentInformation' => 'MULTIUPLOAD_DOCUMENT_INFORMATION', 'multiupload.response' => 'MULTIUPLOAD_RESPONSE',
        'image.document' => 'IMAGE_DOCUMENT', 'image.rules' => 'IMAGE_RULES', 'image.response' => 'IMAGE_RESPONSE',
        'encodedimage.request' => 'ENCODED_IMAGE_REQUEST', 'encodedimage.response' => 'ENCODED_IMAGE_RESPONSE',
        'encodedmultiupload.request' => 'ENCODED_MULTIUPLOAD_REQUEST', 'encodedmultiupload.response' => 'ENCODED_MULTIUPLOAD_RESPONSE',
    ];

    /**
     * The result of comparing the definitions with the spec
     * @var array|null
     */
    private static $conformance;

    /**
     * The names of the definitions reachable from the roots, as in the spec
     * @return array The names as keys
     */
    private static function reachable(): array
    {
        $seen = [];

        foreach (Spec::ROOTS as $root) {
            Spec::closure($root, $seen);
        }

        return $seen;
    }

    /**
     * Compare every definition of the spec with the one of the class
     * @return array The cases (name => true or the reason)
     */
    private static function conformance(): array
    {
        if (self::$conformance !== null) {
            return self::$conformance;
        }

        $C = Spec::components();
        $defs = Schemas::definitions();
        $res = [];

        foreach (array_keys(self::reachable()) as $n) {
            $c = $C[$n];
            $cn = Spec::renamed($n);

            if (!isset($defs[$cn])) {
                $res["$n exists"] = 'missing in the class';
                continue;
            }

            $d = $defs[$cn];

            if (isset($c['$ref'])) {
                $res["$n alias"] = ($d === '@' . Spec::renamed(basename($c['$ref']))) ? true : 'not the alias of ' . $c['$ref'];
                continue;
            }

            if (!isset($c['properties'])) {
                $res["$n scalar or list"] = (Spec::canonClass($d) === Spec::canonSpec($c, $n)) ? true : Spec::canonClass($d) . ' != ' . Spec::canonSpec($c, $n);
                continue;
            }

            $required = $c['required'] ?? [];
            // a field that the schema requires and never defines is, in the class, an optional field of any type, after the others
            $undefined = array_values(array_diff($required, array_keys($c['properties'])));
            $classKeys = [];

            foreach ($d as $k => $v) {
                $classKeys[substr($k, -1) === '?' ? substr($k, 0, -1) : $k] = substr($k, -1) !== '?';
            }

            $specKeys = [];

            foreach ($c['properties'] as $pn => $p) {
                $specKeys[$pn] = in_array($pn, $required, true);
            }

            foreach ($undefined as $key) {
                $specKeys[$key] = false;
                $res["$n.$key (required, never defined)"] = (($d[$key . '?'] ?? null) === 'mixed') ? true : 'not an optional field of any type';
            }

            $res["$n keys and required flags"] = ($classKeys === $specKeys) ? true : 'class=' . json_encode($classKeys) . ' spec=' . json_encode($specKeys);

            foreach ($c['properties'] as $pn => $p) {
                $k = $pn . ($specKeys[$pn] ? '' : '?');

                if (isset($d[$k])) {
                    $a = Spec::canonClass($d[$k]);
                    $b = Spec::canonSpec($p, "$n.$pn");
                    $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
                }
            }
        }

        self::$conformance = $res;

        return self::$conformance;
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has twenty seven definitions reachable from the roots, and the class has exactly those
     * @return void
     */
    public function testTheSpecHasTwentySevenDefinitionsReachableFromTheRootsAndTheClassHasExactlyThose(): void
    {
        $defined = array_keys(Schemas::definitions());
        $reachable = array_map([Spec::class, 'renamed'], array_keys(self::reachable()));
        sort($defined);
        sort($reachable);

        $this->assertCount(27, $reachable);
        $this->assertSame($reachable, $defined);
    }

    /**
     * The root constants name the roots of the spec: the JSON parts of the multipart requests, the JSON requests, and the
     * responses (201) of the five paths
     * @return void
     */
    public function testTheRootConstantsNameTheRootsOfTheSpec(): void
    {
        $C = Spec::components();
        $paths = Spec::spec()['paths'];
        $ref = function (array $node): string {
            return basename($node['$ref']);
        };
        $multipart = function (string $path) use ($paths, $C, $ref): array {
            $body = $ref($paths[$path]['post']['requestBody']['content']['multipart/form-data']['schema']);

            return isset($C[$body]['oneOf']) ? $C[$ref($C[$body]['oneOf'][0])]['properties'] : $C[$body]['properties'];
        };
        $response = function (string $path) use ($paths, $ref): string {
            return $ref($paths[$path]['post']['responses']['201']['content']['application/json']['schema']);
        };
        $json = function (string $path) use ($paths, $C, $ref): string {
            $body = $ref($paths[$path]['post']['requestBody']['content']['application/json']['schema']);

            return isset($C[$body]['oneOf']) ? $ref($C[$body]['oneOf'][0]) : $body;
        };

        $this->assertSame(['/documents/v1/etds/upload', '/documents/v1/lhsimages/upload', '/documents/v1/lhsimages/encodedupload', '/documents/v1/etds/multiupload', '/documents/v1/etds/encodedmultiupload'], array_keys($paths));

        $found = [
            'upload.document' => $ref($multipart('/documents/v1/etds/upload')['document']),
            'upload.response' => $response('/documents/v1/etds/upload'),
            'multiupload.documentInformation' => $ref($multipart('/documents/v1/etds/multiupload')['documentInformation']),
            'multiupload.response' => $response('/documents/v1/etds/multiupload'),
            'image.document' => $ref($multipart('/documents/v1/lhsimages/upload')['document']),
            'image.rules' => $ref($multipart('/documents/v1/lhsimages/upload')['rules']),
            'image.response' => $response('/documents/v1/lhsimages/upload'),
            'encodedimage.request' => $json('/documents/v1/lhsimages/encodedupload'),
            'encodedimage.response' => $response('/documents/v1/lhsimages/encodedupload'),
            'encodedmultiupload.request' => $json('/documents/v1/etds/encodedmultiupload'),
            'encodedmultiupload.response' => $response('/documents/v1/etds/encodedmultiupload'),
        ];

        $this->assertSame(Spec::ROOTS, $found);

        foreach (Spec::ROOTS as $label => $root) {
            $this->assertSame(Spec::renamed($root), constant(Schemas::class . '::' . self::CONSTANTS[$label]), $label);
        }

        $this->assertCount(11, (new \ReflectionClass(Schemas::class))->getConstants(), 'the roots: no other constant');
        $this->assertTrue((new \ReflectionClass(Schemas::class))->isFinal());
    }

    /**
     * The file parts of the multipart requests are the ones that the class sends: attachment, and fileAttachments (at most five)
     * @return void
     */
    public function testTheFilePartsOfTheMultipartRequestsAreTheOnesThatTheClassSends(): void
    {
        $C = Spec::components();

        $this->assertSame(['attachment', 'document'], array_keys($C['FullSchema-DocumentUploadInputVO']['properties']));
        $this->assertSame(['document', 'rules', 'attachment'], array_keys($C['FullSchema-ImageUploadServiceInputVO']['properties']));
        $this->assertSame(['document', 'rules', 'attachment'], $C['FullSchema-ImageUploadServiceInputVO']['required']);
        $this->assertSame(['documentInformation', 'fileAttachments'], array_keys($C['FullSchema-MultiDocumentRequest']['properties']));
        $this->assertSame(5, $C['FullSchema-MultiDocumentRequest']['properties']['fileAttachments']['maxItems']);
    }

    /**
     * Every definition of the spec is in the class with the same shape
     * @return void
     */
    public function testEveryDefinitionOfTheSpecIsInTheClassWithTheSameShape(): void
    {
        $this->assertAll('every definition: same keys, same required keys, same types, enums, most entries and references as the spec', self::conformance());
    }

    /**
     * Document_1 requires rules and workflowName and never defines them: they are optional fields of any type, and nothing else
     * of the spec is required and undefined
     * @return void
     */
    public function testOnlyDocument1RequiresFieldsThatItNeverDefines(): void
    {
        $undefined = [];

        foreach (array_keys(self::reachable()) as $n) {
            $c = Spec::components()[$n];
            $missing = array_values(array_diff($c['required'] ?? [], array_keys($c['properties'] ?? [])));

            if ($missing !== []) {
                $undefined[$n] = $missing;
            }
        }

        $this->assertSame(['Document_1' => ['rules', 'workflowName']], $undefined);
        $this->assertSame('mixed', Schemas::definitions()['Document_1']['rules?']);
        $this->assertSame('mixed', Schemas::definitions()['Document_1']['workflowName?']);
    }

    /**
     * The metadata of the files is a list of at most five entries
     * @return void
     */
    public function testTheMetadataOfTheFilesIsAListOfAtMostFiveEntries(): void
    {
        $validator = new SchemaValidator(false, Schemas::definitions());
        $entry = ['fileName' => 'a.pdf', 'contentType' => 'application/pdf', 'shipDocumentType' => 'COMMERCIAL_INVOICE'];
        $request = ['workflowName' => 'ETDPreshipment', 'carrierCode' => 'FDXE', 'originCountryCode' => 'US', 'destinationCountryCode' => 'CA'];

        $this->assertTrue($validator->validate($request + ['metaData' => array_fill(0, 5, $entry)], '@' . Schemas::MULTIUPLOAD_DOCUMENT_INFORMATION));
        $this->assertFalse($validator->validate($request + ['metaData' => array_fill(0, 6, $entry)], '@' . Schemas::MULTIUPLOAD_DOCUMENT_INFORMATION));
    }

    /**
     * Every reference resolves and every definition but the roots is used
     * @return void
     */
    public function testEveryReferenceResolvesAndEveryDefinitionButTheRootsIsUsed(): void
    {
        $defs = Schemas::definitions();
        $used = [];
        $unresolved = [];
        $walk = function ($node) use (&$walk, &$used, &$unresolved, $defs) {
            if (is_string($node)) {
                foreach (explode('|', $node) as $type) {
                    $type = trim($type);

                    if ($type !== '' && $type[0] === '@') {
                        $used[substr($type, 1)] = true;

                        if (!isset($defs[substr($type, 1)])) {
                            $unresolved[] = $type;
                        }
                    }
                }
            } elseif (is_array($node)) {
                foreach ($node as $key => $value) {
                    if (!in_array($key, ['@enum', '@pattern', '@check'], true)) {
                        $walk($value);
                    }
                }
            }
        };

        foreach ($defs as $definition) {
            $walk($definition);
        }

        $this->assertSame([], array_values(array_unique($unresolved)), 'every @reference resolves to a definition');
        $this->assertSame([], array_values(array_diff(array_keys($defs), array_keys($used), array_map([Spec::class, 'renamed'], Spec::ROOTS))));
    }

    // ---------------------------------------------------------------- the examples of the spec

    /**
     * The JSON parts of the examples of the multipart requests: the ones of the upload of a document are accepted; the example
     * of the upload of an image sends the description and the rules in one part, which the schema (three parts) does not
     * describe; the examples of the upload of several documents are not JSON (a closing brace is missing)
     * @return void
     */
    public function testTheJsonPartsOfTheExamplesOfTheMultipartRequests(): void
    {
        $paths = Spec::spec()['paths'];
        $results = [];
        $parts = function (string $text): array {
            $found = [];

            foreach (explode("\n", $text) as $line) {
                if (preg_match('/^\s*(\w+)\s*:\s*(.*)$/s', $line, $m)) {
                    $found[] = [$m[1], trim($m[2])];
                }
            }

            return $found;
        };
        $validator = function () {
            return (new SchemaValidator(true, Schemas::definitions()))->setLooseScalars(true);
        };

        foreach ($paths['/documents/v1/etds/upload']['post']['requestBody']['content']['multipart/form-data']['examples'] as $name => $example) {
            [$document, $attachment] = $parts($example['value']);
            $v = $validator();

            $results["upload $name: parts"] = ([$document[0], $attachment[0]] === ['document', 'attachment']) ? true : json_encode([$document[0], $attachment[0]]);
            $results["upload $name: accepted"] = $v->validate(json_decode($document[1], true), '@' . Schemas::UPLOAD_DOCUMENT) ? true : implode(' | ', $v->getErrors());
        }

        foreach ($paths['/documents/v1/lhsimages/upload']['post']['requestBody']['content']['multipart/form-data']['examples'] as $name => $example) {
            $found = $parts($example['value']);
            $document = json_decode($found[0][1], true);
            $v = $validator();

            $results["image $name: one JSON part, then the attachment"] = (array_column($found, 0) === ['document', 'attachment']) ? true : json_encode(array_column($found, 0));
            $results["image $name: the part holds document and rules"] = (array_keys($document) === ['document', 'rules']) ? true : json_encode(array_keys($document));
            $results["image $name: its document is accepted as the document part"] = $v->validate($document['document'], '@' . Schemas::IMAGE_DOCUMENT) ? true : implode(' | ', $v->getErrors());
            $results["image $name: its rules are accepted as the rules part"] = $validator()->validate($document['rules'], '@' . Schemas::IMAGE_RULES) ? true : 'refused';
            $results["image $name: the whole part is refused as the document part"] = $validator()->validate($document, '@' . Schemas::IMAGE_DOCUMENT) ? 'accepted' : true;
        }

        foreach ($paths['/documents/v1/etds/multiupload']['post']['requestBody']['content']['multipart/form-data']['examples'] as $name => $example) {
            $found = $parts($example['value']);

            $results["multiupload $name: documentInformation first"] = ($found[0][0] === 'documentInformation') ? true : $found[0][0];
            $results["multiupload $name: not JSON"] = (json_decode($found[0][1], true) === null) ? true : 'decodes';
            $results["multiupload $name: the files are fileAttachments"] = (array_unique(array_column(array_slice($found, 1), 0)) === ['fileAttachments']) ? true : 'other parts';
        }

        $this->assertCount(20, $results, 'three examples of the uploads of documents, one of the upload of an image');
        $this->assertAll('the examples of the multipart requests', $results);
    }

    /**
     * The JSON part of the examples of the upload of several documents is accepted once the missing brace is added, except the
     * first one, which is still not JSON: its last entry of metaData has a value without a key ({"file5.txt", ...})
     * @return void
     */
    public function testTheExamplesOfTheUploadOfSeveralDocumentsWithTheMissingBrace(): void
    {
        $results = [];

        foreach (Spec::spec()['paths']['/documents/v1/etds/multiupload']['post']['requestBody']['content']['multipart/form-data']['examples'] as $name => $example) {
            preg_match('/^documentInformation:\s*(.*)$/m', $example['value'], $m);
            $data = json_decode(trim($m[1]) . '}', true);

            if ($data === null) {
                $results[$name] = 'not JSON';
                continue;
            }

            $v = (new SchemaValidator(true, Schemas::definitions()))->setLooseScalars(true);
            $results[$name] = $v->validate($data, '@' . Schemas::MULTIUPLOAD_DOCUMENT_INFORMATION) ? 'accepted' : implode(' | ', $v->getErrors());
        }

        $names = array_keys($results);

        $this->assertCount(3, $results);
        $this->assertStringContainsString('{"file5.txt", ', Spec::spec()['paths']['/documents/v1/etds/multiupload']['post']['requestBody']['content']['multipart/form-data']['examples'][$names[0]]['value']);
        $this->assertSame('not JSON', $results[$names[0]]);
        $this->assertSame('accepted', $results[$names[1]]);
        $this->assertSame('accepted', $results[$names[2]]);
    }

    /**
     * The examples of the two JSON requests are accepted
     * @return void
     */
    public function testTheExamplesOfTheJsonRequestsAreAccepted(): void
    {
        $results = [];

        foreach (['/documents/v1/lhsimages/encodedupload' => Schemas::ENCODED_IMAGE_REQUEST, '/documents/v1/etds/encodedmultiupload' => Schemas::ENCODED_MULTIUPLOAD_REQUEST] as $path => $root) {
            foreach (Spec::spec()['paths'][$path]['post']['requestBody']['content']['application/json']['examples'] as $name => $example) {
                $v = (new SchemaValidator(true, Schemas::definitions()))->setLooseScalars(true);
                $results["$path $name"] = $v->validate($example['value'], '@' . $root) ? true : implode(' | ', array_slice($v->getErrors(), 0, 4));
            }
        }

        $this->assertNotEmpty($results);
        $this->assertAll('the examples of the JSON requests', $results);
    }

    /**
     * The minimum sample payloads of the spec give the metadata of a file in separate entries of metaData (the type of the
     * document in one, the content in another), and leave out fields that the schema requires: the class follows the schema and
     * refuses them
     * @return void
     */
    public function testTheMinimumSamplePayloadsOfTheSpecAreRefused(): void
    {
        $samples = [
            'MinimumSamplePayload-MultiPreShipment' => Schemas::MULTIUPLOAD_DOCUMENT_INFORMATION,
            'MinimumSamplePayload-MultiPostShipment' => Schemas::MULTIUPLOAD_DOCUMENT_INFORMATION,
            'MinimumSamplePayload-Base64MultiPreShipment' => Schemas::ENCODED_MULTIUPLOAD_REQUEST,
            'MinimumSamplePayload-Base64MultiPostShipment' => Schemas::ENCODED_MULTIUPLOAD_REQUEST,
        ];
        $results = [];

        foreach ($samples as $sample => $root) {
            $example = json_decode(json_encode(Spec::components()[$sample]['example']), true);
            $v = (new SchemaValidator(true, Schemas::definitions()))->setLooseScalars(true);
            $results["$sample: the first entry of metaData has only the type of the document"] = ($example['metaData'][0] === ['shipDocumentType' => 'ETD_LABEL']) ? true : json_encode($example['metaData'][0]);
            $results["$sample: refused"] = $v->validate($example, '@' . $root) ? 'accepted' : true;
        }

        $this->assertAll('the minimum sample payloads', $results);
    }

    // ---------------------------------------------------------------- the instances

    /**
     * The cases of the tests of the instances generated from the spec
     * @return array
     */
    public function rootsProvider(): array
    {
        $cases = [];

        foreach (Spec::ROOTS as $label => $root) {
            $cases[$label] = [$label, Spec::renamed($root), substr($label, -8) !== 'response'];
        }

        return $cases;
    }

    /**
     * Does the validator accept the data as an instance of the root? Requests are strict (unknown keys are errors), responses are not
     * @param array $data The data
     * @param string $root The root, as in the class
     * @param bool $isRequest Is it a request?
     * @return bool
     */
    private static function accepts(array $data, string $root, bool $isRequest): bool
    {
        static $validators = null;

        $validators = $validators ?? [
            true => new SchemaValidator(false, Schemas::definitions()),
            false => new SchemaValidator(true, Schemas::definitions()),
        ];

        return $validators[$isRequest]->validate($data, '@' . $root);
    }

    /**
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root, as in the class
     * @param bool $isRequest Is it a request (or a part of one)?
     */
    public function testTheInstanceGeneratedFromTheSpecIsAccepted(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $validator = new SchemaValidator(!$isRequest, Schemas::definitions());

        $this->assertTrue($validator->validate($instance, '@' . $root), implode(' | ', array_slice($validator->getErrors(), 0, 3)));
        $this->assertNotEmpty($meta['leaves']);
        $this->assertNotEmpty($meta['objects']);
    }

    /**
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root, as in the class
     * @param bool $isRequest Is it a request (or a part of one)?
     */
    public function testEveryLeafIsRefusedWithAWrongTypeAndEveryEnumRuleIsEnforced(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $wrong = ['string' => 123, 'integer' => 'x', 'number' => 'x', 'boolean' => 'x'];
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue; // the spec gives no type: any value is valid
            }

            $key = implode('/', $leaf['path']);
            $res["$key wrong type"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], $wrong[$leaf['type']]), $root, $isRequest) ? 'accepted' : true;

            if ($leaf['enum'] !== null) {
                $res["$key bad enum"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], 'NOT_A_VALUE'), $root, $isRequest) ? 'accepted' : true;
                $all = true;

                foreach ($leaf['enum'] as $value) {
                    $all = $all && self::accepts(SpecInstances::setAt($instance, $leaf['path'], $value), $root, $isRequest);
                }

                $res["$key every enum value accepted"] = $all ? true : 'one refused';
            }
        }

        $this->assertAll("$label: the leaves (" . count($meta['leaves']) . ')', $res);
    }

    /**
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root, as in the class
     * @param bool $isRequest Is it a request (or a part of one)?
     */
    public function testEveryOptionalKeyIsOptionalAndUnknownKeysAreRefusedOnlyInRequests(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $res = [];

        foreach ($meta['objects'] as $object) {
            $key = implode('/', $object['path']) ?: '(root)';

            foreach ($object['required'] as $required) {
                $res["$key without required $required"] = self::accepts(SpecInstances::unsetAt($instance, array_merge($object['path'], [$required])), $root, $isRequest) ? 'accepted' : true;
            }

            foreach (array_diff($object['keys'], $object['required']) as $optional) {
                $res["$key without optional $optional"] = self::accepts(SpecInstances::unsetAt($instance, array_merge($object['path'], [$optional])), $root, $isRequest) ? true : 'refused';
            }

            $withExtra = SpecInstances::setAt($instance, $object['path'], array_merge(SpecInstances::getAt($instance, $object['path']), ['notInTheSchema' => 1]));
            $accepted = self::accepts($withExtra, $root, $isRequest);
            $res["$key with an unknown key"] = $isRequest ? ($accepted ? 'accepted' : true) : ($accepted ? true : 'refused in a response');
        }

        $this->assertAll("$label: the keys of " . count($meta['objects']) . ' objects', $res);
    }
}
