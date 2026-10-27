<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexHarmonizedCodeSchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\HarmonizedCodeSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Harmonized System (HS) Code Classification API. They are compared with
 * schemas/harmonized-code.json, which is read here independently of the code that generated them, with the examples of the
 * file, and an instance of each root is generated from that file and broken one field at a time. The differences from the file
 * are listed one by one
 */
final class FedexHarmonizedCodeSchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The fields that the spec requires and the class makes optional (either country is enough, as the documentation says)
     * @var string[]
     */
    private const OPTIONAL_BY_DECISION = ['HsClassifyVO.destinationCountryCodes', 'HsClassifyVO.originCountryCode'];

    /**
     * The fields that the spec does not require and the class requires (at least one commodity)
     * @var string[]
     */
    private const REQUIRED_BY_DECISION = ['HarmonizedCodeSearchRequestVO.hsClassify'];

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
     * The examples of the path of the spec, request or response
     * @param string $side 'request' or 'response'
     * @return array The examples by name
     */
    private static function examples(string $side): array
    {
        $operation = Spec::spec()['paths']['/commodity/v2/hscodes/search']['post'];
        $content = ($side === 'request') ? $operation['requestBody']['content'] : $operation['responses']['200']['content'];

        return array_map(function ($example) {
            return $example['value'];
        }, $content['application/json']['examples']);
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has eight definitions reachable from the roots, and the class has exactly those
     * @return void
     */
    public function testTheSpecHasEightDefinitionsReachableFromTheRootsAndTheClassHasExactlyThose(): void
    {
        $defined = array_keys(Schemas::definitions());
        $reachable = array_keys(self::reachable());
        sort($defined);
        sort($reachable);

        $this->assertCount(8, $reachable);
        $this->assertSame($reachable, $defined);
    }

    /**
     * The root constants name the request and the response of the only path of the spec
     * @return void
     */
    public function testTheRootConstantsNameTheRequestAndTheResponseOfTheOnlyPathOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];
        $operation = $paths['/commodity/v2/hscodes/search']['post'];

        $this->assertSame(['/commodity/v2/hscodes/search'], array_keys($paths));
        $this->assertSame(['post'], array_keys($paths['/commodity/v2/hscodes/search']));
        $this->assertSame('#/components/schemas/' . Schemas::REQUEST, $operation['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/' . Schemas::RESPONSE, $operation['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertCount(2, (new \ReflectionClass(Schemas::class))->getConstants(), 'the two roots: no other constant');
        $this->assertTrue((new \ReflectionClass(Schemas::class))->isFinal());
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
            $d = $defs[$n];
            $required = $c['required'] ?? [];
            $classKeys = [];
            $specKeys = [];

            foreach ($d as $k => $v) {
                $classKeys[substr($k, -1) === '?' ? substr($k, 0, -1) : $k] = substr($k, -1) !== '?';
            }

            foreach ($c['properties'] as $pn => $p) {
                $specKeys[$pn] = (in_array($pn, $required, true) && !in_array("$n.$pn", self::OPTIONAL_BY_DECISION, true)) || in_array("$n.$pn", self::REQUIRED_BY_DECISION, true);
            }

            $res["$n keys and required flags"] = ($classKeys === $specKeys) ? true : 'class=' . json_encode($classKeys) . ' spec=' . json_encode($specKeys);

            foreach ($c['properties'] as $pn => $p) {
                if ("$n.$pn" === 'HarmonizedCodeSearchRequestVO.hsClassify') {
                    continue; // checked by its own test
                }

                $k = $pn . ($specKeys[$pn] ? '' : '?');
                $a = Spec::canonClass($d[$k]);
                $b = Spec::canonSpec($p, "$n.$pn");
                $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
            }
        }

        $this->assertAll('every definition: same keys, same required keys, same types, lengths, enums and references as the spec', $res);
    }

    /**
     * The commodities are required, at least one, and each one is checked for a country
     * @return void
     */
    public function testTheCommoditiesAreRequiredAtLeastOneAndEachOneIsCheckedForACountry(): void
    {
        $this->assertSame(
            ['@type' => [['@type' => '@HsClassifyVO', '@check' => [Schemas::class, 'hasACountry']]], '@min' => 1],
            Schemas::definitions()[Schemas::REQUEST]['hsClassify']
        );
        $this->assertSame(['HsClassifyVO'], array_map('basename', [Spec::components()[Schemas::REQUEST]['properties']['hsClassify']['items']['$ref']]));
        $this->assertArrayNotHasKey('required', Spec::components()[Schemas::REQUEST], 'the spec does not require it');
    }

    /**
     * A commodity has a country when it has a destination country or an origin country
     * @return void
     */
    public function testACommodityHasACountryWhenItHasADestinationCountryOrAnOriginCountry(): void
    {
        $message = 'the destination country codes or the origin country code is required';

        foreach ([['destinationCountryCodes' => ['US']], ['originCountryCode' => 'IN'], ['destinationCountryCodes' => ['US', 'CA'], 'originCountryCode' => 'IN'], ['originCountryCode' => 5]] as $commodity) {
            $this->assertTrue(Schemas::hasACountry(['commodityDescription' => 'x'] + $commodity), json_encode($commodity));
        }

        foreach ([[], ['destinationCountryCodes' => []], ['originCountryCode' => ''], ['originCountryCode' => null], ['destinationCountryCodes' => 'US']] as $commodity) {
            $this->assertSame($message, Schemas::hasACountry(['commodityDescription' => 'x'] + $commodity), json_encode($commodity));
        }
    }

    /**
     * The facts of the spec that the differences rest on are still in the spec
     * @return void
     */
    public function testTheFactsOfTheSpecThatTheDifferencesRestOnAreStillInTheSpec(): void
    {
        $required = Spec::components()['HsClassifyVO']['required'];
        $examples = self::examples('request');

        $this->assertContains('destinationCountryCodes', $required);
        $this->assertContains('originCountryCode', $required);
        $this->assertArrayNotHasKey('originCountryCode', $examples['MinimumSchemaExample1']['hsClassify'][0], 'the minimum example 1 has only the destination');
        $this->assertArrayNotHasKey('destinationCountryCodes', $examples['MinimumSchemaExample2']['hsClassify'][0], 'the minimum example 2 has only the origin');
        $this->assertSame(4, Spec::components()['SummaryVO']['properties']['subheadingOptions']['maxItems'] ?? null, 'at most four options in the spec, not checked by the class');
    }

    // ---------------------------------------------------------------- the examples and the instances

    /**
     * The four request examples of the spec are accepted by the validator of the class, and the three response examples too
     * @return void
     */
    public function testTheRequestAndResponseExamplesOfTheSpecAreAccepted(): void
    {
        $results = [];

        foreach (self::examples('request') as $name => $example) {
            $validator = (new SchemaValidator(true, Schemas::definitions()))->setLooseScalars(true);
            $results["request $name"] = $validator->validate($example, '@' . Schemas::REQUEST) ? true : implode(' | ', $validator->getErrors());
        }

        foreach (self::examples('response') as $name => $example) {
            $validator = new SchemaValidator(true, Schemas::definitions());
            $results["response $name"] = $validator->validate(json_decode(json_encode($example)), '@' . Schemas::RESPONSE) ? true : implode(' | ', $validator->getErrors());
        }

        $this->assertCount(7, $results);
        $this->assertAll('the examples of the spec', $results);
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

            if (($leaf['max'] ?? null) !== null) {
                $res["$key at the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], str_repeat('x', $leaf['max'])), $root, $isRequest) ? true : 'refused';
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
    public function testTheRequiredAndOptionalKeysAndTheUnknownKeys(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $res = [];

        foreach ($meta['objects'] as $object) {
            $key = implode('/', $object['path']) ?: '(root)';

            foreach ($object['keys'] as $field) {
                $without = SpecInstances::unsetAt($instance, array_merge($object['path'], [$field]));
                $required = in_array($field, $object['required'], true);

                // either country is enough; at least one commodity is required
                if (in_array($field, ['destinationCountryCodes', 'originCountryCode'], true)) {
                    $required = false;
                } elseif ($field === 'hsClassify') {
                    $required = true;
                }

                $res["$key without $field"] = (self::accepts($without, $root, $isRequest) === !$required) ? true : ($required ? 'accepted' : 'refused');
            }

            $withExtra = SpecInstances::setAt($instance, $object['path'], array_merge(SpecInstances::getAt($instance, $object['path']), ['notInTheSchema' => 1]));
            $accepted = self::accepts($withExtra, $root, $isRequest);
            $res["$key with an unknown key"] = $isRequest ? ($accepted ? 'accepted' : true) : ($accepted ? true : 'refused in a response');
        }

        if ($isRequest) {
            $neither = SpecInstances::unsetAt(SpecInstances::unsetAt($instance, ['hsClassify', 0, 'destinationCountryCodes']), ['hsClassify', 0, 'originCountryCode']);
            $res['a commodity without either country'] = self::accepts($neither, $root, true) ? 'accepted' : true;
            $res['no commodity'] = self::accepts(['hsClassify' => []], $root, true) ? 'accepted' : true;
        }

        $this->assertAll("$label: the keys", $res);
    }
}
