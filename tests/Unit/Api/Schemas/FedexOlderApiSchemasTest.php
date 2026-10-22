<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\FedexAddressValidation;
use Mariopereira\Fedex\Api\FedexLocationsSearch;
use Mariopereira\Fedex\Api\FedexPostalCodeValidation;
use Mariopereira\Fedex\Api\Schemas\FedexAddressValidationSchemas;
use Mariopereira\Fedex\Api\Schemas\FedexLocationsSearchSchemas;
use Mariopereira\Fedex\Api\Schemas\FedexPostalCodeValidationSchemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the schema classes of the postal code validation, locations search and address validation APIs, which used to be
 * methods of the API classes. What the definitions say is tested with the API classes, against the files of schemas/: here is
 * what the classes themselves promise
 */
final class FedexOlderApiSchemasTest extends TestCase
{
    /**
     * The sets of definitions: the class, the sets (a method name and its root names), and the API class that they came from
     * @return array
     */
    public function setsProvider(): array
    {
        return [
            'postal code validation' => [FedexPostalCodeValidationSchemas::class, [['definitions', ['RESPONSE']]], FedexPostalCodeValidation::class, ['responseSchemas']],
            'locations search' => [FedexLocationsSearchSchemas::class, [['definitions', ['REQUEST', 'RESPONSE']]], FedexLocationsSearch::class, ['schemas']],
            'address validation' => [FedexAddressValidationSchemas::class, [['requestDefinitions', ['REQUEST']], ['responseDefinitions', ['RESPONSE']]], FedexAddressValidation::class, ['inputSchemas', 'outputSchemas']],
        ];
    }

    /**
     * @dataProvider setsProvider
     * @param string $class The schema class
     * @param array $sets The sets: the method and the constants of the roots that it has
     * @param string $api The API class that the definitions came from
     * @param string[] $removed The methods that the API class does not have any more
     */
    public function testTheSchemaClassIsFinalAndTheApiClassNoLongerHasTheMethods(string $class, array $sets, string $api, array $removed): void
    {
        $this->assertTrue((new \ReflectionClass($class))->isFinal());

        foreach ($removed as $method) {
            $this->assertFalse(method_exists($api, $method), "$api::$method() is gone: the definitions are in $class");
        }

        foreach ($sets as [$method]) {
            $reflection = new \ReflectionMethod($class, $method);

            $this->assertTrue($reflection->isPublic() && $reflection->isStatic(), "$class::$method()");
            $this->assertSame('array', $reflection->getReturnType()->getName());
        }
    }

    /**
     * @dataProvider setsProvider
     * @param string $class The schema class
     * @param array $sets The sets: the method and the constants of the roots that it has
     * @param string $api The API class that the definitions came from
     * @param string[] $removed The methods that the API class does not have any more
     */
    public function testTheRootConstantsNameDefinitionsOfTheirSetAndTheClassHasNoOtherConstant(string $class, array $sets, string $api, array $removed): void
    {
        $constants = [];

        foreach ($sets as [$method, $roots]) {
            $definitions = $class::$method();

            foreach ($roots as $root) {
                $name = constant($class . '::' . $root);
                $constants[] = $root;

                $this->assertArrayHasKey($name, $definitions, "$class::$root is a definition of $method()");
            }
        }

        sort($constants);
        $actual = array_keys((new \ReflectionClass($class))->getConstants());
        sort($actual);

        $this->assertSame($constants, $actual, 'the constants are the roots, and nothing else');
    }

