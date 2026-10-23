<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexServiceAvailability;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexServiceAvailabilityException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\ServiceAvailabilitySpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Service Availability API: what the three operations send, how they check the request and the response, the
 * convenience methods, and what they repeat
 */
final class FedexServiceAvailabilityTest extends TestCase
{
    use BatchAssertions;

    /**
     * The transport of the last object made by api()
     * @var FakeTransport
     */
    private $transport;

    /**
     * The authorization of the last object made by api()
     * @var StubAuth
     */
    private $auth;

    /**
     * A FedexServiceAvailability over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexServiceAvailability
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexServiceAvailability
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexServiceAvailability($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The three operations: the label of the spec, the method, the endpoint, the method that makes the empty request
     * @return array
     */
    public function operationsProvider(): array
    {
        return [
            'transit times' => ['transittimes', 'getTransitTimes', 'availability/v1/transittimes', 'newTransitTimesRequest'],
            'package and service options' => ['packageoptions', 'getPackageAndServiceOptions', 'availability/v1/packageandserviceoptions', 'newPackageAndServiceOptionsRequest'],
            'special service options' => ['specialoptions', 'getSpecialServiceOptions', 'availability/v1/specialserviceoptions', 'newSpecialServiceOptionsRequest'],
        ];
    }

    /**
     * Run something and give the exception it throws
     * @param callable $fn What to run
     * @return \Throwable|null
     */
    private static function thrown(callable $fn): ?\Throwable
    {
        try {
            $fn();

            return null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * Describe an exception, for the message of a failed case
     * @param \Throwable|null $e The exception
     * @return string
     */
    private static function describe(?\Throwable $e): string
    {
        return ($e === null) ? 'no exception' : get_class($e) . ' ' . $e->getCode() . ' ' . $e->getMessage() . ($e instanceof FedexException ? ' ' . implode(' | ', array_map('strval', $e->getErrors())) : '');
    }

    /**
     * The example of a sample of the spec, as an array
     * @param string $sample The name of the sample
     * @return array
     */
    private static function sample(string $sample): array
    {
        return json_decode(json_encode(Spec::components()[$sample]['example']), true);
    }

    /**
     * The class extends the base of the API classes, has its own exception and the endpoints of the spec
     * @return void
     */
    public function testTheClassExtendsTheBaseOfTheApiClassesHasItsOwnExceptionAndTheEndpointsOfTheSpec(): void
    {
        $this->assertInstanceOf(AbstractFedexApi::class, $this->api());
        $this->assertInstanceOf(FedexException::class, new FedexServiceAvailabilityException('x'));
        $this->assertSame('availability/v1/transittimes', FedexServiceAvailability::ENDPOINT_TRANSIT_TIMES);
        $this->assertSame('availability/v1/packageandserviceoptions', FedexServiceAvailability::ENDPOINT_PACKAGE_OPTIONS);
        $this->assertSame('availability/v1/specialserviceoptions', FedexServiceAvailability::ENDPOINT_SPECIAL_OPTIONS);

        foreach ([FedexServiceAvailability::ENDPOINT_TRANSIT_TIMES, FedexServiceAvailability::ENDPOINT_PACKAGE_OPTIONS, FedexServiceAvailability::ENDPOINT_SPECIAL_OPTIONS] as $endpoint) {
            $this->assertArrayHasKey('/' . $endpoint, Spec::spec()['paths']);
        }
    }

    // ---------------------------------------------------------------- what the operations send

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $endpoint The endpoint
     */
    public function testEachOperationSendsOnePostWithTheGeneratedRequest(string $label, string $method, string $endpoint): void
    {
        $request = Spec::instance($label . '.request')[0];
        $api = $this->api();

        $result = $api->$method($request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/' . $endpoint, $calls[0]['url']);
        $this->assertEquals($request, $this->transport->bodyOf(0));
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('T1', $result->transactionId);
    }

    /**
     * The samples of the spec are sent as they are, to the operation they belong to
     * @return void
     */
    public function testTheSamplesOfTheSpecAreSentAsTheyAreToTheOperationTheyBelongTo(): void
    {
        $methods = ['transittimes' => 'getTransitTimes', 'packageoptions' => 'getPackageAndServiceOptions', 'specialoptions' => 'getSpecialServiceOptions'];
        $results = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $api = $this->api();
            $method = $methods[explode('.', $label)[0]];
            $example = self::sample($sample);
            $e = self::thrown(function () use ($api, $method, $example) {
                $api->$method($example);
            });

            $results[$sample] = ($e === null && $this->transport->bodyOf(0) === $example) ? true : self::describe($e);
        }

        $this->assertCount(8, $results);
        $this->assertAll('the samples of the spec', $results);
    }

    /**
     * Each operation sends the headers of the spec and a UUID that is different in every call
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testEachOperationSendsTheHeadersOfTheSpecAndAUuidThatIsDifferentInEveryCall(string $label, string $method): void
    {
        $api = $this->api();
        $request = Spec::instance($label . '.request')[0];

        $api->$method($request);
        $api->$method($request);
        $ids = [];

        foreach ($this->transport->calls as $call) {
            $this->assertContains('Authorization: Bearer TOK', $call['headers']);
            $this->assertContains('X-locale: en_US', $call['headers']);
            $this->assertContains('Content-Type: application/json', $call['headers']);
            $this->assertContains('Accept: application/json', $call['headers']);
            $found = array_values(preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $call['headers']));
            $this->assertCount(1, $found);
            $ids[] = $found[0];
        }

        $this->assertNotSame($ids[0], $ids[1]);
    }

    /**
     * The production url is used when the authorization is not the sandbox
     * @return void
     */
    public function testTheProductionUrlIsUsedWhenTheAuthorizationIsNotTheSandbox(): void
    {
        $api = $this->api([], null, new StubAuth(false, 'client_credentials', 'id', 'secret'));

        $api->getTransitTimes(self::sample('TransitTimes_Domestic'));

        $this->assertSame('https://apis.fedex.com/availability/v1/transittimes', $this->transport->calls[0]['url']);
    }

    /**
     * An operation takes an object as well as an array
     * @return void
     */
    public function testAnOperationTakesAnObjectAsWellAsAnArray(): void
    {
        $request = self::sample('Package_and_Service_Options_Domestic');
        $api = $this->api();

        $api->getPackageAndServiceOptions(json_decode(json_encode($request)));

        $this->assertSame($request, $this->transport->bodyOf(0));
    }

    /**
     * A request that is not an array or an object is refused with its own message
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testARequestThatIsNotAnArrayOrAnObjectIsRefusedWithItsOwnMessage(string $label, string $method): void
    {
        $api = $this->api();

        foreach ([null, 'text', 5, 1.5, true] as $request) {
            $e = self::thrown(function () use ($api, $method, $request) {
                $api->$method($request);
            });

            $this->assertInstanceOf(FedexServiceAvailabilityException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * An unknown key is refused before anything is sent, not even the token request
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testAnUnknownKeyIsRefusedBeforeAnythingIsSentNotEvenTheTokenRequest(string $label, string $method): void
    {
        $api = $this->api();
        $request = Spec::instance($label . '.request')[0];

        foreach ([['notInTheSchema' => 1], ['requestedShipment' => ['shipper' => ['address' => ['city' => 'Memphis', 'unknown' => 1]]]]] as $extra) {
            $e = self::thrown(function () use ($api, $method, $request, $extra) {
                $api->$method(array_replace_recursive($request, $extra));
            });

            $this->assertInstanceOf(FedexServiceAvailabilityException::class, $e, self::describe($e));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('Invalid request', $e->getMessage());
            $this->assertNotEmpty($e->getErrors());
        }

        $this->assertSame([], $this->transport->calls);
        $this->assertSame([], $this->auth->forceSeen, 'no token was requested');
    }

    /**
     * The empty requests are the required keys of the spec, and are refused until they are filled in
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $endpoint The endpoint
     * @param string $new The method that makes the empty request
     */
    public function testTheEmptyRequestsAreTheRequiredKeysOfTheSpecAndAreRefusedUntilTheyAreFilledIn(string $label, string $method, string $endpoint, string $new): void
    {
        $api = $this->api();
        $empty = FedexServiceAvailability::$new();

        $this->assertArrayHasKey('requestedShipment', $empty);

        $e = self::thrown(function () use ($api, $method, $empty) {
            $api->$method($empty);
        });

        if ($label === 'transittimes') {
            // the schema requires nothing at the top of this request, and the empty one is only nulls and empty lists, so nothing is left
            $this->assertNull($e, 'the schema does not require anything of the request');
            $this->assertSame([], $this->transport->bodyOf(0));
        } else {
            $this->assertInstanceOf(FedexServiceAvailabilityException::class, $e, self::describe($e));
            $this->assertNotEmpty($e->getErrors());
            $this->assertSame([], $this->transport->calls);
        }

        // the keys that the spec requires of the request (it requires none of the transit times)
        $root = Spec::components()[Spec::ROOTS[$label . '.request']];

        foreach ($root['required'] ?? [] as $key) {
            $this->assertArrayHasKey($key, $empty);
        }

        $this->assertSame($label !== 'transittimes', isset($root['required']));

        if ($label !== 'transittimes') {
            $this->assertEquals(Spec::requiredOnly($root, [Spec::ROOTS[$label . '.request']]), $empty, 'the empty request is the required keys of the spec');
        }
    }

    /**
     * The empty requests, filled in, are accepted
     * @return void
     */
    public function testTheEmptyRequestsFilledInAreAccepted(): void
    {
        $api = $this->api();

        $request = FedexServiceAvailability::newTransitTimesRequest();
        $request['requestedShipment']['shipper']['address'] = ['postalCode' => '38117', 'countryCode' => 'US'];
        $request['requestedShipment']['recipients'][] = ['address' => ['postalCode' => '75201', 'countryCode' => 'US']];
        $request['requestedShipment']['packagingType'] = 'YOUR_PACKAGING';
        $request['requestedShipment']['requestedPackageLineItems'][] = ['weight' => ['units' => 'LB', 'value' => 10]];
        $api->getTransitTimes($request);

        $request = FedexServiceAvailability::newPackageAndServiceOptionsRequest();
        $request['requestedShipment']['shipper']['address']['countryCode'] = 'US';
        $request['requestedShipment']['recipients'][] = ['address' => ['countryCode' => 'CA']];
        $api->getPackageAndServiceOptions($request);

        $request = FedexServiceAvailability::newSpecialServiceOptionsRequest();
        $request['requestedShipment']['shipper']['address']['countryCode'] = 'US';
        $request['requestedShipment']['recipient']['address']['countryCode'] = 'CA';
        $request['requestedShipment']['packagingType'] = 'YOUR_PACKAGING';
        $request['requestedShipment']['requestedPackageLineItems'][] = ['weight' => ['units' => 'LB', 'value' => '10']];
        $api->getSpecialServiceOptions($request);

        $this->assertCount(3, $this->transport->calls);
        $this->assertSame(['postalCode' => '38117', 'countryCode' => 'US'], $this->transport->bodyOf(0)['requestedShipment']['shipper']['address']);
        $this->assertSame(['address' => ['countryCode' => 'CA']], $this->transport->bodyOf(2)['requestedShipment']['recipient']);
    }

    /**
     * Every required field that is missing is refused and nothing is sent
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testEveryRequiredFieldThatIsMissingIsRefusedAndNothingIsSent(string $label, string $method): void
    {
        [$request, $meta] = Spec::instance($label . '.request');
        $results = [];

        foreach ($meta['objects'] as $object) {
            foreach ($object['required'] as $key) {
                if ($object['path'] !== [] && count($object['keys']) === 1) {
                    continue; // the object is left empty and is pruned, and an optional object that is not there is fine
                }

                $copy = SpecInstances::unsetAt($request, array_merge($object['path'], [$key]));
                $api = $this->api();
                $e = self::thrown(function () use ($api, $method, $copy) {
                    $api->$method($copy);
                });
                $results[implode('.', array_merge($object['path'], [$key]))] = ($e instanceof FedexServiceAvailabilityException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the required fields', $results);
    }

    /**
     * A value of the wrong type in any leaf is refused and nothing is sent
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testAValueOfTheWrongTypeInAnyLeafIsRefusedAndNothingIsSent(string $label, string $method): void
    {
        [$request, $meta] = Spec::instance($label . '.request');
        $wrong = ['string' => 12345, 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $copy = SpecInstances::setAt($request, $leaf['path'], $wrong[$leaf['type']]);
            $api = $this->api();
            $e = self::thrown(function () use ($api, $method, $copy) {
                $api->$method($copy);
            });
            $results[implode('.', $leaf['path'])] = ($e instanceof FedexServiceAvailabilityException && $this->transport->calls === []) ? true : self::describe($e);
        }

        $this->assertAll('the values of the wrong type', $results);
    }

    /**
     * A number can be a number or a text that is a number, and any other text is refused
     * @return void
     */
    public function testANumberCanBeANumberOrATextThatIsANumberAndAnyOtherTextIsRefused(): void
    {
        foreach ([10, 10.5, '10', '10.5', '+7', '1e3'] as $value) {
            $request = self::sample('TransitTimes_Domestic');
            $request['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'] = $value;
            $api = $this->api();

            $api->getTransitTimes($request);

            $this->assertSame($value, $this->transport->bodyOf(0)['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'], 'it is sent as it was given');
        }

        foreach (['abc', '1,5', ' 5', true, [1]] as $value) {
            $request = self::sample('TransitTimes_Domestic');
            $request['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'] = $value;
            $api = $this->api();
            $e = self::thrown(function () use ($api, $request) {
                $api->getTransitTimes($request);
            });

            $this->assertInstanceOf(FedexServiceAvailabilityException::class, $e, json_encode($value));
            $this->assertStringContainsString('weight.value', implode(' ', $e->getErrors()));
            $this->assertSame([], $this->transport->calls);
        }
    }

    /**
     * Null and empty lists are left out of the request
     * @return void
     */
    public function testNullAndEmptyListsAreLeftOut(): void
    {
        $api = $this->api();
        $request = self::sample('Package_and_Service_Options_Domestic');
        $expected = $request;
        $request['version'] = null;
        $request['carrierCodes'] = [];
        unset($expected['version'], $expected['carrierCodes']);

        $api->getPackageAndServiceOptions($request);

        $this->assertSame($expected, $this->transport->bodyOf(0));
    }

    // ---------------------------------------------------------------- the convenience methods

    /**
     * The transit times between two addresses make their request
     * @return void
     */
    public function testTheTransitTimesBetweenTwoAddressesMakeTheirRequest(): void
    {
        $api = $this->api();

        $result = $api->getTransitTimesBetween(['postalCode' => '38117', 'countryCode' => 'US'], ['postalCode' => '75201', 'countryCode' => 'US'], 10.5, 'KG', 'FEDEX_BOX', ['FDXE', 'FDXG'], ['version' => ['major' => 1]]);

        $this->assertSame('T1', $result->transactionId);
        $this->assertSame('https://apis-sandbox.fedex.com/availability/v1/transittimes', $this->transport->calls[0]['url']);
        $this->assertSame([
            'version' => ['major' => 1],
            'requestedShipment' => [
                'shipper' => ['address' => ['postalCode' => '38117', 'countryCode' => 'US']],
                'recipients' => [['address' => ['postalCode' => '75201', 'countryCode' => 'US']]],
                'packagingType' => 'FEDEX_BOX',
                'requestedPackageLineItems' => [['weight' => ['units' => 'KG', 'value' => 10.5]]],
            ],
            'carrierCodes' => ['FDXE', 'FDXG'],
        ], $this->transport->bodyOf(0));
    }

    /**
     * The defaults of the transit times between two addresses are pounds, your packaging and no carriers
     * @return void
     */
    public function testTheDefaultsOfTheTransitTimesBetweenTwoAddressesArePoundsYourPackagingAndNoCarriers(): void
    {
        $api = $this->api();

        $api->getTransitTimesBetween(['postalCode' => '38117', 'countryCode' => 'US'], ['postalCode' => '75201', 'countryCode' => 'US'], '12');

        $body = $this->transport->bodyOf(0);

        $this->assertArrayNotHasKey('carrierCodes', $body);
        $this->assertSame('YOUR_PACKAGING', $body['requestedShipment']['packagingType']);
        $this->assertSame(['units' => 'LB', 'value' => '12'], $body['requestedShipment']['requestedPackageLineItems'][0]['weight']);
    }

    /**
     * The package and service options between two addresses make their request
     * @return void
     */
    public function testThePackageAndServiceOptionsBetweenTwoAddressesMakeTheirRequest(): void
    {
        $api = $this->api();

        $api->getPackageAndServiceOptionsBetween(['countryCode' => 'US'], ['countryCode' => 'CA'], ['FDXE'], ['accountNumber' => ['value' => '123456789']]);

        $this->assertSame('https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions', $this->transport->calls[0]['url']);
        $this->assertSame([
            'accountNumber' => ['value' => '123456789'],
            'requestedShipment' => [
                'shipper' => ['address' => ['countryCode' => 'US']],
                'recipients' => [['address' => ['countryCode' => 'CA']]],
            ],
            'carrierCodes' => ['FDXE'],
        ], $this->transport->bodyOf(0));
    }

    /**
     * The special service options between two addresses make their request, with one recipient and not a list
     * @return void
     */
    public function testTheSpecialServiceOptionsBetweenTwoAddressesMakeTheirRequestWithOneRecipientAndNotAList(): void
    {
        $api = $this->api();

        $api->getSpecialServiceOptionsBetween(['countryCode' => 'US'], ['countryCode' => 'CA'], 5, 'LB', 'YOUR_PACKAGING', ['FDXG']);

        $this->assertSame('https://apis-sandbox.fedex.com/availability/v1/specialserviceoptions', $this->transport->calls[0]['url']);
        $this->assertSame([
            'requestedShipment' => [
                'shipper' => ['address' => ['countryCode' => 'US']],
                'recipient' => ['address' => ['countryCode' => 'CA']],
                'packagingType' => 'YOUR_PACKAGING',
                'requestedPackageLineItems' => [['weight' => ['units' => 'LB', 'value' => 5]]],
            ],
            'carrierCodes' => ['FDXG'],
        ], $this->transport->bodyOf(0));
    }

    /**
     * The carrier codes are sent as a list, whatever their keys
     * @return void
     */
    public function testTheCarrierCodesAreSentAsAListWhateverTheirKeys(): void
    {
        $api = $this->api();

        $api->getPackageAndServiceOptionsBetween(['countryCode' => 'US'], ['countryCode' => 'CA'], [3 => 'FDXE', 7 => 'FDXG']);

        $this->assertSame(['FDXE', 'FDXG'], $this->transport->bodyOf(0)['carrierCodes']);
    }

    /**
     * The options that the methods set themselves are refused
     * @return void
     */
    public function testTheOptionsThatTheMethodsSetThemselvesAreRefused(): void
    {
        $api = $this->api();
        $us = ['countryCode' => 'US'];
        $calls = [
            'getTransitTimesBetween' => function (array $options) use ($api, $us) {
                $api->getTransitTimesBetween($us, $us, 1, 'LB', 'YOUR_PACKAGING', [], $options);
            },
            'getPackageAndServiceOptionsBetween' => function (array $options) use ($api, $us) {
                $api->getPackageAndServiceOptionsBetween($us, $us, [], $options);
            },
            'getSpecialServiceOptionsBetween' => function (array $options) use ($api, $us) {
                $api->getSpecialServiceOptionsBetween($us, $us, 1, 'LB', 'YOUR_PACKAGING', [], $options);
            },
        ];

        foreach ($calls as $method => $call) {
            foreach (['requestedShipment', 'carrierCodes'] as $field) {
                $e = self::thrown(function () use ($call, $field) {
                    $call([$field => 'x']);
                });

                $this->assertInstanceOf(FedexServiceAvailabilityException::class, $e, "$method $field");
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
                $this->assertSame("The option '$field' cannot be used with $method(): it is set by the method", $e->getMessage());
            }
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * An address that is not valid, and a unit that is not valid, are refused before anything is sent
     * @return void
     */
    public function testAnAddressThatIsNotValidAndAUnitThatIsNotValidAreRefusedBeforeAnythingIsSent(): void
    {
        $api = $this->api();
        $us = ['countryCode' => 'US'];
        $cases = [
            function () use ($api, $us) {
                $api->getTransitTimesBetween($us, $us, 1, 'STONE');
            },
            function () use ($api, $us) {
                $api->getTransitTimesBetween($us, $us, 'heavy');
            },
            function () use ($api) {
                $api->getPackageAndServiceOptionsBetween(['countryCode' => 'US', 'nope' => 1], ['countryCode' => 'US']);
            },
            function () use ($api, $us) {
                $api->getSpecialServiceOptionsBetween($us, [], 1);
            },
        ];

        foreach ($cases as $i => $case) {
            $e = self::thrown($case);

            $this->assertInstanceOf(FedexServiceAvailabilityException::class, $e, "case $i: " . self::describe($e));
            $this->assertSame('Invalid request', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * The responses generated from the spec are accepted
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testTheResponseGeneratedFromTheSpecIsAccepted(string $label, string $method): void
    {
        $generated = Spec::instance($label . '.response')[0];
        $api = $this->api([], Responses::make(200, json_encode($generated)));

        $this->assertEquals(json_decode(json_encode($generated)), $api->$method(Spec::instance($label . '.request')[0]));
    }

    /**
     * A field that FedEx adds to the response is tolerated
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testAFieldThatFedexAddsToTheResponseIsTolerated(string $label, string $method): void
    {
        $response = Spec::instance($label . '.response')[0];
        $response['addedByFedex'] = ['x' => 1];
        $api = $this->api([], Responses::make(200, json_encode($response)));

        $result = $api->$method(Spec::instance($label . '.request')[0]);

        $this->assertSame(1, $result->addedByFedex->x);
    }

    /**
     * A response with a value of the wrong type in any leaf is refused with the response in the exception
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testAResponseWithAValueOfTheWrongTypeInAnyLeafIsRefusedWithTheResponseInTheException(string $label, string $method): void
    {
        [$response, $meta] = Spec::instance($label . '.response');
        $request = Spec::instance($label . '.request')[0];
        $wrong = ['string' => 12345, 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $copy = SpecInstances::setAt($response, $leaf['path'], $wrong[$leaf['type']]);
            $api = $this->api([], Responses::make(200, json_encode($copy)));
            $e = self::thrown(function () use ($api, $method, $request) {
                $api->$method($request);
            });
            $ok = ($e instanceof FedexServiceAvailabilityException)
                && ($e->getCode() === FedexException::EC_BAD_RESULT_RETURNED)
                && ($e->getMessage() === 'Invalid response received')
                && ($e->getData() == json_decode(json_encode($copy)))
                && !empty($e->getErrors());
            $results[implode('.', $leaf['path'])] = $ok ? true : self::describe($e);
        }

        $this->assertAll('the responses with a value of the wrong type', $results);
    }

    /**
     * A response that is not an object is refused with the response and no errors
     * @return void
     */
    public function testAResponseThatIsNotAnObjectIsRefusedWithTheResponseAndNoErrors(): void
    {
        foreach (['[1,2]' => [1, 2], '5' => 5, '"text"' => 'text', 'true' => true, '' => null] as $body => $decoded) {
            $api = $this->api([], Responses::make(200, (string) $body));
            $e = self::thrown(function () use ($api) {
                $api->getTransitTimes(self::sample('TransitTimes_Domestic'));
            });

            $this->assertInstanceOf(FedexServiceAvailabilityException::class, $e, $body);
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('Invalid response', $e->getMessage());
            $this->assertSame([], $e->getErrors());
            $this->assertSame($decoded, $e->getData());
        }
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * A failure that is transient is repeated by every operation, because they change nothing
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testAFailureThatIsTransientIsRepeatedByEveryOperationBecauseTheyChangeNothing(string $label, string $method): void
    {
        $request = Spec::instance($label . '.request')[0];
        $ok = Responses::make(200, json_encode(Spec::instance($label . '.response')[0]));

        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], $ok, null, true);

            $result = $api->$method($request);

            $this->assertInstanceOf(\stdClass::class, $result);
            $this->assertCount(2, $this->transport->calls, "HTTP $status is repeated once");
            $this->assertSame($this->transport->calls[0]['body'], $this->transport->calls[1]['body']);
            $this->assertSame($this->transport->calls[0]['headers'], $this->transport->calls[1]['headers'], 'the same headers, so the same customer transaction id');
        }
    }

    /**
     * Nothing is repeated when the client has no policy
     * @return void
     */
    public function testNothingIsRepeatedWhenTheClientHasNoPolicy(): void
    {
        foreach ([[503, '{"errors":[{"code":"X","message":"boom"}]}', null], [500, '{"errors":[]}', null], [400, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error), Responses::make(200, '{"transactionId":"T2"}')]);

            $e = self::thrown(function () use ($api) {
                $api->getTransitTimes(self::sample('TransitTimes_Domestic'));
            });

            $this->assertInstanceOf(FedexClientException::class, $e, "HTTP $status");
            $this->assertSame($status, $e->getStatusCode());
            $this->assertCount(1, $this->transport->calls, "HTTP $status is not repeated");
        }
    }

    /**
     * A 401 is retried once with a fresh token, and two 401s in a row stop after two requests
     * @return void
     */
    public function testA401IsRetriedOnceWithAFreshTokenAndTwo401sInARowStopAfterTwoRequests(): void
    {
        $request = self::sample('Special_Service_Options_Domestic_Ground');
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $result = $api->getSpecialServiceOptions($request);
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($calls[0]['body'], $calls[1]['body']);
        $this->assertSame('T2', $result->transactionId);

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);
        $e = self::thrown(function () use ($api, $request) {
            $api->getSpecialServiceOptions($request);
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(401, $e->getStatusCode());
        $this->assertCount(2, $this->transport->calls);
    }

    /**
     * A token that cannot be had is an auth exception and nothing else is sent
     * @return void
     */
    public function testATokenThatCannotBeHadIsAnAuthExceptionAndNothingElseIsSent(): void
    {
        $auth = StubAuth::make();
        $auth->throwOnToken = new FedexAuthException('token failed', FedexException::EC_UNABLE_TO_AUTHENTICATE, null, 401);
        $api = $this->api([], null, $auth);

        $e = self::thrown(function () use ($api) {
            $api->getTransitTimes(self::sample('TransitTimes_Domestic'));
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * A response that is not JSON is a client exception and a single request
     * @return void
     */
    public function testAResponseThatIsNotJsonIsAClientExceptionAndASingleRequest(): void
    {
        $api = $this->api([Responses::make(200, 'not json')]);

        $e = self::thrown(function () use ($api) {
            $api->getTransitTimes(self::sample('TransitTimes_Domestic'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertCount(1, $this->transport->calls);
    }

    /**
     * An HTTP 400 with errors keeps the status, the transaction id and the errors
     * @return void
     */
    public function testAnHttp400WithErrorsKeepsTheStatusTheTransactionIdAndTheErrors(): void
    {
        $api = $this->api([Responses::make(400, '{"transactionId":"624deea6","errors":[{"code":"INPUT.DATA.INVALID","message":"Invalid input."}]}')]);

        $e = self::thrown(function () use ($api) {
            $api->getTransitTimes(self::sample('TransitTimes_Domestic'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }
}
