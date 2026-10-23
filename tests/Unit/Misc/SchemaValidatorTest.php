<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Misc;

use Mariopereira\Fedex\Misc\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the schema validator: the type specifications, the references to named definitions, the list and object
 * schemas, every rule of a rule node, and the text of every error
 */
final class SchemaValidatorTest extends TestCase
{
    /**
     * A validator
     * @param bool $allowExtra Allow keys that the schema does not have?
     * @param array $definitions The named definitions
     * @return SchemaValidator
     */
    private static function validator(bool $allowExtra = true, array $definitions = []): SchemaValidator
    {
        return new SchemaValidator($allowExtra, $definitions);
    }

    /**
     * The errors that validating the data gives
     * @param mixed $data The data
     * @param mixed $schema The schema
     * @param bool $allowExtra Allow keys that the schema does not have?
     * @param array $definitions The named definitions
     * @return array
     */
    private static function errorsOf($data, $schema, bool $allowExtra = true, array $definitions = []): array
    {
        $validator = self::validator($allowExtra, $definitions);
        $validator->validate($data, $schema);

        return $validator->getErrors();
    }

    // ---------------------------------------------------------------- the names of the definitions

    /**
     * @dataProvider validNamesProvider
     * @param string $name A name that is valid
     */
    public function testAValidDefinitionNameIsAccepted(string $name): void
    {
        $validator = self::validator();

        $this->assertSame($validator, $validator->define($name, 'string'));
        $this->assertTrue($validator->validate('x', '@' . $name));
    }

    /**
     * The cases of testAValidDefinitionNameIsAccepted(): a valid definition name is accepted
     * @return array
     */
    public function validNamesProvider(): array
    {
        return ['a letter' => ['A'], 'an underscore first' => ['_a'], 'letters and digits' => ['a1'], 'with an underscore' => ['A_b']];
    }

    /**
     * @dataProvider invalidNamesProvider
     * @param string $name A name that is not valid
     */
    public function testAnInvalidDefinitionNameIsRefused(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid definition name '$name'");

        self::validator()->define($name, 'string');
    }

    /**
     * The cases of testAnInvalidDefinitionNameIsRefused(): an invalid definition name is refused
     * @return array
     */
    public function invalidNamesProvider(): array
    {
        return ['empty' => [''], 'a digit first' => ['1a'], 'a hyphen' => ['a-b'], 'a space' => ['a b'], 'a dot' => ['A.B'], 'an accent' => ['é'], 'a trailing newline' => ["a\n"]];
    }

    /**
     * The constructor defines the definitions and checks their names
     * @return void
     */
    public function testTheConstructorDefinesTheDefinitionsAndChecksTheirNames(): void
    {
        $this->assertTrue(self::validator(true, ['Name' => 'string'])->validate('x', '@Name'));

        $this->expectException(\InvalidArgumentException::class);
        self::validator(true, ['bad-name' => 'string']);
    }

