<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\FedexConsolidationSchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\ConsolidationSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Consolidation API. They are compared with schemas/consolidation.json, which is read here
 * independently of the code that generated them, and an instance of each of the eighteen roots is generated from that file
 * and broken one field at a time. The differences from the file are listed one by one
 */
final class FedexConsolidationSchemasTest extends TestCase
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
        'createshipment.request' => 'CREATE_SHIPMENT_REQUEST', 'createshipment.response' => 'CREATE_SHIPMENT_RESPONSE',
        'results.request' => 'SHIPMENT_RESULTS_REQUEST', 'results.response' => 'SHIPMENT_RESULTS_RESPONSE',
        'confirm.request' => 'CONFIRM_REQUEST', 'confirm.response' => 'CONFIRM_RESPONSE',
        'confirmresults.request' => 'CONFIRM_RESULTS_REQUEST', 'confirmresults.response' => 'CONFIRM_RESULTS_RESPONSE',
        'retrieve.request' => 'RETRIEVE_REQUEST', 'retrieve.response' => 'RETRIEVE_RESPONSE',
        'deleteshipments.request' => 'DELETE_SHIPMENTS_REQUEST', 'deleteshipments.response' => 'DELETE_SHIPMENTS_RESPONSE',
        'delete.request' => 'DELETE_REQUEST', 'delete.response' => 'DELETE_RESPONSE',
    ];

    /**
     * The sample payloads of the spec that pass the whole of the schema (the other sixteen contradict it in nested objects)
     * @var string[]
     */
    private const SAMPLES_THAT_PASS_THE_WHOLE_SCHEMA = [
        'MinimumSamplePayload-ConsolidationShipmentResults',
        'MinimumSamplePayload-confirmConsolidationResults',
        'MinimumSamplePayload-DeleteShipment',
    ];

    /**
     * The names that the eighteen roots have in the spec, with the names of the definitions reachable from them
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

                if (isset($d[$k])) {
                    $a = Spec::canonClass($d[$k]);
                    $b = Spec::canonSpec($p, "$n.$pn");
                    $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
                }
            }
        }

        self::$conformance = [$res, array_keys($expectedDerived)];

        return self::$conformance;
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has three hundred and thirty six definitions reachable from the eighteen roots
     * @return void
     */
    public function testTheSpecHasThreeHundredAndThirtySixDefinitionsReachableFromTheEighteenRoots(): void
    {
        $this->assertCount(336, self::reachable());
    }

    /**
     * The root constants name the roots of the spec, and ROOTS has all of them
     * @return void
     */
    public function testTheRootConstantsNameTheRootsOfTheSpecAndRootsHasAllOfThem(): void
    {
        $defs = Schemas::definitions();
        $names = [];

        foreach (Spec::ROOTS as $label => $root) {
            $constant = constant(Schemas::class . '::' . self::CONSTANTS[$label]);

            $this->assertSame(Spec::renamed($root), $constant, $label);
            $this->assertArrayHasKey($constant, $defs, $label);
            $names[] = $constant;
        }

        $this->assertSame($names, Schemas::ROOTS, 'ROOTS lists the constants in the order of the operations, a request and then its response');
        $this->assertCount(19, (new \ReflectionClass(Schemas::class))->getConstants(), 'the 18 roots and ROOTS: no other constant');
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

        $this->assertSame($expected, $extra, 'the extra definitions are exactly the derived ones');
        $this->assertCount(342, $defs, 'the 336 definitions of the spec and the derived ones');
        $this->assertCount(6, $expected);
    }

    /**
     * The derived overlays are the base with the required keys of the overlay
     * @return void
     */
    public function testTheDerivedOverlaysAreTheBaseWithTheRequiredKeysOfTheOverlay(): void
    {
        $C = Spec::components();
        $defs = Schemas::definitions();
        $compare = function (string $name, string $base, array $overlayRequired) use ($C, $defs) {
            $expected = array_values(array_unique(array_merge($C[$base]['required'] ?? [], $overlayRequired)));
            $d = $defs[$name] ?? null;

            if ($d === null) {
                return 'missing';
            }

            $required = [];
            $keys = [];

            foreach ($d as $k => $v) {
                $optional = substr($k, -1) === '?';
                $key = $optional ? substr($k, 0, -1) : $k;
                $keys[] = $key;

                if (!$optional) {
                    $required[] = $key;
                }
            }

            sort($required);
            sort($expected);

            return ($required === $expected && $keys === array_keys($C[$base]['properties'])) ? true : 'required ' . json_encode($required) . ' keys ' . json_encode($keys);
        };

        $this->assertAll('the derived overlays: the properties of the base, the required keys of the base plus those of the overlay', [
            'BrokerDetail_broker' => $compare('BrokerDetail_broker', 'Party_1', ['address', 'contact']),
            'BrokerDetail_1_broker' => $compare('BrokerDetail_1_broker', 'Party_1_1', ['address', 'contact']),
            'VariableHandlingChargeDetail_fixedValue' => $compare('VariableHandlingChargeDetail_fixedValue', 'Money', ['amount', 'currency']),
        ]);
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

        $unused = array_values(array_diff(array_keys($defs), array_keys($used), Schemas::ROOTS));
        sort($unused);

        $this->assertSame([], array_values(array_unique($unresolved)), 'every @reference resolves to a definition');
        // Party_1_1 is the base of the overlay that BrokerDetail_1_broker is made from, and ServiceCategory is the second text of the allOf that
        // the items of the inclusion specifications are made from: the spec defines them, nothing refers to them
        $this->assertSame(['Party_1_1', 'ServiceCategory'], $unused, 'every definition is used by another one or is one of the eighteen roots, apart from these two');
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

        $this->assertContains('recipient', $C['RequestedConsolidationShipment']['required']);
        $this->assertArrayNotHasKey('recipient', $C['RequestedConsolidationShipment']['properties'], 'the field that is required is never defined');
        $this->assertArrayHasKey('recipients', $C['RequestedConsolidationShipment']['properties'], 'the field that is defined is another');
        $this->assertNotContains('recipients', $C['RequestedConsolidationShipment']['required']);
        $this->assertCount(1, $C['Label']['properties']['type']['enum'], 'Label.type has one value');
        $this->assertStringContainsString(' - ', $C['Label']['properties']['type']['enum'][0], 'which is a list of values joined in a text');
        $this->assertCount(1, $C['ShippingDocument']['properties']['type']['enum']);
        $this->assertSame('string', $C['CarrierCode']['type']);
        $this->assertSame('string', $C['ServiceCategory']['type']);
        $this->assertArrayNotHasKey('enum', $C['CarrierCode']);
        $this->assertArrayNotHasKey('enum', $C['ServiceCategory']);
        $this->assertCount(2, $C['DistributionLocations']['properties']['inclusionSpecifications']['items']['allOf']);
        $this->assertContains('FullSchema-DeleteShipment', array_keys($C));

        foreach (['v1_consolidations_body', 'v1_consolidations_body_1', 'consolidations_shipments_body'] as $body) {
            foreach (array_slice($C[$body]['oneOf'], 1) as $sample) {
                $schema = $C[basename($sample['$ref'])];

                $this->assertSame(['example'], array_keys($schema), basename($sample['$ref']) . ' is only an example, so the oneOf says nothing');
            }
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
        $this->assertSame('object', $shallow[Schemas::CONFIRM_REQUEST]['consolidationKey']);
        $this->assertSame('object', $shallow[Schemas::CONFIRM_REQUEST]['labelSpecification']);
        $this->assertSame('array', $shallow[Schemas::CREATE_SHIPMENT_REQUEST]['processingOptions?']);
        $this->assertSame('object', $shallow[Schemas::RETRIEVE_REQUEST]['consolidationKey']);
        $this->assertSame('object', $shallow[Schemas::RETRIEVE_REQUEST]['accountNumber'], 'an alias of an alias of an object is an object');
    }

    /**
     * The sample payloads of the spec are all accepted by the definitions of the top level
     * @return void
     */
    public function testTheSamplePayloadsOfTheSpecAreAllAcceptedByTheDefinitionsOfTheTopLevel(): void
    {
        $results = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = json_decode(json_encode(Spec::components()[$sample]['example']), true);
            $validator = new SchemaValidator(false, Schemas::shallowDefinitions());
            $results[$sample] = $validator->validate($example, '@' . Spec::renamed(Spec::ROOTS[$label])) ? true : implode(' | ', array_slice($validator->getErrors(), 0, 4));
        }

        $this->assertCount(19, $results);
        $this->assertAll('the sample payloads against the top level', $results);
    }

    /**
     * Only three of the sample payloads pass the whole of the schema: the spec contradicts itself in the nested objects
     * @return void
     */
    public function testOnlyThreeOfTheSamplePayloadsPassTheWholeOfTheSchema(): void
    {
        $accepted = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = json_decode(json_encode(Spec::components()[$sample]['example']), true);
            $validator = new SchemaValidator(false, Schemas::definitions());

            if ($validator->validate($example, '@' . Spec::renamed(Spec::ROOTS[$label]))) {
                $accepted[] = $sample;
            }
        }

        $this->assertSame(self::SAMPLES_THAT_PASS_THE_WHOLE_SCHEMA, $accepted, 'if FedEx corrects the spec this list grows: the class could then check more');
    }

    /**
     * The top level refuses what the spec's samples never have: a missing required field, an unknown field, a wrong type
     * @return void
     */
    public function testTheTopLevelRefusesAMissingRequiredFieldAnUnknownFieldAndAWrongType(): void
    {
        $example = json_decode(json_encode(Spec::components()['MinimumSamplePayload-deleteConsolidation']['example']), true);
        $validator = new SchemaValidator(false, Schemas::shallowDefinitions());
        $root = '@' . Schemas::DELETE_REQUEST;

        $this->assertTrue($validator->validate($example, $root));

        foreach (['accountNumber', 'consolidationKey', 'trackingId'] as $key) {
            $copy = $example;
            unset($copy[$key]);

            $this->assertFalse($validator->validate($copy, $root), "without $key");
        }

        $this->assertFalse($validator->validate($example + ['notInTheSchema' => 1], $root));
        $this->assertFalse($validator->validate(['consolidationKey' => 'text'] + $example, $root), 'a text where an object is');
        $this->assertFalse($validator->validate(['trackingId' => [1, 2]] + $example, $root), 'a list where an object is');
        $this->assertTrue($validator->validate(['trackingId' => ['anything' => ['at' => 'all']]] + $example, $root), 'what is inside an object is not checked');
    }

    /**
     * The top level of the responses accepts a field that FedEx adds and checks the fields it knows
     * @return void
     */
    public function testTheTopLevelOfTheResponsesAcceptsAFieldThatFedexAddsAndChecksTheFieldsItKnows(): void
    {
        $validator = new SchemaValidator(true, Schemas::shallowDefinitions());

        foreach ([Schemas::CREATE_RESPONSE, Schemas::RETRIEVE_RESPONSE, Schemas::DELETE_SHIPMENTS_RESPONSE] as $root) {
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
     * About 20 seconds: it validates large instances thousands of times. Leave it out with --exclude-group slow
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
                $name = $this->definitionOf($label, $leaf['path']);

                if (in_array($name, Spec::TEXT_ENUMS, true)) {
                    $res["$key is text, not the enum of the spec"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], 'NOT_A_VALUE'), $root, $isRequest) ? true : 'refused';
                    continue;
                }

                $res["$key bad enum"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], is_bool($leaf['enum'][0]) ? 'NOT_A_VALUE' : 'NOT_A_VALUE'), $root, $isRequest) ? 'accepted' : true;
                $all = true;

                foreach ($leaf['enum'] as $value) {
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
     * The definition and the field of a leaf, in the form of ConsolidationSpec::TEXT_ENUMS
     * @param string $label The root
     * @param array $path The path of the leaf in the instance
     * @return string
     */
    private function definitionOf(string $label, array $path): string
    {
        $components = Spec::components();
        $definition = Spec::original(Spec::renamed(Spec::ROOTS[$label]));
        $field = '';

        foreach ($path as $key) {
            if (is_int($key)) {
                continue;
            }

            while (!isset($components[$definition]['properties']) && (Spec::refOf($components[$definition]) !== null)) {
                $definition = Spec::original(Spec::refOf($components[$definition]));
            }

            $property = $components[$definition]['properties'][$key] ?? null;
            $field = $key;
            $ref = ($property === null) ? null : Spec::refOf($property);

            if ($ref === null && $property !== null && ($property['type'] ?? '') === 'array') {
                $ref = Spec::refOf($property['items']);
            }

            if ($ref === null) {
                return "$definition.$field";
            }

            $definition = Spec::original($ref);
        }

        return "$definition.$field";
    }

    /**
     * About 15 seconds: it validates large instances thousands of times. Leave it out with --exclude-group slow
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
