<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api\Schemas;

use Mariopereira\Fedex\Api\Schemas\FedexBasicTrackingSchemas as Schemas;
use Mariopereira\Fedex\Misc\SchemaValidator;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\TrackSpec as Spec;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the definitions of the Basic Tracking API: they are compared with schemas/track.json, which is read here
 * independently of the code that generated them, field by field. The differences from the file are listed one by one
 */
final class FedexBasicTrackingSchemasTest extends TestCase
{
    use BatchAssertions;

    /**
     * The constants of the class for each root of the spec
     * @var string[]
     */
    private const CONSTANTS = [
        'trackingnumbers.request' => 'TRACKING_NUMBERS_REQUEST', 'trackingnumbers.response' => 'TRACKING_NUMBERS_RESPONSE',
        'associated.request' => 'ASSOCIATED_REQUEST', 'associated.response' => 'ASSOCIATED_RESPONSE',
        'references.request' => 'REFERENCES_REQUEST', 'references.response' => 'REFERENCES_RESPONSE',
        'tcn.request' => 'TCN_REQUEST', 'tcn.response' => 'TCN_RESPONSE',
        'documents.request' => 'DOCUMENTS_REQUEST', 'documents.response' => 'DOCUMENTS_RESPONSE',
        'notification.request' => 'NOTIFICATION_REQUEST', 'notification.response' => 'NOTIFICATION_RESPONSE',
    ];

    /**
     * The fields that the class has and the spec does not: they are accepted on purpose
     * @var string[]
     */
    private const EXTRA_FIELDS = [
        'ReferenceInformation.shipDateEnd' => 'string',
        'AddressVO.addressVerificationId' => 'mixed',
    ];

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
     * The fields of a definition of the class, without the question mark of the optional ones
     * @param mixed $definition A definition of the class
     * @return array Name => node, and the names of the required fields in the key 'required'
     */
    private static function fieldsOf($definition): array
    {
        $fields = [];
        $required = [];

        foreach ($definition as $key => $node) {
            $optional = substr($key, -1) === '?';
            $name = $optional ? substr($key, 0, -1) : $key;
            $fields[$name] = $node;

            if (!$optional) {
                $required[] = $name;
            }
        }

        return [$fields, $required];
    }

    /**
     * The constants are the roots of the spec
     * @return void
     */
    public function testTheConstantsAreTheRootsOfTheSpec(): void
    {
        $components = Spec::components();

        foreach (Spec::ROOTS as $label => $root) {
            $constant = constant(Schemas::class . '::' . self::CONSTANTS[$label]);

            $this->assertSame($root, $constant, $label);
            $this->assertArrayHasKey($constant, $components, "$label is a definition of the spec");
            $this->assertArrayHasKey($constant, self::definitions(), "$label is a definition of the class");
        }

        $this->assertCount(12, self::CONSTANTS);
        $this->assertCount(12, (new \ReflectionClass(Schemas::class))->getConstants(), 'the class has no other constant');
    }

    /**
     * The roots are the requests and responses of the paths of the spec
     * @return void
     */
    public function testTheRootsAreTheRequestsAndResponsesOfThePathsOfTheSpec(): void
    {
        $byPath = [
            '/track/v1/trackingnumbers' => ['trackingnumbers.request', 'trackingnumbers.response'],
            '/track/v1/associatedshipments' => ['associated.request', 'associated.response'],
            '/track/v1/referencenumbers' => ['references.request', 'references.response'],
            '/track/v1/tcn' => ['tcn.request', 'tcn.response'],
            '/track/v1/trackingdocuments' => ['documents.request', 'documents.response'],
            '/track/v1/notifications' => ['notification.request', 'notification.response'],
        ];
        $paths = Spec::spec()['paths'];

        $implemented = array_keys($byPath);
        $inSpec = array_keys($paths);
        sort($implemented);
        sort($inSpec);

        $this->assertSame($implemented, $inSpec, 'every path of the spec is implemented, and nothing else');

        foreach ($byPath as $path => [$request, $response]) {
            $operation = $paths[$path]['post'];

            $this->assertSame(['post'], array_keys($paths[$path]), "$path has only POST");
            $this->assertSame('#/components/schemas/' . Spec::ROOTS[$request], $operation['requestBody']['content']['application/json']['schema']['oneOf'][0]['$ref'], $path);
            $this->assertSame('#/components/schemas/' . Spec::ROOTS[$response], $operation['responses']['200']['content']['application/json']['schema']['$ref'], $path);
        }
    }

