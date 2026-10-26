<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexPickupSchemas as Schemas;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\PickupSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Pickup Request API. They are compared with schemas/pickup.json, which is read here independently
 * of the code that generated them, and an instance of each of the six roots is generated from that file and broken one field at a
 * time. The differences from the file are listed one by one
 */
final class FedexPickupSchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The result of comparing the definitions with the spec: the cases, and the derived definitions that the spec calls for
     * @var array|null
     */
    private static $conformance;

    /**
     * The constants of the class for each root of the spec
     * @var string[]
     */
    private const CONSTANTS = [
        'create.request' => 'CREATE_REQUEST', 'create.response' => 'CREATE_RESPONSE',
        'availability.request' => 'AVAILABILITY_REQUEST', 'availability.response' => 'AVAILABILITY_RESPONSE',
        'cancel.request' => 'CANCEL_REQUEST', 'cancel.response' => 'CANCEL_RESPONSE',
    ];

    /**
     * The fields that the spec requires and the class makes optional, because the minimum fields of an address vary among
     * countries and the examples of FedEx leave them out: definition.field
     * @var string[]
     */
    private const OPTIONAL_BY_DECISION = ['PickupAddress.postalCode', 'PickupLocationParty_address_overlay.stateOrProvinceCode'];

    /**
     * The definitions that are used by no other definition and are not a root: the base of a definition that is made of a base and an
     * overlay
     * @var string[]
     */
    private const UNUSED = ['Party_createPickup', 'PickupLocationParty_address', 'PickupLocationParty_contact'];

    /**
     * The names of the definitions reachable from the six roots
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
     * The definition of the spec that a name stands for, with an alias followed to the end
     * @param string $name The name
     * @return string
     */
    private static function resolved(string $name): string
    {
        $components = Spec::components();

        while (!isset($components[$name]['properties']) && Spec::refOf($components[$name]) !== null) {
            $name = Spec::refOf($components[$name]);
        }

        return $name;
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

            if (!isset($defs[$n])) {
                $res["$n exists"] = 'missing in the class';
                continue;
            }

            $d = $defs[$n];

            if (isset($c['$ref'])) {
                $res["$n alias"] = ($d === '@' . basename($c['$ref'])) ? true : 'not the alias of ' . $c['$ref'];
                continue;
            }

            if (Spec::isOverlay($c)) {
                // the base with the required keys of the overlays, and the properties that only an overlay has
                $baseName = self::resolved(basename($c['allOf'][0]['$ref']));
                $overlay = Spec::mergedOverlay($c);
                $props = $C[$baseName]['properties'];
                $required = array_values(array_unique(array_merge($C[$baseName]['required'] ?? [], $overlay['required'])));

                foreach ($overlay['properties'] as $pn => $op) {
                    if (!isset($props[$pn])) {
                        $props[$pn] = $op;
                    } elseif (isset($op['$ref']) && !isset($C[basename($op['$ref'])]['type'])) {
                        $expectedDerived["{$n}_{$pn}_overlay"] = true;
                        $props[$pn] = ['$ref' => '#/components/schemas/' . "{$n}_{$pn}_overlay"];
                    }
                }

                $c = ['properties' => $props, 'required' => $required];
                $res["$n is the base $baseName with the overlay"] = true;
            } elseif (!isset($c['properties']) && isset($c['allOf']) && Spec::refOf($c) !== null) {
                $res["$n alias (a definition that is only an allOf of one reference)"] = ($d === '@' . Spec::refOf($c)) ? true : json_encode($d);
                continue;
            } elseif (!isset($c['properties'])) {
                // an enum, a list or a text
                $res["$n scalar or list"] = (Spec::canonClass($d) === Spec::canonSpec($c, $n)) ? true : Spec::canonClass($d) . ' != ' . Spec::canonSpec($c, $n);
                continue;
            }

            $required = $c['required'] ?? [];
            $undefined = array_values(array_diff($required, array_keys($c['properties'])));
            $classKeys = [];

            foreach ($d as $k => $v) {
                $classKeys[substr($k, -1) === '?' ? substr($k, 0, -1) : $k] = substr($k, -1) !== '?';
            }

            $specKeys = [];

            foreach ($c['properties'] as $pn => $p) {
                $specKeys[$pn] = in_array($pn, $required, true) && !in_array("$n.$pn", self::OPTIONAL_BY_DECISION, true);

                // an overlay that only has a description changes nothing: the property is the definition that it refers to
                if (Spec::isOverlay($p) && (Spec::mergedOverlay($p)['required'] !== [] || Spec::mergedOverlay($p)['properties'] !== [])) {
                    $expectedDerived[str_replace('.', '_', "$n.$pn")] = true;
                } elseif (($p['type'] ?? '') === 'object' && isset($p['properties'])) {
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
                $k = $pn . ((in_array($pn, $required, true) && !in_array("$n.$pn", self::OPTIONAL_BY_DECISION, true)) ? '' : '?');

                if (!isset($d[$k])) {
                    continue;
                }

                $a = Spec::canonClass($d[$k]);
                $b = Spec::canonSpec($p, "$n.$pn");

                // two samples of the spec send a list with one object where the schema says object: the class takes an object or a list
                if ("$n.$pn" === 'Full_Schema_Pickup_Availability.shipmentAttributes') {
                    $b .= '|array';
                }

                $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
            }
        }

        // the overlay of the responsible party of the payor tightens its account number too: a definition of its own
        $expectedDerived['Payor_responsibleParty_accountNumber'] = true;

        self::$conformance = [$res, array_keys($expectedDerived)];

        return self::$conformance;
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has forty three definitions reachable from the six roots
     * @return void
     */
    public function testTheSpecHasFortyThreeDefinitionsReachableFromTheSixRoots(): void
    {
        $this->assertCount(43, self::reachable());
    }

    /**
     * The root constants name the roots of the spec
     * @return void
     */
    public function testTheRootConstantsNameTheRootsOfTheSpec(): void
    {
        $defs = Schemas::definitions();

        foreach (Spec::ROOTS as $label => $root) {
            $constant = constant(Schemas::class . '::' . self::CONSTANTS[$label]);

            $this->assertSame($root, $constant, $label);
            $this->assertArrayHasKey($constant, $defs, $label);
        }

        $this->assertCount(6, (new \ReflectionClass(Schemas::class))->getConstants(), 'the six roots: no other constant');
        $this->assertTrue((new \ReflectionClass(Schemas::class))->isFinal());
    }

    /**
     * The roots are the requests and responses of the three paths of the spec
     * @return void
     */
    public function testTheRootsAreTheRequestsAndResponsesOfTheThreePathsOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];
        $operations = [
            '/pickup/v1/pickups' => ['post', 'create', 'body'],
            '/pickup/v1/pickups/availabilities' => ['post', 'availability', 'body_1'],
            '/pickup/v1/pickups/cancel' => ['put', 'cancel', 'body_2'],
        ];

        $this->assertSame(array_keys($operations), array_keys($paths));

        foreach ($operations as $path => [$verb, $label, $body]) {
            $operation = $paths[$path][$verb];

            $this->assertSame([$verb], array_keys($paths[$path]), "$path has only $verb");
            $this->assertSame('#/components/schemas/' . $body, $operation['requestBody']['content']['application/json']['schema']['$ref'], $path);
            $this->assertSame('#/components/schemas/' . Spec::ROOTS[$label . '.request'], Spec::components()[$body]['oneOf'][0]['$ref'], "$path: the real schema is the first of the oneOf");
            $this->assertSame('#/components/schemas/' . Spec::ROOTS[$label . '.response'], $operation['responses']['200']['content']['application/json']['schema']['$ref'] ?? $operation['responses']['200']['$ref'], $path);
        }
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
        $extra = array_values(array_diff(array_keys($defs), array_keys(self::reachable())));
        $expected = self::conformance()[1];
        sort($extra);
        sort($expected);

        $this->assertSame($expected, $extra, 'the extra definitions are exactly the derived ones');
        $this->assertSame(['Payor_responsibleParty', 'Payor_responsibleParty_accountNumber', 'PickupLocationParty_address_overlay', 'PickupLocationParty_contact_overlay'], $extra);
        $this->assertCount(47, $defs);
    }

    /**
     * The definitions that are made of a base and an overlay have the keys that the overlay requires
     * @return void
     */
    public function testTheDefinitionsThatAreMadeOfABaseAndAnOverlayHaveTheKeysThatTheOverlayRequires(): void
    {
        $defs = Schemas::definitions();
        $required = function (string $name) use ($defs) {
            $keys = [];

            foreach ($defs[$name] as $key => $node) {
                if (substr($key, -1) !== '?') {
                    $keys[] = $key;
                }
            }

            sort($keys);

            return $keys;
        };

        $this->assertSame(['countryCode'], $required('PickupAddress'), 'the postal code is optional (see OPTIONAL_BY_DECISION)');
        $this->assertSame(['address', 'contact'], $required('PickupLocationParty'));
        $this->assertSame(['city', 'countryCode', 'postalCode', 'streetLines'], $required('PickupLocationParty_address_overlay'), 'the state is optional (see OPTIONAL_BY_DECISION)');
        $this->assertSame([], $required('PickupLocationParty_contact_overlay'));
        $this->assertSame('@PickupLocationParty_address_overlay', $defs['PickupLocationParty']['address']);
        $this->assertSame('@PickupLocationParty_contact_overlay', $defs['PickupLocationParty']['contact']);
    }

    /**
     * The minimum length of the street lines is not checked, and the defaultLatestTimeOptions, which has no type in the spec, takes any value
     * @return void
     */
    public function testTheMinimumLengthOfTheStreetLinesIsNotCheckedAndTheDefaultLatestTimeOptionsTakesAnyValue(): void
    {
        $C = Spec::components();
        $validator = new SchemaValidator(false, Schemas::definitions());

        $this->assertArrayHasKey('minLength', $C['Address']['properties']['streetLines']['items'], 'the spec has a minimum length');
        $this->assertArrayNotHasKey('type', $C['PickupScheduleOption']['properties']['defaultLatestTimeOptions']);
        $this->assertSame('mixed', Schemas::definitions()['PickupScheduleOption']['defaultLatestTimeOptions?']);

        foreach ([[''], ['a']] as $lines) {
            $this->assertTrue($validator->validate(['streetLines' => $lines], '@Address'), json_encode($lines));
        }

        foreach ([null, 1, 'x', ['a'], ['k' => 'v']] as $value) {
            $this->assertTrue($validator->validate(['defaultLatestTimeOptions' => $value], '@PickupScheduleOption'), json_encode($value));
        }
    }

    /**
     * Every reference resolves, and the definitions that nothing uses are the ones that the overlays leave
     * @return void
     */
    public function testEveryReferenceResolvesAndTheDefinitionsThatNothingUsesAreTheOnesThatTheOverlaysLeave(): void
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

        $unused = array_values(array_diff(array_keys($defs), array_keys($used), array_values(Spec::ROOTS)));
        sort($unused);

        $this->assertSame([], array_values(array_unique($unresolved)), 'every @reference resolves to a definition');
        $this->assertSame(self::UNUSED, $unused);
    }

    /**
     * The validator accepts the definitions and their names
     * @return void
     */
    public function testTheValidatorAcceptsTheDefinitionsAndTheirNames(): void
    {
        $defs = Schemas::definitions();

        new SchemaValidator(false, $defs);

        $this->assertSame([], array_values(array_filter(array_keys($defs), function ($name) {
            return !preg_match('/^[A-Za-z_]\w*$/D', $name);
        })), 'there is no name that the validator would refuse');
    }

    /**
     * The facts of the spec that the differences rest on are still in the spec
     * @return void
     */
    public function testTheFactsOfTheSpecThatTheDifferencesRestOnAreStillInTheSpec(): void
    {
        $C = Spec::components();

        foreach (['body', 'body_1', 'body_2'] as $body) {
            foreach (array_slice($C[$body]['oneOf'], 1) as $sample) {
                $this->assertSame(['example'], array_keys($C[basename($sample['$ref'])]), basename($sample['$ref']) . ' is only an example, so the oneOf says nothing');
            }
        }

        $this->assertCount(8, Spec::SAMPLES);
    }

    // ---------------------------------------------------------------- the samples and the instances

    /**
     * All the sample payloads of the spec are accepted, strictly (the definitions are the ones of the spec)
     * @return void
     */
    public function testAllTheSamplePayloadsOfTheSpecAreAccepted(): void
    {
        $results = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = FedexUtils::pruneEmpty(json_decode(json_encode(Spec::components()[$sample]['example']), true));
            $validator = new SchemaValidator(false, Schemas::definitions());
            $results[$sample] = $validator->validate($example, '@' . Spec::ROOTS[$label]) ? true : implode(' | ', array_slice($validator->getErrors(), 0, 4));
        }

        $this->assertAll('the sample payloads', $results);
    }

    /**
     * The shipment attributes are an object or a list, because two samples of the spec send a list where the schema says object
     * @return void
     */
    public function testTheShipmentAttributesAreAnObjectOrAListBecauseTwoSamplesOfTheSpecSendAList(): void
    {
        $C = Spec::components();
        $validator = new SchemaValidator(false, Schemas::definitions());
        $base = FedexUtils::pruneEmpty(json_decode(json_encode($C['Check_Pickup_Availability_Domestic_Ground']['example']), true));
        $results = [];

        foreach (['Check_Pickup_Availability_Domestic_Express', 'Check_Pickup_Availability_International_Express'] as $sample) {
            $this->assertSame([0], array_keys($C[$sample]['example']['shipmentAttributes']), "$sample sends a list with one object");
        }

        foreach ([['serviceType' => 'FEDEX_FIRST'], [['serviceType' => 'FEDEX_FIRST']], [['serviceType' => 'x'], ['serviceType' => 'y']]] as $value) {
            $request = ['shipmentAttributes' => $value] + $base;
            $results[json_encode($value)] = $validator->validate($request, '@Full_Schema_Pickup_Availability') ? true : implode(' | ', $validator->getErrors());
        }

        foreach (['text', 5, true] as $value) {
            $request = ['shipmentAttributes' => $value] + $base;
            $results['refused ' . json_encode($value)] = $validator->validate($request, '@Full_Schema_Pickup_Availability') ? 'accepted' : true;
        }

        $this->assertAll('the shipment attributes', $results);
    }

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
     * @param string $root The root
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
     * @param string $root The root
     * @param bool $isRequest Is it a request?
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
     * @param string $root The root
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
     * @param string $root The root
     * @param bool $isRequest Is it a request?
     */
    public function testEveryRequiredKeyIsEnforcedEveryOptionalKeyIsOptionalAndUnknownKeysAreRefusedOnlyInRequests(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $res = [];

        foreach ($meta['objects'] as $object) {
            $key = implode('/', $object['path']) ?: '(root)';

            foreach ($object['required'] as $required) {
                if (($required === 'stateOrProvinceCode' && in_array('pickupLocation', $object['path'], true)) || ($required === 'postalCode' && $object['path'] === ['pickupAddress'])) {
                    $res["$key without $required, which the class makes optional"] = self::accepts(SpecInstances::unsetAt($instance, array_merge($object['path'], [$required])), $root, $isRequest) ? true : 'refused';
                    continue;
                }

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
