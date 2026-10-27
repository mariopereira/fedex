<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexHarmonizedCode;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexHarmonizedCodeException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\HarmonizedCodeSpec as Spec;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Harmonized System (HS) Code Classification API: what the search sends (with the x-content-type header), how it
 * checks the request and the response, the convenience method, and what it repeats
 */
final class FedexHarmonizedCodeTest extends TestCase
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
     * A FedexHarmonizedCode over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexHarmonizedCode
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexHarmonizedCode
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexHarmonizedCode($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The request generated from the spec, with every field filled in
     * @return array
     */
    private static function request(): array
    {
        return Spec::instance('search.request')[0];
    }

    /**
     * The examples of the path of the spec, request or response
     * @param string $side 'request' or 'response'
     * @return array The examples by name
     */
    private static function examples(string $side): array
    {
        $operation = Spec::spec()['paths']['/commodity/v2/hscodes/search']['post'];
        $content = ($side === 'request') ? $operation['requestBody']['content'] : $operation['responses']['200']['content'];

        return array_map(function ($example) {
            return $example['value'];
        }, $content['application/json']['examples']);
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
        $this->assertInstanceOf(FedexException::class, new FedexHarmonizedCodeException('x'));
        $this->assertSame('commodity/v2/hscodes/search', FedexHarmonizedCode::ENDPOINT);
        $this->assertArrayHasKey('/' . FedexHarmonizedCode::ENDPOINT, Spec::spec()['paths']);
    }

    // ---------------------------------------------------------------- what the search sends

    /**
     * The search sends one post with the generated request and the headers of the spec, with x-content-type
     * @return void
     */
    public function testTheSearchSendsOnePostWithTheGeneratedRequestAndTheHeadersOfTheSpec(): void
    {
        $request = self::request();
        $api = $this->api();

        $result = $api->searchHarmonizedCodes($request);
        $api->searchHarmonizedCodes($request);
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/commodity/v2/hscodes/search', $calls[0]['url']);
        $this->assertEquals($request, $this->transport->bodyOf(0));
        $this->assertSame('T1', $result->transactionId);
        $ids = [];

        foreach ($calls as $call) {
            $this->assertContains('Authorization: Bearer TOK', $call['headers']);
            $this->assertContains('X-locale: en_US', $call['headers']);
            $this->assertContains('Content-Type: application/json', $call['headers']);
            $this->assertContains('x-content-type: application/json', $call['headers'], 'required by the spec');
            $found = array_values(preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $call['headers']));
            $this->assertCount(1, $found);
            $ids[] = $found[0];
        }

        $this->assertNotSame($ids[0], $ids[1]);
    }

    /**
     * The four request examples of the spec are sent as they are
     * @return void
     */
    public function testTheFourRequestExamplesOfTheSpecAreSentAsTheyAre(): void
    {
        $results = [];

        foreach (self::examples('request') as $name => $example) {
            $api = $this->api();
            $e = self::thrown(function () use ($api, $example) {
                $api->searchHarmonizedCodes($example);
            });
            $results[$name] = ($e === null && $this->transport->bodyOf(0) === $example) ? true : self::describe($e);
        }

        $this->assertCount(4, $results);
        $this->assertAll('the request examples', $results);
    }

    /**
     * The production url is used when the authorization is not the sandbox, and an object is taken as well as an array
     * @return void
     */
    public function testTheProductionUrlAndAnObject(): void
    {
        $example = self::examples('request')['MinimumSchemaExample1'];
        $api = $this->api([], null, new StubAuth(false, 'client_credentials', 'id', 'secret'));

        $api->searchHarmonizedCodes(json_decode(json_encode($example)));

        $this->assertSame('https://apis.fedex.com/commodity/v2/hscodes/search', $this->transport->calls[0]['url']);
        $this->assertSame($example, $this->transport->bodyOf(0));
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
                $api->searchHarmonizedCodes($request);
            });

            $this->assertInstanceOf(FedexHarmonizedCodeException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * An unknown key is sent, and null and empty lists are left out
     * @return void
     */
    public function testAnUnknownKeyIsSentAndNullAndEmptyListsAreLeftOut(): void
    {
        $api = $this->api();

        $api->searchHarmonizedCodes(['hsClassify' => [['commodityDescription' => 'shoes', 'destinationCountryCodes' => [], 'originCountryCode' => 'IN', 'material' => null, 'notInTheSchema' => 1]]]);

        $this->assertSame(['hsClassify' => [['commodityDescription' => 'shoes', 'originCountryCode' => 'IN', 'notInTheSchema' => 1]]], $this->transport->bodyOf(0));
    }

    /**
     * The empty request and the empty commodity, and the request filled in
     * @return void
     */
    public function testTheEmptyRequestAndTheEmptyCommodityAndTheRequestFilledIn(): void
    {
        $this->assertSame(['hsClassify' => []], FedexHarmonizedCode::newRequest());
        $this->assertSame(['commodityDescription' => null, 'destinationCountryCodes' => [], 'originCountryCode' => null], FedexHarmonizedCode::newClassification());

        $api = $this->api();
        $request = FedexHarmonizedCode::newRequest();
        $commodity = FedexHarmonizedCode::newClassification();
        $commodity['commodityDescription'] = 'Footwear with outer soles of rubber or plastics. Sports footwear';
        $commodity['destinationCountryCodes'][] = 'US';
        $request['hsClassify'][] = $commodity;

        $api->searchHarmonizedCodes($request);

        $this->assertSame(self::examples('request')['MinimumSchemaExample1'], $this->transport->bodyOf(0), 'it is the minimum example 1 of the spec');
    }

    /**
     * No commodity, a commodity without either country and a commodity without its description are refused before anything is sent
     * @return void
     */
    public function testNoCommodityACommodityWithoutEitherCountryAndACommodityWithoutItsDescriptionAreRefused(): void
    {
        $api = $this->api();
        $cases = [
            'the empty request' => FedexHarmonizedCode::newRequest(),
            'nothing' => [],
            'an empty commodity' => ['hsClassify' => [FedexHarmonizedCode::newClassification()]],
            'without either country' => ['hsClassify' => [['commodityDescription' => 'shoes']]],
            'the second without either country' => ['hsClassify' => [['commodityDescription' => 'shoes', 'originCountryCode' => 'IN'], ['commodityDescription' => 'socks', 'destinationCountryCodes' => []]]],
            'without the description' => ['hsClassify' => [['originCountryCode' => 'IN']]],
        ];
        $results = [];

        foreach ($cases as $name => $request) {
            $e = self::thrown(function () use ($api, $request) {
                $api->searchHarmonizedCodes($request);
            });
            $results[$name] = ($e instanceof FedexHarmonizedCodeException && $e->getMessage() === 'Invalid request' && $e->getErrors() !== []) ? true : self::describe($e);
        }

        $this->assertAll('the requests that are refused', $results);
        $this->assertSame([], $this->transport->calls);

        $e = self::thrown(function () use ($api) {
            $api->searchHarmonizedCodes(['hsClassify' => [['commodityDescription' => 'shoes', 'originCountryCode' => 'IN'], ['commodityDescription' => 'socks']]]);
        });

        $this->assertSame(['$.hsClassify[1]: the destination country codes or the origin country code is required'], $e->getErrors());
    }

    /**
     * Every required field that is missing is refused and nothing is sent, except the countries, of which either one is enough
     * @return void
     */
    public function testEveryRequiredFieldThatIsMissingIsRefusedExceptTheCountriesOfWhichEitherOneIsEnough(): void
    {
        [$request, $meta] = Spec::instance('search.request');
        $results = [];

        foreach ($meta['objects'] as $object) {
            foreach ($object['required'] as $key) {
                $copy = SpecInstances::unsetAt($request, array_merge($object['path'], [$key]));
                $api = $this->api();
                $e = self::thrown(function () use ($api, $copy) {
                    $api->searchHarmonizedCodes($copy);
                });
                $name = implode('.', array_merge($object['path'], [$key]));

                if (in_array($key, ['destinationCountryCodes', 'originCountryCode'], true)) {
                    $results["$name (either one is enough)"] = ($e === null && count($this->transport->calls) === 1) ? true : self::describe($e);
                    continue;
                }

                $results[$name] = ($e instanceof FedexHarmonizedCodeException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the required fields', $results);
    }

    /**
     * A value of the wrong type or too long in any leaf is refused and nothing is sent
     * @return void
     */
    public function testAValueOfTheWrongTypeOrTooLongInAnyLeafIsRefusedAndNothingIsSent(): void
    {
        [$request, $meta] = Spec::instance('search.request');
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $values = [$wrong[$leaf['type']]];

            if (($leaf['max'] ?? null) !== null) {
                $values[] = str_repeat('x', $leaf['max'] + 1);
            }

            foreach ($values as $value) {
                $copy = SpecInstances::setAt($request, $leaf['path'], $value);
                $api = $this->api();
                $e = self::thrown(function () use ($api, $copy) {
                    $api->searchHarmonizedCodes($copy);
                });
                $results[implode('.', $leaf['path']) . ' ' . substr(json_encode($value), 0, 12)] = ($e instanceof FedexHarmonizedCodeException && $e->getErrors() !== [] && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the values of the wrong type or too long', $results);
    }

    /**
     * The scalars of a looser type than the one of the schema are sent as they are
     * @return void
     */
    public function testTheScalarsOfALooserTypeAreSentAsTheyAre(): void
    {
        [$request, $meta] = Spec::instance('search.request');

        $this->assertLooseScalarsAreSent($request, $meta['leaves'], function (array $r) {
            $api = $this->api();
            $api->searchHarmonizedCodes($r);

            return $this->transport->bodyOf(0);
        });
    }

    // ---------------------------------------------------------------- the convenience method

    /**
     * The search for one commodity makes its request, with the destinations as a list whatever their keys
     * @return void
     */
    public function testTheSearchForOneCommodityMakesItsRequest(): void
    {
        $api = $this->api();

        $api->searchFor('Athletic running shoes', [3 => 'US', 7 => 'CA'], 'IN', ['material' => 'leather']);

        $this->assertSame(['hsClassify' => [[
            'material' => 'leather',
            'commodityDescription' => 'Athletic running shoes',
            'destinationCountryCodes' => ['US', 'CA'],
            'originCountryCode' => 'IN',
        ]]], $this->transport->bodyOf(0));

        $api->searchFor('Athletic running shoes', ['US']);
        $api->searchFor('Athletic running shoes', [], 'IN');

        $this->assertSame(['hsClassify' => [['commodityDescription' => 'Athletic running shoes', 'destinationCountryCodes' => ['US']]]], $this->transport->bodyOf(1));
        $this->assertSame(['hsClassify' => [['commodityDescription' => 'Athletic running shoes', 'originCountryCode' => 'IN']]], $this->transport->bodyOf(2));
    }

    /**
     * The search for one commodity refuses the options that it sets itself, and a commodity without either country
     * @return void
     */
    public function testTheSearchForOneCommodityRefusesTheOptionsThatItSetsItselfAndACommodityWithoutEitherCountry(): void
    {
        $api = $this->api();

        foreach (['commodityDescription', 'destinationCountryCodes', 'originCountryCode'] as $field) {
            $e = self::thrown(function () use ($api, $field) {
                $api->searchFor('shoes', ['US'], null, [$field => 'x']);
            });

            $this->assertInstanceOf(FedexHarmonizedCodeException::class, $e, $field);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame("The option '$field' cannot be used with searchFor(): it is set by the method", $e->getMessage());
        }

        $e = self::thrown(function () use ($api) {
            $api->searchFor('shoes');
        });

        $this->assertInstanceOf(FedexHarmonizedCodeException::class, $e);
        $this->assertSame('Invalid request', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * The response examples of the spec and the generated response are accepted, and a field that FedEx adds is tolerated
     * @return void
     */
    public function testTheResponseExamplesOfTheSpecAndTheGeneratedResponseAreAccepted(): void
    {
        $request = self::examples('request')['MinimumSchemaExample1'];
        $responses = self::examples('response') + ['generated' => Spec::instance('search.response')[0]];

        foreach ($responses as $name => $response) {
            $api = $this->api([], Responses::make(200, json_encode($response)));

            $this->assertEquals(json_decode(json_encode($response)), $api->searchHarmonizedCodes($request), $name);
        }

        $response = Spec::instance('search.response')[0];
        $response['addedByFedex'] = ['x' => 1];
        $api = $this->api([], Responses::make(200, json_encode($response)));

        $this->assertSame(1, $api->searchHarmonizedCodes($request)->addedByFedex->x);
    }

    /**
     * A response with a value of the wrong type in any leaf is refused with the response in the exception, and so is one that is not an object
     * @return void
     */
    public function testAResponseWithAValueOfTheWrongTypeIsRefusedWithTheResponse(): void
    {
        [$response, $meta] = Spec::instance('search.response');
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
                $api->searchHarmonizedCodes($request);
            });
            $ok = ($e instanceof FedexHarmonizedCodeException)
                && ($e->getCode() === FedexException::EC_BAD_RESULT_RETURNED)
                && ($e->getMessage() === 'Invalid response received')
                && ($e->getData() == json_decode(json_encode($copy)))
                && !empty($e->getErrors());
            $results[implode('.', $leaf['path'])] = $ok ? true : self::describe($e);
        }

        $this->assertAll('the responses with a value of the wrong type', $results);

        $api = $this->api([], Responses::make(200, '[1,2]'));
        $e = self::thrown(function () use ($api, $request) {
            $api->searchHarmonizedCodes($request);
        });

        $this->assertInstanceOf(FedexHarmonizedCodeException::class, $e);
        $this->assertSame('Invalid response', $e->getMessage());
        $this->assertSame([1, 2], $e->getData());
        $this->assertSame([], $e->getErrors());
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * A failure that is transient is repeated, because the search changes nothing
     * @return void
     */
    public function testAFailureThatIsTransientIsRepeatedBecauseTheSearchChangesNothing(): void
    {
        $request = self::request();

        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], null, null, true);

            $api->searchHarmonizedCodes($request);

            $this->assertCount(2, $this->transport->calls, "HTTP $status is repeated once");
            $this->assertSame($this->transport->calls[0]['headers'], $this->transport->calls[1]['headers'], 'the same headers, so the same customer transaction id');
        }
    }

    /**
     * Nothing is repeated without a policy, a 401 is retried once with a fresh token, a token that cannot be had sends nothing,
     * a response that is not JSON and a 400 keep what FedEx said
     * @return void
     */
    public function testNoPolicyA401NoTokenNotJsonAndA400(): void
    {
        $request = self::examples('request')['MinimumSchemaExample2'];

        $api = $this->api([Responses::make(503, '{"errors":[]}')]);
        $e = self::thrown(function () use ($api, $request) {
            $api->searchHarmonizedCodes($request);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertCount(1, $this->transport->calls);

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);
        $this->assertSame('T2', $api->searchHarmonizedCodes($request)->transactionId);
        $this->assertSame([false, true], $this->auth->forceSeen);

        $auth = StubAuth::make();
        $auth->throwOnToken = new FedexAuthException('token failed', FedexException::EC_UNABLE_TO_AUTHENTICATE, null, 401);
        $api = $this->api([], null, $auth);
        $e = self::thrown(function () use ($api, $request) {
            $api->searchHarmonizedCodes($request);
        });
        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame([], $this->transport->calls);

        $api = $this->api([Responses::make(200, 'not json')]);
        $e = self::thrown(function () use ($api, $request) {
            $api->searchHarmonizedCodes($request);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());

        $api = $this->api([Responses::make(400, '{"transactionId":"624deea6","errors":[{"code":"INPUT.DATA.INVALID","message":"Invalid input."}]}')]);
        $e = self::thrown(function () use ($api, $request) {
            $api->searchHarmonizedCodes($request);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }
}