    /**
     * The definitions are exactly the ones that the twelve roots need
     * @return void
     */
    public function testTheDefinitionsAreExactlyTheOnesThatTheTwelveRootsNeed(): void
    {
        $needed = [];

        foreach (Spec::ROOTS as $root) {
            Spec::closure($root, $needed);
        }

        $expected = array_keys($needed);
        $actual = array_keys(self::definitions());
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
        $this->assertCount(85, $actual);
    }

    /**
     * Every reference of a definition is a definition
     * @return void
     */
    public function testEveryReferenceOfADefinitionIsADefinition(): void
    {
        $definitions = self::definitions();
        $results = [];

        $walk = function ($node, string $where) use (&$walk, $definitions, &$results) {
            if (is_string($node)) {
                preg_match_all('/@([A-Za-z_]\w*)/', $node, $found);

                foreach ($found[1] as $name) {
                    $results["$where -> $name"] = isset($definitions[$name]) ? true : 'not defined';
                }

                return;
            }

            if (!is_array($node)) {
                return;
            }

            foreach ($node as $key => $child) {
                $walk($child, "$where/" . $key);
            }
        };

        foreach ($definitions as $name => $definition) {
            $walk($definition, $name);
        }

        $this->assertAll('the references of the definitions', $results);
    }

    /**
     * Every field of every definition says what the spec says, apart from the listed differences
     * @return void
     */
    public function testEveryFieldOfEveryDefinitionSaysWhatTheSpecSaysApartFromTheListedDifferences(): void
    {
        $components = Spec::components();
        $results = [];
        $differences = [];

        foreach (self::definitions() as $name => $definition) {
            $spec = $components[$name];

            if (!is_array($definition) || !isset($spec['properties'])) {
                continue;
            }

            [$fields] = self::fieldsOf($definition);

            foreach ($spec['properties'] as $property => $schema) {
                $class = isset($fields[$property]) ? Spec::canonClass($fields[$property]) : 'MISSING';
                $text = Spec::canonSpec($schema, "$name.$property");

                $results["$name.$property"] = ($class === $text) ? true : "the class says '$class', the spec says '$text'";
            }

            foreach (array_diff(array_keys($fields), array_keys($spec['properties'])) as $extra) {
                $differences["$name.$extra"] = Spec::canonClass($fields[$extra]);
            }
        }

        $this->assertAll('the fields of the definitions', $results);
        $expected = self::EXTRA_FIELDS;
        ksort($expected);
        ksort($differences);

        $this->assertSame($expected, $differences, 'the fields that the class has and the spec does not');

    }

    /**
     * The required fields are the ones of the spec, apart from the ones that the spec never defines
     * @return void
     */
    public function testTheRequiredFieldsAreTheOnesOfTheSpecApartFromTheOnesThatTheSpecNeverDefines(): void
    {
        $components = Spec::components();
        $results = [];
        $undefined = [];

        foreach (self::definitions() as $name => $definition) {
            $spec = $components[$name];

            if (!is_array($definition) || !isset($spec['properties'])) {
                continue;
            }

            [, $required] = self::fieldsOf($definition);
            $expected = [];

            foreach ($spec['required'] ?? [] as $key) {
                if (isset($spec['properties'][$key])) {
                    $expected[] = $key;
                } else {
                    $undefined[] = "$name.$key";
                }
            }

            // the class does not require includeDetailedScans of the tracking numbers: the examples of FedEx leave it out
            if ($name === 'Full_Schema_Tracking_Numbers') {
                $expected = array_values(array_diff($expected, ['includeDetailedScans']));
            }

            // the class requires shipDateEnd of the references, which the spec never defines, and not the shipDateEndDate that the spec requires
            if ($name === 'ReferenceInformation') {
                $expected = array_values(array_merge(array_diff($expected, ['shipDateEndDate']), ['shipDateEnd']));
            }

            sort($required);
            sort($expected);
            $results[$name] = ($required === $expected) ? true : 'required ' . json_encode($required) . ', the spec requires ' . json_encode($expected);
        }

        $this->assertAll('the required fields of the definitions', $results);
        $this->assertSame(['AddressVO.addressVerificationId'], $undefined, 'the required fields that the spec never defines, in the definitions of the class');
    }