    /**
     * @dataProvider setsProvider
     * @param string $class The schema class
     * @param array $sets The sets: the method and the constants of the roots that it has
     * @param string $api The API class that the definitions came from
     * @param string[] $removed The methods that the API class does not have any more
     */
    public function testEveryReferenceResolvesEveryDefinitionIsUsedAndTheValidatorAcceptsTheSet(string $class, array $sets, string $api, array $removed): void
    {
        foreach ($sets as [$method, $roots]) {
            $definitions = $class::$method();
            $used = [];
            $unresolved = [];
            $walk = function ($node) use (&$walk, &$used, &$unresolved, $definitions) {
                if (is_string($node)) {
                    foreach (explode('|', $node) as $type) {
                        $type = trim($type);

                        if ($type !== '' && $type[0] === '@') {
                            $used[substr($type, 1)] = true;

                            if (!isset($definitions[substr($type, 1)])) {
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

            foreach ($definitions as $definition) {
                $walk($definition);
            }

            $rootNames = array_map(function (string $root) use ($class) {
                return constant($class . '::' . $root);
            }, $roots);
            $unused = array_values(array_diff(array_keys($definitions), array_keys($used), $rootNames));

            $this->assertSame([], array_values(array_unique($unresolved)), "$method(): every @reference resolves");
            $this->assertSame([], $unused, "$method(): every definition is used or is a root");
            $this->assertSame([], array_values(array_filter(array_keys($definitions), function ($name) {
                return !preg_match('/^[A-Za-z_]\w*$/D', $name);
            })), "$method(): no name that the validator would refuse");

            new SchemaValidator(false, $definitions);
        }
    }

    /**
     * Calling a method twice gives the same definitions
     * @return void
     */
    public function testCallingAMethodTwiceGivesTheSameDefinitions(): void
    {
        $this->assertSame(FedexPostalCodeValidationSchemas::definitions(), FedexPostalCodeValidationSchemas::definitions());
        $this->assertSame(FedexLocationsSearchSchemas::definitions(), FedexLocationsSearchSchemas::definitions());
        // the request of the address validation has a closure (the check of the date), and two closures are never the same object
        $this->assertEquals(FedexAddressValidationSchemas::requestDefinitions(), FedexAddressValidationSchemas::requestDefinitions());
        $this->assertSame(FedexAddressValidationSchemas::responseDefinitions(), FedexAddressValidationSchemas::responseDefinitions());
    }

    /**
     * The request and the response of the address validation have sets of their own, with no name in common
     * @return void
     */
    public function testTheRequestAndTheResponseOfTheAddressValidationHaveSetsOfTheirOwnWithNoNameInCommon(): void
    {
        $request = array_keys(FedexAddressValidationSchemas::requestDefinitions());
        $response = array_keys(FedexAddressValidationSchemas::responseDefinitions());

        $this->assertSame(['Full_Schema_Validate_Address', 'AddressResolutionControlParameters', 'ResolveContactAndAddress', 'Address'], $request);
        $this->assertCount(8, $response);
        $this->assertSame([], array_values(array_intersect($request, $response)));
        $this->assertSame('Full_Schema_Validate_Address', FedexAddressValidationSchemas::REQUEST);
        $this->assertSame('AdvcResponseVO', FedexAddressValidationSchemas::RESPONSE);
    }

    /**
     * The date of the address validation request is checked by the definitions
     * @return void
     */
    public function testTheDateOfTheAddressValidationRequestIsCheckedByTheDefinitions(): void
    {
        $validator = new SchemaValidator(false, FedexAddressValidationSchemas::requestDefinitions());
        $request = ['addressesToValidate' => [['address' => ['streetLines' => ['1 Main St'], 'city' => 'Memphis', 'postalCode' => '38017']]]];
        $root = '@' . FedexAddressValidationSchemas::REQUEST;

        $this->assertTrue($validator->validate($request + ['inEffectAsOfTimestamp' => '2020-02-29'], $root), implode(' | ', $validator->getErrors()));
        $this->assertFalse($validator->validate($request + ['inEffectAsOfTimestamp' => '2021-02-29'], $root), 'a date that does not exist');
        $this->assertFalse($validator->validate($request + ['inEffectAsOfTimestamp' => '29/02/2020'], $root), 'a date in another format');
    }
}
