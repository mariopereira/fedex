<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexEndOfDayClose;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexEndOfDayCloseException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\EndOfDayCloseSpec as Spec;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Ship End of Day Close API: what the close and the reprint send, how they check the request and the response, the
 * convenience methods, and what they repeat (only the reprint: the close changes data in FedEx)
 */
final class FedexEndOfDayCloseTest extends TestCase
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
     * A FedexEndOfDayClose over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexEndOfDayClose
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexEndOfDayClose
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexEndOfDayClose($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The two operations: the label of the spec, the method, the HTTP method, the sample, and whether it changes data in FedEx
     * @return array
     */
    public function operationsProvider(): array
    {
        return [
            'close' => ['close', 'closeGround', 'PUT', 'MinimumSamplePayload-Put-GroundClose', true],
            'reprint' => ['reprint', 'reprintReports', 'POST', 'MinimumSamplePayload-Post-Reprintground', false],
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
     * The class extends the base of the API classes, has its own exception and the endpoint of the spec, with its trailing slash
     * @return void
     */
    public function testTheClassExtendsTheBaseOfTheApiClassesHasItsOwnExceptionAndTheEndpointOfTheSpec(): void
    {
        $this->assertInstanceOf(AbstractFedexApi::class, $this->api());
        $this->assertInstanceOf(FedexException::class, new FedexEndOfDayCloseException('x'));
        $this->assertSame('ship/v1/endofday/', FedexEndOfDayClose::ENDPOINT);
        $this->assertArrayHasKey('/' . FedexEndOfDayClose::ENDPOINT, Spec::spec()['paths']);
        $this->assertSame(['GCDR', 'RGCDR'], [FedexEndOfDayClose::CLOSE_REQUEST_TYPE, FedexEndOfDayClose::REPRINT_REQUEST_TYPE]);
    }

    // ---------------------------------------------------------------- what the operations send

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     */
    public function testEachOperationSendsOneRequestWithTheGeneratedRequest(string $label, string $method, string $verb): void
    {
        $request = Spec::instance($label . '.request')[0];
        $api = $this->api();

        $result = $api->$method($request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame($verb, $calls[0]['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/ship/v1/endofday/', $calls[0]['url']);
        $this->assertEquals($request, $this->transport->bodyOf(0));
        $this->assertSame('T1', $result->transactionId);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     * @param string $sample The sample of the spec
     */
    public function testTheSampleOfTheSpecIsSentAsItIsWithItsIntegerAccountNumber(string $label, string $method, string $verb, string $sample): void
    {
        $api = $this->api();

        $api->$method(self::sample($sample));

        $this->assertSame(self::sample($sample), $this->transport->bodyOf(0));
        $this->assertIsInt($this->transport->bodyOf(0)['accountNumber']['value']);
    }

    /**
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

        $api->reprintReports(self::sample('MinimumSamplePayload-Post-Reprintground'));

        $this->assertSame('https://apis.fedex.com/ship/v1/endofday/', $this->transport->calls[0]['url']);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testARequestThatIsNotAnArrayOrAnObjectIsRefusedAndAnObjectIsTaken(string $label, string $method): void
    {
        $api = $this->api();

        foreach ([null, 'text', 5, 1.5, true] as $request) {
            $e = self::thrown(function () use ($api, $method, $request) {
                $api->$method($request);
            });

            $this->assertInstanceOf(FedexEndOfDayCloseException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);

        $request = Spec::instance($label . '.request')[0];
        $api->$method(json_decode(json_encode($request)));

        $this->assertEquals($request, $this->transport->bodyOf(0));
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testAnUnknownKeyIsSentAndNullAndEmptyListsAreLeftOut(string $label, string $method): void
    {
        $api = $this->api();
        $request = ['accountNumber' => ['value' => '123456789'], 'notInTheSchema' => 1, 'trackingNumber' => null, 'closeDocumentSpecification' => ['closeDocumentTypes' => []]];

        $api->$method($request);

        $this->assertSame(['accountNumber' => ['value' => '123456789'], 'notInTheSchema' => 1], $this->transport->bodyOf(0));
    }

    /**
     * The templates carry the key inputs of the documentation, and are sent as they are once filled in (the schema requires nothing)
     * @return void
     */
    public function testTheTemplatesCarryTheKeyInputsOfTheDocumentation(): void
    {
        $this->assertSame(['closeReqType' => 'GCDR', 'groundServiceCategory' => 'GROUND', 'accountNumber' => ['value' => null], 'closeDate' => null], FedexEndOfDayClose::newCloseRequest());
        $this->assertSame(['closeReqType' => 'RGCDR', 'groundServiceCategory' => 'GROUND', 'reprintOption' => 'BY_SHIP_DATE', 'accountNumber' => ['value' => null], 'closeDate' => null], FedexEndOfDayClose::newReprintRequest());

        $api = $this->api();
        $close = FedexEndOfDayClose::newCloseRequest();
        $close['accountNumber']['value'] = '123456789';
        $close['closeDate'] = '2026-11-03';
        $api->closeGround($close);
        $reprint = FedexEndOfDayClose::newReprintRequest();
        $api->reprintReports($reprint);

        $this->assertSame($close, $this->transport->bodyOf(0));
        $this->assertSame(['closeReqType' => 'RGCDR', 'groundServiceCategory' => 'GROUND', 'reprintOption' => 'BY_SHIP_DATE'], $this->transport->bodyOf(1), 'the schema requires nothing: what is left after the nulls is sent');
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testAValueOfTheWrongTypeOrOutsideAnEnumInAnyLeafIsRefusedAndNothingIsSent(string $label, string $method): void
    {
        [$request, $meta] = Spec::instance($label . '.request');
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            foreach (array_filter([$wrong[$leaf['type']], $leaf['enum'] !== null ? 'NOT_A_VALUE' : null]) as $value) {
                $copy = SpecInstances::setAt($request, $leaf['path'], $value);
                $api = $this->api();
                $e = self::thrown(function () use ($api, $method, $copy) {
                    $api->$method($copy);
                });
                $results[implode('.', $leaf['path']) . ' ' . json_encode($value)] = ($e instanceof FedexEndOfDayCloseException && $e->getErrors() !== [] && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the values of the wrong type or outside an enum', $results);
    }

    /**
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

    // ---------------------------------------------------------------- the convenience methods

    /**
     * The close for an account makes its request with PUT
     * @return void
     */
    public function testTheCloseForAnAccountMakesItsRequest(): void
    {
        $api = $this->api();

        $api->closeGroundFor('123456789', '2026-11-03', ['closeDocumentSpecification' => ['closeDocumentTypes' => ['MANIFEST']]]);

        $this->assertSame('PUT', $this->transport->calls[0]['method']);
        $this->assertSame([
            'closeDocumentSpecification' => ['closeDocumentTypes' => ['MANIFEST']],
            'closeReqType' => 'GCDR',
            'groundServiceCategory' => 'GROUND',
            'accountNumber' => ['value' => '123456789'],
            'closeDate' => '2026-11-03',
        ], $this->transport->bodyOf(0));
    }

    /**
     * The reprint for an account makes its request with POST, by ship date
     * @return void
     */
    public function testTheReprintForAnAccountMakesItsRequest(): void
    {
        $api = $this->api();

        $api->reprintReportsFor('123456789', '2026-11-03');

        $this->assertSame('POST', $this->transport->calls[0]['method']);
        $this->assertSame([
            'closeReqType' => 'RGCDR',
            'groundServiceCategory' => 'GROUND',
            'reprintOption' => 'BY_SHIP_DATE',
            'accountNumber' => ['value' => '123456789'],
            'closeDate' => '2026-11-03',
        ], $this->transport->bodyOf(0));
    }

    /**
     * The options that the methods set themselves are refused
     * @return void
     */
    public function testTheOptionsThatTheMethodsSetThemselvesAreRefused(): void
    {
        $api = $this->api();
        $cases = [
            'closeGroundFor' => [['closeReqType', 'groundServiceCategory', 'accountNumber', 'closeDate'], function (array $options) use ($api) {
                $api->closeGroundFor('1', '2026-11-03', $options);
            }],
            'reprintReportsFor' => [['closeReqType', 'groundServiceCategory', 'reprintOption', 'accountNumber', 'closeDate'], function (array $options) use ($api) {
                $api->reprintReportsFor('1', '2026-11-03', $options);
            }],
        ];

        foreach ($cases as $method => [$fields, $call]) {
            foreach ($fields as $field) {
                $e = self::thrown(function () use ($call, $field) {
                    $call([$field => 'x']);
                });

                $this->assertInstanceOf(FedexEndOfDayCloseException::class, $e, "$method $field");
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
                $this->assertSame("The option '$field' cannot be used with $method(): it is set by the method", $e->getMessage());
            }
        }

        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testTheResponseGeneratedFromTheSpecIsAcceptedAndAFieldThatFedexAddsIsTolerated(string $label, string $method): void
    {
        $generated = Spec::instance($label . '.response')[0];
        $api = $this->api([], Responses::make(200, json_encode($generated)));

        $this->assertEquals(json_decode(json_encode($generated)), $api->$method(Spec::instance($label . '.request')[0]));

        $generated['addedByFedex'] = ['x' => 1];
        $api = $this->api([], Responses::make(200, json_encode($generated)));

        $this->assertSame(1, $api->$method(Spec::instance($label . '.request')[0])->addedByFedex->x);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     * @param string $sample The sample of the spec
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAResponseWithAValueOfTheWrongTypeIsRefusedWithTheResponseAndTheCloseSaysItMayHaveBeenDone(string $label, string $method, string $verb, string $sample, bool $changes): void
    {
        [$response, $meta] = Spec::instance($label . '.response');
        $request = Spec::instance($label . '.request')[0];
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $note = $changes ? ' (the operation may have been done by FedEx: check it before repeating it)' : '';
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
            $ok = ($e instanceof FedexEndOfDayCloseException)
                && ($e->getCode() === FedexException::EC_BAD_RESULT_RETURNED)
                && ($e->getMessage() === 'Invalid response received' . $note)
                && ($e->getData() == json_decode(json_encode($copy)))
                && !empty($e->getErrors());
            $results[implode('.', $leaf['path'])] = $ok ? true : self::describe($e);
        }

        $this->assertAll('the responses with a value of the wrong type', $results);

        $api = $this->api([], Responses::make(200, '[1,2]'));
        $e = self::thrown(function () use ($api, $method, $request) {
            $api->$method($request);
        });

        $this->assertInstanceOf(FedexEndOfDayCloseException::class, $e);
        $this->assertSame('Invalid response' . $note, $e->getMessage());
        $this->assertSame([1, 2], $e->getData());
        $this->assertSame([], $e->getErrors());
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     * @param string $verb The HTTP method
     * @param string $sample The sample of the spec
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAFailureThatIsTransientIsRepeatedForTheReprintAndNotForTheClose(string $label, string $method, string $verb, string $sample, bool $changes): void
    {
        $request = Spec::instance($label . '.request')[0];

        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], null, null, true);

            if ($changes) {
                $e = self::thrown(function () use ($api, $method, $request) {
                    $api->$method($request);
                });

                $this->assertInstanceOf(FedexClientException::class, $e, "HTTP $status");
                $this->assertCount(1, $this->transport->calls, "HTTP $status is not repeated: the close changes data in FedEx");
                continue;
            }

            $api->$method($request);

            $this->assertCount(2, $this->transport->calls, "HTTP $status is repeated once");
            $this->assertSame($this->transport->calls[0]['headers'], $this->transport->calls[1]['headers'], 'the same headers, so the same customer transaction id');
        }
    }

    /**
     * @dataProvider operationsProvider
     * @param string $label The label of the operation in the spec
     * @param string $method The method
     */
    public function testA401IsRetriedOnceWithAFreshTokenAndTwo401sInARowStopAfterTwoRequests(string $label, string $method): void
    {
        $request = Spec::instance($label . '.request')[0];
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $this->assertSame('T2', $api->$method($request)->transactionId);
        $this->assertCount(2, $this->transport->calls);
        $this->assertSame([false, true], $this->auth->forceSeen);

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}')]);
        $e = self::thrown(function () use ($api, $method, $request) {
            $api->$method($request);
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(401, $e->getStatusCode());
        $this->assertCount(2, $this->transport->calls);
    }

    /**
     * Nothing is repeated without a policy, a token that cannot be had sends nothing, a response that is not JSON and a 400 keep what FedEx said
     * @return void
     */
    public function testNoPolicyNoTokenNotJsonAndA400(): void
    {
        $request = self::sample('MinimumSamplePayload-Post-Reprintground');

        $api = $this->api([Responses::make(503, '{"errors":[]}')]);
        $e = self::thrown(function () use ($api, $request) {
            $api->reprintReports($request);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertCount(1, $this->transport->calls);

        $auth = StubAuth::make();
        $auth->throwOnToken = new FedexAuthException('token failed', FedexException::EC_UNABLE_TO_AUTHENTICATE, null, 401);
        $api = $this->api([], null, $auth);
        $e = self::thrown(function () use ($api, $request) {
            $api->reprintReports($request);
        });
        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame([], $this->transport->calls);

        $api = $this->api([Responses::make(200, 'not json')]);
        $e = self::thrown(function () use ($api, $request) {
            $api->reprintReports($request);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());

        $api = $this->api([Responses::make(400, '{"transactionId":"624deea6","errors":[{"code":"INPUT.DATA.INVALID","message":"Invalid input."}]}')]);
        $e = self::thrown(function () use ($api, $request) {
            $api->reprintReports($request);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
        $this->assertSame(FedexUtils::pruneEmpty($request), $request, 'the sample has nothing to prune');
    }
}