    /**
     * The facts of the spec that the differences rest on are still in the spec
     * @return void
     */
    public function testTheFactsOfTheSpecThatTheDifferencesRestOnAreStillInTheSpec(): void
    {
        $c = Spec::components();
        $reference = $c['ReferenceInformation'];

        $this->assertContains('shipDateEndDate', $reference['required']);
        $this->assertArrayHasKey('shipDateEndDate', $reference['properties']);
        $this->assertArrayNotHasKey('shipDateEnd', $reference['properties'], 'the spec does not define shipDateEnd for the references');
        $this->assertArrayHasKey('shipDateEnd', $c['Full_Schema_Tracking_References']['example']['referencesInformation'], 'but its example uses it');
        $this->assertArrayNotHasKey('shipDateEndDate', $c['Full_Schema_Tracking_References']['example']['referencesInformation']);

        foreach (['AddressVO', 'AddressVO_1'] as $name) {
            $this->assertContains('addressVerificationId', $c[$name]['required'], $name);
            $this->assertArrayNotHasKey('addressVerificationId', $c[$name]['properties'], "$name never defines it");
        }

        $this->assertSame(['SNOW', 'TORNADO', 'EARTHQUAKE etc'], $c['DelayDetail']['properties']['subType']['enum']);
        $this->assertContains('USPS_DELIVERING"', $c['ServiceCommitMessage']['properties']['type']['enum']);
        $this->assertStringContainsString('Maximum limit is 30', $c['Full_Schema_Tracking_Numbers']['properties']['trackingInfo']['description']);
        $this->assertArrayNotHasKey('maxItems', $c['Full_Schema_Tracking_Numbers']['properties']['trackingInfo'], 'the 30 is only in the description');
    }

    /**
     * The three examples of the spec are accepted, apart from what the differences allow
     * @return void
     */
    public function testTheThreeExamplesOfTheSpecAreAccepted(): void
    {
        $results = [];

        foreach (['associated.request', 'references.request', 'tcn.request'] as $label) {
            $root = Spec::ROOTS[$label];
            $example = json_decode(json_encode(Spec::components()[$root]['example']), true);

            if ($label === 'references.request') {
                // the example writes shipDateEnd, which the class requires, and not the shipDateEndDate that the spec requires
                $this->assertArrayNotHasKey('shipDateEndDate', $example['referencesInformation']);
                // and the example has includeDetailedScans as the text "true", where the schema says boolean
                $this->assertSame('true', $example['includeDetailedScans']);
                $example['includeDetailedScans'] = true;
            }

            $validator = new SchemaValidator(false, self::definitions());
            $results[$label] = $validator->validate($example, '@' . $root) ? true : implode(' | ', $validator->getErrors());
        }

        $this->assertAll('the examples of the spec', $results);
    }

    /**
     * The example of the references without shipDateEnd is refused, because it is the required one, even with the shipDateEndDate that
     * the spec requires
     * @return void
     */
    public function testTheExampleOfTheReferencesWithoutShipDateEndIsRefusedBecauseItIsTheRequiredOne(): void
    {
        $example = json_decode(json_encode(Spec::components()['Full_Schema_Tracking_References']['example']), true);
        $example['includeDetailedScans'] = true;
        $validator = new SchemaValidator(false, self::definitions());

        $this->assertTrue($validator->validate($example, '@Full_Schema_Tracking_References'), implode(' | ', $validator->getErrors()));

        $withDate = $example;
        $withDate['referencesInformation']['shipDateEndDate'] = $example['referencesInformation']['shipDateEnd'];
        unset($withDate['referencesInformation']['shipDateEnd']);

        $this->assertFalse($validator->validate($withDate, '@Full_Schema_Tracking_References'));
        $this->assertStringContainsString('shipDateEnd: required key is missing', implode(' | ', $validator->getErrors()));

        $withDate['referencesInformation']['shipDateEnd'] = '2019-02-13';

        $this->assertTrue($validator->validate($withDate, '@Full_Schema_Tracking_References'), implode(' | ', $validator->getErrors()));
    }

