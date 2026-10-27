<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexGlobalTrade;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexGlobalTradeException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\GlobalTradeSpec as Spec;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Global Trade API: what the operation sends, how it checks the request and the response, the convenience method,
 * and what it repeats
 */
final class FedexGlobalTradeTest extends TestCase
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
     * A FedexGlobalTrade over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexGlobalTrade
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexGlobalTrade
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1"}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexGlobalTrade($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The request generated from the spec, with every field filled in
     * @return array
     */
    private static function request(): array
    {
        return Spec::instance('retrieve.request')[0];
    }

    /**
     * The sample of the spec, as an array
     * @return array
     */
    private static function sample(): array
    {
        return json_decode(json_encode(Spec::components()['MinimumSamplePayload']['example']), true);
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
        $this->assertInstanceOf(FedexException::class, new FedexGlobalTradeException('x'));
        $this->assertSame('globaltrade/v1/shipments/regulatorydetails/retrieve', FedexGlobalTrade::ENDPOINT);
        $this->assertArrayHasKey('/' . FedexGlobalTrade::ENDPOINT, Spec::spec()['paths']);
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

        $result = $api->retrieveRegulatoryDetails($request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/globaltrade/v1/shipments/regulatorydetails/retrieve', $calls[0]['url']);
        $this->assertEquals($request, $this->transport->bodyOf(0));
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('T1', $result->transactionId);
    }

    /**
     * The sample of the spec is sent as it is (an empty list is left out), with its blank amount
     * @return void
     */
    public function testTheSampleOfTheSpecIsSentAsItIs(): void
    {
        $api = $this->api();

        $api->retrieveRegulatoryDetails(self::sample());

        $this->assertSame(FedexUtils::pruneEmpty(self::sample()), $this->transport->bodyOf(0));
        $this->assertSame('', $this->transport->bodyOf(0)['customsClearanceDetail']['customsValue']['amount']);
    }

    /**
     * The operation sends the headers of the spec and a UUID that is different in every call
     * @return void
     */
    public function testTheOperationSendsTheHeadersOfTheSpecAndAUuidThatIsDifferentInEveryCall(): void
    {
        $api = $this->api();

        $api->retrieveRegulatoryDetails(self::request());
        $api->retrieveRegulatoryDetails(self::request());
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

        $api->retrieveRegulatoryDetails(self::sample());

        $this->assertSame('https://apis.fedex.com/globaltrade/v1/shipments/regulatorydetails/retrieve', $this->transport->calls[0]['url']);
    }

    /**
     * The operation takes an object as well as an array
     * @return void
     */
    public function testTheOperationTakesAnObjectAsWellAsAnArray(): void
    {
        $request = self::request();
        $api = $this->api();

        $api->retrieveRegulatoryDetails(json_decode(json_encode($request)));

        $this->assertEquals($request, $this->transport->bodyOf(0));
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
                $api->retrieveRegulatoryDetails($request);
            });

            $this->assertInstanceOf(FedexGlobalTradeException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * Null and empty lists are left out of the request
     * @return void
     */
    public function testNullAndEmptyListsAreLeftOut(): void
    {
        $api = $this->api();
        $request = ['originAddress' => ['countryCode' => 'IN'], 'destinationAddress' => ['countryCode' => 'CA'], 'carrierCode' => 'FDXE'];
        $expected = $request;
        $request['serviceType'] = null;
        $request['serviceOptionType'] = [];
        $request['customsClearanceDetail'] = ['commodities' => []];

        $api->retrieveRegulatoryDetails($request);

        $this->assertSame($expected, $this->transport->bodyOf(0));
    }

    /**
     * A key that the schema does not define is sent: FedEx decides about it
     * @return void
     */
    public function testAKeyThatTheSchemaDoesNotDefineIsSentFedexDecidesAboutIt(): void
    {
        $api = $this->api();
        $request = self::request() + ['notInTheSchema' => 1];

        $api->retrieveRegulatoryDetails($request);

        $this->assertSame(1, $this->transport->bodyOf(0)['notInTheSchema']);
    }

    /**
     * The empty request is the required keys of the spec, and is refused until it is filled in
     * @return void
     */
    public function testTheEmptyRequestIsTheRequiredKeysOfTheSpecAndIsRefusedUntilItIsFilledIn(): void
    {
        $api = $this->api();
        $root = Spec::ROOTS['retrieve.request'];

        $this->assertEquals(Spec::requiredOnly(Spec::components()[$root], [$root]), FedexGlobalTrade::newRequest());

        $e = self::thrown(function () use ($api) {
            $api->retrieveRegulatoryDetails(FedexGlobalTrade::newRequest());
        });

        $this->assertInstanceOf(FedexGlobalTradeException::class, $e, self::describe($e));
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);

        $request = FedexGlobalTrade::newRequest();
        $request['originAddress']['countryCode'] = 'IN';
        $request['destinationAddress']['countryCode'] = 'CA';
        $request['carrierCode'] = 'FDXE';
        $api->retrieveRegulatoryDetails($request);

        $this->assertSame(['originAddress' => ['countryCode' => 'IN'], 'destinationAddress' => ['countryCode' => 'CA'], 'carrierCode' => 'FDXE'], $this->transport->bodyOf(0));
    }

    /**
     * Every required field that is missing is refused and nothing is sent, except the value of a weight, which is optional
     * @return void
     */
    public function testEveryRequiredFieldThatIsMissingIsRefusedAndNothingIsSentExceptTheValueOfAWeight(): void
    {
        [$request, $meta] = Spec::instance('retrieve.request');
        $results = [];

        foreach ($meta['objects'] as $object) {
            foreach ($object['required'] as $key) {
                if ($object['path'] !== [] && count($object['keys']) === 1) {
                    continue; // the object is left empty and is pruned, and an optional object that is not there is fine
                }

                $copy = SpecInstances::unsetAt($request, array_merge($object['path'], [$key]));
                $api = $this->api();
                $e = self::thrown(function () use ($api, $copy) {
                    $api->retrieveRegulatoryDetails($copy);
                });
                $name = implode('.', array_merge($object['path'], [$key]));

                if ($key === 'value' && in_array(end($object['path']), ['weight', 'totalWeight'], true)) {
                    $results["$name (optional)"] = ($e === null && count($this->transport->calls) === 1) ? true : self::describe($e);
                    continue;
                }

                $results[$name] = ($e instanceof FedexGlobalTradeException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the required fields', $results);
    }

    /**
     * The weight of a commodity sent as the collection of FedEx sends it (values, a typo, and no value) is accepted
     * @return void
     */
    public function testTheWeightOfACommoditySentAsTheCollectionOfFedexSendsItIsAccepted(): void
    {
        $api = $this->api();
        $request = self::sample();
        $request['customsClearanceDetail']['commodities'][0]['weight'] = ['units' => 'KG', 'values' => '10'];

        $api->retrieveRegulatoryDetails($request);

        $this->assertSame(['units' => 'KG', 'values' => '10'], $this->transport->bodyOf(0)['customsClearanceDetail']['commodities'][0]['weight']);

        $request['customsClearanceDetail']['commodities'][0]['weight'] = ['units' => 'STONE', 'value' => 10];
        $e = self::thrown(function () use ($api, $request) {
            $api->retrieveRegulatoryDetails($request);
        });

        $this->assertInstanceOf(FedexGlobalTradeException::class, $e, 'the units are still checked');
        $this->assertCount(1, $this->transport->calls);
    }

    /**
     * A value of the wrong type in any leaf is refused and nothing is sent
     * @return void
     */
    public function testAValueOfTheWrongTypeInAnyLeafIsRefusedAndNothingIsSent(): void
    {
        [$request, $meta] = Spec::instance('retrieve.request');
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $copy = SpecInstances::setAt($request, $leaf['path'], $wrong[$leaf['type']]);
            $api = $this->api();
            $e = self::thrown(function () use ($api, $copy) {
                $api->retrieveRegulatoryDetails($copy);
            });
            $results[implode('.', $leaf['path'])] = ($e instanceof FedexGlobalTradeException && $this->transport->calls === []) ? true : self::describe($e);
        }

        $this->assertAll('the values of the wrong type', $results);
    }

    /**
     * The scalars of a looser type than the one of the schema are sent as they are
     * @return void
     */
    public function testTheScalarsOfALooserTypeAreSentAsTheyAre(): void
    {
        [$request, $meta] = Spec::instance('retrieve.request');

        $this->assertLooseScalarsAreSent($request, $meta['leaves'], function (array $r) {
            $api = $this->api();
            $api->retrieveRegulatoryDetails($r);

            return $this->transport->bodyOf(0);
        });
    }

    // ---------------------------------------------------------------- the convenience method

    /**
     * The regulatory details for two addresses make their request
     * @return void
     */
    public function testTheRegulatoryDetailsForTwoAddressesMakeTheirRequest(): void
    {
        $api = $this->api();

        $result = $api->retrieveRegulatoryDetailsFor(['countryCode' => 'IN'], ['countryCode' => 'CA', 'postalCode' => 'M5V2T6'], 'FDXE', ['serviceType' => 'FEDEX_INTERNATIONAL_PRIORITY']);

        $this->assertSame('T1', $result->transactionId);
        $this->assertSame([
            'serviceType' => 'FEDEX_INTERNATIONAL_PRIORITY',
            'originAddress' => ['countryCode' => 'IN'],
            'destinationAddress' => ['countryCode' => 'CA', 'postalCode' => 'M5V2T6'],
            'carrierCode' => 'FDXE',
        ], $this->transport->bodyOf(0));
    }

    /**
     * The options that the method sets itself are refused, and an address without its country code is refused
     * @return void
     */
    public function testTheOptionsThatTheMethodSetsItselfAreRefusedAndAnAddressWithoutItsCountryCodeIsRefused(): void
    {
        $api = $this->api();

        foreach (['originAddress', 'destinationAddress', 'carrierCode'] as $field) {
            $e = self::thrown(function () use ($api, $field) {
                $api->retrieveRegulatoryDetailsFor(['countryCode' => 'IN'], ['countryCode' => 'CA'], 'FDXE', [$field => 'x']);
            });

            $this->assertInstanceOf(FedexGlobalTradeException::class, $e, $field);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame("The option '$field' cannot be used with retrieveRegulatoryDetailsFor(): it is set by the method", $e->getMessage());
        }

        $e = self::thrown(function () use ($api) {
            $api->retrieveRegulatoryDetailsFor(['postalCode' => '1'], ['countryCode' => 'CA'], 'FDXE');
        });

        $this->assertInstanceOf(FedexGlobalTradeException::class, $e);
        $this->assertSame('Invalid request', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * The response generated from the spec is accepted, and a field that FedEx adds is tolerated
     * @return void
     */
    public function testTheResponseGeneratedFromTheSpecIsAcceptedAndAFieldThatFedexAddsIsTolerated(): void
    {
        $generated = Spec::instance('retrieve.response')[0];
        $api = $this->api([], Responses::make(200, json_encode($generated)));

        $this->assertEquals(json_decode(json_encode($generated)), $api->retrieveRegulatoryDetails(self::request()));

        $generated['addedByFedex'] = ['x' => 1];
        $api = $this->api([], Responses::make(200, json_encode($generated)));

        $this->assertSame(1, $api->retrieveRegulatoryDetails(self::request())->addedByFedex->x);
    }

    /**
     * A response with a value of the wrong type in any leaf is refused with the response in the exception
     * @return void
     */
    public function testAResponseWithAValueOfTheWrongTypeInAnyLeafIsRefusedWithTheResponseInTheException(): void
    {
        [$response, $meta] = Spec::instance('retrieve.response');
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
                $api->retrieveRegulatoryDetails($request);
            });
            $ok = ($e instanceof FedexGlobalTradeException)
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
                $api->retrieveRegulatoryDetails(self::sample());
            });

            $this->assertInstanceOf(FedexGlobalTradeException::class, $e, $body);
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('Invalid response', $e->getMessage());
            $this->assertSame([], $e->getErrors());
            $this->assertSame($decoded, $e->getData());
        }
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * A failure that is transient is repeated, because the operation changes nothing
     * @return void
     */
    public function testAFailureThatIsTransientIsRepeatedBecauseTheOperationChangesNothing(): void
    {
        $request = self::request();
        $ok = Responses::make(200, json_encode(Spec::instance('retrieve.response')[0]));

        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], $ok, null, true);

            $result = $api->retrieveRegulatoryDetails($request);

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
                $api->retrieveRegulatoryDetails(self::sample());
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
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $result = $api->retrieveRegulatoryDetails(self::sample());
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($calls[0]['body'], $calls[1]['body']);
        $this->assertSame('T2', $result->transactionId);

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);
        $e = self::thrown(function () use ($api) {
            $api->retrieveRegulatoryDetails(self::sample());
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
            $api->retrieveRegulatoryDetails(self::sample());
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
            $api->retrieveRegulatoryDetails(self::sample());
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
            $api->retrieveRegulatoryDetails(self::sample());
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }
}
