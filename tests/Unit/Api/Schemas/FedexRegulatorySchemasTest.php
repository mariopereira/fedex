<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexRegulatorySchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\RegulatorySpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Regulatory API. They are compared with schemas/regulatory.json, which is read here
 * independently of the code that generated them, with the examples of the file, and an instance of each root is generated from
 * that file and broken one field at a time. The differences from the file are listed one by one
 */
final class FedexRegulatorySchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The fields that differ from the spec by decision, with what the class has
     * @var array
     */
    private const DIFFERENT = [
        'Contact.phoneExtension' => 'int',
        'Contact.phoneNumber' => 'string',
        'LotDetails.lotNumberQualifier' => ['@type' => 'int', '@enum' => [1, 2, '1', '2']],
        'ProductDetails.gtinUpcCode' => 'int',
        'RegulatoryProfileRequest_inner.details' => ['@type' => ['@RegulatoryDetail'], '@min' => 1],
    ];

    /**
     * The names of the definitions reachable from the roots
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
     * The request examples of the path of the spec
     * @return array The examples by name
     */
    private static function examples(): array
    {
        return array_map(function ($example) {
            return $example['value'];
        }, Spec::spec()['paths']['/regulatory/v1/profiles']['post']['requestBody']['content']['application/json']['examples']);
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has sixteen definitions reachable from the roots, and the class has exactly those
     * @return void
     */
    public function testTheSpecHasSixteenDefinitionsReachableFromTheRootsAndTheClassHasExactlyThose(): void
    {
        $defined = array_keys(Schemas::definitions());
        $reachable = array_keys(self::reachable());
        sort($defined);
        sort($reachable);

        $this->assertCount(16, $reachable);
        $this->assertSame($reachable, $defined);
    }

    /**
     * The root constants name the request and the response of the only path of the spec
     * @return void
     */
    public function testTheRootConstantsNameTheRequestAndTheResponseOfTheOnlyPathOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];
        $operation = $paths['/regulatory/v1/profiles']['post'];

        $this->assertSame(['/regulatory/v1/profiles'], array_keys($paths));
        $this->assertSame(['post'], array_keys($paths['/regulatory/v1/profiles']));
        $this->assertSame('#/components/schemas/' . Schemas::REQUEST, $operation['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/' . Schemas::RESPONSE, $operation['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertCount(2, (new \ReflectionClass(Schemas::class))->getConstants(), 'the two roots: no other constant');
        $this->assertTrue((new \ReflectionClass(Schemas::class))->isFinal());
    }

    /**
     * The request is a list of 1 to 25 profiles (the spec says at most 25)
     * @return void
     */
    public function testTheRequestIsAListOfOneToTwentyFiveProfiles(): void
    {
        $spec = Spec::components()[Schemas::REQUEST];

        $this->assertSame('array', $spec['type']);
        $this->assertSame(25, $spec['maxItems']);
        $this->assertArrayNotHasKey('minItems', $spec);
        $this->assertSame(['@type' => ['@RegulatoryProfileRequest_inner'], '@min' => 1, '@max' => 25], Schemas::definitions()[Schemas::REQUEST]);
    }

    /**
     * Every definition of the spec is in the class with the same shape, apart from the decided differences
     * @return void
     */
    public function testEveryDefinitionOfTheSpecIsInTheClassWithTheSameShapeApartFromTheDecidedDifferences(): void
    {
        $C = Spec::components();
        $defs = Schemas::definitions();
        $res = [];

        foreach (array_keys(self::reachable()) as $n) {
            $c = $C[$n];

            if (!isset($c['properties'])) {
                continue; // the root, a list, checked by its own test
            }

            $d = $defs[$n];
            $required = $c['required'] ?? [];
            $classKeys = [];
            $specKeys = [];

            foreach ($d as $k => $v) {
                $classKeys[substr($k, -1) === '?' ? substr($k, 0, -1) : $k] = substr($k, -1) !== '?';
            }

            foreach ($c['properties'] as $pn => $p) {
                $specKeys[$pn] = in_array($pn, $required, true);
            }

            $res["$n keys and required flags"] = ($classKeys === $specKeys) ? true : 'class=' . json_encode($classKeys) . ' spec=' . json_encode($specKeys);

            foreach ($c['properties'] as $pn => $p) {
                $node = $d[$pn . ($specKeys[$pn] ? '' : '?')];

                if (array_key_exists("$n.$pn", self::DIFFERENT)) {
                    $res["$n.$pn (decided)"] = ($node === self::DIFFERENT["$n.$pn"]) ? true : json_encode($node);
                    continue;
                }

                $a = Spec::canonClass($node);
                $b = Spec::canonSpec($p, "$n.$pn");
                $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
            }
        }

        $this->assertAll('every definition: same keys, same required keys, same types, lengths, enums and references as the spec', $res);
    }

    /**
     * The facts of the spec that the differences rest on are still in the spec
     * @return void
     */
    public function testTheFactsOfTheSpecThatTheDifferencesRestOnAreStillInTheSpec(): void
    {
        $C = Spec::components();
        $example3 = self::examples()['cpscExample3'][0]['details'][0]['fullMessageSet'];

        foreach (['ProductDetails' => 'gtinUpcCode', 'Contact' => 'phoneExtension', 'LotDetails' => 'lotNumberQualifier'] as $definition => $field) {
            $this->assertSame('integer', $C[$definition]['properties'][$field]['type'], "$definition.$field");
            $this->assertArrayHasKey('maxLength', $C[$definition]['properties'][$field], "$definition.$field has a length");
        }

        $this->assertSame(15, $C['Contact']['properties']['phoneNumber']['maxLength']);
        $this->assertSame(17, strlen($example3['manufacturerDetails']['contact']['phoneNumber']), 'example 3 sends a longer number');
        $this->assertSame('1', $example3['lotDetails']['lotNumberQualifier'], 'example 3 sends the qualifier as text');
        $this->assertSame('00012345678905', $example3['productDetails']['gtinUpcCode'], 'and the GTIN as text, with its leading zeros');
    }

    // ---------------------------------------------------------------- the examples and the instances

    /**
     * The six request examples of the spec are accepted by the validator of the class
     * @return void
     */
    public function testTheSixRequestExamplesOfTheSpecAreAccepted(): void
    {
        $results = [];

        foreach (self::examples() as $name => $example) {
            $validator = (new SchemaValidator(true, Schemas::definitions()))->setLooseScalars(true);
            $results[$name] = $validator->validate($example, '@' . Schemas::REQUEST) ? true : implode(' | ', $validator->getErrors());
        }

        $this->assertCount(6, $results);
        $this->assertAll('the request examples', $results);
    }

    /**
     * The numbers of profiles and of details are enforced, and the integer fields with a length in the spec take any integer
     * @return void
     */
    public function testTheNumbersOfProfilesAndOfDetailsAreEnforcedAndTheIntegerFieldsTakeAnyInteger(): void
    {
        $validator = (new SchemaValidator(true, Schemas::definitions()))->setLooseScalars(true);
        $one = self::examples()['cpscExample1'][0];

        $this->assertTrue($validator->validate(array_fill(0, 25, $one), '@' . Schemas::REQUEST));
        $this->assertFalse($validator->validate(array_fill(0, 26, $one), '@' . Schemas::REQUEST));
        $this->assertFalse($validator->validate([], '@' . Schemas::REQUEST));
        $this->assertFalse($validator->validate([['details' => []] + $one], '@' . Schemas::REQUEST));
        $this->assertTrue($validator->validate(['gtinUpcCode' => 12345678905], '@ProductDetails'), 'a GTIN as a number');
        $this->assertTrue($validator->validate(['phoneExtension' => 123456], '@Contact'), 'a long extension: FedEx checks it');
        $this->assertTrue($validator->validate(['phoneNumber' => '+86-755-8888-0001'], '@Contact'));
        $this->assertFalse($validator->validate(['lotNumberQualifier' => 3], '@LotDetails'));
        $this->assertFalse($validator->validate(['lotNumberQualifier' => '3'], '@LotDetails'));
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
        [$instance] = Spec::instance($label);

        $this->assertTrue(self::accepts($instance, $root, $isRequest));
    }

    /**
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root
     * @param bool $isRequest Is it a request?
     */
    public function testEveryLeafIsRefusedWithAWrongTypeAndEveryRuleOfLengthAndEnumIsEnforced(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $wrong = ['string' => 123, 'integer' => 'x', 'number' => 'x', 'boolean' => 'x'];
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $key = implode('/', $leaf['path']);
            $res["$key wrong type"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], $wrong[$leaf['type']]), $root, $isRequest) ? 'accepted' : true;

            if ($leaf['enum'] !== null) {
                $res["$key bad enum"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], 'NOT_A_VALUE'), $root, $isRequest) ? 'accepted' : true;
            }

            // the length of a text (the integers and the phone number with a length in the spec are not checked, by decision)
            if (($leaf['max'] ?? null) !== null && $leaf['type'] === 'string' && end($leaf['path']) !== 'phoneNumber') {
                // a value that is also an enum is checked by the enum: its first value is the one at the limit
                $atLimit = ($leaf['enum'] !== null) ? $leaf['enum'][0] : str_repeat('x', $leaf['max']);
                $res["$key at the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], $atLimit), $root, $isRequest) ? true : 'refused';
                $res["$key over the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], str_repeat('x', $leaf['max'] + 1)), $root, $isRequest) ? 'accepted' : true;
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
                $res["$key without required $required"] = self::accepts(SpecInstances::unsetAt($instance, array_merge($object['path'], [$required])), $root, $isRequest) ? 'accepted' : true;
            }

            foreach (array_diff($object['keys'], $object['required']) as $optional) {
                $res["$key without optional $optional"] = self::accepts(SpecInstances::unsetAt($instance, array_merge($object['path'], [$optional])), $root, $isRequest) ? true : 'refused';
            }

            if ($object['path'] === []) {
                continue; // the root of the request is a list
            }

            $withExtra = SpecInstances::setAt($instance, $object['path'], array_merge(SpecInstances::getAt($instance, $object['path']), ['notInTheSchema' => 1]));
            $accepted = self::accepts($withExtra, $root, $isRequest);
            $res["$key with an unknown key"] = $isRequest ? ($accepted ? 'accepted' : true) : ($accepted ? true : 'refused in a response');
        }

        $this->assertAll("$label: the keys of " . count($meta['objects']) . ' objects', $res);
    }
}
