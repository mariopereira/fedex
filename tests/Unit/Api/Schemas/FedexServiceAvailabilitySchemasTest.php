<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexServiceAvailabilitySchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\ServiceAvailabilitySpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Service Availability API. They are compared with schemas/service-availability.json, which is read
 * here independently of the code that generated them, and an instance of each of the six roots is generated from that file and
 * broken one field at a time. The differences from the file are listed one by one
 */
final class FedexServiceAvailabilitySchemasTest extends TestCase
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
        'transittimes.request' => 'TRANSIT_TIMES_REQUEST', 'transittimes.response' => 'TRANSIT_TIMES_RESPONSE',
        'packageoptions.request' => 'PACKAGE_OPTIONS_REQUEST', 'packageoptions.response' => 'PACKAGE_OPTIONS_RESPONSE',
        'specialoptions.request' => 'SPECIAL_OPTIONS_REQUEST', 'specialoptions.response' => 'SPECIAL_OPTIONS_RESPONSE',
    ];

    /**
     * The definitions that are used by no other definition and are not a root: the base of a definition that is made of a base
     * and an overlay, and an overlay-like component that tightens a field
     * @var string[]
     */
    private const UNUSED = ['CODDetail', 'Dimensions1', 'FedExFreightBillingContactAndAddress1', 'PackageAndOptionsRequestedShipment_2', 'ShipmentDryIceDetail1', 'ShipmentDryIceDetail_totalWeight'];

    /**
     * The numbers of the responses, which are not relaxed: the definitions that only the responses reach
     * @var string[]
     */
    private const STRICT_NUMBERS = ['Distance.value', 'LocationDetail_geoPositionalCoordinates.latitude', 'LocationDetail_geoPositionalCoordinates.longitude'];

    /**
     * The fields that the spec requires and the class makes optional, because the examples of FedEx leave them out: definition.field
     * @var string[]
     */
    private const OPTIONAL_BY_DECISION = ['PackageSpecialServicesRequested.codDetail', 'RequestedPackageLineItem.weight', 'TransitTimeRequestedPackageLineItem.weight'];

    /**
     * Is a key of an object of an instance one of those that the class makes optional (see OPTIONAL_BY_DECISION)?
     * @param array $path The path of the object
     * @param string $key The key
     * @return bool
     */
    private static function isOptionalByDecision(array $path, string $key): bool
    {
        return $key === 'codDetail' || ($key === 'weight' && in_array('requestedPackageLineItems', $path, true));
    }

    /**
     * The names that the six roots have in the spec, with the names of the definitions reachable from them
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
     * The names of the definitions that the requests reach
     * @return array The names as keys
     */
    private static function requestSide(): array
    {
        $seen = [];

        foreach (['transittimes.request', 'packageoptions.request', 'specialoptions.request'] as $label) {
            Spec::closure(Spec::ROOTS[$label], $seen);
        }

        return $seen;
    }

    /**
     * Is a definition, or one derived from a definition, reached by a request?
     * @param string $name The name of the definition
     * @return bool
     */
    private static function isRequestSide(string $name): bool
    {
        foreach (array_keys(self::requestSide()) as $request) {
            if ($name === $request || strpos($name, $request . '_') === 0) {
                return true;
            }
        }

        return false;
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
            // a field that the schema requires and never defines is, in the class, an optional field of any type, after the others
            $undefined = array_values(array_diff($required, array_keys($c['properties'])));
            $classKeys = [];

            foreach ($d as $k => $v) {
                $classKeys[substr($k, -1) === '?' ? substr($k, 0, -1) : $k] = substr($k, -1) !== '?';
            }

            $specKeys = [];

            foreach ($c['properties'] as $pn => $p) {
                $specKeys[$pn] = in_array($pn, $required, true) && !in_array("$n.$pn", self::OPTIONAL_BY_DECISION, true);

                if (($p['type'] ?? '') === 'object' && isset($p['properties'])) {
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

                // in the definitions that a request reaches a number is a number or a text that is a number
                if ($b === 'number' && $a === 'number|string' && self::isRequestSide($n)) {
                    $a = 'number';
                }

                $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
            }
        }

        self::$conformance = [$res, array_keys($expectedDerived)];

        return self::$conformance;
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has a hundred and thirty six definitions reachable from the six roots
     * @return void
     */
    public function testTheSpecHasAHundredAndThirtySixDefinitionsReachableFromTheSixRoots(): void
    {
        $this->assertCount(136, self::reachable());
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
        $wrappers = [
            '/availability/v1/transittimes' => ['transittimes', 'body'],
            '/availability/v1/packageandserviceoptions' => ['packageoptions', 'body_1'],
            '/availability/v1/specialserviceoptions' => ['specialoptions', 'body_specialserviceoptions'],
        ];

        $this->assertSame(array_keys($wrappers), array_keys($paths));

        foreach ($wrappers as $path => [$label, $body]) {
            $operation = $paths[$path]['post'];
            $first = Spec::components()[$body]['oneOf'][0]['$ref'];

            $this->assertSame(['post'], array_keys($paths[$path]), "$path has only POST");
            $this->assertSame('#/components/schemas/' . $body, $operation['requestBody']['content']['application/json']['schema']['$ref'], $path);
            $this->assertSame('#/components/schemas/' . Spec::ROOTS[$label . '.request'], $first, "$path: the real schema is the first of the oneOf");
            $this->assertSame('#/components/schemas/' . Spec::ROOTS[$label . '.response'], $operation['responses']['200']['content']['application/json']['schema']['$ref'], $path);
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
        $this->assertSame(['ShipmentDryIceDetail_totalWeight_overlay'], $extra);
        $this->assertCount(137, $defs);
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

        $this->assertSame(['displayText', 'key'], $required('KeyDisplayTextVO'));
        $this->assertSame(['units', 'value'], $required('Weight'));
        $this->assertSame([], $required('Dimensions'));
        $this->assertSame(['codCollectionAmount'], $required('CodDetail'));
        $this->assertSame(['height', 'length', 'units', 'width'], $required('ShipmentDimensions'));
        $this->assertSame(['packageCount', 'totalWeight'], $required('ShipmentDryIceDetail'));
        $this->assertSame(['address', 'contact'], $required('FedExFreightBillingContactAndAddress'));
        $this->assertSame(['recipients', 'shipper'], $required('PackageAndOptionsRequestedShipment'));
        $this->assertSame('@ShipmentDryIceDetail_totalWeight_overlay', $defs['ShipmentDryIceDetail']['totalWeight']);
        $this->assertSame(['units', 'value'], $required('ShipmentDryIceDetail_totalWeight_overlay'));
        $this->assertSame(['@type' => 'string', '@enum' => ['KG', 'LB']], $defs['ShipmentDryIceDetail_totalWeight_overlay']['units']);
    }

    /**
     * The freight billing contact is the contact and address that it is an alias of, with address and contact required
     * @return void
     */
    public function testTheFreightBillingContactIsTheContactAndAddressThatItIsAnAliasOfWithAddressAndContactRequired(): void
    {
        $defs = Schemas::definitions();
        $keys = array_map(function ($key) {
            return rtrim($key, '?');
        }, array_keys($defs['FedExFreightBillingContactAndAddress']));

        $this->assertSame(array_map(function ($key) {
            return rtrim($key, '?');
        }, array_keys($defs['ContactAndAddress'])), $keys);
        $this->assertArrayHasKey('address', $defs['FedExFreightBillingContactAndAddress']);
        $this->assertArrayHasKey('contact', $defs['FedExFreightBillingContactAndAddress']);
        $this->assertArrayHasKey('address?', $defs['ContactAndAddress'], 'optional in the base');
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

        $unused = array_values(array_diff(array_keys($defs), array_keys($used), array_map(function ($label) {
            return Spec::ROOTS[$label];
        }, array_keys(Spec::ROOTS))));
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

        $this->assertContains('codDelocationContactAndAddress"', $C['HoldAtLocationDetail']['required']);
        $this->assertArrayNotHasKey('codDelocationContactAndAddress"', $C['HoldAtLocationDetail']['properties'], 'the field that is required is never defined');
        $this->assertContains('locationType', $C['HoldAtLocationDetail']['required']);
        $this->assertArrayNotHasKey('type', $C['Address']['properties']['postalCode'], 'a description and no type');
        $this->assertArrayNotHasKey('type', $C['Address']['properties']['countryCode']);
        $this->assertSame('number', $C['TransitTimeRequestedPackageLineItem_weight']['properties']['value']['type'], 'the schema says number, and the samples send text');

        foreach (['TransitTimes_Domestic', 'Special_Service_Options_Domestic_Ground'] as $sample) {
            $this->assertIsString($C[$sample]['example']['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'], $sample);
        }

        foreach (['body', 'body_1', 'body_specialserviceoptions'] as $body) {
            foreach (array_slice($C[$body]['oneOf'], 1) as $sample) {
                $this->assertSame(['example'], array_keys($C[basename($sample['$ref'])]), basename($sample['$ref']) . ' is only an example, so the oneOf says nothing');
            }
        }
    }

    // ---------------------------------------------------------------- the numbers

    /**
     * A number is checked as a number or a text that is a number
     * @return void
     */
    public function testANumberIsCheckedAsANumberOrATextThatIsANumber(): void
    {
        foreach ([10, 0, -3, 1.5, -0.25, 1e3, '10', '0', '-3', '+7', '68.25', '.5', '1e3', '1.5E-3', '007'] as $good) {
            $this->assertTrue(Schemas::numberOrNumericText($good), json_encode($good));
        }

        foreach (['', ' ', ' 5', '5 ', "5\n", 'abc', '1,5', '1.', '0x1A', '1 000', 'NaN', 'Infinity', '--1', '1e', '.'] as $bad) {
            $this->assertSame('not a number or a text that is a number', Schemas::numberOrNumericText($bad), json_encode($bad));
        }

        $this->assertSame('not a number or a text that is a number', Schemas::numberOrNumericText(true), 'a boolean is not a number');
    }

    /**
     * The numbers of the requests, and of the definitions that the responses share, are numbers or numbers as text, and the others are numbers
     * @return void
     */
    public function testTheNumbersOfTheRequestsAreNumbersOrNumbersAsTextAndTheOthersAreNumbers(): void
    {
        $defs = Schemas::definitions();
        $relaxed = [];
        $strict = [];

        foreach ($defs as $name => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            foreach ($definition as $key => $node) {
                $node = (is_array($node) && array_keys($node) === [0]) ? $node[0] : $node;

                if ($node === 'number') {
                    $strict[] = "$name." . rtrim($key, '?');
                } elseif (is_array($node) && ($node['@type'] ?? null) === 'number|string') {
                    $this->assertSame([Schemas::class, 'numberOrNumericText'], $node['@check'] ?? null, "$name.$key");
                    $relaxed[] = "$name." . rtrim($key, '?');
                }
            }
        }

        sort($strict);
        $expected = self::STRICT_NUMBERS;
        sort($expected);

        $this->assertSame($expected, $strict, 'the numbers that only the responses reach are numbers');
        $this->assertCount(18, $relaxed);

        foreach ($relaxed as $field) {
            $this->assertTrue(self::isRequestSide(explode('.', $field)[0]), "$field is reached by a request");
        }
    }

    /**
     * The weight and the customs value of a request can be a number or a text that is a number
     * @return void
     */
    public function testTheWeightAndTheCustomsValueOfARequestCanBeANumberOrATextThatIsANumber(): void
    {
        $results = [];

        foreach (['10', 10, 10.5, '10.5'] as $value) {
            $request = json_decode(json_encode(Spec::components()['TransitTimes_International']['example']), true);
            $request['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'] = $value;
            $results['weight ' . json_encode($value)] = (new SchemaValidator(false, Schemas::definitions()))->validate($request, '@' . Schemas::TRANSIT_TIMES_REQUEST) ? true : 'refused';
        }

        foreach (['abc', '', '1,5', true, [1], null] as $value) {
            $request = json_decode(json_encode(Spec::components()['TransitTimes_International']['example']), true);
            $request['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'] = $value;
            $results['weight ' . json_encode($value) . ' refused'] = (new SchemaValidator(false, Schemas::definitions()))->validate($request, '@' . Schemas::TRANSIT_TIMES_REQUEST) ? 'accepted' : true;
        }

        $this->assertAll('the weight', $results);
    }

    // ---------------------------------------------------------------- the samples and the instances

    /**
     * All the sample payloads of the spec are accepted
     * @return void
     */
    public function testAllTheSamplePayloadsOfTheSpecAreAccepted(): void
    {
        $results = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = json_decode(json_encode(Spec::components()[$sample]['example']), true);
            $validator = new SchemaValidator(false, Schemas::definitions());
            $results[$sample] = $validator->validate($example, '@' . Spec::ROOTS[$label]) ? true : implode(' | ', array_slice($validator->getErrors(), 0, 4));
        }

        $this->assertCount(8, $results);
        $this->assertAll('the sample payloads', $results);
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
                if (self::isOptionalByDecision($object['path'], $required)) {
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

    /**
     * The hold at location detail requires the location type and accepts the field that the spec requires and never defines
     * @return void
     */
    public function testTheHoldAtLocationDetailRequiresTheLocationTypeAndAcceptsTheFieldThatTheSpecRequiresAndNeverDefines(): void
    {
        $validator = new SchemaValidator(false, Schemas::definitions());

        $this->assertTrue($validator->validate(['locationType' => 'FEDEX_ONSITE'], '@HoldAtLocationDetail'), implode(' | ', $validator->getErrors()));
        $this->assertFalse($validator->validate([], '@HoldAtLocationDetail'));

        foreach ([null, 1, 'x', ['a'], ['k' => 'v']] as $value) {
            $this->assertTrue($validator->validate(['locationType' => 'FEDEX_ONSITE', 'codDelocationContactAndAddress"' => $value], '@HoldAtLocationDetail'), json_encode($value));
        }
    }
}