    /**
     * The instances generated from the spec are accepted: the requests strictly and the responses leniently
     * @return void
     */
    public function testTheInstancesGeneratedFromTheSpecAreAccepted(): void
    {
        $results = [];

        foreach (Spec::ROOTS as $label => $root) {
            $instance = json_decode(json_encode(Spec::instance($label)[0]), true);
            $validator = new SchemaValidator(substr($label, -8) === 'response', self::definitions());

            $results[$label] = $validator->validate($instance, '@' . $root) ? true : implode(' | ', array_slice($validator->getErrors(), 0, 3));
        }

        $this->assertAll('the generated instances', $results);
    }

    /**
     * A field of a generated instance with a value of the wrong type is refused, and an enum with a value outside it
     * @return void
     */
    public function testAFieldOfAGeneratedInstanceWithAValueOfTheWrongTypeIsRefusedAndAnEnumWithAValueOutsideIt(): void
    {
        $results = [];

        foreach (Spec::ROOTS as $label => $root) {
            [$instance, $meta] = Spec::instance($label);
            $lenient = substr($label, -8) === 'response';

            foreach ($meta['leaves'] as $leaf) {
                if (!$leaf['typed']) {
                    continue; // the spec gives no type: any value is valid
                }

                $where = $label . ' ' . implode('.', $leaf['path']);
                $wrong = ['string' => 12345, 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'][$leaf['type']];
                $results["$where as $leaf[type]"] = $this->refusedWith($instance, $leaf['path'], $wrong, $lenient, $root);

                if (is_array($leaf['enum'])) {
                    $name = $this->definitionOf($label, $leaf['path']);

                    if (in_array($name, Spec::TEXT_ENUMS, true)) {
                        $results["$where outside its enum, checked as text"] = ($this->refusedWith($instance, $leaf['path'], 'NOT_IN_THE_ENUM', $lenient, $root) === true)
                            ? 'the enum is checked, and it is defective in the spec' : true;
                        continue;
                    }

                    $results["$where outside its enum"] = $this->refusedWith($instance, $leaf['path'], 'NOT_IN_THE_ENUM', $lenient, $root);
                }
            }
        }

        $this->assertAll('the values that are not valid', $results);
    }

    /**
     * Check that an instance with a value changed is refused
     * @param array $instance The instance
     * @param array $path Where to change it
     * @param mixed $value The value
     * @param bool $lenient True for a response
     * @param string $root The definition of the root
     * @return bool|string True when it was refused, why not otherwise
     */
    private function refusedWith(array $instance, array $path, $value, bool $lenient, string $root)
    {
        $copy = json_decode(json_encode($instance), true);
        $node = &$copy;

        foreach ($path as $key) {
            $node = &$node[$key];
        }

        $node = $value;
        $validator = new SchemaValidator($lenient, self::definitions());

        return $validator->validate($copy, '@' . $root) ? 'it was accepted' : true;
    }

    /**
     * The definition and the field of a leaf, in the form of TrackSpec::TEXT_ENUMS
     * @param string $label The root
     * @param array $path The path of the leaf in the instance
     * @return string
     */
    private function definitionOf(string $label, array $path): string
    {
        $components = Spec::components();
        $definition = Spec::ROOTS[$label];
        $field = '';

        foreach ($path as $key) {
            if (is_int($key)) {
                continue;
            }

            // a definition that is only a reference to another one stands for it
            while (!isset($components[$definition]['properties']) && (Spec::refOf($components[$definition]) !== null)) {
                $definition = Spec::refOf($components[$definition]);
            }

            $property = $components[$definition]['properties'][$key] ?? null;
            $field = $key;
            $ref = ($property === null) ? null : Spec::refOf($property);

            if ($ref === null && $property !== null && ($property['type'] ?? '') === 'array') {
                $ref = Spec::refOf($property['items']);
            }

            if ($ref !== null) {
                $definition = $ref;
            } else {
                return "$definition.$field";
            }
        }

        return "$definition.$field";
    }

    /**
     * A required field that is missing is refused, and so is a field that the schema does not have in a request
     * @return void
     */
    public function testARequiredFieldThatIsMissingIsRefusedAndSoIsAFieldThatTheSchemaDoesNotHaveInARequest(): void
    {
        $results = [];

        foreach (Spec::ROOTS as $label => $root) {
            [$instance, $meta] = Spec::instance($label);
            $lenient = substr($label, -8) === 'response';

            foreach ($meta['objects'] as $object) {
                foreach ($object['required'] as $key) {
                    if ($key === 'includeDetailedScans' && $object['path'] === [] && $label === 'trackingnumbers.request') {
                        continue; // optional in the class: the examples of FedEx leave it out
                    }

                    $copy = json_decode(json_encode($instance), true);
                    $node = &$copy;

                    foreach ($object['path'] as $step) {
                        $node = &$node[$step];
                    }

                    unset($node[$key]);
                    $validator = new SchemaValidator($lenient, self::definitions());
                    $results["$label " . implode('.', array_merge($object['path'], [$key])) . ' missing'] = $validator->validate($copy, '@' . $root) ? 'it was accepted' : true;
                    unset($node);
                }

                if (!$lenient) {
                    $copy = json_decode(json_encode($instance), true);
                    $node = &$copy;

                    foreach ($object['path'] as $step) {
                        $node = &$node[$step];
                    }

                    $node['notInTheSchema'] = 1;
                    $validator = new SchemaValidator(false, self::definitions());
                    $results["$label " . implode('.', $object['path']) . ' with a field of its own'] = $validator->validate($copy, '@' . $root) ? 'it was accepted' : true;
                    unset($node);
                }
            }
        }

        $this->assertAll('the fields that are missing or that the schema does not have', $results);
    }

    /**
     * A field that FedEx adds to a response is tolerated
     * @return void
     */
    public function testAFieldThatFedexAddsToAResponseIsTolerated(): void
    {
        $results = [];

        foreach (Spec::ROOTS as $label => $root) {
            if (substr($label, -8) !== 'response') {
                continue;
            }

            [$instance, $meta] = Spec::instance($label);

            foreach ($meta['objects'] as $object) {
                $copy = json_decode(json_encode($instance), true);
                $node = &$copy;

                foreach ($object['path'] as $step) {
                    $node = &$node[$step];
                }

                $node['addedByFedex'] = ['x' => 1];
                $validator = new SchemaValidator(true, self::definitions());
                $results["$label " . implode('.', $object['path'])] = $validator->validate($copy, '@' . $root) ? true : implode(' | ', array_slice($validator->getErrors(), 0, 2));
                unset($node);
            }
        }

        $this->assertAll('the fields that FedEx adds', $results);
    }

    /**
     * The fields that the spec does not define are accepted with a value of any type
     * @return void
     */
    public function testTheFieldsThatTheSpecDoesNotDefineAreAcceptedWithAValueOfAnyType(): void
    {
        $results = [];
        [$response] = Spec::instance('trackingnumbers.response');
        $address = ['streetLines' => ['1 Main St'], 'countryCode' => 'US'];

        foreach ([null, 1, 1.5, true, 'text', ['a'], ['k' => 'v']] as $value) {
            $validator = new SchemaValidator(true, self::definitions());
            $results['addressVerificationId ' . json_encode($value)] = $validator->validate($address + ['addressVerificationId' => $value], '@AddressVO') ? true : implode(' | ', $validator->getErrors());
        }

        $validator = new SchemaValidator(true, self::definitions());
        $results['AddressVO without it'] = $validator->validate($address, '@AddressVO') ? true : implode(' | ', $validator->getErrors());

        $validator = new SchemaValidator(false, self::definitions());
        $request = ['referencesInformation' => ['value' => 'v', 'shipDateBegin' => '2020-01-01', 'shipDateEndDate' => '2020-01-02', 'shipDateEnd' => '2020-01-03']];
        $results['both dates of the references'] = $validator->validate($request, '@Full_Schema_Tracking_References') ? true : implode(' | ', $validator->getErrors());

        $request['referencesInformation']['shipDateEnd'] = 5;
        $results['shipDateEnd is text'] = $validator->validate($request, '@Full_Schema_Tracking_References') ? 'a number was accepted' : true;

        $request['referencesInformation']['shipDateEnd'] = '2020-01-03';
        $request['referencesInformation']['shipDateEndDate'] = 5;
        $results['shipDateEndDate is text'] = $validator->validate($request, '@Full_Schema_Tracking_References') ? 'a number was accepted' : true;

        $this->assertIsArray($response);
        $this->assertAll('the fields that the spec does not define', $results);
    }

    /**
     * The two enums that are defective in the spec are checked as text, and the others are not
     * @return void
     */
    public function testTheTwoEnumsThatAreDefectiveInTheSpecAreCheckedAsTextAndTheOthersAreNot(): void
    {
        $definitions = self::definitions();

        $this->assertSame('string', $definitions['DelayDetail']['subType?']);
        $this->assertSame('string', $definitions['ServiceCommitMessage']['type?']);
        $this->assertSame(['@type' => 'string', '@enum' => ['WEATHER', 'OPERATIONAL', 'LOCAL', 'GENERAL', 'CLEARANCE']], $definitions['DelayDetail']['type?']);
        $this->assertSame(['@type' => 'string', '@enum' => ['DELAYED', 'ON_TIME', 'EARLY']], $definitions['DelayDetail']['status?']);

        $validator = new SchemaValidator(true, $definitions);

        $this->assertTrue($validator->validate(['subType' => 'WIND', 'type' => 'WEATHER'], '@DelayDetail'), implode(' | ', $validator->getErrors()));
        $this->assertTrue($validator->validate(['type' => 'USPS_DELIVERING'], '@ServiceCommitMessage'), implode(' | ', $validator->getErrors()));
        $this->assertFalse($validator->validate(['type' => 'SUNSHINE'], '@DelayDetail'));
        $this->assertFalse($validator->validate(['subType' => 5], '@DelayDetail'), 'it is still text');
    }

    /**
     * The carrier code and the associated type are the enums of the spec
     * @return void
     */
    public function testTheCarrierCodeAndTheAssociatedTypeAreTheEnumsOfTheSpec(): void
    {
        $components = Spec::components();
        $definitions = self::definitions();

        $this->assertSame($components['TrackingNumberInfo']['properties']['carrierCode']['enum'], $definitions['TrackingNumberInfo']['carrierCode?']['@enum']);
        $this->assertCount(19, $definitions['TrackingNumberInfo']['carrierCode?']['@enum']);
        $this->assertSame(['OUTBOUND_LINK_TO_RETURN', 'STANDARD_MPS', 'GROUP_MPS'], $definitions['Full_Schema_Multiple_Piece_Shipment']['associatedType']['@enum']);
    }

    /**
     * The values that are only described in the spec are checked as text, not as a list that the spec does not have
     * @return void
     */
    public function testTheValuesThatAreOnlyDescribedInTheSpecAreCheckedAsText(): void
    {
        $definitions = self::definitions();

        $this->assertSame('string', $definitions['ReferenceInformation']['type?']);
        $this->assertSame('string', $definitions['TrackDocumentDetail']['documentType']);
        $this->assertSame('string', $definitions['TrackDocumentDetail']['documentFormat?']);
        $this->assertSame('string', $definitions['TrackingNotificationDetail']['notificationType']);
        $this->assertSame('string', $definitions['TrackingNotification']['role?']);
        $this->assertSame(['string'], $definitions['TrackingNotification']['notificationEventTypes']);
        $this->assertSame('string', $definitions['Localization']['languageCode']);
    }

    /**
     * The document of the response is a list of text
     * @return void
     */
    public function testTheDocumentOfTheResponseIsAListOfText(): void
    {
        $validator = new SchemaValidator(true, self::definitions());

        $this->assertTrue($validator->validate(['document' => ['QUJD', 'REVG']], '@SPODResponseVO'), implode(' | ', $validator->getErrors()));
        $this->assertFalse($validator->validate(['document' => 'QUJD'], '@SPODResponseVO'), 'one text is not a list');
        $this->assertFalse($validator->validate(['document' => [1]], '@SPODResponseVO'));
    }
}
