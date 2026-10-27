<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexEndOfDayCloseSchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\EndOfDayCloseSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Ship End of Day Close API. They are compared with schemas/close.json, which is read here
 * independently of the code that generated them, and an instance of each root is generated from that file and broken one field
 * at a time. The definitions have no difference from the file
 */
final class FedexEndOfDayCloseSchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The result of comparing the definitions with the spec
     * @var array|null
     */
    private static $conformance;

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

            if (!isset($defs[$n])) {
                $res["$n exists"] = 'missing in the class';
                continue;
            }

            $d = $defs[$n];

            if (isset($c['$ref'])) {
                $res["$n alias"] = ($d === '@' . basename($c['$ref'])) ? true : 'not the alias of ' . $c['$ref'];
                continue;
            }

            if (!isset($c['properties']) && isset($c['allOf']) && Spec::refOf($c) !== null) {
                $res["$n alias (a definition that is only an allOf of one reference)"] = ($d === '@' . Spec::refOf($c)) ? true : json_encode($d);
                continue;
            }

            if (!isset($c['properties'])) {
                $res["$n scalar or list"] = (Spec::canonClass($d) === Spec::canonSpec($c, $n)) ? true : Spec::canonClass($d) . ' != ' . Spec::canonSpec($c, $n);
                continue;
            }

            $required = $c['required'] ?? [];
            $classKeys = [];

            foreach ($d as $k => $v) {
                $classKeys[substr($k, -1) === '?' ? substr($k, 0, -1) : $k] = substr($k, -1) !== '?';
            }

            $specKeys = [];

            foreach ($c['properties'] as $pn => $p) {
                $specKeys[$pn] = in_array($pn, $required, true);
            }

            $res["$n keys and required flags"] = ($classKeys === $specKeys) ? true : 'class=' . json_encode($classKeys) . ' spec=' . json_encode($specKeys);

            foreach ($c['properties'] as $pn => $p) {
                $k = $pn . ($specKeys[$pn] ? '' : '?');

                if (!isset($d[$k])) {
                    continue;
                }

                $a = Spec::canonClass($d[$k]);
                $b = Spec::canonSpec($p, "$n.$pn");
                $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
            }
        }

        self::$conformance = $res;

        return self::$conformance;
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has fourteen definitions reachable from the roots, and the class has exactly those
     * @return void
     */
    public function testTheSpecHasFourteenDefinitionsReachableFromTheRootsAndTheClassHasExactlyThose(): void
    {
        $defined = array_keys(Schemas::definitions());
        $reachable = array_keys(self::reachable());
        sort($defined);
        sort($reachable);

        $this->assertCount(14, $reachable);
        $this->assertSame($reachable, $defined);
    }

    /**
     * The root constants name the roots of the spec: the requests and the response of the PUT (close) and the POST (reprint) of its only path
     * @return void
     */
    public function testTheRootConstantsNameTheRootsOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];
        $path = '/ship/v1/endofday/';
        $operations = ['put' => [Schemas::CLOSE_REQUEST, Schemas::CLOSE_RESPONSE], 'post' => [Schemas::REPRINT_REQUEST, Schemas::REPRINT_RESPONSE]];

        $this->assertSame([$path], array_keys($paths));
        $this->assertSame(['put', 'post'], array_keys($paths[$path]));

        foreach ($operations as $verb => [$request, $response]) {
            $operation = $paths[$path][$verb];
            $body = basename($operation['requestBody']['content']['application/json']['schema']['$ref']);

            $this->assertSame('#/components/schemas/' . $request, Spec::components()[$body]['oneOf'][0]['$ref'], "$verb: the real schema is the first of the oneOf");
            $this->assertSame('#/components/schemas/' . $response, $operation['responses']['200']['content']['application/json']['schema']['$ref'] ?? $operation['responses']['200']['$ref'], $verb);
        }

        $this->assertSame([Spec::ROOTS['close.request'], Spec::ROOTS['close.response'], Spec::ROOTS['reprint.request'], Spec::ROOTS['reprint.response']], [Schemas::CLOSE_REQUEST, Schemas::CLOSE_RESPONSE, Schemas::REPRINT_REQUEST, Schemas::REPRINT_RESPONSE]);
        $this->assertCount(4, (new \ReflectionClass(Schemas::class))->getConstants(), 'the roots: no other constant');
        $this->assertTrue((new \ReflectionClass(Schemas::class))->isFinal());
    }

    /**
     * Every definition of the spec is in the class with the same shape
     * @return void
     */
    public function testEveryDefinitionOfTheSpecIsInTheClassWithTheSameShape(): void
    {
        $this->assertAll('every definition: same keys, same required keys, same types, enums and references as the spec', self::conformance());
    }

    /**
     * Neither request requires a field, as in the spec
     * @return void
     */
    public function testNeitherRequestRequiresAField(): void
    {
        $C = Spec::components();
        $validator = new SchemaValidator(false, Schemas::definitions());

        foreach ([Schemas::CLOSE_REQUEST, Schemas::REPRINT_REQUEST] as $root) {
            $this->assertArrayNotHasKey('required', $C[$root], "$root requires nothing in the spec");
            $this->assertTrue($validator->validate([], '@' . $root), $root);
        }
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
        $this->assertSame([], array_values(array_diff(array_keys($defs), array_keys($used), array_values(Spec::ROOTS))));
    }

    // ---------------------------------------------------------------- the samples and the instances

    /**
     * The samples of the spec send the account number as an integer: only the loose validator, which the class uses, accepts them
     * @return void
     */
    public function testTheSamplesOfTheSpecAreAcceptedByTheLooseValidatorOnly(): void
    {
        $results = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = json_decode(json_encode(Spec::components()[$sample]['example']), true);
            $strict = new SchemaValidator(false, Schemas::definitions());
            $loose = (new SchemaValidator(true, Schemas::definitions()))->setLooseScalars(true);

            $results["$sample sends an integer"] = is_int($example['accountNumber']['value']) ? true : 'not an integer';
            $results["$sample refused by the strict validator"] = $strict->validate($example, '@' . Spec::ROOTS[$label]) ? 'accepted' : true;
            $results["$sample accepted by the loose validator"] = $loose->validate($example, '@' . Spec::ROOTS[$label]) ? true : implode(' | ', $loose->getErrors());
        }

        $this->assertCount(2, Spec::SAMPLES);
        $this->assertAll('the samples', $results);
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