    /**
     * A numeric key in the definitions is not a valid name
     * @return void
     */
    public function testANumericKeyInTheDefinitionsIsNotAValidName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::validator(true, [0 => 'string']);
    }

    // ---------------------------------------------------------------- the type specifications

    /**
     * @dataProvider typesProvider
     * @param string $spec The type specification
     * @param mixed $value The value
     * @param string|null $got What the error says the value is, or null when the value is accepted
     */
    public function testTypeSpecifications(string $spec, $value, ?string $got): void
    {
        $errors = self::errorsOf($value, $spec);

        $this->assertSame($got === null ? [] : ['$: expected ' . $spec . ', got ' . $got], $errors);
    }

    /**
     * The cases of testTypeSpecifications(): type specifications
     * @return array
     */
    public function typesProvider(): array
    {
        $o = new \stdClass();

        return [
            'string: text' => ['string', 'x', null],
            'string: empty text' => ['string', '', null],
            'string: an integer' => ['string', 1, 'integer'],
            'string: a decimal' => ['string', 1.5, 'double'],
            'string: a boolean' => ['string', true, 'boolean'],
            'string: null' => ['string', null, 'NULL'],
            'string: a list' => ['string', [], 'array'],
            'string: an object' => ['string', $o, 'object'],

            'int: a positive integer' => ['int', 5, null],
            'int: zero' => ['int', 0, null],
            'int: a negative integer' => ['int', -3, null],
            'int: numeric text' => ['int', '5', 'string'],
            'int: a decimal without a fraction' => ['int', 5.0, 'double'],
            'int: a boolean' => ['int', true, 'boolean'],
            'int: null' => ['int', null, 'NULL'],

            'float: a decimal' => ['float', 1.5, null],
            'float: a decimal without a fraction' => ['float', 5.0, null],
            'float: an integer (strict)' => ['float', 5, 'integer'],
            'float: numeric text' => ['float', '1.5', 'string'],

            'number: an integer' => ['number', 5, null],
            'number: a decimal' => ['number', 1.5, null],
            'number: numeric text' => ['number', '5', 'string'],
            'number: a boolean' => ['number', false, 'boolean'],

            'bool: true' => ['bool', true, null],
            'bool: false' => ['bool', false, null],
            'bool: zero' => ['bool', 0, 'integer'],
            'bool: text' => ['bool', 'true', 'string'],
            'bool: null' => ['bool', null, 'NULL'],

            'null: null' => ['null', null, null],
            'null: zero' => ['null', 0, 'integer'],
            'null: empty text' => ['null', '', 'string'],
            'null: false' => ['null', false, 'boolean'],

            'array: an empty list' => ['array', [], null],
            'array: a list' => ['array', [1, 2], null],
            'array: a map' => ['array', ['a' => 1], 'array'],
            'array: an object' => ['array', $o, 'object'],
            'array: text' => ['array', 'x', 'string'],
            'array: null' => ['array', null, 'NULL'],

            'object: an object' => ['object', $o, null],
            'object: a map' => ['object', ['a' => 1], null],
            'object: an empty array' => ['object', [], null],
            'object: a list' => ['object', [1, 2], 'array'],
            'object: text' => ['object', 'x', 'string'],
            'object: null' => ['object', null, 'NULL'],

            'mixed: text' => ['mixed', 'x', null],
            'mixed: null' => ['mixed', null, null],
            'mixed: an object' => ['mixed', $o, null],
            'mixed: a list' => ['mixed', [1], null],

            'a union: the first' => ['int|string', 5, null],
            'a union: the second' => ['int|string', 'x', null],
            'a union: neither' => ['int|string', 1.5, 'double'],
            'a union: null' => ['int|string', null, 'NULL'],
            'a union with spaces' => ['int | string', 'x', null],
            'a union with null' => ['string|null', null, null],

            'nullable: null' => ['?string', null, null],
            'nullable: text' => ['?string', 'x', null],
            'nullable: another type' => ['?string', 5, 'integer'],
            'nullable union: null' => ['?int|string', null, null],
            'nullable union: the first' => ['?int|string', 5, null],
            'nullable union: neither' => ['?int|string', 1.5, 'double'],
        ];
    }

    /**
     * An unknown type is an exception
     * @return void
     */
    public function testAnUnknownTypeIsAnException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown type 'foo' in schema");

        self::validator()->validate('x', 'foo');
    }

    /**
     * An empty spec is an unknown type
     * @return void
     */
    public function testAnEmptySpecIsAnUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown type '' in schema");

        self::validator()->validate('x', '');
    }

    /**
     * An unknown type in a union is only found when the types before it do not match
     * @return void
     */
    public function testAnUnknownTypeInAUnionIsOnlyFoundWhenTheTypesBeforeItDoNotMatch(): void
    {
        $this->assertTrue(self::validator()->validate('x', 'string|foo'), 'the match comes first, so foo is never looked at');

        $this->expectException(\InvalidArgumentException::class);
        self::validator()->validate(5, 'string|foo');
    }

    /**
     * Anything that is not a string or an array is an invalid schema node
     * @return void
     */
    public function testAnythingThatIsNotAStringOrAnArrayIsAnInvalidSchemaNode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid schema node at $');

        self::validator()->validate('x', 5);
    }

    // ---------------------------------------------------------------- references

    /**
     * The definitions used by the tests of the references
     * @return array
     */
    private static function definitions(): array
    {
        return [
            'Address' => ['street' => 'string', 'city' => 'string', 'zip?' => ['@type' => 'string', '@pattern' => '/^\d{4}-\d{3}$/']],
            'Category' => ['name' => 'string', 'children?' => ['@Category']],
            'Alias' => '@Address',
            'Id' => 'int',
        ];
    }

    /**
     * A reference validates against the definition
     * @return void
     */
    public function testAReferenceValidatesAgainstTheDefinition(): void
    {
        $this->assertSame([], self::errorsOf(['street' => 'Rua X', 'city' => 'Porto'], '@Address', true, self::definitions()));
    }

    /**
     * The errors of a reference are reported at their own path
     * @return void
     */
    public function testTheErrorsOfAReferenceAreReportedAtTheirOwnPath(): void
    {
        $this->assertSame(['$.address: expected object, got string'], self::errorsOf(['address' => 'x'], ['address' => '@Address'], true, self::definitions()));
        $this->assertSame(['$.city: required key is missing'], self::errorsOf(['street' => 'Rua X'], '@Address', true, self::definitions()));
        $this->assertSame(['$.address.city: required key is missing', '$.address.zip: does not match pattern /^\d{4}-\d{3}$/'], self::errorsOf(['address' => ['street' => 'x', 'zip' => '4000']], ['address' => '@Address'], true, self::definitions()));
    }

    /**
     * A reference to a scalar definition
     * @return void
     */
    public function testAReferenceToAScalarDefinition(): void
    {
        $this->assertSame([], self::errorsOf(5, '@Id', true, self::definitions()));
        $this->assertSame(['$: expected int, got string'], self::errorsOf('5', '@Id', true, self::definitions()));
    }

    /**
     * A definition can be an alias of another
     * @return void
     */
    public function testADefinitionCanBeAnAliasOfAnother(): void
    {
        $this->assertSame([], self::errorsOf(['street' => 'x', 'city' => 'y'], '@Alias', true, self::definitions()));
        $this->assertSame(['$.city: required key is missing'], self::errorsOf(['street' => 'x'], '@Alias', true, self::definitions()));
    }

    /**
     * A recursive definition
     * @return void
     */
    public function testARecursiveDefinition(): void
    {
        $tree = ['name' => 'root', 'children' => [['name' => 'kid', 'children' => [['name' => 5]]]]];

        $this->assertSame([], self::errorsOf(['name' => 'root', 'children' => [['name' => 'kid']]], '@Category', true, self::definitions()));
        $this->assertSame(['$.children[0].children[0].name: expected string, got integer'], self::errorsOf($tree, '@Category', true, self::definitions()));
    }

    /**
     * A nullable reference
     * @return void
     */
    public function testANullableReference(): void
    {
        $this->assertSame([], self::errorsOf(null, '?@Address', true, self::definitions()));
        $this->assertSame([], self::errorsOf(['street' => 'x', 'city' => 'y'], '?@Address', true, self::definitions()));
        $this->assertSame(['$.street: required key is missing', '$.city: required key is missing'], self::errorsOf([], '?@Address', true, self::definitions()));
    }

    /**
     * A reference in a union matches when the value is valid for it
     * @return void
     */
    public function testAReferenceInAUnionMatchesWhenTheValueIsValidForIt(): void
    {
        $definitions = self::definitions();

        $this->assertSame([], self::errorsOf('x', 'string|@Address', true, $definitions));
        $this->assertSame([], self::errorsOf(['street' => 'x', 'city' => 'y'], 'string|@Address', true, $definitions));
        $this->assertSame([], self::errorsOf(5, 'string|@Id', true, $definitions), 'the integer matches @Id');
    }

    /**
     * A failed alternative of a union leaves no nested errors
     * @return void
     */
    public function testAFailedAlternativeOfAUnionLeavesNoNestedErrors(): void
    {
        $errors = self::errorsOf(['street' => 'x'], 'string|@Address', true, self::definitions());

        $this->assertSame(['$: expected string|@Address, got array'], $errors, 'one error for the union, not those of @Address');
    }

    /**
     * Errors found before a union are kept
     * @return void
     */
    public function testErrorsFoundBeforeAUnionAreKept(): void
    {
        $errors = self::errorsOf(['a' => 5, 'b' => 5.5], ['a' => 'string', 'b' => 'string|@Id'], true, self::definitions());

        $this->assertSame(['$.a: expected string, got integer', '$.b: expected string|@Id, got double'], $errors);
    }

    /**
     * An unknown definition is an exception
     * @return void
     */
    public function testAnUnknownDefinitionIsAnException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown definition '@Nope'");

        self::validator()->validate('x', '@Nope');
    }

    /**
     * An unknown definition in a union is an exception
     * @return void
     */
    public function testAnUnknownDefinitionInAUnionIsAnException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown definition '@Nope'");

        self::validator()->validate(5, 'string|@Nope');
    }

    // ---------------------------------------------------------------- list schemas

    /**
     * A list schema checks every item
     * @return void
     */
    public function testAListSchemaChecksEveryItem(): void
    {
        $this->assertSame([], self::errorsOf(['a', 'b'], ['string']));
        $this->assertSame([], self::errorsOf([], ['string']), 'an empty list');
        $this->assertSame(['$[1]: expected string, got integer', '$[2]: expected string, got NULL'], self::errorsOf(['a', 5, null], ['string']));
    }

    /**
     * @dataProvider notAListProvider
     * @param mixed $value Something that is not a list
     * @param string $got What the error says it is
     */
    public function testAListSchemaRefusesWhatIsNotAList($value, string $got): void
    {
        $this->assertSame(['$: expected list, got ' . $got], self::errorsOf($value, ['string']));
    }

    /**
     * The cases of testAListSchemaRefusesWhatIsNotAList(): a list schema refuses what is not a list
     * @return array
     */
    public function notAListProvider(): array
    {
        return [
            'a map with a matching value (a map must not pass as a list)' => [['a' => 'x'], 'array'],
            'a map with several matching values' => [['a' => 'x', 'b' => 'y'], 'array'],
            'a list that does not start at zero' => [[1 => 'x', 2 => 'y'], 'array'],
            'an object' => [(object) ['a' => 'x'], 'object'],
            'text' => ['x', 'string'],
            'null' => [null, 'NULL'],
            'a number' => [5, 'integer'],
        ];
    }

    /**
     * Lists inside lists
     * @return void
     */
    public function testListsInsideLists(): void
    {
        $this->assertSame([], self::errorsOf([['a'], ['b', 'c'], []], [['string']]));
        $this->assertSame(['$[1][0]: expected string, got integer'], self::errorsOf([['a'], [5]], [['string']]));
        $this->assertSame(['$[1]: expected list, got string'], self::errorsOf([['a'], 'b'], [['string']]));
    }

    /**
     * A list of objects
     * @return void
     */
    public function testAListOfObjects(): void
    {
        $schema = [['id' => 'int', 'tag?' => 'string']];

        $this->assertSame([], self::errorsOf([['id' => 1], ['id' => 2, 'tag' => 'x']], $schema));
        $this->assertSame(['$[1].id: required key is missing'], self::errorsOf([['id' => 1], ['tag' => 'x']], $schema));
    }

    // ---------------------------------------------------------------- object schemas

    /**
     * Required and optional keys
     * @return void
     */
    public function testRequiredAndOptionalKeys(): void
    {
        $schema = ['name' => 'string', 'age?' => 'int'];

        $this->assertSame([], self::errorsOf(['name' => 'Ana'], $schema));
        $this->assertSame([], self::errorsOf(['name' => 'Ana', 'age' => 30], $schema));
        $this->assertSame(['$.name: required key is missing'], self::errorsOf(['age' => 30], $schema));
        $this->assertSame(['$.age: expected int, got string'], self::errorsOf(['name' => 'Ana', 'age' => '30'], $schema));
    }

    /**
     * An object is as good as an array
     * @return void
     */
    public function testAnObjectIsAsGoodAsAnArray(): void
    {
        $schema = ['name' => 'string', 'age?' => 'int'];

        $this->assertSame([], self::errorsOf((object) ['name' => 'Ana', 'age' => 30], $schema));
        $this->assertSame(['$.name: required key is missing'], self::errorsOf((object) ['age' => 30], $schema));
    }

    /**
     * A key that is present with null is present
     * @return void
     */
    public function testAKeyThatIsPresentWithNullIsPresent(): void
    {
        $this->assertSame(['$.name: expected string, got NULL'], self::errorsOf(['name' => null], ['name' => 'string']));
        $this->assertSame([], self::errorsOf(['name' => null], ['name' => '?string']));
        $this->assertSame([], self::errorsOf(['name' => null], ['name?' => '?string']));
    }

    /**
     * Unknown keys are an error only when the validator is strict
     * @return void
     */
    public function testUnknownKeysAreAnErrorOnlyWhenTheValidatorIsStrict(): void
    {
        $data = ['name' => 'Ana', 'other' => 1, 'more' => 2];
        $schema = ['name' => 'string'];

        $this->assertSame([], self::errorsOf($data, $schema, true), 'lenient');
        $this->assertSame(['$.other: unexpected key', '$.more: unexpected key'], self::errorsOf($data, $schema, false), 'strict');
        $this->assertSame([], self::errorsOf(['name' => 'Ana'], $schema, false));
    }

    /**
     * The optional keys count as known in a strict validator
     * @return void
     */
    public function testTheOptionalKeysCountAsKnownInAStrictValidator(): void
    {
        $this->assertSame([], self::errorsOf(['a' => 1, 'b' => 2], ['a' => 'int', 'b?' => 'int'], false));
        $this->assertSame(['$.c: unexpected key'], self::errorsOf(['a' => 1, 'c' => 3], ['a' => 'int', 'b?' => 'int'], false));
    }

    /**
     * Numeric keys of the data are reported as text
     * @return void
     */
    public function testNumericKeysOfTheDataAreReportedAsText(): void
    {
        $this->assertSame(['$.5: unexpected key'], self::errorsOf([5 => 'x', 'name' => 'Ana'], ['name' => 'string'], false));
    }

    /**
     * Nested objects report the full path
     * @return void
     */
    public function testNestedObjectsReportTheFullPath(): void
    {
        $schema = ['a' => ['b' => ['c' => 'int']]];

        $this->assertSame([], self::errorsOf(['a' => ['b' => ['c' => 1]]], $schema));
        $this->assertSame(['$.a.b.c: expected int, got string'], self::errorsOf(['a' => ['b' => ['c' => 'x']]], $schema));
        $this->assertSame(['$.a.b.c: required key is missing'], self::errorsOf(['a' => ['b' => []]], $schema));
        $this->assertSame(['$.a.b: expected object, got integer'], self::errorsOf(['a' => ['b' => 5]], $schema));
    }

    /**
     * @dataProvider notAnObjectProvider
     * @param mixed $value Something that is not an object
     * @param string $got What the error says it is
     */
    public function testAnObjectSchemaRefusesWhatIsNotAMap($value, string $got): void
    {
        $this->assertSame(['$: expected object, got ' . $got], self::errorsOf($value, ['name' => 'string']));
    }

    /**
     * The cases of testAnObjectSchemaRefusesWhatIsNotAMap(): an object schema refuses what is not a map
     * @return array
     */
    public function notAnObjectProvider(): array
    {
        return ['a list' => [['Ana'], 'array'], 'text' => ['Ana', 'string'], 'null' => [null, 'NULL'], 'a number' => [5, 'integer']];
    }

    /**
     * An empty array is an empty object and an empty list
     * @return void
     */
    public function testAnEmptyArrayIsAnEmptyObjectAndAnEmptyList(): void
    {
        $this->assertSame(['$.name: required key is missing'], self::errorsOf([], ['name' => 'string']));
        $this->assertSame([], self::errorsOf([], ['name?' => 'string']));
        $this->assertSame([], self::errorsOf([], ['string']));
    }

    /**
     * An empty schema is an object that accepts any map and refuses the keys when strict
     * @return void
     */
    public function testAnEmptySchemaIsAnObjectThatAcceptsAnyMapAndRefusesTheKeysWhenStrict(): void
    {
        $this->assertSame([], self::errorsOf(['a' => 1], []));
        $this->assertSame(['$.a: unexpected key'], self::errorsOf(['a' => 1], [], false));
        $this->assertSame(['$: expected object, got array'], self::errorsOf([1, 2], []));
    }

    /**
     * Every problem is reported in order
     * @return void
     */
    public function testEveryProblemIsReportedInOrder(): void
    {
        $errors = self::errorsOf(['b' => 'x', 'z' => 1], ['a' => 'int', 'b' => 'int', 'c?' => 'int'], false);

        $this->assertSame(['$.a: required key is missing', '$.b: expected int, got string', '$.z: unexpected key'], $errors);
    }

    // ---------------------------------------------------------------- rule nodes: the type

    /**
     * A rule node without a type is mixed
     * @return void
     */
    public function testARuleNodeWithoutATypeIsMixed(): void
    {
        $this->assertSame([], self::errorsOf(5, ['@min' => 1]));
        $this->assertSame([], self::errorsOf('x', ['@enum' => ['x']]));
    }

    /**
     * The type of a rule node is checked
     * @return void
     */
    public function testTheTypeOfARuleNodeIsChecked(): void
    {
        $this->assertSame(['$: expected string, got integer'], self::errorsOf(5, ['@type' => 'string', '@min' => 10, '@enum' => ['x'], '@pattern' => '/^x$/']), 'a type error stops the other rules, which this value would also fail');
    }

    /**
     * The type of a rule node can be a list schema or a reference
     * @return void
     */
    public function testTheTypeOfARuleNodeCanBeAListSchemaOrAReference(): void
    {
        $this->assertSame([], self::errorsOf(['a', 'b'], ['@type' => ['string'], '@max' => 2]));
        $this->assertSame(['$: item count must be <= 2 (got 3)'], self::errorsOf(['a', 'b', 'c'], ['@type' => ['string'], '@max' => 2]));
        $this->assertSame(['$[0]: expected string, got integer'], self::errorsOf([5], ['@type' => ['string'], '@max' => 2]));
        $this->assertSame([], self::errorsOf(5, ['@type' => '@Id', '@min' => 1], true, self::definitions()));
        $this->assertSame(['$: value must be >= 10 (got 5)'], self::errorsOf(5, ['@type' => '@Id', '@min' => 10], true, self::definitions()));
    }

    /**
     * Null stops the rules when the type allows it
     * @return void
     */
    public function testNullStopsTheRulesWhenTheTypeAllowsIt(): void
    {
        $this->assertSame([], self::errorsOf(null, ['@type' => '?string', '@min' => 5, '@enum' => ['abcde'], '@pattern' => '/^x/']));
        $this->assertSame(['$: expected string, got NULL'], self::errorsOf(null, ['@type' => 'string', '@min' => 5]));
    }

    /**
     * An unknown rule is an exception
     * @return void
     */
    public function testAnUnknownRuleIsAnException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown rule(s) at $: @foo, @bar');

        self::validator()->validate('x', ['@type' => 'string', '@foo' => 1, '@bar' => 2]);
    }

    /**
     * A key that is not a rule in a rule node is an unknown rule
     * @return void
     */
    public function testAKeyThatIsNotARuleInARuleNodeIsAnUnknownRule(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown rule(s) at $: name');

        self::validator()->validate(['name' => 'x'], ['@type' => 'object', 'name' => 'string']);
    }

    // ---------------------------------------------------------------- rule nodes: @enum

    /**
     * An enum accepts only its values
     * @return void
     */
    public function testAnEnumAcceptsOnlyItsValues(): void
    {
        $schema = ['@type' => 'string', '@enum' => ['admin', 'user', 'guest']];

        $this->assertSame([], self::errorsOf('user', $schema));
        $this->assertSame(['$: must be one of ["admin","user","guest"]'], self::errorsOf('root', $schema));
        $this->assertSame(['$: must be one of ["admin","user","guest"]'], self::errorsOf('User', $schema), 'case sensitive');
        $this->assertSame(['$: must be one of ["admin","user","guest"]'], self::errorsOf('', $schema));
    }

    /**
     * @dataProvider strictEnumProvider
     * @param mixed $value A value that loosely equals one of the values of the enum
     * @param array $enum The enum
     */
    public function testAnEnumIsComparedStrictly($value, array $enum): void
    {
        $this->assertCount(1, self::errorsOf($value, ['@enum' => $enum]), 'a value that only loosely equals one of them is refused');
        $this->assertSame([], self::errorsOf($enum[0], ['@enum' => $enum]));
    }

    /**
     * The cases of testAnEnumIsComparedStrictly(): an enum is compared strictly
     * @return array
     */
    public function strictEnumProvider(): array
    {
        return [
            'text and an exponent' => ['1e1', ['10']],
            'text and a number' => ['1', [1, 2]],
            'a number and text' => [1, ['1']],
            'false and zero' => [false, [0]],
            'zero and false' => [0, [false]],
            'a decimal and an integer' => [1.0, [1]],
        ];
    }

    /**
     * An enum of numbers
     * @return void
     */
    public function testAnEnumOfNumbers(): void
    {
        $this->assertSame([], self::errorsOf(2, ['@type' => 'int', '@enum' => [1, 2, 3]]));
        $this->assertSame(['$: must be one of [1,2,3]'], self::errorsOf(4, ['@type' => 'int', '@enum' => [1, 2, 3]]));
    }

    /**
     * An enum given as a single value is a long list
     * @return void
     */
    public function testAnEnumGivenAsASingleValueIsALongList(): void
    {
        $this->assertSame([], self::errorsOf('A', ['@enum' => 'A']));
        $this->assertSame(['$: must be one of ["A"]'], self::errorsOf('B', ['@enum' => 'A']));
    }

    /**
     * The message of an enum keeps the accents
     * @return void
     */
    public function testTheMessageOfAnEnumKeepsTheAccents(): void
    {
        $this->assertSame(['$: must be one of ["é","ção"]'], self::errorsOf('x', ['@enum' => ['é', 'ção']]));
    }

    /**
     * The message of an enum is a JSON list even when the keys are not in order
     * @return void
     */
    public function testTheMessageOfAnEnumIsAJsonListEvenWhenTheKeysAreNotInOrder(): void
    {
        $this->assertSame(['$: must be one of ["a","b"]'], self::errorsOf('x', ['@enum' => [3 => 'a', 7 => 'b']]));
    }

    // ---------------------------------------------------------------- rule nodes: @min and @max

    /**
     * @dataProvider boundsProvider
     * @param mixed $value The value
     * @param array $rules The rules
     * @param array $expected The errors
     */
    public function testBounds($value, array $rules, array $expected): void
    {
        $this->assertSame($expected, self::errorsOf($value, $rules));
    }

    /**
     * The cases of testBounds(): bounds
     * @return array
     */
    public function boundsProvider(): array
    {
        $object = (object) ['a' => 1, 'b' => 2];

        return [
            'a number at the minimum' => [5, ['@type' => 'int', '@min' => 5], []],
            'a number below the minimum' => [4, ['@type' => 'int', '@min' => 5], ['$: value must be >= 5 (got 4)']],
            'a number at the maximum' => [5, ['@type' => 'int', '@max' => 5], []],
            'a number above the maximum' => [6, ['@type' => 'int', '@max' => 5], ['$: value must be <= 5 (got 6)']],
            'a number between' => [5, ['@type' => 'int', '@min' => 1, '@max' => 10], []],
            'a decimal below the minimum' => [2.5, ['@type' => 'number', '@min' => 3], ['$: value must be >= 3 (got 2.5)']],
            'a decimal above the maximum' => [10.5, ['@type' => 'float', '@max' => 10], ['$: value must be <= 10 (got 10.5)']],
            'a negative number' => [-1, ['@type' => 'int', '@min' => 0], ['$: value must be >= 0 (got -1)']],
            'zero at the minimum' => [0, ['@type' => 'int', '@min' => 0], []],

            'a text at the minimum length' => ['abc', ['@type' => 'string', '@min' => 3], []],
            'a text shorter than the minimum' => ['ab', ['@type' => 'string', '@min' => 3], ['$: length must be >= 3 (got 2)']],
            'a text at the maximum length' => ['abcde', ['@type' => 'string', '@max' => 5], []],
            'a text longer than the maximum' => ['abcdef', ['@type' => 'string', '@max' => 5], ['$: length must be <= 5 (got 6)']],
            'an empty text with a minimum of zero' => ['', ['@type' => 'string', '@min' => 0], []],
            'an empty text with a minimum of one' => ['', ['@type' => 'string', '@min' => 1], ['$: length must be >= 1 (got 0)']],
            'a text with a maximum of zero' => ['a', ['@type' => 'string', '@max' => 0], ['$: length must be <= 0 (got 1)']],

            'a list at the minimum' => [[1, 2], ['@type' => ['int'], '@min' => 2], []],
            'a list below the minimum' => [[1], ['@type' => ['int'], '@min' => 2], ['$: item count must be >= 2 (got 1)']],
            'a list at the maximum' => [[1, 2], ['@type' => ['int'], '@max' => 2], []],
            'a list above the maximum' => [[1, 2, 3], ['@type' => ['int'], '@max' => 2], ['$: item count must be <= 2 (got 3)']],
            'an empty list' => [[], ['@type' => ['int'], '@min' => 1], ['$: item count must be >= 1 (got 0)']],

            'a map counts its keys' => [['a' => 1, 'b' => 2, 'c' => 3], ['@type' => 'object', '@max' => 2], ['$: item count must be <= 2 (got 3)']],
            'an object counts its properties' => [$object, ['@type' => 'object', '@min' => 3], ['$: item count must be >= 3 (got 2)']],
            'an object at the maximum' => [$object, ['@type' => 'object', '@max' => 2], []],
        ];
    }

    /**
     * The length of a text counts characters not bytes
     * @return void
     */
    public function testTheLengthOfATextCountsCharactersNotBytes(): void
    {
        if (!function_exists('mb_strlen')) {
            $this->markTestSkipped('mbstring is not loaded');
        }

        $this->assertSame([], self::errorsOf('ção', ['@type' => 'string', '@max' => 3]), 'three characters, five bytes');
        $this->assertSame(['$: length must be <= 2 (got 3)'], self::errorsOf('ção', ['@type' => 'string', '@max' => 2]));
    }

    /**
     * Both bounds are reported independently
     * @return void
     */
    public function testBothBoundsAreReportedIndependently(): void
    {
        $this->assertSame(['$: value must be >= 5 (got 1)'], self::errorsOf(1, ['@type' => 'int', '@min' => 5, '@max' => 10]));
        $this->assertSame(['$: value must be <= 10 (got 20)'], self::errorsOf(20, ['@type' => 'int', '@min' => 5, '@max' => 10]));
    }

    /**
     * @dataProvider noBoundsProvider
     * @param mixed $value A value that has no size
     * @param string $type What the error says it is
     */
    public function testBoundsCannotBeAppliedToWhatHasNoSize($value, string $type): void
    {
        $this->assertSame(['$: @min/@max cannot be applied to ' . $type], self::errorsOf($value, ['@type' => 'mixed', '@min' => 1]));
        $this->assertSame(['$: @min/@max cannot be applied to ' . $type], self::errorsOf($value, ['@type' => 'mixed', '@max' => 1]));
    }

    /**
     * The cases of testBoundsCannotBeAppliedToWhatHasNoSize(): bounds cannot be applied to what has no size
     * @return array
     */
    public function noBoundsProvider(): array
    {
        return ['a boolean' => [true, 'boolean']];
    }

    // ---------------------------------------------------------------- rule nodes: @pattern

    /**
     * A pattern
     * @return void
     */
    public function testAPattern(): void
    {
        $schema = ['@type' => 'string', '@pattern' => '/^[A-Z]{2}\d+$/'];

        $this->assertSame([], self::errorsOf('AB12', $schema));
        $this->assertSame(['$: does not match pattern /^[A-Z]{2}\d+$/'], self::errorsOf('ab12', $schema));
        $this->assertSame(['$: does not match pattern /^[A-Z]{2}\d+$/'], self::errorsOf('', $schema));
    }

    /**
     * A pattern that is not anchored matches anywhere
     * @return void
     */
    public function testAPatternThatIsNotAnchoredMatchesAnywhere(): void
    {
        $this->assertSame([], self::errorsOf('xx-123-yy', ['@type' => 'string', '@pattern' => '/\d{3}/']));
    }

    /**
     * The dollar modifier refuses a trailing new line
     * @return void
     */
    public function testTheDollarModifierRefusesATrailingNewLine(): void
    {
        $this->assertSame([], self::errorsOf('US', ['@type' => 'string', '@pattern' => '/^[A-Z]{2}$/D']));
        $this->assertCount(1, self::errorsOf("US\n", ['@type' => 'string', '@pattern' => '/^[A-Z]{2}$/D']));
        $this->assertSame([], self::errorsOf("US\n", ['@type' => 'string', '@pattern' => '/^[A-Z]{2}$/']), 'without it a trailing new line is let through');
    }

    /**
     * A pattern needs a string
     * @return void
     */
    public function testAPatternNeedsAString(): void
    {
        $this->assertSame(['$: @pattern requires a string, got integer'], self::errorsOf(5, ['@type' => 'mixed', '@pattern' => '/^5$/']));
    }

    /**
     * An invalid pattern is an exception
     * @return void
     */
    public function testAnInvalidPatternIsAnException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex at $: /(unclosed');

        self::validator()->validate('x', ['@type' => 'string', '@pattern' => '/(unclosed']);
    }

    // ---------------------------------------------------------------- rule nodes: @check

    /**
     * A check that returns true accepts
     * @return void
     */
    public function testACheckThatReturnsTrueAccepts(): void
    {
        $this->assertSame([], self::errorsOf('a@b.pt', ['@type' => 'string', '@check' => function ($value) {
            return strpos($value, '@') !== false;
        }]));
    }

    /**
     * A check that returns a message gives that message
     * @return void
     */
    public function testACheckThatReturnsAMessageGivesThatMessage(): void
    {
        $this->assertSame(['$: not a valid e-mail'], self::errorsOf('x', ['@type' => 'string', '@check' => function ($value) {
            return strpos($value, '@') !== false ? true : 'not a valid e-mail';
        }]));
    }

    /**
     * @dataProvider notTrueProvider
     * @param mixed $returned What the check returns
     */
    public function testACheckThatDoesNotReturnTrueIsACustomFailure($returned): void
    {
        $this->assertSame(['$: custom check failed'], self::errorsOf('x', ['@type' => 'string', '@check' => function () use ($returned) {
            return $returned;
        }]));
    }

    /**
     * The cases of testACheckThatDoesNotReturnTrueIsACustomFailure(): a check that does not return true is a custom failure
     * @return array
     */
    public function notTrueProvider(): array
    {
        return ['false' => [false], 'one' => [1], 'null' => [null], 'a list' => [[true]], 'zero' => [0]];
    }

    /**
     * A check gets the value and can be any callable
     * @return void
     */
    public function testACheckGetsTheValueAndCanBeAnyCallable(): void
    {
        $this->assertSame([], self::errorsOf('x', ['@type' => 'string', '@check' => 'is_string']));
        $this->assertSame(['$: custom check failed'], self::errorsOf('x', ['@type' => 'string', '@check' => 'is_int']));
    }

    /**
     * A check that is not callable is an exception
     * @return void
     */
    public function testACheckThatIsNotCallableIsAnException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('@check at $ is not callable');

        self::validator()->validate('x', ['@type' => 'string', '@check' => 'no_such_function_here']);
    }

    // ---------------------------------------------------------------- rule nodes together

    /**
     * The rules report their errors in their order
     * @return void
     */
    public function testTheRulesReportTheirErrorsInTheirOrder(): void
    {
        $errors = self::errorsOf('ab', [
            '@type' => 'string',
            '@enum' => ['abc'],
            '@min' => 3,
            '@pattern' => '/^\d+$/',
            '@check' => function () {
                return 'the check says no';
            },
        ]);

        $this->assertSame([
            '$: must be one of ["abc"]',
            '$: length must be >= 3 (got 2)',
            '$: does not match pattern /^\d+$/',
            '$: the check says no',
        ], $errors);
    }

    /**
     * A rule node inside an object uses the name of the key in its path
     * @return void
     */
    public function testARuleNodeInsideAnObjectUsesTheNameOfTheKeyInItsPath(): void
    {
        $schema = ['id' => ['@type' => 'int', '@min' => 1], 'role' => ['@type' => 'string', '@enum' => ['a', 'b']]];

        $this->assertSame(['$.id: value must be >= 1 (got 0)', '$.role: must be one of ["a","b"]'], self::errorsOf(['id' => 0, 'role' => 'c'], $schema));
    }

    // ---------------------------------------------------------------- validate(), validateJson() and getErrors()

    /**
     * Every validation starts without the errors of the last
     * @return void
     */
    public function testEveryValidationStartsWithoutTheErrorsOfTheLast(): void
    {
        $validator = self::validator();

        $this->assertFalse($validator->validate(5, 'string'));
        $this->assertSame(['$: expected string, got integer'], $validator->getErrors());

        $this->assertTrue($validator->validate('x', 'string'));
        $this->assertSame([], $validator->getErrors());
    }

    /**
     * There are no errors before the first validation
     * @return void
     */
    public function testThereAreNoErrorsBeforeTheFirstValidation(): void
    {
        $this->assertSame([], self::validator()->getErrors());
    }

    /**
     * Validate JSON validates the decoded document
     * @return void
     */
    public function testValidateJsonValidatesTheDecodedDocument(): void
    {
        $validator = self::validator();

        $this->assertTrue($validator->validateJson('{"name":"Ana","tags":["a","b"]}', ['name' => 'string', 'tags' => ['string']]));
        $this->assertFalse($validator->validateJson('{"name":5}', ['name' => 'string']));
        $this->assertSame(['$.name: expected string, got integer'], $validator->getErrors());
    }

    /**
     * Validate JSON objects are objects and empty lists are lists
     * @return void
     */
    public function testValidateJsonObjectsAreObjectsAndEmptyListsAreLists(): void
    {
        $validator = self::validator();

        $this->assertTrue($validator->validateJson('{}', 'object'));
        $this->assertTrue($validator->validateJson('[]', ['string']));
        $this->assertFalse($validator->validateJson('{}', ['string']));
        $this->assertSame(['$: expected list, got object'], $validator->getErrors());
    }

    /**
     * @dataProvider badJsonProvider
     * @param string $json Something that is not JSON
     */
    public function testValidateJsonRefusesInvalidJsonWithItsOwnError(string $json): void
    {
        $validator = self::validator();

        $this->assertFalse($validator->validateJson($json, 'mixed'));
        $this->assertCount(1, $validator->getErrors());
        $this->assertStringStartsWith('Invalid JSON: ', $validator->getErrors()[0]);
    }

    /**
     * The cases of testValidateJsonRefusesInvalidJsonWithItsOwnError(): validate JSON refuses invalid JSON with its own error
     * @return array
     */
    public function badJsonProvider(): array
    {
        return ['empty' => [''], 'text' => ['not json'], 'a trailing comma' => ['{"a":1,}'], 'single quotes' => ["{'a':1}"], 'unclosed' => ['{"a":']];
    }

    /**
     * Validate JSON of null is null not an error
     * @return void
     */
    public function testValidateJsonOfNullIsNullNotAnError(): void
    {
        $validator = self::validator();

        $this->assertTrue($validator->validateJson('null', '?string'));
        $this->assertFalse($validator->validateJson('null', 'string'));
        $this->assertSame(['$: expected string, got NULL'], $validator->getErrors());
    }

    /**
     * The example of the schema syntax as a document
     * @return void
     */
    public function testTheExampleOfTheSchemaSyntaxAsADocument(): void
    {
        $validator = self::validator(false, self::definitions() + ['Order' => ['sku' => ['@type' => 'string', '@pattern' => '/^[A-Z]{2}\d+$/'], 'qty' => ['@type' => 'int', '@min' => 1, '@max' => 100]]]);
        $schema = [
            'id' => ['@type' => 'int', '@min' => 1],
            'name' => ['@type' => 'string', '@min' => 2, '@max' => 50],
            'role' => ['@type' => 'string', '@enum' => ['admin', 'user', 'guest']],
            'nickname' => '?string',
            'tags' => ['@type' => ['string'], '@max' => 5],
            'address?' => '@Address',
            'orders' => ['@Order'],
        ];
        $json = '{"id":0,"name":"Ana","role":"root","nickname":null,"tags":["a","b"],"address":{"street":"Rua X","city":"Porto","zip":"4000"},"orders":[{"sku":"AB12","qty":2},{"sku":"ab12","qty":500}]}';

        $this->assertFalse($validator->validateJson($json, $schema));
        $this->assertSame([
            '$.id: value must be >= 1 (got 0)',
            '$.role: must be one of ["admin","user","guest"]',
            '$.address.zip: does not match pattern /^\d{4}-\d{3}$/',
            '$.orders[1].sku: does not match pattern /^[A-Z]{2}\d+$/',
            '$.orders[1].qty: value must be <= 100 (got 500)',
        ], $validator->getErrors());
    }

    // ---------------------------------------------------------------- the loose scalars

    /**
     * The cases of the loose scalars: the type, the value, and whether it is accepted when the validator is loose
     * @return array
     */
    public function looseScalarsProvider(): array
    {
        return [
            'text <- integer' => ['string', 5, true],
            'text <- float' => ['string', 1.5, true],
            'text <- text' => ['string', 'x', true],
            'text <- boolean' => ['string', true, true],
            'text <- false' => ['string', false, true],
            'text <- list' => ['string', ['x'], false],
            'text <- null' => ['string', null, false],
            'int <- float' => ['int', 10.5, true],
            'int <- text that is a number' => ['int', '10', true],
            'int <- text that is a decimal' => ['int', '10.5', true],
            'int <- text with an exponent' => ['int', '1e3', true],
            'int <- text with a sign' => ['int', '-7', true],
            'int <- text that is not a number' => ['int', 'ten', false],
            'int <- empty text' => ['int', '', true],
            'int <- text of spaces' => ['int', '  ', false],
            'number <- text of spaces' => ['number', ' ', false],
            'int <- text with a space' => ['int', ' 5', false],
            'int <- text with a new line' => ['int', "5\n", false],
            'int <- boolean' => ['int', true, false],
            'int <- list' => ['int', [1], false],
            'number <- text that is a number' => ['number', '68.25', true],
            'number <- text that starts with a point' => ['number', '.5', true],
            'number <- text that ends with a point' => ['number', '5.', false],
            'number <- text that is not a number' => ['number', 'abc', false],
            'number <- text with a comma' => ['number', '1,5', false],
            'number <- boolean' => ['number', false, false],
            'bool <- the text true' => ['bool', 'true', true],
            'bool <- the text false' => ['bool', 'false', true],
            'bool <- the text TRUE' => ['bool', 'TRUE', true],
            'bool <- the text False' => ['bool', 'False', true],
            'bool <- the text with a space' => ['bool', 'true ', false],
            'bool <- the text yes' => ['bool', 'yes', false],
            'bool <- the integer 1' => ['bool', 1, true],
            'bool <- the integer 0' => ['bool', 0, true],
            'bool <- the integer 2' => ['bool', 2, false],
            'bool <- the text 1' => ['bool', '1', false],
            'bool <- the float 1' => ['bool', 1.0, false],
            'number <- empty text' => ['number', '', true],
            'bool <- empty text' => ['bool', '', false],
        ];
    }

    /**
     * @dataProvider looseScalarsProvider
     * @param string $type The type of the schema
     * @param mixed $value The value
     * @param bool $accepted Is it accepted when the validator is loose?
     */
    public function testALooseValidatorAcceptsTheLooserScalarsAndNothingElse(string $type, $value, bool $accepted): void
    {
        $loose = self::validator()->setLooseScalars(true);

        $this->assertSame($accepted, $loose->validate(['v' => $value], ['v' => $type]), json_encode($value) . ' as ' . $type);
    }

    /**
     * @dataProvider looseScalarsProvider
     * @param string $type The type of the schema
     * @param mixed $value The value
     * @param bool $accepted Is it accepted when the validator is loose?
     */
    public function testAValidatorThatIsNotLooseAcceptsOnlyTheTypeOfTheSchema(string $type, $value, bool $accepted): void
    {
        $strict = [
            'string' => is_string($value),
            'int' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'bool' => is_bool($value),
        ][$type];

        $this->assertSame($strict, self::validator()->validate(['v' => $value], ['v' => $type]), json_encode($value) . ' as ' . $type);
    }

    /**
     * The validator is not loose by default, and the setter can turn the mode off again, and returns the validator
     * @return void
     */
    public function testTheValidatorIsNotLooseByDefaultAndTheSetterCanTurnTheModeOffAgainAndReturnsTheValidator(): void
    {
        $validator = self::validator();

        $this->assertFalse($validator->validate(['v' => '5'], ['v' => 'int']));
        $this->assertSame($validator, $validator->setLooseScalars(true));
        $this->assertTrue($validator->validate(['v' => '5'], ['v' => 'int']));
        $this->assertFalse($validator->setLooseScalars(false)->validate(['v' => '5'], ['v' => 'int']));
    }

    /**
     * The loose mode reaches the nested fields, the lists, the references and the unions, and leaves the other rules as strict as ever
     * @return void
     */
    public function testTheLooseModeReachesNestedFieldsListsReferencesAndUnionsAndLeavesTheOtherRulesStrict(): void
    {
        $validator = self::validator(true, [
            'Item' => ['qty' => 'int', 'price' => 'number', 'gift?' => 'bool', 'sku' => ['@type' => 'string', '@pattern' => '/^[A-Z]{2}\d+$/']],
        ])->setLooseScalars(true);

        $this->assertTrue($validator->validate(['items' => [['qty' => '2', 'price' => '9.5', 'gift' => 'true', 'sku' => 'AB1']]], ['items' => ['@Item']]), implode(' | ', $validator->getErrors()));
        $this->assertTrue($validator->validate(['v' => '2'], ['v' => 'int|bool']), 'in a union');
        $this->assertFalse($validator->validate(['v' => 12], ['v' => ['@type' => 'string', '@enum' => ['12']]]), 'an enum compares strictly: the integer 12 is not the text 12');
        $this->assertFalse($validator->validate(['items' => [['qty' => '2', 'price' => '9.5', 'sku' => 12]]], ['items' => ['@Item']]), 'a pattern is checked on the text: a number is not a text that matches');
        $this->assertFalse($validator->validate(['v' => '5'], ['v' => ['@type' => 'int', '@min' => 10]]), 'a bound is checked');
        $this->assertFalse($validator->validate(['v' => 'x'], ['v' => ['@type' => 'int', '@check' => function () {
            return true;
        }]]), 'the type is checked before the check');
    }

    /**
     * Nothing is converted: the loose values stay as they were given
     * @return void
     */
    public function testNothingIsConvertedTheLooseValuesStayAsTheyWereGiven(): void
    {
        $data = ['a' => '5', 'b' => 5.5, 'c' => 'true'];
        $copy = $data;

        self::validator()->setLooseScalars(true)->validate($data, ['a' => 'int', 'b' => 'string', 'c' => 'bool']);

        $this->assertSame($copy, $data);
    }
}
