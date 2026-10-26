<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexPickup;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexPickupException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\PickupSpec as Spec;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Pickup Request API: what the three operations send, how they check the request and the response, the convenience
 * methods, and what they repeat (only the availability: creating and cancelling a pickup change data in FedEx)
 */
final class FedexPickupTest extends TestCase
{
    use BatchAssertions;
    use LooseScalars;

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
     * A FedexPickup over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexPickup
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexPickup
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexPickup($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The three operations: the label of the spec, the method, the HTTP method, the endpoint, the method that makes the empty
     * request, and whether the operation changes data in FedEx
     * @return array
     */
    public function operationsProvider(): array
    {
        return [
            'create' => ['create', 'createPickup', 'POST', 'pickup/v1/pickups', 'newCreateRequest', true],
            'availability' => ['availability', 'checkPickupAvailability', 'POST', 'pickup/v1/pickups/availabilities', 'newAvailabilityRequest', false],
            'cancel' => ['cancel', 'cancelPickup', 'PUT', 'pickup/v1/pickups/cancel', 'newCancelRequest', true],
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
        $this->assertInstanceOf(FedexException::class, new FedexPickupException('x'));
        $this->assertSame('pickup/v1/pickups', FedexPickup::ENDPOINT_CREATE);
        $this->assertSame('pickup/v1/pickups/availabilities', FedexPickup::ENDPOINT_AVAILABILITY);
        $this->assertSame('pickup/v1/pickups/cancel', FedexPickup::ENDPOINT_CANCEL);

        foreach ([FedexPickup::ENDPOINT_CREATE, FedexPickup::ENDPOINT_AVAILABILITY, FedexPickup::ENDPOINT_CANCEL] as $endpoint) {
            $this->assertArrayHasKey('/' . $endpoint, Spec::spec()['paths']);
        }
    }

    // ---------------------------------------------------------------- what the operations send

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     * @param string $endpoint The endpoint
     */
    public function testEachOperationSendsOneRequestWithTheGeneratedRequest(string $label, string $method, string $verb, string $endpoint): void
    {
        $request = Spec::instance($label . '.request')[0];
        $api = $this->api();

        $result = $api->$method($request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame($verb, $calls[0]['method']);
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
        $methods = ['create' => 'createPickup', 'availability' => 'checkPickupAvailability', 'cancel' => 'cancelPickup'];
        $results = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $api = $this->api();
            $method = $methods[explode('.', $label)[0]];
            $example = self::sample($sample);
            $e = self::thrown(function () use ($api, $method, $example) {
                $api->$method($example);
            });

            $results[$sample] = ($e === null && $this->transport->bodyOf(0) === FedexUtils::pruneEmpty($example)) ? true : self::describe($e);
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

        $api->cancelPickup(self::sample('Cancel_Pickup_Ground'));

        $this->assertSame('https://apis.fedex.com/pickup/v1/pickups/cancel', $this->transport->calls[0]['url']);
    }

    /**
     * An operation takes an object as well as an array
     * @return void
     */
    public function testAnOperationTakesAnObjectAsWellAsAnArray(): void
    {
        $request = self::sample('Create_Pickup_Ground');
        $api = $this->api();

        $api->createPickup(json_decode(json_encode($request)));

        $this->assertSame(FedexUtils::pruneEmpty($request), $this->transport->bodyOf(0));
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

            $this->assertInstanceOf(FedexPickupException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * An unknown key is sent, because FedEx decides about it
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testAnUnknownKeyIsSentBecauseFedexDecidesAboutIt(string $label, string $method): void
    {
        $api = $this->api();
        $request = Spec::instance($label . '.request')[0] + ['notInTheSchema' => 1];

        $api->$method($request);

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame(1, $this->transport->bodyOf(0)['notInTheSchema']);
    }

    /**
     * The empty requests are the required keys of the spec, and are refused until they are filled in
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     * @param string $endpoint The endpoint
     * @param string $new The method that makes the empty request
     */
    public function testTheEmptyRequestsAreTheRequiredKeysOfTheSpecAndAreRefusedUntilTheyAreFilledIn(string $label, string $method, string $verb, string $endpoint, string $new): void
    {
        $api = $this->api();
        $empty = FedexPickup::$new();
        $root = Spec::ROOTS[$label . '.request'];
        $expected = Spec::requiredOnly(Spec::components()[$root], [$root]);

        // the account number has its value, null, so that it is there to be filled in
        if (isset($expected['associatedAccountNumber'])) {
            $expected['associatedAccountNumber'] = ['value' => null];
        }

        if ($label === 'create') {
            // the helper of the spec lists every key of an object that requires none (the contact), and of the address that the overlay tightens
            $expected['originDetail']['pickupLocation'] = [
                'contact' => [],
                'address' => ['streetLines' => [], 'city' => null, 'stateOrProvinceCode' => null, 'postalCode' => null, 'countryCode' => null],
            ];
        }

        $this->assertEquals($expected, $empty, 'the empty request is the required keys of the spec');

        $e = self::thrown(function () use ($api, $method, $empty) {
            $api->$method($empty);
        });

        $this->assertInstanceOf(FedexPickupException::class, $e, self::describe($e));
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The empty requests, filled in, are accepted
     * @return void
     */
    public function testTheEmptyRequestsFilledInAreAccepted(): void
    {
        $api = $this->api();

        $request = FedexPickup::newCreateRequest();
        $request['associatedAccountNumber']['value'] = '123456789';
        $request['originDetail']['pickupLocation']['contact'] = ['personName' => 'John Taylor', 'phoneNumber' => '9013577890'];
        $request['originDetail']['pickupLocation']['address'] = ['streetLines' => ['10 FedEx Parkway'], 'city' => 'Beverly Hills', 'stateOrProvinceCode' => 'CA', 'postalCode' => '90210', 'countryCode' => 'US'];
        $request['originDetail']['readyDateTimestamp'] = '2026-11-03T09:00:00Z';
        $request['originDetail']['customerCloseTime'] = '17:00:00';
        $request['carrierCode'] = 'FDXE';
        $api->createPickup($request);

        $request = FedexPickup::newAvailabilityRequest();
        $request['pickupAddress'] = ['postalCode' => '38117', 'countryCode' => 'US'];
        $request['pickupRequestType'][] = 'SAME_DAY';
        $request['carriers'][] = 'FDXG';
        $request['countryRelationship'] = 'DOMESTIC';
        $api->checkPickupAvailability($request);

        $request = FedexPickup::newCancelRequest();
        $request['associatedAccountNumber']['value'] = '123456789';
        $request['pickupConfirmationCode'] = '0123456789';
        $request['scheduledDate'] = '2026-11-03';
        $api->cancelPickup($request);

        $this->assertCount(3, $this->transport->calls);
        $this->assertSame('0123456789', $this->transport->bodyOf(2)['pickupConfirmationCode']);
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

                // optional in the class: the minimum fields of an address vary among countries
                if (($key === 'stateOrProvinceCode' && in_array('pickupLocation', $object['path'], true)) || ($key === 'postalCode' && $object['path'] === ['pickupAddress'])) {
                    $results[implode('.', array_merge($object['path'], [$key])) . ' (optional)'] = ($e === null && count($this->transport->calls) === 1) ? true : self::describe($e);
                    continue;
                }

                $results[implode('.', array_merge($object['path'], [$key]))] = ($e instanceof FedexPickupException && $this->transport->calls === []) ? true : self::describe($e);
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
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
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
            $results[implode('.', $leaf['path'])] = ($e instanceof FedexPickupException && $this->transport->calls === []) ? true : self::describe($e);
        }

        $this->assertAll('the values of the wrong type', $results);
    }

    /**
     * The scalars of a looser type than the one of the schema are sent as they are
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testTheScalarsOfALooserTypeAreSentAsTheyAre(string $label, string $method): void
    {
        [$request, $meta] = Spec::instance($label . '.request');

        $this->assertLooseScalarsAreSent($request, $meta['leaves'], function (array $r) use ($method) {
            $api = $this->api();
            $api->$method($r);

            return $this->transport->bodyOf(0);
        });
    }

    /**
     * A carrier outside the enum is refused, and every value of the enums is sent
     * @return void
     */
    public function testACarrierOutsideTheEnumIsRefusedAndEveryValueOfTheEnumsIsSent(): void
    {
        $api = $this->api();

        foreach (['FDXE', 'FDXG'] as $carrier) {
            $request = self::sample('Create_Pickup_Ground');
            $request['carrierCode'] = $carrier;
            $api->createPickup($request);
        }

        $this->assertCount(2, $this->transport->calls);

        $request['carrierCode'] = 'FXFR';
        $e = self::thrown(function () use ($api, $request) {
            $api->createPickup($request);
        });

        $this->assertInstanceOf(FedexPickupException::class, $e);
        $this->assertStringContainsString('carrierCode', implode(' ', $e->getErrors()));
        $this->assertCount(2, $this->transport->calls, 'nothing more was sent');
    }

    /**
     * Null and empty lists are left out of the request
     * @return void
     */
    public function testNullAndEmptyListsAreLeftOut(): void
    {
        $api = $this->api();
        $request = self::sample('Cancel_Pickup_Ground');
        $expected = $request;
        $request['remarks'] = null;
        $request['location'] = [];
        unset($expected['remarks'], $expected['location']);

        $api->cancelPickup($request);

        $this->assertSame($expected, $this->transport->bodyOf(0));
    }

    // ---------------------------------------------------------------- the convenience methods

    /**
     * The availability at an address makes its request
     * @return void
     */
    public function testTheAvailabilityAtAnAddressMakesItsRequest(): void
    {
        $api = $this->api();

        $result = $api->checkAvailabilityAt(['postalCode' => 75063, 'countryCode' => 'US'], ['FDXE', 'FDXG'], 'DOMESTIC', ['SAME_DAY', 'FUTURE_DAY'], ['numberOfBusinessDays' => 3]);

        $this->assertSame('T1', $result->transactionId);
        $this->assertSame('POST', $this->transport->calls[0]['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/pickup/v1/pickups/availabilities', $this->transport->calls[0]['url']);
        $this->assertSame([
            'numberOfBusinessDays' => 3,
            'pickupAddress' => ['postalCode' => 75063, 'countryCode' => 'US'],
            'carriers' => ['FDXE', 'FDXG'],
            'countryRelationship' => 'DOMESTIC',
            'pickupRequestType' => ['SAME_DAY', 'FUTURE_DAY'],
        ], $this->transport->bodyOf(0));
    }

    /**
     * The defaults of the availability at an address are domestic and the same day, and the lists are sent as lists whatever their keys
     * @return void
     */
    public function testTheDefaultsOfTheAvailabilityAtAnAddressAreDomesticAndTheSameDayAndTheListsAreSentAsLists(): void
    {
        $api = $this->api();

        $api->checkAvailabilityAt(['postalCode' => '38117', 'countryCode' => 'US'], [3 => 'FDXG']);

        $body = $this->transport->bodyOf(0);

        $this->assertSame('DOMESTIC', $body['countryRelationship']);
        $this->assertSame(['SAME_DAY'], $body['pickupRequestType']);
        $this->assertSame(['FDXG'], $body['carriers']);

        $api->checkAvailabilityAt(['postalCode' => '38117', 'countryCode' => 'US'], ['FDXE'], 'DOMESTIC', [2 => 'FUTURE_DAY']);

        $this->assertSame(['FUTURE_DAY'], $this->transport->bodyOf(1)['pickupRequestType']);
    }

    /**
     * Cancelling by confirmation makes its request
     * @return void
     */
    public function testCancellingByConfirmationMakesItsRequest(): void
    {
        $api = $this->api();

        $result = $api->cancelByConfirmation('123456789', '0123456789', '2026-11-03', ['remarks' => 'not needed', 'carrierCode' => 'FDXG']);

        $this->assertSame('T1', $result->transactionId);
        $this->assertSame('PUT', $this->transport->calls[0]['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/pickup/v1/pickups/cancel', $this->transport->calls[0]['url']);
        $this->assertSame([
            'remarks' => 'not needed',
            'carrierCode' => 'FDXG',
            'associatedAccountNumber' => ['value' => '123456789'],
            'pickupConfirmationCode' => '0123456789',
            'scheduledDate' => '2026-11-03',
        ], $this->transport->bodyOf(0));
    }

    /**
     * The options that the methods set themselves are refused
     * @return void
     */
    public function testTheOptionsThatTheMethodsSetThemselvesAreRefused(): void
    {
        $api = $this->api();
        $us = ['postalCode' => '38117', 'countryCode' => 'US'];
        $cases = [
            'checkAvailabilityAt' => [['pickupAddress', 'carriers', 'countryRelationship', 'pickupRequestType'], function (array $options) use ($api, $us) {
                $api->checkAvailabilityAt($us, ['FDXE'], 'DOMESTIC', ['SAME_DAY'], $options);
            }],
            'cancelByConfirmation' => [['associatedAccountNumber', 'pickupConfirmationCode', 'scheduledDate'], function (array $options) use ($api) {
                $api->cancelByConfirmation('1', '2', '2026-11-03', $options);
            }],
        ];

        foreach ($cases as $method => [$fields, $call]) {
            foreach ($fields as $field) {
                $e = self::thrown(function () use ($call, $field) {
                    $call([$field => 'x']);
                });

                $this->assertInstanceOf(FedexPickupException::class, $e, "$method $field");
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
                $this->assertSame("The option '$field' cannot be used with $method(): it is set by the method", $e->getMessage());
            }
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * A carrier that is not valid and a list that is empty are refused before anything is sent
     * @return void
     */
    public function testACarrierThatIsNotValidAndAnEmptyListAreRefusedBeforeAnythingIsSent(): void
    {
        $api = $this->api();
        $us = ['postalCode' => '38117', 'countryCode' => 'US'];
        $cases = [
            function () use ($api, $us) {
                $api->checkAvailabilityAt($us, ['UPS']);
            },
            function () use ($api, $us) {
                $api->checkAvailabilityAt($us, ['FDXE'], 'ABROAD');
            },
            function () use ($api, $us) {
                $api->checkAvailabilityAt($us, ['FDXE'], 'DOMESTIC', ['TOMORROW']);
            },
            function () use ($api) {
                $api->checkAvailabilityAt(['postalCode' => '38117'], ['FDXE']);
            },
            function () use ($api, $us) {
                $api->checkAvailabilityAt($us, []);
            },
        ];

        foreach ($cases as $i => $case) {
            $e = self::thrown($case);

            $this->assertInstanceOf(FedexPickupException::class, $e, "case $i: " . self::describe($e));
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
     * A response with a value of the wrong type in any leaf is refused with the response in the exception, and the message of the
     * operations that change data says that they may have been done
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     * @param string $endpoint The endpoint
     * @param string $new The method that makes the empty request
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAResponseWithAValueOfTheWrongTypeInAnyLeafIsRefusedWithTheResponseInTheException(string $label, string $method, string $verb, string $endpoint, string $new, bool $changes): void
    {
        [$response, $meta] = Spec::instance($label . '.response');
        $request = Spec::instance($label . '.request')[0];
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $message = 'Invalid response received' . ($changes ? ' (the operation may have been done by FedEx: check it before repeating it)' : '');
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
            $ok = ($e instanceof FedexPickupException)
                && ($e->getCode() === FedexException::EC_BAD_RESULT_RETURNED)
                && ($e->getMessage() === $message)
                && ($e->getData() == json_decode(json_encode($copy)))
                && !empty($e->getErrors());
            $results[implode('.', $leaf['path'])] = $ok ? true : self::describe($e);
        }

        $this->assertAll('the responses with a value of the wrong type', $results);
    }

    /**
     * A response that is not an object is refused with the response and no errors
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     * @param string $endpoint The endpoint
     * @param string $new The method that makes the empty request
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAResponseThatIsNotAnObjectIsRefusedWithTheResponseAndNoErrors(string $label, string $method, string $verb, string $endpoint, string $new, bool $changes): void
    {
        foreach (['[1,2]' => [1, 2], '5' => 5, '"text"' => 'text', 'true' => true, '' => null] as $body => $decoded) {
            $api = $this->api([], Responses::make(200, (string) $body));
            $e = self::thrown(function () use ($api, $method, $label) {
                $api->$method(Spec::instance($label . '.request')[0]);
            });

            $this->assertInstanceOf(FedexPickupException::class, $e, $body);
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('Invalid response' . ($changes ? ' (the operation may have been done by FedEx: check it before repeating it)' : ''), $e->getMessage());
            $this->assertSame([], $e->getErrors());
            $this->assertSame($decoded, $e->getData());
        }
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * A failure that is transient is repeated for the availability, which changes nothing, and for nothing else
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     * @param string $endpoint The endpoint
     * @param string $new The method that makes the empty request
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAFailureThatIsTransientIsRepeatedForTheAvailabilityAndForNothingElse(string $label, string $method, string $verb, string $endpoint, string $new, bool $changes): void
    {
        $request = Spec::instance($label . '.request')[0];
        $ok = Responses::make(200, json_encode(Spec::instance($label . '.response')[0]));

        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], $ok, null, true);

            if ($changes) {
                $e = self::thrown(function () use ($api, $method, $request) {
                    $api->$method($request);
                });

                $this->assertInstanceOf(FedexClientException::class, $e, "HTTP $status");
                $this->assertCount(1, $this->transport->calls, "HTTP $status is not repeated: the operation changes data in FedEx");
                continue;
            }

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
                $api->checkPickupAvailability(self::sample('Check_Pickup_Availability_Domestic_Ground'));
            });

            $this->assertInstanceOf(FedexClientException::class, $e, "HTTP $status");
            $this->assertSame($status, $e->getStatusCode());
            $this->assertCount(1, $this->transport->calls, "HTTP $status is not repeated");
        }
    }

    /**
     * A 401 is retried once with a fresh token by every operation, and two 401s in a row stop after two requests
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testA401IsRetriedOnceWithAFreshTokenAndTwo401sInARowStopAfterTwoRequests(string $label, string $method): void
    {
        $request = Spec::instance($label . '.request')[0];
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $result = $api->$method($request);
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($calls[0]['body'], $calls[1]['body']);
        $this->assertSame('T2', $result->transactionId);

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);
        $e = self::thrown(function () use ($api, $method, $request) {
            $api->$method($request);
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
            $api->createPickup(self::sample('Create_Pickup_Ground'));
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
            $api->createPickup(self::sample('Create_Pickup_Ground'));
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
            $api->cancelPickup(self::sample('Cancel_Pickup_Ground'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }
}
