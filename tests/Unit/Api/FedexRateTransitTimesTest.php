<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexRateTransitTimes;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexRateTransitTimesException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\RateSpec as Spec;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Rates and Transit Times API: what the operation sends, how it checks the request and the response, the convenience
 * method, and what it repeats
 */
final class FedexRateTransitTimesTest extends TestCase
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
     * A FedexRateTransitTimes over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexRateTransitTimes
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexRateTransitTimes
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexRateTransitTimes($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The request generated from the spec, with every field filled in
     * @return array
     */
    private static function request(): array
    {
        return Spec::instance('rates.request')[0];
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
     * The class extends the base of the API classes, has its own exception and the endpoint of the spec
     * @return void
     */
    public function testTheClassExtendsTheBaseOfTheApiClassesHasItsOwnExceptionAndTheEndpointOfTheSpec(): void
    {
        $this->assertInstanceOf(AbstractFedexApi::class, $this->api());
        $this->assertInstanceOf(FedexException::class, new FedexRateTransitTimesException('x'));
        $this->assertSame('rate/v1/rates/quotes', FedexRateTransitTimes::ENDPOINT);
        $this->assertArrayHasKey('/' . FedexRateTransitTimes::ENDPOINT, Spec::spec()['paths']);
    }

    // ---------------------------------------------------------------- what the operation sends

    /**
     * The operation sends one post with the generated request
     * @return void
     */
    public function testTheOperationSendsOnePostWithTheGeneratedRequest(): void
    {
        $request = self::request();
        $api = $this->api();

        $result = $api->getRates($request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/rate/v1/rates/quotes', $calls[0]['url']);
        $this->assertEquals($request, $this->transport->bodyOf(0));
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('T1', $result->transactionId);
    }

    /**
     * The samples of the spec are sent as they are, a null being left out, except the two that the schema contradicts
     * @return void
     */
    public function testTheSamplesOfTheSpecAreSentAsTheyAreExceptTheTwoThatTheSchemaContradicts(): void
    {
        $results = [];
        $refused = [];

        foreach (array_keys(Spec::SAMPLES) as $sample) {
            $api = $this->api();
            $example = self::sample($sample);
            $e = self::thrown(function () use ($api, $example) {
                $api->getRates($example);
            });

            if ($e instanceof FedexRateTransitTimesException) {
                $refused[] = $sample;
                $this->assertSame([], $this->transport->calls, "$sample is refused before anything is sent");
                continue;
            }

            $results[$sample] = ($e === null && $this->transport->bodyOf(0) === FedexUtils::pruneEmpty($example)) ? true : self::describe($e);
        }

        sort($refused);

        $this->assertAll('the samples of the spec', $results);
        $this->assertCount(20, $results);
        $this->assertSame(['FDGSamplePayload-RateResourceDetails-IntraEurope', 'FreightSamplePayload-RateResourceDetails_1'], $refused);
    }

    /**
     * The operation sends the headers of the spec: the token, the locale, the content type and a UUID that is different in every call
     * @return void
     */
    public function testTheOperationSendsTheHeadersOfTheSpecAndAUuidThatIsDifferentInEveryCall(): void
    {
        $api = $this->api();

        $api->getRates(self::request());
        $api->getRates(self::request());
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

        $api->getRates(self::sample('US_Domestic_Rate_shop'));

        $this->assertSame('https://apis.fedex.com/rate/v1/rates/quotes', $this->transport->calls[0]['url']);
    }

    /**
     * The operation takes an object as well as an array
     * @return void
     */
    public function testTheOperationTakesAnObjectAsWellAsAnArray(): void
    {
        $request = self::sample('US_Domestic_Rate_shop');
        $api = $this->api();

        $api->getRates(json_decode(json_encode($request)));

        $this->assertSame($request, $this->transport->bodyOf(0));
    }

    /**
     * A request that is not an array or an object is refused with its own message
     * @return void
     */
    public function testARequestThatIsNotAnArrayOrAnObjectIsRefusedWithItsOwnMessage(): void
    {
        $api = $this->api();

        foreach ([null, 'text', 5, 1.5, true] as $request) {
            $e = self::thrown(function () use ($api, $request) {
                $api->getRates($request);
            });

            $this->assertInstanceOf(FedexRateTransitTimesException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * A key that the schema does not define is sent: FedEx decides about it
     * @return void
     */
    public function testAKeyThatTheSchemaDoesNotDefineIsSentFedexDecidesAboutIt(): void
    {
        $api = $this->api();
        $request = array_replace_recursive(self::sample('US_Domestic_Rate_shop'), ['notInTheSchema' => 1, 'requestedShipment' => ['shipper' => ['address' => ['unknown' => 'x']]]]);

        $api->getRates($request);

        $this->assertSame($request, $this->transport->bodyOf(0));
    }

    /**
     * The empty request is refused until it is filled in, and it is the required keys of the spec
     * @return void
     */
    public function testTheEmptyRequestIsRefusedUntilItIsFilledInAndItIsTheRequiredKeysOfTheSpec(): void
    {
        $api = $this->api();
        $empty = FedexRateTransitTimes::newRatesRequest();
        $root = Spec::ROOTS['rates.request'];

        $this->assertEquals(Spec::requiredOnly(Spec::components()[$root], [$root]), $empty);

        $e = self::thrown(function () use ($api, $empty) {
            $api->getRates($empty);
        });

        $this->assertInstanceOf(FedexRateTransitTimesException::class, $e, self::describe($e));
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The empty request filled in is accepted
     * @return void
     */
    public function testTheEmptyRequestFilledInIsAccepted(): void
    {
        $api = $this->api();
        $request = FedexRateTransitTimes::newRatesRequest();
        $request['accountNumber']['value'] = '123456789';
        $request['requestedShipment']['shipper']['address'] = ['postalCode' => '38117', 'countryCode' => 'US'];
        $request['requestedShipment']['recipient']['address'] = ['postalCode' => 75201, 'countryCode' => 'US'];
        $request['requestedShipment']['pickupType'] = 'DROPOFF_AT_FEDEX_LOCATION';
        $request['requestedShipment']['requestedPackageLineItems'][] = ['weight' => ['units' => 'LB', 'value' => '10']];

        $api->getRates($request);

        $this->assertSame(75201, $this->transport->bodyOf(0)['requestedShipment']['recipient']['address']['postalCode'], 'sent as it was given');
        $this->assertSame('10', $this->transport->bodyOf(0)['requestedShipment']['requestedPackageLineItems'][0]['weight']['value']);
    }

    /**
     * Every required field that is missing is refused and nothing is sent
     * @return void
     */
    public function testEveryRequiredFieldThatIsMissingIsRefusedAndNothingIsSent(): void
    {
        [$request, $meta] = Spec::instance('rates.request');
        $results = [];

        foreach ($meta['objects'] as $object) {
            foreach ($object['required'] as $key) {
                if ($object['path'] !== [] && count($object['keys']) === 1) {
                    continue; // the object is left empty and is pruned, and an optional object that is not there is fine
                }

                $copy = SpecInstances::unsetAt($request, array_merge($object['path'], [$key]));
                $api = $this->api();
                $e = self::thrown(function () use ($api, $copy) {
                    $api->getRates($copy);
                });
                $results[implode('.', array_merge($object['path'], [$key]))] = ($e instanceof FedexRateTransitTimesException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the required fields', $results);
    }

    /**
     * A value of the wrong type in any leaf is refused and nothing is sent
     * @return void
     */
    public function testAValueOfTheWrongTypeInAnyLeafIsRefusedAndNothingIsSent(): void
    {
        [$request, $meta] = Spec::instance('rates.request');
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $copy = SpecInstances::setAt($request, $leaf['path'], $wrong[$leaf['type']]);
            $api = $this->api();
            $e = self::thrown(function () use ($api, $copy) {
                $api->getRates($copy);
            });
            $results[implode('.', $leaf['path'])] = ($e instanceof FedexRateTransitTimesException && $this->transport->calls === []) ? true : self::describe($e);
        }

        $this->assertAll('the values of the wrong type', $results);
    }

    /**
     * A pickup type outside the enum is refused, and every value of the enum is sent
     * @return void
     */
    public function testAPickupTypeOutsideTheEnumIsRefusedAndEveryValueOfTheEnumIsSent(): void
    {
        $api = $this->api();

        foreach (['CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION', 'USE_SCHEDULED_PICKUP'] as $pickup) {
            $request = self::sample('US_Domestic_Rate_shop');
            $request['requestedShipment']['pickupType'] = $pickup;
            $api->getRates($request);
        }

        $this->assertCount(3, $this->transport->calls);

        $request['requestedShipment']['pickupType'] = 'AT_THE_DOOR';
        $e = self::thrown(function () use ($api, $request) {
            $api->getRates($request);
        });

        $this->assertInstanceOf(FedexRateTransitTimesException::class, $e);
        $this->assertStringContainsString('pickupType', implode(' ', $e->getErrors()));
        $this->assertCount(3, $this->transport->calls, 'nothing more was sent');
    }

    /**
     * A number can be a number or a text that be a number, a postal code a text or an integer, and anything else is refused
     * @return void
     */
    public function testANumberAndAPostalCodeAcceptWhatTheSamplesSendAndNothingElse(): void
    {
        foreach ([10, 10.5, '10', '10.5', '+7', '1e3'] as $value) {
            $request = self::sample('US_Domestic_Rate_shop');
            $request['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'] = $value;
            $api = $this->api();

            $api->getRates($request);

            $this->assertSame($value, $this->transport->bodyOf(0)['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'], 'it is sent as it was given');
        }

        foreach (['abc', '1,5', ' 5', true, [1]] as $value) {
            $request = self::sample('US_Domestic_Rate_shop');
            $request['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'] = $value;
            $api = $this->api();
            $e = self::thrown(function () use ($api, $request) {
                $api->getRates($request);
            });

            $this->assertInstanceOf(FedexRateTransitTimesException::class, $e, json_encode($value));
            $this->assertStringContainsString('weight.value', implode(' ', $e->getErrors()));
            $this->assertSame([], $this->transport->calls);
        }

        foreach ([['75063', true], [75063, true], [1.5, false], [true, false], [['x'], false]] as [$value, $ok]) {
            $request = self::sample('US_Domestic_Rate_shop');
            $request['requestedShipment']['shipper']['address']['postalCode'] = $value;
            $api = $this->api();
            $e = self::thrown(function () use ($api, $request) {
                $api->getRates($request);
            });

            $this->assertSame($ok, $e === null, json_encode($value) . ' ' . self::describe($e));
            $this->assertSame($ok ? 1 : 0, count($this->transport->calls));
        }
    }

    /**
     * Null and empty lists are left out of the request
     * @return void
     */
    public function testNullAndEmptyListsAreLeftOut(): void
    {
        $api = $this->api();
        $request = self::sample('US_Domestic_Rate_shop');
        $expected = $request;
        $request['version'] = null;
        $request['carrierCodes'] = [];
        unset($expected['version'], $expected['carrierCodes']);

        $api->getRates($request);

        $this->assertSame($expected, $this->transport->bodyOf(0));
    }

    // ---------------------------------------------------------------- the convenience method

    /**
     * The rates between two addresses make their request
     * @return void
     */
    public function testTheRatesBetweenTwoAddressesMakeTheirRequest(): void
    {
        $api = $this->api();

        $result = $api->getRatesBetween('123456789', ['postalCode' => '38117', 'countryCode' => 'US'], ['postalCode' => 75201, 'countryCode' => 'US'], 10.5, 'KG', 'FEDEX_BOX', 'USE_SCHEDULED_PICKUP', ['carrierCodes' => ['FDXE'], 'version' => ['major' => 1]]);

        $this->assertSame('T1', $result->transactionId);
        $this->assertSame('https://apis-sandbox.fedex.com/rate/v1/rates/quotes', $this->transport->calls[0]['url']);
        $this->assertSame([
            'carrierCodes' => ['FDXE'],
            'version' => ['major' => 1],
            'accountNumber' => ['value' => '123456789'],
            'requestedShipment' => [
                'shipper' => ['address' => ['postalCode' => '38117', 'countryCode' => 'US']],
                'recipient' => ['address' => ['postalCode' => 75201, 'countryCode' => 'US']],
                'pickupType' => 'USE_SCHEDULED_PICKUP',
                'packagingType' => 'FEDEX_BOX',
                'requestedPackageLineItems' => [['weight' => ['units' => 'KG', 'value' => 10.5]]],
            ],
        ], $this->transport->bodyOf(0));
    }

    /**
     * The defaults of the rates between two addresses are pounds, your packaging and drop off at a FedEx location
     * @return void
     */
    public function testTheDefaultsOfTheRatesBetweenTwoAddressesArePoundsYourPackagingAndDropOffAtAFedexLocation(): void
    {
        $api = $this->api();

        $api->getRatesBetween('123456789', ['postalCode' => '38117', 'countryCode' => 'US'], ['postalCode' => '75201', 'countryCode' => 'US'], '12');

        $shipment = $this->transport->bodyOf(0)['requestedShipment'];

        $this->assertSame('YOUR_PACKAGING', $shipment['packagingType']);
        $this->assertSame('DROPOFF_AT_FEDEX_LOCATION', $shipment['pickupType']);
        $this->assertSame(['units' => 'LB', 'value' => '12'], $shipment['requestedPackageLineItems'][0]['weight']);
        $this->assertSame(['accountNumber', 'requestedShipment'], array_keys($this->transport->bodyOf(0)));
    }

    /**
     * The options that the method sets itself are refused
     * @return void
     */
    public function testTheOptionsThatTheMethodSetsItselfAreRefused(): void
    {
        $api = $this->api();
        $us = ['postalCode' => '38117', 'countryCode' => 'US'];

        foreach (['accountNumber', 'requestedShipment'] as $field) {
            $e = self::thrown(function () use ($api, $us, $field) {
                $api->getRatesBetween('123456789', $us, $us, 1, 'LB', 'YOUR_PACKAGING', 'DROPOFF_AT_FEDEX_LOCATION', [$field => 'x']);
            });

            $this->assertInstanceOf(FedexRateTransitTimesException::class, $e, $field);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame("The option '$field' cannot be used with getRatesBetween(): it is set by the method", $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * An address that is not valid, a unit that is not valid and a pickup type that is not valid are refused before anything is sent
     * @return void
     */
    public function testAnAddressAUnitAndAPickupTypeThatAreNotValidAreRefusedBeforeAnythingIsSent(): void
    {
        $api = $this->api();
        $us = ['postalCode' => '38117', 'countryCode' => 'US'];
        $cases = [
            function () use ($api, $us) {
                $api->getRatesBetween('1', $us, $us, 1, 'STONE');
            },
            function () use ($api, $us) {
                $api->getRatesBetween('1', $us, $us, 'heavy');
            },
            function () use ($api, $us) {
                $api->getRatesBetween('1', $us, $us, 1, 'LB', 'YOUR_PACKAGING', 'AT_THE_DOOR');
            },
            function () use ($api, $us) {
                $api->getRatesBetween('1', $us, ['countryCode' => 'US'], 1);
            },
            function () use ($api, $us) {
                $api->getRatesBetween('1', $us, [], 1);
            },
        ];

        foreach ($cases as $i => $case) {
            $e = self::thrown($case);

            $this->assertInstanceOf(FedexRateTransitTimesException::class, $e, "case $i: " . self::describe($e));
            $this->assertSame('Invalid request', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * The response generated from the spec is accepted
     * @return void
     */
    public function testTheResponseGeneratedFromTheSpecIsAccepted(): void
    {
        $generated = Spec::instance('rates.response')[0];
        $api = $this->api([], Responses::make(200, json_encode($generated)));

        $this->assertEquals(json_decode(json_encode($generated)), $api->getRates(self::request()));
    }

    /**
     * A field that FedEx adds to the response is tolerated
     * @return void
     */
    public function testAFieldThatFedexAddsToTheResponseIsTolerated(): void
    {
        $response = Spec::instance('rates.response')[0];
        $response['addedByFedex'] = ['x' => 1];
        $api = $this->api([], Responses::make(200, json_encode($response)));

        $result = $api->getRates(self::request());

        $this->assertSame(1, $result->addedByFedex->x);
    }

    /**
     * A response with a value of the wrong type in any leaf is refused with the response in the exception
     * @return void
     */
    public function testAResponseWithAValueOfTheWrongTypeInAnyLeafIsRefusedWithTheResponseInTheException(): void
    {
        [$response, $meta] = Spec::instance('rates.response');
        $request = self::request();
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $copy = SpecInstances::setAt($response, $leaf['path'], $wrong[$leaf['type']]);
            $api = $this->api([], Responses::make(200, json_encode($copy)));
            $e = self::thrown(function () use ($api, $request) {
                $api->getRates($request);
            });
            $ok = ($e instanceof FedexRateTransitTimesException)
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
                $api->getRates(self::sample('US_Domestic_Rate_shop'));
            });

            $this->assertInstanceOf(FedexRateTransitTimesException::class, $e, $body);
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('Invalid response', $e->getMessage());
            $this->assertSame([], $e->getErrors());
            $this->assertSame($decoded, $e->getData());
        }
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * A failure that is transient is repeated, because a quote changes nothing
     * @return void
     */
    public function testAFailureThatIsTransientIsRepeatedBecauseAQuoteChangesNothing(): void
    {
        $request = self::request();
        $ok = Responses::make(200, json_encode(Spec::instance('rates.response')[0]));

        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], $ok, null, true);

            $result = $api->getRates($request);

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
                $api->getRates(self::sample('US_Domestic_Rate_shop'));
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
        $request = self::sample('US_Domestic_Rate_shop');
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $result = $api->getRates($request);
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($calls[0]['body'], $calls[1]['body']);
        $this->assertSame('T2', $result->transactionId);

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);
        $e = self::thrown(function () use ($api, $request) {
            $api->getRates($request);
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
            $api->getRates(self::sample('US_Domestic_Rate_shop'));
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
            $api->getRates(self::sample('US_Domestic_Rate_shop'));
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
            $api->getRates(self::sample('US_Domestic_Rate_shop'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }
}
