<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexOpenShipSchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\OpenShipSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Open Ship API. They are compared with schemas/open-ship.json, which is read here
 * independently of the code that generated them, and an instance of each of the twenty roots is generated from that file
 * and broken one field at a time. The differences from the file are listed one by one
 */
final class FedexOpenShipSchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The result of comparing the definitions with the spec: the cases, and the derived definitions that the spec calls for
     * @var array|null
     */
    private static $conformance;

    /**
     * The validators used by accepts(): the strict one (for requests) under the key true, the lenient one (for responses) under false
     * @var SchemaValidator[]|null
     */
    private static $validators;

    /**
     * The constants of the class for each root of the spec
     * @var string[]
     */
    private const CONSTANTS = [
        'create.request' => 'CREATE_REQUEST', 'create.response' => 'CREATE_RESPONSE',
        'modify.request' => 'MODIFY_REQUEST', 'modify.response' => 'MODIFY_RESPONSE',
        'confirm.request' => 'CONFIRM_REQUEST', 'confirm.response' => 'CONFIRM_RESPONSE',
        'modifyPackage.request' => 'MODIFY_PACKAGE_REQUEST', 'modifyPackage.response' => 'MODIFY_PACKAGE_RESPONSE',
        'addPackages.request' => 'ADD_PACKAGES_REQUEST', 'addPackages.response' => 'ADD_PACKAGES_RESPONSE',
        'deletePackages.request' => 'DELETE_PACKAGES_REQUEST', 'deletePackages.response' => 'DELETE_PACKAGES_RESPONSE',
        'retrievePackage.request' => 'RETRIEVE_PACKAGE_REQUEST', 'retrievePackage.response' => 'RETRIEVE_PACKAGE_RESPONSE',
        'delete.request' => 'DELETE_REQUEST', 'delete.response' => 'DELETE_RESPONSE',
        'retrieve.request' => 'RETRIEVE_REQUEST', 'retrieve.response' => 'RETRIEVE_RESPONSE',
        'results.request' => 'RESULTS_REQUEST', 'results.response' => 'RESULTS_RESPONSE',
    ];

    /**
     * The path and the HTTP method of each operation in the spec, by the label of its roots
     * @var string[]
     */
    private const PATHS = [
        'create' => ['/ship/v1/openshipments/create', 'post'],
        'modify' => ['/ship/v1/openshipments/', 'put'],
        'confirm' => ['/ship/v1/openshipments/', 'post'],
        'modifyPackage' => ['/ship/v1/openshipments/packages', 'put'],
        'addPackages' => ['/ship/v1/openshipments/packages', 'post'],
        'deletePackages' => ['/ship/v1/openshipments/packages/delete', 'put'],
        'retrievePackage' => ['/ship/v1/openshipments/packages/retrieve', 'post'],
        'delete' => ['/ship/v1/openshipments/delete', 'put'],
        'retrieve' => ['/ship/v1/openshipments/retrieve', 'post'],
        'results' => ['/ship/v1/openshipments/results', 'post'],
    ];

    /**
     * The names that the twenty roots have in the spec, with the names of the definitions reachable from them
     * @return array
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
     * @return array [the cases (name => true or the reason), the names of the derived definitions that have to exist]
     */
    private static function conformance(): array
    {
        if (self::$conformance !== null) {
            return self::$conformance;
        }

        $C = Spec::components();
        $defs = Schemas::definitions();
        $expectedDerived = [];
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

            if (!isset($c['properties']) && isset($c['allOf']) && Spec::refOf($c) !== null) {
                $res["$n alias (a definition that is only an allOf of one reference)"] = ($d === '@' . Spec::refOf($c)) ? true : json_encode($d);
                continue;
            }

            if (!isset($c['properties'])) {
                // an enum, a list or a text
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

                if (Spec::isOverlay($p) || (($p['type'] ?? '') === 'object' && isset($p['properties']))) {
                    $expectedDerived[str_replace('.', '_', "$n.$pn")] = true;
                } elseif (($p['type'] ?? '') === 'array' && ($p['items']['type'] ?? '') === 'object' && isset($p['items']['properties'])) {
                    $expectedDerived[str_replace('.', '_', "$n.$pn") . '_item'] = true;
                }
            }

            foreach ($undefined as $key) {
                $specKeys[$key] = false;
                $res["$n.$key (required, never defined)"] = (($d[$key . '?'] ?? null) === 'mixed') ? true : 'not an optional field of any type';
            }

            $res["$n keys and required flags"] = ($classKeys === $specKeys) ? true : 'class=' . json_encode($classKeys) . ' spec=' . json_encode($specKeys);

            foreach ($c['properties'] as $pn => $p) {
                $k = $pn . (in_array($pn, $required, true) ? '' : '?');

                if (!isset($d[$k])) {
                    continue;
                }

                $a = Spec::canonClass($d[$k]);
                $b = Spec::canonSpec($p, "$n.$pn");

                if ("$n.$pn" === 'AlcoholDetail.alcoholRecipientType') {
                    // the typo of the spec, corrected as in schemas/ship.json
                    $b = str_replace('"CONSUMERstomer"', '"CONSUMER"', $b);
                }

                $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
            }
        }

        self::$conformance = [$res, array_keys($expectedDerived)];

        return self::$conformance;
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has two hundred and twenty five definitions reachable from the twenty roots
     * @return void
     */
    public function testTheSpecHasTwoHundredAndTwentyFiveDefinitionsReachableFromTheTwentyRoots(): void
    {
        $this->assertCount(225, self::reachable());
    }

    /**
     * The root constants name the roots of the spec, the request bodies and the responses of the ten operations, and ROOTS
     * has all of them
     * @return void
     */
    public function testTheRootConstantsNameTheRootsOfTheSpecAndRootsHasAllOfThem(): void
    {
        $defs = Schemas::definitions();
        $paths = Spec::spec()['paths'];
        $names = [];

        foreach (Spec::ROOTS as $label => $root) {
            $constant = constant(Schemas::class . '::' . self::CONSTANTS[$label]);
            [$operation, $kind] = explode('.', $label);
            [$path, $verb] = self::PATHS[$operation];
            $spec = $paths[$path][$verb];
            $found = ($kind === 'request')
                ? basename($spec['requestBody']['content']['application/json']['schema']['oneOf'][0]['$ref'])
                : basename($spec['responses']['200']['content']['application/json']['schema']['$ref']);

            $this->assertSame($root, $found, "$label is the one of $verb $path");
            $this->assertSame(Spec::renamed($root), $constant, $label);
            $this->assertArrayHasKey($constant, $defs, $label);
            $names[] = $constant;
        }

        $this->assertSame($names, Schemas::ROOTS, 'ROOTS lists the constants in the order of the operations, a request and then its response');
        $this->assertCount(21, (new \ReflectionClass(Schemas::class))->getConstants(), 'the 20 roots and ROOTS: no other constant');
        $this->assertTrue((new \ReflectionClass(Schemas::class))->isFinal());
        $this->assertSame(10, array_sum(array_map('count', $paths)), 'ten operations');
    }

    /**
     * Every definition of the spec is in the class with the same shape
     * @return void
     */
    public function testEveryDefinitionOfTheSpecIsInTheClassWithTheSameShape(): void
    {
        $this->assertAll('every definition: same keys, same required keys, same types, enums and references as the spec', self::conformance()[0]);
    }

    /**
     * The class has the definitions of the spec and the derived ones
     * @return void
     */
    public function testTheClassHasTheDefinitionsOfTheSpecAndTheDerivedOnes(): void
    {
        $defs = Schemas::definitions();
        $extra = array_values(array_diff(array_keys($defs), array_map([Spec::class, 'renamed'], array_keys(self::reachable()))));
        $expected = self::conformance()[1];
        sort($extra);
        sort($expected);

        // the overlay of DeliveryOnInvoiceAcceptanceDetail.recipient has address and contact overlays of its own: they are
        // derived from the derived definition, which the walk over the spec does not reach
        $nested = ['DeliveryOnInvoiceAcceptanceDetail_recipient_address', 'DeliveryOnInvoiceAcceptanceDetail_recipient_contact'];

        $this->assertSame($expected, array_values(array_diff($extra, $nested)), 'the extra definitions are the derived ones');
        $this->assertSame($nested, array_values(array_intersect($extra, $nested)));
        $this->assertCount(230, $defs, 'the 225 definitions of the spec and 5 derived ones');
    }

    /**
     * Every reference resolves and every definition is used
     * @return void
     */
    public function testEveryReferenceResolvesAndEveryDefinitionIsUsed(): void
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
                    if ($key !== '@enum') {
                        $walk($value);
                    }
                }
            }
        };

        foreach ($defs as $definition) {
            $walk($definition);
        }

        $this->assertSame([], array_values(array_unique($unresolved)), 'every @reference resolves to a definition');
        $this->assertSame([], array_values(array_diff(array_keys($defs), array_keys($used), Schemas::ROOTS)), 'every definition is used or is a root');
    }

    /**
     * The validator accepts the definitions and their names
     * @return void
     */
    public function testTheValidatorAcceptsTheDefinitionsAndTheirNames(): void
    {
        foreach ([Schemas::definitions(), Schemas::shallowDefinitions()] as $defs) {
            new SchemaValidator(false, $defs);

            $this->assertSame([], array_values(array_filter(array_keys($defs), function ($name) {
                return !preg_match('/^[A-Za-z_]\w*$/D', $name);
            })), 'there is no name that the validator would refuse');
        }
    }

    /**
     * The facts of the spec that the differences rest on are still in the spec
     * @return void
     */
    public function testTheFactsOfTheSpecThatTheDifferencesRestOnAreStillInTheSpec(): void
    {
        $C = Spec::components();

        $this->assertContains('trackingId', $C['FullSchema-RetrieveOpenShipment']['required']);
        $this->assertArrayNotHasKey('trackingId', $C['FullSchema-RetrieveOpenShipment']['properties'], 'the field that is required is never defined');
        $this->assertSame('mixed', Schemas::definitions()[Schemas::RETRIEVE_REQUEST]['trackingId?']);
        $this->assertSame(['LICENSEE', 'CONSUMERstomer'], $C['AlcoholDetail']['properties']['alcoholRecipientType']['enum'], 'the typo of the spec');
        $this->assertSame(['LICENSEE', 'CONSUMER'], json_decode(file_get_contents(__DIR__ . '/../../../../schemas/ship.json'), true)['components']['schemas']['AlcoholDetail']['properties']['alcoholRecipientType']['enum'], 'the values of the Ship API');
        $this->assertSame(['LICENSEE', 'CONSUMER'], Schemas::definitions()['AlcoholDetail']['alcoholRecipientType?']['@enum']);

        foreach (array_keys(Spec::SAMPLES) as $sample) {
            $this->assertSame(['example'], array_keys($C[$sample]), "$sample is only an example, so the oneOf says nothing");
        }

        foreach (Spec::HYPHENATED as $name) {
            $this->assertArrayHasKey($name, $C);
        }
    }

    // ---------------------------------------------------------------- the definitions of the top level

    /**
     * The definitions of the top level have the roots, with the same fields and the same required fields
     * @return void
     */
    public function testTheDefinitionsOfTheTopLevelHaveTheRootsWithTheSameFieldsAndTheSameRequiredFields(): void
    {
        $full = Schemas::definitions();
        $shallow = Schemas::shallowDefinitions();
        $res = [];

        $this->assertSame(Schemas::ROOTS, array_keys($shallow));

        foreach (Schemas::ROOTS as $root) {
            $res["$root fields and required flags"] = (array_keys($shallow[$root]) === array_keys($full[$root])) ? true : 'the keys differ';
        }

        $this->assertAll('the roots of the definitions of the top level', $res);
    }

    /**
     * A field of the top level that holds an object is an object, a list is a list, and the others are as in the whole
     * @return void
     */
    public function testAFieldOfTheTopLevelThatHoldsAnObjectIsAnObjectAListIsAListAndTheOthersAreAsInTheWhole(): void
    {
        $full = Schemas::definitions();
        $shallow = Schemas::shallowDefinitions();
        $res = [];

        foreach (Schemas::ROOTS as $root) {
            foreach ($full[$root] as $key => $node) {
                $name = rtrim($key, '?');
                $got = $shallow[$root][$key];

                if (is_string($node) && $node[0] === '@') {
                    $target = $node;

                    while (is_string($target) && $target !== '' && $target[0] === '@') {
                        $target = $full[substr($target, 1)];
                    }

                    $expected = is_string($target) ? $target : (isset($target['@type']) ? $target : ((array_keys($target) === [0]) ? 'array' : 'object'));
                } elseif (is_array($node) && !isset($node['@type'])) {
                    $expected = 'array';
                } else {
                    $expected = $node;
                }

                $res["$root.$name"] = ($got === $expected) ? true : 'got ' . json_encode($got) . ', expected ' . json_encode($expected);
            }
        }

        $this->assertAll('the fields of the top level', $res);
        $this->assertSame('object', $shallow[Schemas::CREATE_REQUEST]['requestedShipment']);
        $this->assertSame('object', $shallow[Schemas::CREATE_REQUEST]['accountNumber'], 'an alias of an alias of an object is an object');
        $this->assertSame('object', $shallow[Schemas::CONFIRM_REQUEST]['labelSpecification']);
        $this->assertSame(['@type' => 'string', '@enum' => ['URL_ONLY', 'LABEL']], $shallow[Schemas::CONFIRM_REQUEST]['labelResponseOptions']);
        $this->assertSame('array', $shallow[Schemas::ADD_PACKAGES_REQUEST]['requestedPackageLineItems']);
        $this->assertSame(['@type' => 'string', '@enum' => ['CREATE', 'MODIFY', 'CONFIRM']], $shallow[Schemas::RESULTS_REQUEST]['resultMethodType']);
    }

    /**
     * The sample payloads of the spec are accepted by the definitions of the top level; the whole of the schema refuses the
     * sample of the packages, which sends its numbers as text, unless the scalars are loose (as when a request is sent)
     * @return void
     */
    public function testTheSamplePayloadsOfTheSpecAreAcceptedByTheTopLevel(): void
    {
        $results = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = json_decode(json_encode(Spec::components()[$sample]['example']), true);
            $root = '@' . Spec::renamed(Spec::ROOTS[$label]);
            $top = new SchemaValidator(false, Schemas::shallowDefinitions());
            $loose = (new SchemaValidator(false, Schemas::definitions()))->setLooseScalars(true);
            $results["$sample top level"] = $top->validate($example, $root) ? true : implode(' | ', $top->getErrors());
            $results["$sample whole, loose"] = $loose->validate($example, $root) ? true : implode(' | ', $loose->getErrors());
        }

        $strict = new SchemaValidator(false, Schemas::definitions());
        $example = json_decode(json_encode(Spec::components()['MinimumSamplePayload-addPackagesInOpenShipment']['example']), true);
        $results['the sample of the packages refused by the whole, strict'] = $strict->validate($example, '@' . Schemas::ADD_PACKAGES_REQUEST) ? 'accepted' : true;

        $this->assertCount(5, $results);
        $this->assertAll('the sample payloads', $results);
    }

    /**
     * The top level refuses a missing required field, a wrong type and a value outside an enum, and does not look inside
     * an object
     * @return void
     */
    public function testTheTopLevelRefusesAMissingRequiredFieldAWrongTypeAndAValueOutsideAnEnum(): void
    {
        $example = json_decode(json_encode(Spec::components()['MinimumSamplePayload-confirmOpenShipment']['example']), true);
        $validator = new SchemaValidator(false, Schemas::shallowDefinitions());
        $root = '@' . Schemas::CONFIRM_REQUEST;

        $this->assertTrue($validator->validate($example, $root));

        foreach (['accountNumber', 'index', 'labelResponseOptions', 'labelSpecification'] as $key) {
            $copy = $example;
            unset($copy[$key]);

            $this->assertFalse($validator->validate($copy, $root), "without $key");
        }

        $this->assertFalse($validator->validate($example + ['notInTheSchema' => 1], $root));
        $this->assertFalse($validator->validate(['labelSpecification' => 'text'] + $example, $root), 'a text where an object is');
        $this->assertFalse($validator->validate(['labelResponseOptions' => 'PDF'] + $example, $root), 'outside the enum');
        $this->assertFalse($validator->validate(['index' => ['x']] + $example, $root), 'a list where a text is');
        $this->assertTrue($validator->validate(['labelSpecification' => ['anything' => ['at' => 'all']]] + $example, $root), 'what is inside an object is not checked');
    }

    /**
     * The top level of the responses accepts a field that FedEx adds and checks the fields it knows
     * @return void
     */
    public function testTheTopLevelOfTheResponsesAcceptsAFieldThatFedexAddsAndChecksTheFieldsItKnows(): void
    {
        $validator = new SchemaValidator(true, Schemas::shallowDefinitions());

        foreach ([Schemas::CREATE_RESPONSE, Schemas::CONFIRM_RESPONSE, Schemas::RESULTS_RESPONSE] as $root) {
            $this->assertTrue($validator->validate(['transactionId' => 'T', 'output' => ['anything' => 1], 'addedByFedex' => true], '@' . $root), $root);
            $this->assertFalse($validator->validate(['transactionId' => 5], '@' . $root), "$root: the transaction id is text");
            $this->assertFalse($validator->validate(['output' => 'text'], '@' . $root), "$root: the output is an object");
        }
    }

    // ---------------------------------------------------------------- instances generated from the spec

    /**
     * The cases of the tests of the instances generated from the spec
     * @return array
     */
    public function rootsProvider(): array
    {
        $cases = [];

        foreach (Spec::ROOTS as $label => $root) {
            $cases[$label] = [$label, $root, substr($label, -7) === 'request'];
        }

        return $cases;
    }

    /**
     * Does the validator accept the data as an instance of the root? Requests are strict (unknown keys are errors), responses are not
     * @param array $data The data
     * @param string $root The root, as named in the spec
     * @param bool $isRequest Is it a request?
     * @return bool
     */
    private static function accepts(array $data, string $root, bool $isRequest): bool
    {
        if (self::$validators === null) {
            // building a validator with the definitions is not free, and thousands of cases use it: one per mode
            self::$validators = [
                true => new SchemaValidator(false, Schemas::definitions()),
                false => new SchemaValidator(true, Schemas::definitions()),
            ];
        }

        return self::$validators[$isRequest]->validate($data, '@' . Spec::renamed($root));
    }

    /**
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root, as named in the spec
     * @param bool $isRequest Is it a request?
     */
    public function testTheInstanceGeneratedFromTheSpecIsAcceptedByTheWholeAndByTheTopLevel(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $validator = new SchemaValidator(!$isRequest, Schemas::definitions());
        $top = new SchemaValidator(!$isRequest, Schemas::shallowDefinitions());

        $this->assertTrue($validator->validate($instance, '@' . Spec::renamed($root)), implode(' | ', array_slice($validator->getErrors(), 0, 3)));
        $this->assertTrue($top->validate($instance, '@' . Spec::renamed($root)), implode(' | ', array_slice($top->getErrors(), 0, 3)));
        $this->assertNotEmpty($meta['leaves']);
        $this->assertNotEmpty($meta['objects']);
    }

    /**
     * It validates large instances thousands of times. Leave it out with --exclude-group slow
     * @group slow
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root, as named in the spec
     * @param bool $isRequest Is it a request?
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
                $values = $leaf['enum'];

                if (in_array('CONSUMERstomer', $values, true)) {
                    // the typo of the spec, corrected as in schemas/ship.json
                    $values = ['LICENSEE', 'CONSUMER'];
                    $res["$key the typo of the spec is refused"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], 'CONSUMERstomer'), $root, $isRequest) ? 'accepted' : true;
                }

                $res["$key bad enum"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], 'NOT_A_VALUE'), $root, $isRequest) ? 'accepted' : true;
                $all = true;

                foreach ($values as $value) {
                    $all = $all && self::accepts(SpecInstances::setAt($instance, $leaf['path'], $value), $root, $isRequest);
                }

                $res["$key every enum value accepted"] = $all ? true : 'one refused';
            }

            if ($leaf['max'] !== null && $leaf['enum'] === null) {
                $res["$key at the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], str_repeat('x', $leaf['max'])), $root, $isRequest) ? true : 'refused at the limit';
                $res["$key over the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], str_repeat('x', $leaf['max'] + 1)), $root, $isRequest) ? 'accepted' : true;
            }
        }

        $this->assertAll("$label: the leaves (" . count($meta['leaves']) . ')', $res);
    }

    /**
     * It validates large instances thousands of times. Leave it out with --exclude-group slow
     * @group slow
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root, as named in the spec
     * @param bool $isRequest Is it a request?
     */
    public function testEveryRequiredKeyIsEnforcedEveryOptionalKeyIsOptionalAndUnknownKeysAreRefusedOnlyInRequests(string $label, string $root, bool $isRequest): void
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
