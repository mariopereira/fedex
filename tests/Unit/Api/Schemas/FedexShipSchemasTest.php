<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexShipSchemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\ShipSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Ship API. They are compared with schemas/ship.json, and an instance of each of the twelve
 * roots is generated from that file and broken one field at a time
 */
final class FedexShipSchemasTest extends TestCase
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
     * The fields that the spec requires and the class makes optional, because the examples of FedEx leave them out: definition.field
     * @var string[]
     */
    private const OPTIONAL_BY_DECISION = [
        'RequestedShipment_1.totalWeight', 'RequestedShipmentVerify.totalWeight', 'CreateTagRequestedShipment.totalWeight',
        'CustomsClearanceDetail.commercialInvoice', 'CustomsClearanceDetail_1.commercialInvoice', 'CustomsClearanceDetail_tag.commercialInvoice',
        'HoldAtLocationDetail.locationId', 'CustomsClearanceDetail.commodities', 'CustomsClearanceDetail_1.commodities', 'CustomsClearanceDetail_tag.commodities',
        'PartyContact.phoneNumber', 'RequestedShipment_1.labelSpecification', 'RequestedShipmentVerify.labelSpecification',
        'CreateTagRequestedShipment.labelSpecification', 'ReturnEmailDetail.allowedSpecialService',
    ];

    /**
     * Is a key of an object of an instance one of those that the class makes optional (see OPTIONAL_BY_DECISION)?
     * @param string $key The key
     * @param array $path The path of the object
     * @return bool
     */
    private static function isOptionalByDecision(string $key, array $path): bool
    {
        if ($key === 'phoneNumber') {
            // the contact of the recipient of the delivery on invoice keeps it required: it is not the contact of the other parties
            return !in_array('deliveryOnInvoiceAcceptanceDetail', $path, true);
        }

        return in_array($key, ['totalWeight', 'commercialInvoice', 'locationId', 'commodities', 'labelSpecification', 'allowedSpecialService'], true);
    }

    /**
     * The names that the twelve roots have in the spec, with the names of the definitions reachable from them
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
        $defs = FedexShipSchemas::definitions();
        $expectedDerived = [];
        $res = [];

        foreach (array_keys(self::reachable()) as $n) {
            $c = $C[$n];
            $cn = Spec::renamed($n);

            if ($n === 'RequestedShipment_tag') {
                // its properties are those of CreateTagRequestedShipment (checked below): it is not a definition of the class
                $res['RequestedShipment_tag is folded into CreateTagRequestedShipment, not a definition of the class'] = isset($defs[$cn]) ? 'present' : true;
                continue;
            }

            if (!isset($defs[$cn])) {
                $res["$n exists"] = 'missing in the class';
                continue;
            }

            $d = $defs[$cn];

            if (isset($c['$ref'])) {
                $res["$n alias"] = ($d === '@' . Spec::renamed(basename($c['$ref']))) ? true : 'not the alias of ' . $c['$ref'];
                continue;
            }

            if ($n === 'CreateTagRequestedShipment') {
                // decided with the owner: RequestedShipment_tag and the pickupDetail of the overlay; the three required keys that the
                // spec never defines are optional values of any type; everything else that the spec requires is required
                $base = $C['RequestedShipment_tag'];
                $undefined = array_values(array_diff($c['required'], array_keys($base['properties']), ['pickupDetail']));
                $expectKeys = array_merge(array_fill_keys(array_keys($base['properties']), null), ['pickupDetail' => null], array_fill_keys($undefined, null));
                $expectRequired = array_values(array_unique(array_merge($base['required'], array_diff($c['required'], $undefined))));
                $expectRequired = array_values(array_diff($expectRequired, ['totalWeight', 'labelSpecification']));
                sort($expectRequired);
                $gotKeys = [];
                $gotRequired = [];

                foreach ($d as $k => $v) {
                    $name = rtrim($k, '?');
                    $gotKeys[$name] = null;

                    if (substr($k, -1) !== '?') {
                        $gotRequired[] = $name;
                    }
                }

                sort($gotRequired);
                $res["$n: the keys are those of RequestedShipment_tag, pickupDetail and the three undefined ones"] = ($gotKeys === $expectKeys) ? true : 'keys differ: ' . json_encode(array_keys($gotKeys));
                $res["$n: the required keys are those of the base and of the spec, without the three undefined ones"] = ($gotRequired === $expectRequired) ? true : json_encode($gotRequired) . ' vs ' . json_encode($expectRequired);
                $res["$n: the three undefined keys are optional and accept any value"] = (json_encode($undefined) === json_encode(['shipTimestamp', 'shippingLabelType', 'specialServicesRequested']) && $d['shipTimestamp?'] === 'mixed' && $d['shippingLabelType?'] === 'mixed' && $d['specialServicesRequested?'] === 'mixed') ? true : json_encode($undefined);
                $res["$n: pickupDetail is required and is the derived definition"] = ($d['pickupDetail'] === '@CreateTagRequestedShipment_pickupDetail') ? true : json_encode($d['pickupDetail'] ?? null);
                $overlay = $c['allOf'][1]['properties']['pickupDetail'];
                $res["$n: pickupDetail requires what the spec overlay requires"] = ($overlay['required'] === ['latestPickupDateTime', 'readyPickupDateTime'] && ($defs['CreateTagRequestedShipment_pickupDetail'] ?? null) === ['readyPickupDateTime' => 'string', 'latestPickupDateTime' => 'string']) ? true : json_encode($defs['CreateTagRequestedShipment_pickupDetail'] ?? null);

                foreach ($base['properties'] as $pn => $p) {
                    $k = $pn . ((in_array($pn, $base['required'], true) && !in_array($pn, ['totalWeight', 'labelSpecification'], true)) ? '' : '?');
                    $res["$n.$pn"] = (isset($d[$k]) && Spec::canonClass($d[$k]) === Spec::canonSpec($p, "$n.$pn")) ? true : 'differs';
                }

                continue;
            }

            if (!isset($c['properties']) && isset($c['allOf']) && Spec::refOf($c) !== null) {
                $res["$n alias (a definition that is only an allOf of one reference)"] = ($d === '@' . Spec::refOf($c)) ? true : json_encode($d);
                continue;
            }

            if (!isset($c['properties'])) {
                // an enum, for example
                $res["$n scalar"] = (Spec::canonClass($d) === Spec::canonSpec($c, $n)) ? true : Spec::canonClass($d) . ' != ' . Spec::canonSpec($c, $n);
                continue;
            }

            $required = $c['required'] ?? [];
            $classKeys = [];

            foreach ($d as $k => $v) {
                $classKeys[substr($k, -1) === '?' ? substr($k, 0, -1) : $k] = substr($k, -1) !== '?';
            }

            $specKeys = [];

            foreach ($c['properties'] as $pn => $p) {
                $specKeys[$pn] = in_array($pn, $required, true) && !in_array("$cn.$pn", self::OPTIONAL_BY_DECISION, true);

                if (Spec::isOverlay($p)) {
                    $expectedDerived[str_replace('.', '_', "$n.$pn")] = true;
                } elseif (($p['type'] ?? '') === 'object' && isset($p['properties'])) {
                    $expectedDerived[str_replace('.', '_', "$n.$pn")] = true;
                } elseif (($p['type'] ?? '') === 'array' && ($p['items']['type'] ?? '') === 'object' && isset($p['items']['properties'])) {
                    $expectedDerived[str_replace('.', '_', "$n.$pn") . '_item'] = true;
                }
            }

            $res["$n keys and required flags"] = ($classKeys === $specKeys) ? true : 'class=' . json_encode($classKeys) . ' spec=' . json_encode($specKeys);

            foreach ($c['properties'] as $pn => $p) {
                $k = $pn . ((in_array($pn, $required, true) && !in_array("$cn.$pn", self::OPTIONAL_BY_DECISION, true)) ? '' : '?');

                if (isset($d[$k])) {
                    $a = Spec::canonClass($d[$k]);
                    $b = Spec::canonSpec($p, "$n.$pn");
                    $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
                }
            }
        }

        // the overlay of the recipient also tightens its address and its contact: two more definitions
        $expectedDerived['DeliveryOnInvoiceAcceptanceDetail_recipient_address'] = true;
        $expectedDerived['DeliveryOnInvoiceAcceptanceDetail_recipient_contact'] = true;
        // the pickupDetail that the overlay of CreateTagRequestedShipment adds
        $expectedDerived['CreateTagRequestedShipment_pickupDetail'] = true;

        self::$conformance = [$res, array_keys($expectedDerived)];

        return self::$conformance;
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has two hundred and forty two definitions reachable from the twelve roots
     * @return void
     */
    public function testTheSpecHasTwoHundredAndFortyTwoDefinitionsReachableFromTheTwelveRoots(): void
    {
        $this->assertCount(242, self::reachable());
    }

    /**
     * The root constants name the roots of the spec
     * @return void
     */
    public function testTheRootConstantsNameTheRootsOfTheSpec(): void
    {
        $defs = FedexShipSchemas::definitions();
        $constants = [FedexShipSchemas::CREATE_REQUEST, FedexShipSchemas::CREATE_RESPONSE, FedexShipSchemas::VALIDATE_REQUEST, FedexShipSchemas::VALIDATE_RESPONSE, FedexShipSchemas::CANCEL_REQUEST, FedexShipSchemas::CANCEL_RESPONSE, FedexShipSchemas::CREATE_TAG_REQUEST, FedexShipSchemas::CREATE_TAG_RESPONSE, FedexShipSchemas::CANCEL_TAG_REQUEST, FedexShipSchemas::CANCEL_TAG_RESPONSE, FedexShipSchemas::RESULTS_REQUEST, FedexShipSchemas::RESULTS_RESPONSE];

        foreach ($constants as $name) {
            $this->assertArrayHasKey($name, $defs);
        }

        $this->assertSame(array_map([Spec::class, 'renamed'], array_values(Spec::ROOTS)), $constants, 'the roots of the spec, with the three hyphens written as underscores');
    }

    /**
     * Every definition of the spec is in the class with the same shape
     * @return void
     */
    public function testEveryDefinitionOfTheSpecIsInTheClassWithTheSameShape(): void
    {
        $this->assertAll('every definition: same keys, same required keys, same types, enums, lengths and references as the spec', self::conformance()[0]);
    }

    /**
     * The class has the definitions of the spec and the derived ones
     * @return void
     */
    public function testTheClassHasTheDefinitionsOfTheSpecAndTheDerivedOnes(): void
    {
        $defs = FedexShipSchemas::definitions();
        $extra = array_values(array_diff(array_keys($defs), array_map([Spec::class, 'renamed'], array_keys(self::reachable()))));
        $expected = self::conformance()[1];
        sort($extra);
        sort($expected);

        // the 242 definitions of the spec, less RequestedShipment_tag, plus the 11 derived ones
        $this->assertCount(252, $defs);
        $this->assertCount(11, $expected);
        $this->assertSame($expected, $extra, 'the extra definitions are exactly the derived ones');
    }

    /**
     * The derived overlays are the base with the required keys of the overlay
     * @return void
     */
    public function testTheDerivedOverlaysAreTheBaseWithTheRequiredKeysOfTheOverlay(): void
    {
        $C = Spec::components();
        $defs = FedexShipSchemas::definitions();
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
            'DeliveryOnInvoiceAcceptanceDetail_recipient' => $compare('DeliveryOnInvoiceAcceptanceDetail_recipient', 'RecipientsParty', ['address', 'contact']),
            'DeliveryOnInvoiceAcceptanceDetail_recipient_address' => $compare('DeliveryOnInvoiceAcceptanceDetail_recipient_address', 'PartyAddress_2', ['countryCode', 'streetLines']),
            'DeliveryOnInvoiceAcceptanceDetail_recipient_contact' => $compare('DeliveryOnInvoiceAcceptanceDetail_recipient_contact', 'PartyContact', ['companyName', 'personName', 'phoneNumber']),
            'VariableHandlingChargeDetail_fixedValue' => $compare('VariableHandlingChargeDetail_fixedValue', 'Money', ['amount', 'currency']),
        ]);

        $this->assertSame('@DeliveryOnInvoiceAcceptanceDetail_recipient_address', $defs['DeliveryOnInvoiceAcceptanceDetail_recipient']['address']);
        $this->assertSame('@DeliveryOnInvoiceAcceptanceDetail_recipient_contact', $defs['DeliveryOnInvoiceAcceptanceDetail_recipient']['contact']);
        $this->assertSame(['address', 'contact'], $C['RecipientsParty']['required'] ?? [], 'RecipientsParty already required them, so the overlay adds nothing there');
    }

    /**
     * Every reference resolves and every definition is used
     * @return void
     */
    public function testEveryReferenceResolvesAndEveryDefinitionIsUsed(): void
    {
        $defs = FedexShipSchemas::definitions();
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

        $unused = array_values(array_diff(array_keys($defs), array_keys($used), array_map([Spec::class, 'renamed'], array_values(Spec::ROOTS))));

        $this->assertSame([], array_values(array_unique($unresolved)), 'every @reference resolves to a definition');
        $this->assertSame([], $unused, 'every definition is used by another one or is one of the twelve roots');
    }

    /**
     * The validator accepts the definitions and their names
     * @return void
     */
    public function testTheValidatorAcceptsTheDefinitionsAndTheirNames(): void
    {
        $defs = FedexShipSchemas::definitions();

        new SchemaValidator(false, $defs);

        $this->assertSame([], array_values(array_filter(array_keys($defs), function ($name) {
            return !preg_match('/^[A-Za-z_]\w*$/D', $name);
        })), 'there is no name that the validator would refuse');
    }

    // ---------------------------------------------------------------- instances generated from the spec

    /**
     * The cases of testTheInstanceGeneratedFromTheSpecIsAccepted(), testEveryLeafIsRefusedWithAWrongTypeAndEveryEnumAndLengthRuleIsEnforced() and testEveryRequiredKeyIsEnforcedEveryOptionalKeyIsOptionalAndUnknownKeysAreRefusedOnlyInRequests(): the instance generated from the spec is accepted
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
                true => new SchemaValidator(false, FedexShipSchemas::definitions()),
                false => new SchemaValidator(true, FedexShipSchemas::definitions()),
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
    public function testTheInstanceGeneratedFromTheSpecIsAccepted(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $validator = new SchemaValidator(!$isRequest, FedexShipSchemas::definitions());

        $accepted = $validator->validate($instance, '@' . Spec::renamed($root));

        $this->assertTrue($accepted, implode(' | ', array_slice($validator->getErrors(), 0, 3)));
        $this->assertNotEmpty($meta['leaves']);
        $this->assertNotEmpty($meta['objects']);
    }

    /**
     * About 8 seconds: it validates a large instance thousands of times. Leave it out with --exclude-group slow
     * @group slow
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root, as named in the spec
     * @param bool $isRequest Is it a request?
     */
    public function testEveryLeafIsRefusedWithAWrongTypeAndEveryEnumAndLengthRuleIsEnforced(string $label, string $root, bool $isRequest): void
    {
        [$instance, $meta] = Spec::instance($label);
        $wrong = ['string' => 123, 'integer' => 'x', 'number' => 'x', 'boolean' => 'x'];
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
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

            if ($leaf['max'] !== null) {
                // a value that is also an enum is checked by the enum above: 'xxxx' is not one of its values
                $atLimit = ($leaf['enum'] !== null) ? $leaf['enum'][0] : str_repeat('x', $leaf['max']);
                $res["$key at the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], $atLimit), $root, $isRequest) ? true : 'refused at the limit';
                $res["$key over the length limit"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], str_repeat('x', $leaf['max'] + 1)), $root, $isRequest) ? 'accepted' : true;
            }
        }

        $this->assertAll("$label: the leaves (" . count($meta['leaves']) . ')', $res);
    }

    /**
     * About 5 seconds: it validates a large instance thousands of times. Leave it out with --exclude-group slow
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
                if (self::isOptionalByDecision($required, $object['path'])) {
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
