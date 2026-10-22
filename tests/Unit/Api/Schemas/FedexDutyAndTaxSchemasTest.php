<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexDutyAndTaxSchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\DutyAndTaxSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Estimated Duty and Tax API. They are compared with schemas/dutiestaxes.json, which is read
 * here independently of the code that generated them, field by field, and an instance of each of the two roots is generated from
 * that file and broken one field at a time. The only difference from the file is the two-letter country code
 */
final class FedexDutyAndTaxSchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The fields whose country code has to be two letters, which the schema does not say but its description does
     * @var string[]
     */
    private const COUNTRY_CODES = ['Shipper', 'Recipient'];

    /**
     * The definitions of the class
     * @return array
     */
    private static function definitions(): array
    {
        static $definitions = null;

        return $definitions ?? ($definitions = Schemas::definitions());
    }

    /**
     * The names of the definitions that the two roots need
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
     * Does the validator accept the data as an instance of the root? Requests are strict (unknown keys are errors), responses are not
     * @param array $data The data
     * @param string $root The root
     * @param bool $isRequest Is it a request?
     * @return bool
     */
    private static function accepts(array $data, string $root, bool $isRequest): bool
    {
        return (new SchemaValidator(!$isRequest, self::definitions()))->validate($data, '@' . $root);
    }

    /**
     * The constants are the roots of the spec
     * @return void
     */
    public function testTheConstantsAreTheRootsOfTheSpec(): void
    {
        $this->assertSame(Spec::ROOTS['estimate.request'], Schemas::REQUEST);
        $this->assertSame(Spec::ROOTS['estimate.response'], Schemas::RESPONSE);
        $this->assertArrayHasKey(Schemas::REQUEST, self::definitions());
        $this->assertArrayHasKey(Schemas::RESPONSE, self::definitions());
        $this->assertCount(2, (new \ReflectionClass(Schemas::class))->getConstants(), 'the class has no other constant');
        $this->assertTrue((new \ReflectionClass(Schemas::class))->isFinal());
    }

    /**
     * The roots are the request and the response of the only path of the spec
     * @return void
     */
    public function testTheRootsAreTheRequestAndTheResponseOfTheOnlyPathOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];

        $this->assertSame(['/dutytax/v1/estimate'], array_keys($paths));
        $this->assertSame(['post'], array_keys($paths['/dutytax/v1/estimate']));
        $this->assertSame('#/components/schemas/' . Schemas::REQUEST, $paths['/dutytax/v1/estimate']['post']['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/' . Schemas::RESPONSE, $paths['/dutytax/v1/estimate']['post']['responses']['200']['content']['application/json']['schema']['$ref']);
    }

    /**
     * The definitions are exactly the eleven that the two roots need
     * @return void
     */
    public function testTheDefinitionsAreExactlyTheElevenThatTheTwoRootsNeed(): void
    {
        $expected = array_keys(self::reachable());
        $actual = array_keys(self::definitions());
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
        $this->assertCount(11, $actual);
    }

    /**
     * Every field of every definition says what the spec says
     * @return void
     */
    public function testEveryFieldOfEveryDefinitionSaysWhatTheSpecSays(): void
    {
        $components = Spec::components();
        $results = [];

        foreach (self::definitions() as $name => $definition) {
            $spec = $components[$name];
            $required = $spec['required'] ?? [];
            $classKeys = [];

            foreach ($definition as $key => $node) {
                $classKeys[rtrim($key, '?')] = substr($key, -1) !== '?';
            }

            $specKeys = [];

            foreach ($spec['properties'] as $property => $schema) {
                $specKeys[$property] = in_array($property, $required, true);
                $class = isset($definition[$property . (in_array($property, $required, true) ? '' : '?')]) ? Spec::canonClass($definition[$property . (in_array($property, $required, true) ? '' : '?')]) : 'MISSING';
                $text = Spec::canonSpec($schema, "$name.$property");

                $results["$name.$property"] = ($class === $text) ? true : "the class says '$class', the spec says '$text'";
            }

            $results["$name keys and required flags"] = ($classKeys === $specKeys) ? true : 'class=' . json_encode($classKeys) . ' spec=' . json_encode($specKeys);
        }

        $this->assertAll('the fields of the definitions', $results);
    }

    /**
     * The lengths of the schema are the lengths of the definitions
     * @return void
     */
    public function testTheLengthsOfTheSchemaAreTheLengthsOfTheDefinitions(): void
    {
        $components = Spec::components();
        $found = [];

        foreach (self::definitions() as $name => $definition) {
            foreach ($components[$name]['properties'] as $property => $schema) {
                $key = $property . (in_array($property, $components[$name]['required'] ?? [], true) ? '' : '?');
                $classMax = is_array($definition[$key]) ? ($definition[$key]['@max'] ?? null) : null;

                $this->assertSame($schema['maxLength'] ?? null, $classMax, "$name.$property");

                if ($classMax !== null) {
                    $found["$name.$property"] = $classMax;
                }
            }
        }

        $this->assertSame(['Commodities.commodityName' => 256, 'Commodities.countryOfManufacture' => 2, 'Commodities.description' => 512], $found);
    }

    /**
     * Only the country codes of the shipper and the recipient have a pattern, which is two letters
     * @return void
     */
    public function testOnlyTheCountryCodesOfTheShipperAndTheRecipientHaveAPatternWhichIsTwoLetters(): void
    {
        $patterns = [];

        foreach (self::definitions() as $name => $definition) {
            foreach ($definition as $key => $node) {
                if (is_array($node) && isset($node['@pattern'])) {
                    $patterns["$name." . rtrim($key, '?')] = $node['@pattern'];
                }
            }
        }

        ksort($patterns);

        $this->assertSame(['Recipient.countryCode' => '/^[A-Za-z]{2}$/D', 'Shipper.countryCode' => '/^[A-Za-z]{2}$/D'], $patterns);
    }

    /**
     * The facts of the spec that the pattern rests on are still in the spec
     * @return void
     */
    public function testTheFactsOfTheSpecThatThePatternRestsOnAreStillInTheSpec(): void
    {
        $c = Spec::components();

        foreach (self::COUNTRY_CODES as $name) {
            $property = $c[$name]['properties']['countryCode'];

            $this->assertContains('countryCode', $c[$name]['required']);
            $this->assertStringContainsString('two-letter code', $property['description'], $name);
            $this->assertArrayNotHasKey('maxLength', $property, "$name: the schema has no length");
            $this->assertArrayNotHasKey('pattern', $property);
        }
    }

    /**
     * The country codes of the shipper and the recipient are two letters
     * @return void
     */
    public function testTheCountryCodesOfTheShipperAndTheRecipientAreTwoLetters(): void
    {
        $results = [];

        foreach (['shipper', 'recipient'] as $party) {
            foreach (['US', 'us', 'Gb', 'IN'] as $good) {
                $request = Spec::requestExamples()['MinimumSchemaSample'];
                $request[$party]['countryCode'] = $good;
                $results["$party $good accepted"] = self::accepts($request, Schemas::REQUEST, true) ? true : 'refused';
            }

            foreach (['USA', 'U', '', '1A', 'U ', ' US', "US\n", 'U-', 'ÜS', 12, null, ['US']] as $bad) {
                $request = Spec::requestExamples()['MinimumSchemaSample'];
                $request[$party]['countryCode'] = $bad;
                $results["$party " . json_encode($bad) . ' refused'] = self::accepts($request, Schemas::REQUEST, true) ? 'accepted' : true;
            }
        }

        $this->assertAll('the country codes', $results);
    }

    /**
     * Every reference resolves and every definition is used
     * @return void
     */
    public function testEveryReferenceResolvesAndEveryDefinitionIsUsed(): void
    {
        $defs = self::definitions();
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
                    if (!in_array($key, ['@enum', '@pattern'], true)) {
                        $walk($value);
                    }
                }
            }
        };

        foreach ($defs as $definition) {
            $walk($definition);
        }

        $unused = array_values(array_diff(array_keys($defs), array_keys($used), Spec::ROOTS));

        $this->assertSame([], array_values(array_unique($unresolved)), 'every @reference resolves');
        $this->assertSame([], $unused, 'every definition is used by another one or is one of the two roots');
        new SchemaValidator(false, $defs);
        $this->assertSame([], array_values(array_filter(array_keys($defs), function ($name) {
            return !preg_match('/^[A-Za-z_]\w*$/D', $name);
        })));
    }

    /**
     * The examples of the spec are accepted: the requests strictly and the response leniently
     * @return void
     */
    public function testTheExamplesOfTheSpecAreAccepted(): void
    {
        $results = [];

        foreach (Spec::requestExamples() as $name => $example) {
            $validator = new SchemaValidator(false, self::definitions());
            $results["request $name"] = $validator->validate($example, '@' . Schemas::REQUEST) ? true : implode(' | ', $validator->getErrors());
        }

        foreach (Spec::responseExamples() as $name => $example) {
            $validator = new SchemaValidator(true, self::definitions());
            $results["response $name"] = $validator->validate($example, '@' . Schemas::RESPONSE) ? true : implode(' | ', $validator->getErrors());
        }

        $this->assertCount(4, $results);
        $this->assertAll('the examples of the spec', $results);
    }

    /**
     * The instances generated from the spec are accepted
     * @return void
     */
    public function testTheInstancesGeneratedFromTheSpecAreAccepted(): void
    {
        foreach (Spec::ROOTS as $label => $root) {
            [$instance, $meta] = Spec::instance($label);

            $this->assertTrue(self::accepts($instance, $root, substr($label, -7) === 'request'), $label);
            $this->assertNotEmpty($meta['leaves']);
            $this->assertNotEmpty($meta['objects']);
        }
    }

    /**
     * Every leaf is refused with a wrong type, and every enum and length rule is enforced
     * @return void
     */
    public function testEveryLeafIsRefusedWithAWrongTypeAndEveryEnumAndLengthRuleIsEnforced(): void
    {
        $wrong = ['string' => 123, 'integer' => 'x', 'number' => 'x', 'boolean' => 'x'];
        $res = [];

        foreach (Spec::ROOTS as $label => $root) {
            [$instance, $meta] = Spec::instance($label);
            $isRequest = substr($label, -7) === 'request';

            foreach ($meta['leaves'] as $leaf) {
                $key = $label . ' ' . implode('/', $leaf['path']);
                $res["$key wrong type"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], $wrong[$leaf['type']]), $root, $isRequest) ? 'accepted' : true;

                if ($leaf['enum'] !== null) {
                    $res["$key bad enum"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], 'NOT_A_VALUE'), $root, $isRequest) ? 'accepted' : true;
                    $all = true;

                    foreach ($leaf['enum'] as $value) {
                        $all = $all && self::accepts(SpecInstances::setAt($instance, $leaf['path'], $value), $root, $isRequest);
                    }

                    $res["$key every enum value accepted"] = $all ? true : 'one refused';
                }

                if ($leaf['max'] !== null) {
                    $res["$key at the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], str_repeat('x', $leaf['max'])), $root, $isRequest) ? true : 'refused at the limit';
                    $res["$key over the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], str_repeat('x', $leaf['max'] + 1)), $root, $isRequest) ? 'accepted' : true;
                }
            }
        }

        $this->assertAll('the leaves', $res);
    }

    /**
     * Every required key is enforced, every optional key is optional, and an unknown key is refused only in a request
     * @return void
     */
    public function testEveryRequiredKeyIsEnforcedEveryOptionalKeyIsOptionalAndUnknownKeysAreRefusedOnlyInRequests(): void
    {
        $res = [];

        foreach (Spec::ROOTS as $label => $root) {
            [$instance, $meta] = Spec::instance($label);
            $isRequest = substr($label, -7) === 'request';

            foreach ($meta['objects'] as $object) {
                $key = $label . ' ' . (implode('/', $object['path']) ?: '(root)');

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
        }

        $this->assertAll('the keys', $res);
    }

    /**
     * The required fields of the spec are the ones that the class requires
     * @return void
     */
    public function testTheRequiredFieldsOfTheSpecAreTheOnesThatTheClassRequires(): void
    {
        $definitions = self::definitions();

        $this->assertSame(['shipper', 'recipient', 'commodities'], array_keys(array_filter($definitions['EdtDetailsRequestVO'], function ($key) {
            return substr($key, -1) !== '?';
        }, ARRAY_FILTER_USE_KEY)));
        $this->assertSame(['description', 'quantity', 'unitPrice'], array_keys(array_filter($definitions['Commodities'], function ($key) {
            return substr($key, -1) !== '?';
        }, ARRAY_FILTER_USE_KEY)));
        $this->assertSame(['amount', 'currencyCode'], array_keys($definitions['UnitPrice']));
        $this->assertSame(['@type' => 'string', '@enum' => ['DOCUMENTS', 'FOR_RESALE', 'NOT_FOR_RESALE', 'GIFT', 'RETURN']], $definitions['EdtDetailsRequestVO']['shipmentPurpose?']);
        $this->assertSame('int', $definitions['Commodities']['quantity']);
        $this->assertSame('number', $definitions['UnitPrice']['amount']);
        $this->assertSame('string', $definitions['ShippingCost']['currency?'], 'the shipping cost has currency and the unit price has currencyCode, as in the spec');
    }
}
