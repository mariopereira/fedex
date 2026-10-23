<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexDutyAndTax;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexDutyAndTaxException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\DutyAndTaxSpec as Spec;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Estimated Duty and Tax API: what the estimate sends, how it checks the request and the response, the convenience
 * methods, and what it repeats
 */
final class FedexDutyAndTaxTest extends TestCase
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
     * A FedexDutyAndTax over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexDutyAndTax
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexDutyAndTax
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexDutyAndTax($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The request generated from the spec, with every field filled in
     * @return array
     */
    private static function request(): array
    {
        return Spec::instance('estimate.request')[0];
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
        $this->assertInstanceOf(FedexException::class, new FedexDutyAndTaxException('x'));
        $this->assertSame('dutytax/v1/estimate', FedexDutyAndTax::ENDPOINT);
        $this->assertArrayHasKey('/' . FedexDutyAndTax::ENDPOINT, Spec::spec()['paths']);
    }

    // ---------------------------------------------------------------- what the estimate sends

    /**
     * The estimate sends one post with the generated request
     * @return void
     */
    public function testTheEstimateSendsOnePostWithTheGeneratedRequest(): void
    {
        $request = self::request();
        $api = $this->api();

        $result = $api->estimate($request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/dutytax/v1/estimate', $calls[0]['url']);
        $this->assertSame($request, $this->transport->bodyOf(0));
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('T1', $result->transactionId);
    }

    /**
     * The examples of the spec are sent as they are
     * @return void
     */
    public function testTheExamplesOfTheSpecAreSentAsTheyAre(): void
    {
        $results = [];

        foreach (Spec::requestExamples() as $name => $example) {
            $api = $this->api();
            $e = self::thrown(function () use ($api, $example) {
                $api->estimate($example);
            });

            $results[$name] = ($e === null && $this->transport->bodyOf(0) === $example) ? true : self::describe($e);
        }

        $this->assertCount(3, $results);
        $this->assertAll('the examples of the spec', $results);
    }

    /**
     * The estimate sends the headers of the spec: the token, the locale, the two content types and a UUID that is different in every call
     * @return void
     */
    public function testTheEstimateSendsTheHeadersOfTheSpecAndAUuidThatIsDifferentInEveryCall(): void
    {
        $api = $this->api();

        $api->estimate(self::request());
        $api->estimate(self::request());
        $ids = [];

        foreach ($this->transport->calls as $call) {
            $this->assertContains('Authorization: Bearer TOK', $call['headers']);
            $this->assertContains('X-locale: en_US', $call['headers']);
            $this->assertContains('Content-Type: application/json', $call['headers']);
            $this->assertContains('x-content-type: application/json', $call['headers'], 'required by the spec');
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

        $api->estimate(self::request());

        $this->assertSame('https://apis.fedex.com/dutytax/v1/estimate', $this->transport->calls[0]['url']);
    }

    /**
     * The estimate takes an object as well as an array
     * @return void
     */
    public function testTheEstimateTakesAnObjectAsWellAsAnArray(): void
    {
        $request = self::request();
        $api = $this->api();

        $api->estimate(json_decode(json_encode($request)));

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
                $api->estimate($request);
            });

            $this->assertInstanceOf(FedexDutyAndTaxException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * An unknown key is sent, because FedEx decides about it
     * @return void
     */
    public function testAnUnknownKeyIsSentBecauseFedexDecidesAboutIt(): void
    {
        $api = $this->api();

        foreach ([['notInTheSchema' => 1], ['shipper' => ['countryCode' => 'US', 'city' => 'Memphis']], ['commodities' => [['description' => 'x', 'quantity' => 1, 'unitPrice' => ['amount' => 1, 'currencyCode' => 'USD', 'tax' => 2]]]]] as $i => $extra) {
            $request = array_replace_recursive(Spec::requestExamples()['MinimumSchemaSample'], $extra);

            $api->estimate($request);

            $this->assertCount($i + 1, $this->transport->calls);
            $this->assertEquals($request, $this->transport->bodyOf($i));
        }
    }

    /**
     * The empty request is refused until it is filled in, and it is the required keys of the spec
     * @return void
     */
    public function testTheEmptyRequestIsRefusedUntilItIsFilledInAndItIsTheRequiredKeysOfTheSpec(): void
    {
        $api = $this->api();

        $this->assertSame(Spec::requiredOnly(Spec::components()['EdtDetailsRequestVO'], ['EdtDetailsRequestVO']), FedexDutyAndTax::newRequest());
        $this->assertSame(['description' => null, 'quantity' => null, 'unitPrice' => ['amount' => null, 'currencyCode' => null]], FedexDutyAndTax::newCommodity());
        $this->assertSame(Spec::requiredOnly(Spec::components()['Commodities'], ['Commodities']), FedexDutyAndTax::newCommodity());

        $e = self::thrown(function () use ($api) {
            $api->estimate(FedexDutyAndTax::newRequest());
        });

        $this->assertInstanceOf(FedexDutyAndTaxException::class, $e, self::describe($e));
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The empty request filled in, with the commodity filled in, is accepted
     * @return void
     */
    public function testTheEmptyRequestFilledInWithTheCommodityFilledInIsAccepted(): void
    {
        $request = FedexDutyAndTax::newRequest();
        $request['shipper']['countryCode'] = 'IN';
        $request['recipient']['countryCode'] = 'US';
        $commodity = FedexDutyAndTax::newCommodity();
        $commodity['description'] = 'Athletic Shoes';
        $commodity['quantity'] = 1;
        $commodity['unitPrice'] = ['amount' => 10.39, 'currencyCode' => 'USD'];
        $request['commodities'][] = $commodity;
        $api = $this->api();

        $api->estimate($request);

        $this->assertSame(Spec::requestExamples()['MinimumSchemaSample'], $this->transport->bodyOf(0), 'it is the minimum example of the spec');
    }

    /**
     * Every required field that is missing is refused and nothing is sent
     * @return void
     */
    public function testEveryRequiredFieldThatIsMissingIsRefusedAndNothingIsSent(): void
    {
        [$request, $meta] = Spec::instance('estimate.request');
        $results = [];

        foreach ($meta['objects'] as $object) {
            foreach ($object['required'] as $key) {
                $copy = json_decode(json_encode($request), true);
                $node = &$copy;

                foreach ($object['path'] as $step) {
                    $node = &$node[$step];
                }

                unset($node[$key]);
                unset($node);
                $api = $this->api();
                $e = self::thrown(function () use ($api, $copy) {
                    $api->estimate($copy);
                });
                $results[implode('.', array_merge($object['path'], [$key]))] = ($e instanceof FedexDutyAndTaxException && $this->transport->calls === []) ? true : self::describe($e);
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
        [$request, $meta] = Spec::instance('estimate.request');
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            $copy = json_decode(json_encode($request), true);
            $node = &$copy;

            foreach ($leaf['path'] as $step) {
                $node = &$node[$step];
            }

            $node = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'][$leaf['type']];
            unset($node);
            $api = $this->api();
            $e = self::thrown(function () use ($api, $copy) {
                $api->estimate($copy);
            });
            $results[implode('.', $leaf['path'])] = ($e instanceof FedexDutyAndTaxException && $this->transport->calls === []) ? true : self::describe($e);
        }

        $this->assertAll('the values of the wrong type', $results);
    }

    /**
     * A shipment purpose outside the enum is refused, and every value of the enum is sent
     * @return void
     */
    public function testAShipmentPurposeOutsideTheEnumIsRefusedAndEveryValueOfTheEnumIsSent(): void
    {
        $api = $this->api();

        foreach (['DOCUMENTS', 'FOR_RESALE', 'NOT_FOR_RESALE', 'GIFT', 'RETURN'] as $purpose) {
            $api->estimate(['shipmentPurpose' => $purpose] + Spec::requestExamples()['MinimumSchemaSample']);
        }

        $this->assertCount(5, $this->transport->calls);

        $e = self::thrown(function () use ($api) {
            $api->estimate(['shipmentPurpose' => 'PERSONAL'] + Spec::requestExamples()['MinimumSchemaSample']);
        });

        $this->assertInstanceOf(FedexDutyAndTaxException::class, $e);
        $this->assertStringContainsString('shipmentPurpose', implode(' ', $e->getErrors()));
        $this->assertCount(5, $this->transport->calls, 'nothing more was sent');
    }

    /**
     * The lengths of the schema are enforced
     * @return void
     */
    public function testTheLengthsOfTheSchemaAreEnforced(): void
    {
        $cases = ['commodityName' => 256, 'countryOfManufacture' => 2, 'description' => 512];

        foreach ($cases as $field => $max) {
            $at = Spec::requestExamples()['FullSchemaSample2'];
            $at['commodities'][0][$field] = str_repeat('x', $max);
            $over = $at;
            $over['commodities'][0][$field] = str_repeat('x', $max + 1);
            $api = $this->api();

            $api->estimate($at);

            $this->assertCount(1, $this->transport->calls, "$field at the limit");

            $e = self::thrown(function () use ($api, $over) {
                $api->estimate($over);
            });

            $this->assertInstanceOf(FedexDutyAndTaxException::class, $e, "$field over the limit");
            $this->assertCount(1, $this->transport->calls, "$field over the limit is not sent");
        }
    }

    /**
     * The country codes of the shipper and the recipient have to be two letters
     * @return void
     */
    public function testTheCountryCodesOfTheShipperAndTheRecipientHaveToBeTwoLetters(): void
    {
        foreach (['shipper', 'recipient'] as $party) {
            foreach (['USA', 'U', '', '12', "US\n"] as $bad) {
                $request = Spec::requestExamples()['MinimumSchemaSample'];
                $request[$party]['countryCode'] = $bad;
                $api = $this->api();
                $e = self::thrown(function () use ($api, $request) {
                    $api->estimate($request);
                });

                $this->assertInstanceOf(FedexDutyAndTaxException::class, $e, "$party " . json_encode($bad));
                $this->assertStringContainsString("$party.countryCode", implode(' ', $e->getErrors()));
                $this->assertSame([], $this->transport->calls);
            }
        }
    }

    /**
     * Null and empty lists are left out of the request
     * @return void
     */
    public function testNullAndEmptyListsAreLeftOut(): void
    {
        $api = $this->api();
        $request = Spec::requestExamples()['FullSchemaSample2'];
        $expected = $request;
        $request['serviceType'] = null;
        $request['shippingCost'] = null;
        $request['commodities'][0]['harmonizedCode'] = null;
        unset($expected['serviceType'], $expected['shippingCost'], $expected['commodities'][0]['harmonizedCode']);

        $api->estimate($request);

        $this->assertSame($expected, $this->transport->bodyOf(0));
    }

    // ---------------------------------------------------------------- the convenience methods

    /**
     * The estimate for commodities makes its request
     * @return void
     */
    public function testTheEstimateForCommoditiesMakesItsRequest(): void
    {
        $api = $this->api();
        $shoes = ['description' => 'Athletic Shoes', 'quantity' => 1, 'unitPrice' => ['amount' => 10.39, 'currencyCode' => 'USD']];

        $result = $api->estimateForCommodities('IN', 'US', [$shoes, (object) ['description' => 'Socks', 'quantity' => 3, 'unitPrice' => (object) ['amount' => 2, 'currencyCode' => 'USD']]], ['shipmentPurpose' => 'GIFT', 'preferredCurrency' => 'EUR']);

        $this->assertSame('T1', $result->transactionId);
        $this->assertSame([
            'shipmentPurpose' => 'GIFT',
            'preferredCurrency' => 'EUR',
            'shipper' => ['countryCode' => 'IN'],
            'recipient' => ['countryCode' => 'US'],
            'commodities' => [$shoes, ['description' => 'Socks', 'quantity' => 3, 'unitPrice' => ['amount' => 2, 'currencyCode' => 'USD']]],
        ], $this->transport->bodyOf(0));
        $this->assertSame('https://apis-sandbox.fedex.com/dutytax/v1/estimate', $this->transport->calls[0]['url']);
    }

    /**
     * The estimate for commodities with the minimum example is the minimum example
     * @return void
     */
    public function testTheEstimateForCommoditiesWithTheMinimumExampleIsTheMinimumExample(): void
    {
        $api = $this->api();
        $example = Spec::requestExamples()['MinimumSchemaSample'];

        $api->estimateForCommodities('IN', 'US', $example['commodities']);

        $sent = $this->transport->bodyOf(0);
        ksort($sent);
        ksort($example);

        $this->assertSame($example, $sent);
    }

    /**
     * The options that the method sets itself are refused
     * @return void
     */
    public function testTheOptionsThatTheMethodSetsItselfAreRefused(): void
    {
        $api = $this->api();

        foreach (['shipper', 'recipient', 'commodities'] as $field) {
            $e = self::thrown(function () use ($api, $field) {
                $api->estimateForCommodities('IN', 'US', Spec::requestExamples()['MinimumSchemaSample']['commodities'], [$field => 'x']);
            });

            $this->assertInstanceOf(FedexDutyAndTaxException::class, $e, $field);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame("The option '$field' cannot be used with estimateForCommodities(): it is set by the method", $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * No commodity, a commodity that is not valid and a country code that is not two letters are refused
     * @return void
     */
    public function testNoCommodityACommodityThatIsNotValidAndACountryCodeThatIsNotTwoLettersAreRefused(): void
    {
        $api = $this->api();
        $good = Spec::requestExamples()['MinimumSchemaSample']['commodities'];

        foreach ([['IN', 'US', []], ['IN', 'US', [['description' => 'x']]], ['IN', 'US', [['description' => 'x', 'quantity' => 'one', 'unitPrice' => ['amount' => 1, 'currencyCode' => 'USD']]]], ['IND', 'US', $good], ['IN', 'U', $good]] as $arguments) {
            $e = self::thrown(function () use ($api, $arguments) {
                $api->estimateForCommodities(...$arguments);
            });

            $this->assertInstanceOf(FedexDutyAndTaxException::class, $e, json_encode($arguments));
            $this->assertSame('Invalid request', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * The response generated from the spec is accepted, and so is the example of the response
     * @return void
     */
    public function testTheResponseGeneratedFromTheSpecIsAcceptedAndSoIsTheExampleOfTheResponse(): void
    {
        $generated = Spec::instance('estimate.response')[0];
        $api = $this->api([], Responses::make(200, json_encode($generated)));

        $this->assertEquals(json_decode(json_encode($generated)), $api->estimate(self::request()));

        foreach (Spec::responseExamples() as $name => $example) {
            $api = $this->api([], Responses::make(200, json_encode($example)));

            $this->assertEquals(json_decode(json_encode($example)), $api->estimate(self::request()), $name);
        }
    }

    /**
     * A field that FedEx adds to the response is tolerated
     * @return void
     */
    public function testAFieldThatFedexAddsToTheResponseIsTolerated(): void
    {
        $response = Spec::instance('estimate.response')[0];
        $response['addedByFedex'] = ['x' => 1];
        $response['output']['edtDetails'][0]['summary']['alsoAdded'] = true;
        $api = $this->api([], Responses::make(200, json_encode($response)));

        $result = $api->estimate(self::request());

        $this->assertSame(1, $result->addedByFedex->x);
        $this->assertTrue($result->output->edtDetails[0]->summary->alsoAdded);
    }

    /**
     * A response with a value of the wrong type in any leaf is refused with the response in the exception
     * @return void
     */
    public function testAResponseWithAValueOfTheWrongTypeInAnyLeafIsRefusedWithTheResponseInTheException(): void
    {
        [$response, $meta] = Spec::instance('estimate.response');
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            $copy = json_decode(json_encode($response), true);
            $node = &$copy;

            foreach ($leaf['path'] as $step) {
                $node = &$node[$step];
            }

            $node = ['string' => 12345, 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'][$leaf['type']];
            unset($node);
            $api = $this->api([], Responses::make(200, json_encode($copy)));
            $e = self::thrown(function () use ($api) {
                $api->estimate(self::request());
            });
            $ok = ($e instanceof FedexDutyAndTaxException)
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
                $api->estimate(self::request());
            });

            $this->assertInstanceOf(FedexDutyAndTaxException::class, $e, $body);
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('Invalid response', $e->getMessage());
            $this->assertSame([], $e->getErrors());
            $this->assertSame($decoded, $e->getData());
        }
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * A failure that is transient is repeated, because an estimate changes nothing
     * @return void
     */
    public function testAFailureThatIsTransientIsRepeatedBecauseAnEstimateChangesNothing(): void
    {
        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], Responses::make(200, '{"transactionId":"T2"}'), null, true);

            $result = $api->estimate(self::request());

            $this->assertSame('T2', $result->transactionId, "HTTP $status");
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
                $api->estimate(self::request());
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

        $result = $api->estimate(self::request());
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($calls[0]['body'], $calls[1]['body']);
        $this->assertSame('T2', $result->transactionId);

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);
        $e = self::thrown(function () use ($api) {
            $api->estimate(self::request());
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
            $api->estimate(self::request());
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
            $api->estimate(self::request());
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
            $api->estimate(self::request());
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }

    /**
     * The scalars of a looser type than the one of the schema are sent as they are: a number for a text, a text that is a number
     * for a number, and the text true for a boolean
     * @return void
     */
    public function testTheScalarsOfALooserTypeAreSentAsTheyAre(): void
    {
        [$request, $meta] = Spec::instance('estimate.request');

        $this->assertLooseScalarsAreSent($request, $meta['leaves'], function (array $r) {
            $api = $this->api();
            $api->estimate($r);

            return $this->transport->bodyOf(0);
        });
    }
}
