<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexRateTransitTimesSchemas as Schemas;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\RateSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Rates and Transit Times API. They are compared with schemas/rate.json, which is read here
 * independently of the code that generated them, and an instance of each of the two roots is generated from that file and broken
 * one field at a time. The differences from the file are listed one by one
 */
final class FedexRateTransitTimesSchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The result of comparing the definitions with the spec: the cases, and the derived definitions that the spec calls for
     * @var array|null
     */
    private static $conformance;

    /**
     * The definitions that are used by no other definition and are not a root: the base of a definition that is made of a base
     * and an overlay
     * @var string[]
     */
    private const UNUSED = ['DeliveryOnInvoiceAcceptanceDetailRecipient_address', 'DeliveryOnInvoiceAcceptanceDetailRecipient_contact', 'Dimensions_1', 'Party'];

    /**
     * The names of the definitions reachable from the two roots
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
     * The names of the definitions that the request reaches
     * @return array The names as keys
     */
    private static function requestSide(): array
    {
        $seen = [];

        Spec::closure(Spec::ROOTS['rates.request'], $seen);

        return $seen;
    }

    /**
     * Is a definition, or one derived from a definition, reached by the request?
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
            $undefined = array_values(array_diff($required, array_keys($c['properties'])));
            $classKeys = [];

            foreach ($d as $k => $v) {
                $classKeys[substr($k, -1) === '?' ? substr($k, 0, -1) : $k] = substr($k, -1) !== '?';
            }

            $specKeys = [];

            foreach ($c['properties'] as $pn => $p) {
                $specKeys[$pn] = in_array($pn, $required, true);

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
                $k = $pn . (in_array($pn, $required, true) ? '' : '?');

                if (!isset($d[$k])) {
                    continue;
                }

                $a = Spec::canonClass($d[$k]);
                $b = Spec::canonSpec($p, "$n.$pn");

                // in the definitions that the request reaches a number is a number or a text that is a number, and a postal code a text or an integer
                if ($b === 'number' && $a === 'number|string' && self::isRequestSide($n)) {
                    $a = 'number';
                }

                if ($b === 'string' && $a === 'string|int' && $pn === 'postalCode' && self::isRequestSide($n)) {
                    $a = 'string';
                }

                $res["$n.$pn"] = ($a === $b) ? true : "class '$a' spec '$b'";
            }
        }

        self::$conformance = [$res, array_keys($expectedDerived)];

        return self::$conformance;
    }

    // ---------------------------------------------------------------- the definitions against the spec

    /**
     * The spec has a hundred and eleven definitions reachable from the two roots
     * @return void
     */
    public function testTheSpecHasAHundredAndElevenDefinitionsReachableFromTheTwoRoots(): void
    {
        $this->assertCount(111, self::reachable());
    }

    /**
     * The root constants name the roots of the spec
     * @return void
     */
    public function testTheRootConstantsNameTheRootsOfTheSpec(): void
    {
        $defs = Schemas::definitions();

        $this->assertSame(Spec::ROOTS['rates.request'], Schemas::REQUEST);
        $this->assertSame(Spec::ROOTS['rates.response'], Schemas::RESPONSE);
        $this->assertArrayHasKey(Schemas::REQUEST, $defs);
        $this->assertArrayHasKey(Schemas::RESPONSE, $defs);
        $this->assertCount(2, (new \ReflectionClass(Schemas::class))->getConstants(), 'the two roots: no other constant');
        $this->assertTrue((new \ReflectionClass(Schemas::class))->isFinal());
    }

    /**
     * The roots are the request and the response of the only path of the spec
     * @return void
     */
    public function testTheRootsAreTheRequestAndTheResponseOfTheOnlyPathOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];
        $operation = $paths['/rate/v1/rates/quotes']['post'];

        $this->assertSame(['/rate/v1/rates/quotes'], array_keys($paths));
        $this->assertSame(['post'], array_keys($paths['/rate/v1/rates/quotes']));
        $this->assertSame('#/components/schemas/body', $operation['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/' . Schemas::REQUEST, Spec::components()['body']['oneOf'][0]['$ref'], 'the real schema is the first of the oneOf');
        $this->assertSame('#/components/schemas/' . Schemas::RESPONSE, $operation['responses']['200']['content']['application/json']['schema']['$ref'] ?? $operation['responses']['200']['$ref']);
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
        $this->assertSame([
            'DeliveryOnInvoiceAcceptanceDetailRecipient_address_overlay', 'DeliveryOnInvoiceAcceptanceDetailRecipient_contact_overlay', 'EdtTaxDetail_1_appliedPreferentialTradeAgreement',
            'EdtTaxDetail_1_taxRates_item', 'Payor_responsibleParty', 'RatedShipmentDetail_pickupRateDetail',
        ], $extra);
        $this->assertCount(117, $defs);
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

        $this->assertSame([], $required('Money'), 'the overlay of Money requires nothing');
        $this->assertSame(['units', 'value'], $required('Weight'));
        $this->assertSame(['countryCode', 'postalCode'], $required('RateAddress'));
        $this->assertSame(['address', 'contact'], $required('BrokerDetailBroker'));
        $this->assertSame(['address', 'contact'], $required('DeliveryOnInvoiceAcceptanceDetailRecipient'));
        $this->assertSame(['countryCode'], $required('DeliveryOnInvoiceAcceptanceDetailRecipient_address_overlay'));
        $this->assertSame(['companyName', 'personName', 'phoneNumber'], $required('DeliveryOnInvoiceAcceptanceDetailRecipient_contact_overlay'));
        $this->assertSame('@DeliveryOnInvoiceAcceptanceDetailRecipient_address_overlay', $defs['DeliveryOnInvoiceAcceptanceDetailRecipient']['address']);
        $this->assertSame('@DeliveryOnInvoiceAcceptanceDetailRecipient_contact_overlay', $defs['DeliveryOnInvoiceAcceptanceDetailRecipient']['contact']);
    }

    /**
     * The address of the delivery on invoice recipient has the street lines that the overlay of the spec defines and the base does not
     * @return void
     */
    public function testTheAddressOfTheDeliveryOnInvoiceRecipientHasTheStreetLinesThatTheOverlayDefinesAndTheBaseDoesNot(): void
    {
        $defs = Schemas::definitions();
        $overlay = Spec::components()['DeliveryOnInvoiceAcceptanceDetailRecipient_address'];

        $this->assertArrayHasKey('streetLines', $overlay['properties']);
        $this->assertSame(['string'], $defs['DeliveryOnInvoiceAcceptanceDetailRecipient_address_overlay']['streetLines?']);
        $this->assertArrayNotHasKey('streetLines?', $defs['RateAddress']);
    }

    /**
     * The contact of a broker is a value of any type: the overlay of the spec has it with a description and no type
     * @return void
     */
    public function testTheContactOfABrokerIsAValueOfAnyTypeBecauseTheOverlayOfTheSpecHasItWithADescriptionAndNoType(): void
    {
        $defs = Schemas::definitions();
        $overlay = Spec::components()['BrokerDetailBroker']['allOf'][1];

        $this->assertArrayNotHasKey('type', $overlay['properties']['contact']);
        $this->assertSame('mixed', $defs['BrokerDetailBroker']['contact']);
        $this->assertArrayNotHasKey('contact', $defs['BrokerDetailBroker_RateReply'], 'the overlay of the reply only says address');
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

        $this->assertSame('string', $C['RateAddress']['allOf'][1]['properties']['postalCode']['type'] ?? $C['Address']['properties']['postalCode']['type'], 'the schema says text for the postal code');
        $this->assertIsInt($C['US_Domestic_Rate_shop']['example']['requestedShipment']['shipper']['address']['postalCode'], 'and the samples send an integer');
        $this->assertIsString($C['MinimumSamplePayload_1']['example']['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'], 'the samples send the weight as text');
        $this->assertNull($C['International_Priority_Rate_Quote']['example']['requestedShipment']['customsClearanceDetail']['dutiesPayment']['payor']['responsibleParty'], 'and some a null where an object is');
        $this->assertArrayHasKey('freightShipmentDetail', $C['FreightSamplePayload-RateResourceDetails_1']['example']['requestedShipment'], 'the sample for Freight has fields that the schema does not list');
        $this->assertArrayNotHasKey('accountNumber', $C['FDGSamplePayload-RateResourceDetails-IntraEurope']['example'], 'the sample for Intra Europe has no account number');
        $this->assertCount(23, $C['body']['oneOf'], 'the real schema and twenty two samples');

        foreach (array_slice($C['body']['oneOf'], 1) as $sample) {
            $this->assertSame(['example'], array_keys($C[basename($sample['$ref'])]), basename($sample['$ref']) . ' is only an example, so the oneOf says nothing');
        }
    }

    // ---------------------------------------------------------------- the numbers and the postal codes

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
     * The numbers of the request, and of the definitions that the response shares, are numbers or numbers as text, and the others are numbers
     * @return void
     */
    public function testTheNumbersOfTheRequestAreNumbersOrNumbersAsTextAndTheOthersAreNumbers(): void
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

        $this->assertSame([
            'AncillaryFeesAndTaxes.amount', 'CurrencyExchangeRate.rate', 'EdtTaxDetail.amount',
            'EdtTaxDetail.taxableValue', 'EdtTaxDetail_1_taxRates_item.quantity', 'PackageRateDetail.baseCharge',
            'PackageRateDetail.netCharge', 'PackageRateDetail.netFedExCharge', 'PackageRateDetail.netFreight',
            'PackageRateDetail.totalFreightDiscounts', 'PackageRateDetail.totalRebates', 'PackageRateDetail.totalSurcharges',
            'PackageRateDetail.totalTaxes', 'RateDiscount.amount', 'RateDiscount.percent',
            'RateDiscount_1.amount', 'RateDiscount_1.percent', 'RateDiscount_2.percent',
            'RatedPackageDetail.effectiveNetDiscount', 'RatedShipmentDetail.totalAncillaryFeesAndTaxes', 'RatedShipmentDetail.totalBaseCharge',
            'RatedShipmentDetail.totalDiscounts', 'RatedShipmentDetail.totalDutiesAndTaxes', 'RatedShipmentDetail.totalDutiesTaxesAndFees',
            'RatedShipmentDetail.totalNetCharge', 'RatedShipmentDetail.totalNetChargeWithDutiesAndTaxes', 'RatedShipmentDetail.totalNetFedExCharge',
            'RatedShipmentDetail.totalVatCharge', 'RatedShipmentDetail_pickupRateDetail.fuelSurchargePercent', 'Rebate.percent',
            'ShipmentLegRateDetail_1.totalBaseCharge', 'ShipmentLegRateDetail_1.totalNetCharge', 'ShipmentRateDetail.fuelSurchargePercent',
            'ShipmentRateDetail.totalFreightDiscount', 'ShipmentRateDetail.totalSurcharges', 'Surcharge.amount',
            'Surcharge_1.amount', 'Tax.amount', 'Tax_1.amount',
            'VariableHandlingCharges.totalCustomerCharge', 'VariableHandlingCharges.variableHandlingCharge', 'Weight.value',
            'Weight_1.value',
        ], $strict, 'the numbers that only the response reaches are numbers');
        $this->assertCount(8, $relaxed);

        foreach ($relaxed as $field) {
            $this->assertTrue(self::isRequestSide(explode('.', $field)[0]), "$field is reached by the request");
        }
    }

    /**
     * The postal codes of the request are a text or an integer, and the others are texts
     * @return void
     */
    public function testThePostalCodesOfTheRequestAreATextOrAnIntegerAndTheOthersAreTexts(): void
    {
        $defs = Schemas::definitions();
        $request = [];
        $response = [];

        foreach ($defs as $name => $definition) {
            foreach (is_array($definition) ? $definition : [] as $key => $node) {
                if (rtrim($key, '?') !== 'postalCode') {
                    continue;
                }

                if (self::isRequestSide($name)) {
                    $request[$name] = $node;
                } else {
                    $response[$name] = $node;
                }
            }
        }

        $this->assertSame(['Address', 'Address_1', 'Address_2', 'RateAddress', 'DeliveryOnInvoiceAcceptanceDetailRecipient_address_overlay'], array_keys($request));
        $this->assertSame(['string|int'], array_values(array_unique($request)));
        $this->assertSame(['Brokeraddress' => 'string'], $response);
    }

    /**
     * The postal code of an address can be a text or an integer, and nothing else, and the weight a number or a text that is a number
     * @return void
     */
    public function testThePostalCodeOfAnAddressCanBeATextOrAnIntegerAndNothingElse(): void
    {
        $validator = new SchemaValidator(true, Schemas::definitions());
        $results = [];

        foreach (['75063', 75063, '', 'M5V 2T6'] as $value) {
            $results['accepted ' . json_encode($value)] = $validator->validate(['postalCode' => $value, 'countryCode' => 'US'], '@RateAddress') ? true : implode(' | ', $validator->getErrors());
        }

        foreach ([1.5, true, ['75063'], new \stdClass()] as $value) {
            $results['refused ' . json_encode($value)] = $validator->validate(['postalCode' => $value, 'countryCode' => 'US'], '@RateAddress') ? 'accepted' : true;
        }

        foreach (['10', 10, 10.5, '10.5'] as $value) {
            $results['weight ' . json_encode($value)] = $validator->validate(['units' => 'LB', 'value' => $value], '@Weight_2') ? true : implode(' | ', $validator->getErrors());
        }

        foreach (['abc', '', '1,5', true, [1]] as $value) {
            $results['weight refused ' . json_encode($value)] = $validator->validate(['units' => 'LB', 'value' => $value], '@Weight_2') ? 'accepted' : true;
        }

        $this->assertAll('the postal codes and the weights', $results);
    }

    // ---------------------------------------------------------------- the samples and the instances

    /**
     * The sample payloads of the spec are accepted as they are sent (a null is left out), except the two that the schema contradicts
     * @return void
     */
    public function testTheSamplePayloadsOfTheSpecAreAcceptedAsTheyAreSentExceptTheTwoThatTheSchemaContradicts(): void
    {
        $results = [];
        $refused = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = FedexUtils::pruneEmpty(json_decode(json_encode(Spec::components()[$sample]['example']), true));
            $validator = new SchemaValidator(true, Schemas::definitions());

            if ($validator->validate($example, '@' . Spec::ROOTS[$label])) {
                $results[$sample] = true;
            } else {
                $refused[$sample] = $validator->getErrors();
            }
        }

        $this->assertCount(22, Spec::SAMPLES);
        $this->assertAll('the sample payloads that are accepted', $results);
        $this->assertCount(20, $results);
        ksort($refused);
        $this->assertSame(['FDGSamplePayload-RateResourceDetails-IntraEurope', 'FreightSamplePayload-RateResourceDetails_1'], array_keys($refused));
        $this->assertSame(['$.accountNumber: required key is missing'], $refused['FDGSamplePayload-RateResourceDetails-IntraEurope']);
        $this->assertSame(['$.requestedShipment.requestedPackageLineItems[0].groupPackageCount: expected int, got string'], $refused['FreightSamplePayload-RateResourceDetails_1']);
    }

    /**
     * The cases of the tests of the instances generated from the spec
     * @return array
     */
    public function rootsProvider(): array
    {
        $cases = [];

        foreach (Spec::ROOTS as $label => $root) {
            $cases[$label] = [$label, $root];
        }

        return $cases;
    }

    /**
     * Does the validator accept the data as an instance of the root? Both are lenient: the request allows keys that the schema does not define
     * @param array $data The data
     * @param string $root The root
     * @return bool
     */
    private static function accepts(array $data, string $root): bool
    {
        static $validator = null;

        $validator = $validator ?? new SchemaValidator(true, Schemas::definitions());

        return $validator->validate($data, '@' . $root);
    }

    /**
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root
     */
    public function testTheInstanceGeneratedFromTheSpecIsAccepted(string $label, string $root): void
    {
        [$instance, $meta] = Spec::instance($label);
        $validator = new SchemaValidator(true, Schemas::definitions());

        $this->assertTrue($validator->validate($instance, '@' . $root), implode(' | ', array_slice($validator->getErrors(), 0, 3)));
        $this->assertNotEmpty($meta['leaves']);
        $this->assertNotEmpty($meta['objects']);
    }

    /**
     * @dataProvider rootsProvider
     * @param string $label The label of the root
     * @param string $root The root
     */
    public function testEveryLeafIsRefusedWithAWrongTypeAndEveryEnumRuleIsEnforced(string $label, string $root): void
    {
        [$instance, $meta] = Spec::instance($label);
        $wrong = ['string' => ['x'], 'integer' => 'x', 'number' => 'x', 'boolean' => 'x'];
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue; // the spec gives no type: any value is valid
            }

            $key = implode('/', $leaf['path']);
            $res["$key wrong type"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], $wrong[$leaf['type']]), $root) ? 'accepted' : true;

            if ($leaf['enum'] !== null) {
                $res["$key bad enum"] = self::accepts(SpecInstances::setAt($instance, $leaf['path'], 'NOT_A_VALUE'), $root) ? 'accepted' : true;
                $all = true;

                foreach ($leaf['enum'] as $value) {
                    $all = $all && self::accepts(SpecInstances::setAt($instance, $leaf['path'], $value), $root);
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
     */
    public function testEveryRequiredKeyIsEnforcedEveryOptionalKeyIsOptionalAndUnknownKeysAreAccepted(string $label, string $root): void
    {
        [$instance, $meta] = Spec::instance($label);
        $res = [];

        foreach ($meta['objects'] as $object) {
            $key = implode('/', $object['path']) ?: '(root)';

            foreach ($object['required'] as $required) {
                $res["$key without required $required"] = self::accepts(SpecInstances::unsetAt($instance, array_merge($object['path'], [$required])), $root) ? 'accepted' : true;
            }

            foreach (array_diff($object['keys'], $object['required']) as $optional) {
                $res["$key without optional $optional"] = self::accepts(SpecInstances::unsetAt($instance, array_merge($object['path'], [$optional])), $root) ? true : 'refused';
            }

            $withExtra = SpecInstances::setAt($instance, $object['path'], array_merge(SpecInstances::getAt($instance, $object['path']), ['notInTheSchema' => 1]));
            $res["$key with an unknown key"] = self::accepts($withExtra, $root) ? true : 'refused';
        }

        $this->assertAll("$label: the keys of " . count($meta['objects']) . ' objects', $res);
    }
}
