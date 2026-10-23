<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexConsolidation;
use Mariopereira\Fedex\Api\Schemas\FedexConsolidationSchemas;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexConsolidationException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\ConsolidationSpec as Spec;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Consolidation API: what each operation sends, how it checks the request and the response at the top level,
 * what it reports with checkRequest() and checkResponse(), and what it repeats and what it never repeats
 */
final class FedexConsolidationTest extends TestCase
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
     * A FedexConsolidation over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexConsolidation
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexConsolidation
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexConsolidation($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The request generated from the spec for a root
     * @param string $label A key of ConsolidationSpec::ROOTS ending in .request
     * @return array
     */
    private static function request(string $label): array
    {
        return Spec::instance($label)[0];
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
     * The path of a call, without the host
     * @param array $call A call recorded by the transport
     * @return string
     */
    private static function pathOf(array $call): string
    {
        return preg_replace('#^https://apis(-sandbox)?\.fedex\.com#', '', $call['url']);
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
     * The nine operations: operation name, method, request label, response label, template method, HTTP method, path and whether it changes data
     * @return array
     */
    public function operationsProvider(): array
    {
        return [
            'create' => ['create', 'createConsolidation', 'create.request', 'create.response', 'newCreateRequest', 'POST', '/ship/v1/consolidations', true],
            'modify' => ['modify', 'modifyConsolidation', 'modify.request', 'modify.response', 'newModifyRequest', 'PUT', '/ship/v1/consolidations', true],
            'createShipment' => ['createShipment', 'createConsolidationShipment', 'createshipment.request', 'createshipment.response', 'newCreateShipmentRequest', 'POST', '/ship/v1/consolidations/shipments', true],
            'shipmentResults' => ['shipmentResults', 'getShipmentResults', 'results.request', 'results.response', 'newShipmentResultsRequest', 'POST', '/ship/v1/consolidations/results', false],
            'confirm' => ['confirm', 'confirmConsolidation', 'confirm.request', 'confirm.response', 'newConfirmRequest', 'POST', '/ship/v1/consolidations/confirmations', true],
            'confirmResults' => ['confirmResults', 'getConfirmationResults', 'confirmresults.request', 'confirmresults.response', 'newConfirmResultsRequest', 'POST', '/ship/v1/consolidations/confirmationresults', false],
            'retrieve' => ['retrieve', 'retrieveConsolidation', 'retrieve.request', 'retrieve.response', 'newRetrieveRequest', 'POST', '/ship/v1/consolidations/retrieve', false],
            'deleteShipments' => ['deleteShipments', 'deleteConsolidationShipments', 'deleteshipments.request', 'deleteshipments.response', 'newDeleteShipmentsRequest', 'PUT', '/ship/v1/consolidations/shipments/delete', true],
            'delete' => ['delete', 'deleteConsolidation', 'delete.request', 'delete.response', 'newDeleteRequest', 'PUT', '/ship/v1/consolidations/delete', true],
        ];
    }

    /**
     * The class extends the base of the API classes and has its own exception
     * @return void
     */
    public function testTheClassExtendsTheBaseOfTheApiClassesAndHasItsOwnException(): void
    {
        $this->assertInstanceOf(AbstractFedexApi::class, $this->api());
        $this->assertInstanceOf(FedexException::class, new FedexConsolidationException('x'));
    }

    /**
     * The operations are the paths and methods of the spec, and each one is described as it has to be
     * @return void
     */
    public function testTheOperationsAreThePathsAndMethodsOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];
        $seen = [];

        foreach ($this->operationsProvider() as [$name, , $label, $responseLabel, , $verb, $path, $changes]) {
            $operation = FedexConsolidation::OPERATIONS[$name];

            $this->assertSame($verb, $operation[0], $name);
            $this->assertSame(ltrim($path, '/'), $operation[1], $name);
            $this->assertSame(Spec::renamed(Spec::ROOTS[$label]), $operation[2], $name);
            $this->assertSame(Spec::renamed(Spec::ROOTS[$responseLabel]), $operation[3], $name);
            $this->assertSame($changes, $operation[4], $name);
            $this->assertArrayHasKey(strtolower($verb), $paths[$path], "$verb $path is in the spec");
            $seen[] = strtolower($verb) . ' ' . $path;
        }

        $inSpec = [];

        foreach ($paths as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $inSpec[] = $method . ' ' . $path;
            }
        }

        sort($seen);
        sort($inSpec);

        $this->assertSame($inSpec, $seen, 'every operation of the spec is implemented, and nothing else');
        $this->assertSame(array_keys(FedexConsolidation::OPERATIONS), array_column($this->operationsProvider(), 0));
    }

    // ---------------------------------------------------------------- what each operation sends

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testEachOperationSendsOneCallWithTheGeneratedRequest(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $request = self::request($label);
        $api = $this->api();

        $result = $api->$method($request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame($verb, $calls[0]['method']);
        $this->assertSame($path, self::pathOf($calls[0]));
        $this->assertSame(json_decode(json_encode($request), true), $this->transport->bodyOf(0));
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('T1', $result->transactionId);
    }

    /**
     * Each operation sends the bearer token, the locale and a UUID as the customer transaction ID, and every call has its own
     * @return void
     */
    public function testEachOperationSendsTheBearerTokenTheLocaleAndAUuidThatIsDifferentInEveryCall(): void
    {
        $api = $this->api();

        $api->retrieveConsolidation(self::request('retrieve.request'));
        $api->retrieveConsolidation(self::request('retrieve.request'));
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
     * The sample payloads of the spec are sent as they are
     * @return void
     */
    public function testTheSamplePayloadsOfTheSpecAreSentAsTheyAre(): void
    {
        $methods = [];

        foreach ($this->operationsProvider() as [, $method, $label]) {
            $methods[$label] = $method;
        }

        $results = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = json_decode(json_encode(Spec::components()[$sample]['example']), true);
            $api = $this->api();
            $method = $methods[$label];
            $e = self::thrown(function () use ($api, $method, $example) {
                $api->$method($example);
            });
            $sent = $this->transport->bodyOf(0);

            $results[$sample] = ($e === null && $sent === \Mariopereira\Fedex\Misc\FedexUtils::pruneEmpty($example)) ? true : self::describe($e);
        }

        $this->assertCount(19, $results);
        $this->assertAll('the sample payloads', $results);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAnOperationTakesAnObjectAsWellAsAnArray(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $request = self::request($label);
        $api = $this->api();

        $api->$method(json_decode(json_encode($request)));

        $this->assertSame(json_decode(json_encode($request), true), $this->transport->bodyOf(0));
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testARequestThatIsNotAnArrayOrAnObjectIsRefusedWithItsOwnMessage(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $api = $this->api();

        foreach ([null, 'text', 5, 1.5, true] as $request) {
            $e = self::thrown(function () use ($api, $method, $request) {
                $api->$method($request);
            });

            $this->assertInstanceOf(FedexConsolidationException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAnUnknownKeyAtTheTopLevelIsSentBecauseFedexDecidesAboutIt(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $request = self::request($label);
        $request['notInTheSchema'] = 1;
        $api = $this->api();

        $api->$method($request);

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame(1, $this->transport->bodyOf(0)['notInTheSchema']);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testNestedContentThatTheSchemaDoesNotKnowIsSentAsGiven(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $request = self::request($label);

        foreach (['consolidationKey', 'requestedConsolidation', 'requestedShipment', 'labelSpecification', 'trackingId', 'accountNumber'] as $key) {
            if (isset($request[$key]) && is_array($request[$key])) {
                $request[$key]['notInTheSchema'] = ['deep' => [1, 2, 3]];
                $request[$key]['consolidationType'] = 'WHATEVER';
            }
        }

        $api = $this->api();

        $api->$method($request);

        $this->assertSame(json_decode(json_encode($request), true), $this->transport->bodyOf(0), 'sent as given: FedEx checks what is inside');
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testTheEmptyRequestIsRefusedUntilItIsFilledIn(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $method, $template) {
            $api->$method(FedexConsolidation::$template());
        });

        $this->assertInstanceOf(FedexConsolidationException::class, $e, self::describe($e));
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testTheEmptyRequestHasTheRequiredFieldsOfTheTopLevelOfTheSpec(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $root = Spec::components()[Spec::ROOTS[$label]];
        $shallow = FedexConsolidationSchemas::shallowDefinitions()[Spec::renamed(Spec::ROOTS[$label])];
        $expected = [];

        foreach ($root['required'] as $key) {
            $node = $shallow[$key];
            $expected[$key] = ($key === 'accountNumber') ? ['value' => null] : (($node === 'object' || $node === 'array') ? [] : null);
        }

        $actual = FedexConsolidation::$template();
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }

    /**
     * Every required field of the top level that is missing is refused and nothing is sent
     * @return void
     */
    public function testEveryRequiredFieldOfTheTopLevelThatIsMissingIsRefusedAndNothingIsSent(): void
    {
        $results = [];

        foreach ($this->operationsProvider() as [$name, $method, $label]) {
            foreach (Spec::components()[Spec::ROOTS[$label]]['required'] as $key) {
                $request = self::request($label);
                unset($request[$key]);
                $api = $this->api();
                $e = self::thrown(function () use ($api, $method, $request) {
                    $api->$method($request);
                });
                $results["$name without $key"] = ($e instanceof FedexConsolidationException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the required fields of the top level', $results);
    }

    /**
     * A value of the wrong type at the top level is refused and nothing is sent
     * @return void
     */
    public function testAValueOfTheWrongTypeAtTheTopLevelIsRefusedAndNothingIsSent(): void
    {
        $results = [];

        foreach ($this->operationsProvider() as [$name, $method, $label]) {
            $shallow = FedexConsolidationSchemas::shallowDefinitions()[Spec::renamed(Spec::ROOTS[$label])];

            foreach ($shallow as $key => $node) {
                $field = rtrim($key, '?');
                $request = self::request($label);

                if (!array_key_exists($field, $request)) {
                    continue;
                }

                $wrong = ($node === 'object') ? 'text' : (($node === 'array') ? 'text' : ((is_array($node) || $node === 'string') ? ['x'] : 'mixed'));

                if ($wrong === 'mixed') {
                    continue;
                }

                $request[$field] = $wrong;
                $api = $this->api();
                $e = self::thrown(function () use ($api, $method, $request) {
                    $api->$method($request);
                });
                $results["$name $field as " . json_encode($wrong)] = ($e instanceof FedexConsolidationException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the values of the wrong type at the top level', $results);
    }

    /**
     * A value outside an enum of the top level is refused
     * @return void
     */
    public function testAValueOutsideAnEnumOfTheTopLevelIsRefused(): void
    {
        $api = $this->api();
        $request = self::request('confirm.request');
        $request['processingOptionType'] = 'NOT_AN_ENUM_VALUE';

        $e = self::thrown(function () use ($api, $request) {
            $api->confirmConsolidation($request);
        });

        $this->assertInstanceOf(FedexConsolidationException::class, $e);
        $this->assertStringContainsString('processingOptionType', implode(' ', $e->getErrors()));
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * Null and empty lists and objects are left out of the request
     * @return void
     */
    public function testNullAndEmptyListsAndObjectsAreLeftOut(): void
    {
        $api = $this->api();
        $request = self::request('createshipment.request');
        $expected = $request;
        $request['processingOptions'] = [];
        $request['index'] = null;
        $request['shipAction'] = null;
        $request['version'] = [];
        unset($expected['processingOptions'], $expected['index'], $expected['shipAction'], $expected['version']);

        $api->createConsolidationShipment($request);

        $this->assertSame(json_decode(json_encode($expected), true), $this->transport->bodyOf(0));
    }

    // ---------------------------------------------------------------- the convenience methods

    /**
     * Each method that takes a job id or a key makes its request
     * @return void
     */
    public function testEachMethodThatTakesAJobIdOrAKeyMakesItsRequest(): void
    {
        $key = ['consolidationType' => 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'index' => '12345', 'shipDatestamp' => '2022-03-21'];
        $tracking = ['trackingNumber' => '547698721345', 'formId' => '123', 'trackingIdType' => 'EXPRESS'];
        $api = $this->api();

        $api->getShipmentResultsByJobId('123456789', 'JOB1');
        $api->getConfirmationResultsByJobId('123456789', 'JOB2', ['version' => ['major' => 1]]);
        $api->retrieveByKey('123456789', $key);
        $api->deleteByKey('123456789', $key, $tracking, ['version' => ['major' => 2]]);
        $calls = $this->transport->calls;

        $this->assertSame(['POST /ship/v1/consolidations/results', 'POST /ship/v1/consolidations/confirmationresults', 'POST /ship/v1/consolidations/retrieve', 'PUT /ship/v1/consolidations/delete'], array_map(function (array $call) {
            return $call['method'] . ' ' . self::pathOf($call);
        }, $calls));
        $this->assertSame(['accountNumber' => ['value' => '123456789'], 'jobId' => 'JOB1'], $this->transport->bodyOf(0));
        $this->assertSame(['version' => ['major' => 1], 'accountNumber' => ['value' => '123456789'], 'jobId' => 'JOB2'], $this->transport->bodyOf(1));
        $this->assertSame(['accountNumber' => ['value' => '123456789'], 'consolidationKey' => $key], $this->transport->bodyOf(2));
        $this->assertSame(['version' => ['major' => 2], 'accountNumber' => ['value' => '123456789'], 'consolidationKey' => $key, 'trackingId' => $tracking], $this->transport->bodyOf(3));
    }

    /**
     * The options that the method sets itself are refused
     * @return void
     */
    public function testTheOptionsThatTheMethodSetsItselfAreRefused(): void
    {
        $api = $this->api();
        $cases = [
            ['getShipmentResultsByJobId', ['1', 'J'], ['accountNumber', 'jobId']],
            ['getConfirmationResultsByJobId', ['1', 'J'], ['accountNumber', 'jobId']],
            ['retrieveByKey', ['1', ['index' => '1']], ['accountNumber', 'consolidationKey']],
            ['deleteByKey', ['1', ['index' => '1'], ['trackingNumber' => '1']], ['accountNumber', 'consolidationKey', 'trackingId']],
        ];

        foreach ($cases as [$method, $arguments, $fields]) {
            foreach ($fields as $field) {
                $e = self::thrown(function () use ($api, $method, $arguments, $field) {
                    $api->$method(...array_merge($arguments, [[$field => 'x']]));
                });

                $this->assertInstanceOf(FedexConsolidationException::class, $e, "$method $field");
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
                $this->assertSame("The option '$field' cannot be used with $method(): it is set by the method", $e->getMessage());
            }
        }

        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testTheResponseGeneratedFromTheSpecForTheOperationIsAccepted(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $api = $this->api([], Responses::make(200, json_encode(self::request($responseLabel))));

        $result = $api->$method(self::request($label));

        $this->assertEquals(json_decode(json_encode(self::request($responseLabel))), $result);
        $this->assertSame([], FedexConsolidation::checkResponse($name, $result), 'and the whole of the schema accepts it');
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAFieldThatFedexAddsToTheResponseIsTolerated(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $response = self::request($responseLabel);
        $response['addedByFedex'] = ['x' => 1];
        $response['output']['alsoAdded'] = true;
        $api = $this->api([], Responses::make(200, json_encode($response)));

        $result = $api->$method(self::request($label));

        $this->assertTrue($result->addedByFedex->x === 1 && $result->output->alsoAdded === true);
        $this->assertSame([], FedexConsolidation::checkResponse($name, $result));
    }

    /**
     * A response with something wrong deep inside is returned, and the whole check reports it
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAResponseWithSomethingWrongDeepInsideIsReturnedAndTheWholeCheckReportsIt(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        [$response, $meta] = Spec::instance($responseLabel);
        $leaf = null;

        foreach ($meta['leaves'] as $candidate) {
            if ($candidate['typed'] && $candidate['type'] === 'string' && count($candidate['path']) >= 2 && $candidate['path'][0] === 'output') {
                $leaf = $candidate;
                break;
            }
        }

        if ($leaf === null) {
            $this->addToAssertionCount(1); // a response with nothing deep inside: nothing to break

            return;
        }

        $copy = json_decode(json_encode($response), true);
        $node = &$copy;

        foreach ($leaf['path'] as $step) {
            $node = &$node[$step];
        }

        $node = 12345;
        unset($node);
        $api = $this->api([], Responses::make(200, json_encode($copy)));

        $result = $api->$method(self::request($label));

        $this->assertInstanceOf(\stdClass::class, $result, 'the top level is right, so the response is returned');
        $this->assertNotEmpty(FedexConsolidation::checkResponse($name, $result), implode('.', $leaf['path']));
    }

    /**
     * A response with a wrong top level is refused with the response in the exception
     * @return void
     */
    public function testAResponseWithAWrongTopLevelIsRefusedWithTheResponseInTheException(): void
    {
        foreach ($this->operationsProvider() as [$name, $method, $label, , , , , $changes]) {
            $known = FedexConsolidationSchemas::shallowDefinitions()[Spec::renamed(Spec::ROOTS[$label === '' ? 'create.request' : str_replace('.request', '.response', $label)])];
            $bodies = ['{"output":"text"}'];

            foreach (['transactionId' => '{"transactionId":5}', 'customerTransactionId' => '{"customerTransactionId":["x"]}'] as $field => $body) {
                if (isset($known[$field . '?'])) {
                    $bodies[] = $body;
                }
            }

            foreach ($bodies as $body) {
                $api = $this->api([], Responses::make(200, $body));
                $e = self::thrown(function () use ($api, $method, $label) {
                    $api->$method(self::request($label));
                });

                $this->assertInstanceOf(FedexConsolidationException::class, $e, "$name $body");
                $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
                $this->assertSame('Invalid response received' . ($changes ? ' (the operation may have been done by FedEx: check it before repeating it)' : ''), $e->getMessage());
                $this->assertNotEmpty($e->getErrors());
                $this->assertEquals(json_decode($body), $e->getData(), 'the response is in the exception');
            }
        }
    }

    /**
     * A response that is not an object is refused with the response and no errors, and only an operation that changes data warns
     * @return void
     */
    public function testAResponseThatIsNotAnObjectIsRefusedWithTheResponseAndNoErrors(): void
    {
        foreach ($this->operationsProvider() as [$name, $method, $label, , , , , $changes]) {
            foreach (['[1,2]' => [1, 2], '5' => 5, '"text"' => 'text', 'true' => true, '' => null] as $body => $decoded) {
                $api = $this->api([], Responses::make(200, (string) $body));
                $e = self::thrown(function () use ($api, $method, $label) {
                    $api->$method(self::request($label));
                });

                $this->assertInstanceOf(FedexConsolidationException::class, $e, "$name $body");
                $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
                $this->assertSame('Invalid response' . ($changes ? ' (the operation may have been done by FedEx: check it before repeating it)' : ''), $e->getMessage());
                $this->assertSame([], $e->getErrors());
                $this->assertSame($decoded, $e->getData());
            }
        }
    }

    // ---------------------------------------------------------------- checkRequest() and checkResponse()

    /**
     * The sample payloads that pass the whole schema report nothing, and the others report what the spec contradicts
     * @return void
     */
    public function testTheSamplePayloadsThatPassTheWholeSchemaReportNothingAndTheOthersReportWhatTheSpecContradicts(): void
    {
        $operations = [];

        foreach ($this->operationsProvider() as [$name, , $label]) {
            $operations[$label] = $name;
        }

        $clean = [];

        foreach (Spec::SAMPLES as $sample => $label) {
            $example = json_decode(json_encode(Spec::components()[$sample]['example']), true);
            $problems = FedexConsolidation::checkRequest($operations[$label], $example);

            if ($problems === []) {
                $clean[] = $sample;
            } else {
                $this->assertContainsOnly('string', $problems);
                $this->assertMatchesRegularExpression('/^\$\./', $problems[0], 'each problem starts with the path of the field');
            }
        }

        $this->assertSame([
            'MinimumSamplePayload-ConsolidationShipmentResults',
            'MinimumSamplePayload-confirmConsolidationResults',
            'MinimumSamplePayload-DeleteShipment',
        ], $clean);
    }

    /**
     * The check of a request reports the problems of a nested field that the operation would send
     * @return void
     */
    public function testTheCheckOfARequestReportsTheProblemsOfANestedFieldThatTheOperationWouldSend(): void
    {
        $example = json_decode(json_encode(Spec::components()['MinimumSamplePayload-RetrieveConsolidation']['example']), true);
        $problems = FedexConsolidation::checkRequest('retrieve', $example);

        $this->assertSame([
            '$.consolidationKey.date: required key is missing',
            '$.consolidationKey.type: required key is missing',
            '$.consolidationKey.consolidationType: unexpected key',
            '$.consolidationKey.shipDatestamp: unexpected key',
        ], $problems);

        $api = $this->api();

        $this->assertInstanceOf(\stdClass::class, $api->retrieveConsolidation($example), 'and the operation sends it anyway');
        $this->assertSame($example, $this->transport->bodyOf(0));
    }

    /**
     * The check of a request leaves out null and empty lists as the sending does, and takes an object
     * @return void
     */
    public function testTheCheckOfARequestLeavesOutNullAndEmptyListsAsTheSendingDoesAndTakesAnObject(): void
    {
        $request = ['accountNumber' => ['value' => '1'], 'jobId' => 'J', 'version' => [], 'extra' => null];

        $this->assertSame([], FedexConsolidation::checkRequest('shipmentResults', $request), 'null and the empty list are left out');
        $this->assertSame(['$.extra: unexpected key'], FedexConsolidation::checkRequest('shipmentResults', ['extra' => 1] + $request), 'but a value is not');
        $this->assertSame([], FedexConsolidation::checkRequest('shipmentResults', ['accountNumber' => ['value' => '1'], 'jobId' => 'J', 'version' => []]));
        $this->assertSame([], FedexConsolidation::checkRequest('shipmentResults', json_decode('{"accountNumber":{"value":"1"},"jobId":"J"}')));
        $this->assertNotEmpty(FedexConsolidation::checkRequest('shipmentResults', ['jobId' => 5]));
    }

    /**
     * An operation that is not known, and something that is not a request or a response, are refused
     * @return void
     */
    public function testAnOperationThatIsNotKnownAndSomethingThatIsNotARequestOrAResponseAreRefused(): void
    {
        foreach (['checkRequest', 'checkResponse'] as $method) {
            $e = self::thrown(function () use ($method) {
                FedexConsolidation::$method('nothing', []);
            });

            $this->assertInstanceOf(FedexConsolidationException::class, $e, $method);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame("Unknown operation 'nothing' (use one of: create, modify, createShipment, shipmentResults, confirm, confirmResults, retrieve, deleteShipments, delete)", $e->getMessage());

            foreach ([null, 'text', 5] as $value) {
                $e = self::thrown(function () use ($method, $value) {
                    FedexConsolidation::$method('create', $value);
                });

                $this->assertInstanceOf(FedexConsolidationException::class, $e);
                $this->assertSame($method === 'checkRequest' ? 'The request must be an array or an object' : 'The response must be an array or an object', $e->getMessage());
            }
        }
    }

    /**
     * The check of a response reports a problem anywhere in it and tolerates what FedEx adds
     * @return void
     */
    public function testTheCheckOfAResponseReportsAProblemAnywhereInItAndToleratesWhatFedexAdds(): void
    {
        $response = ['transactionId' => 'T', 'output' => ['alerts' => [['code' => 5]]], 'added' => 1];

        $this->assertSame(['$.output.alerts[0].code: expected string, got integer'], FedexConsolidation::checkResponse('create', $response));
        $this->assertSame([], FedexConsolidation::checkResponse('create', ['transactionId' => 'T', 'added' => 1]));
        $this->assertSame([], FedexConsolidation::checkResponse('create', json_decode('{"transactionId":"T"}')));
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testAFailureThatIsTransientIsRepeatedOnlyByTheOperationsThatCanBeRepeated(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], Responses::make(200, '{"transactionId":"T2"}'), null, true);

            $e = self::thrown(function () use ($api, $method, $label) {
                $api->$method(self::request($label));
            });

            if ($changes) {
                $this->assertInstanceOf(FedexClientException::class, $e, "$name, HTTP $status: it changes data, so it must fail at the first failure");
                $this->assertCount(1, $this->transport->calls, "$name, HTTP $status is never repeated");
            } else {
                $this->assertNull($e, "$name, HTTP $status: " . self::describe($e));
                $this->assertCount(2, $this->transport->calls, "$name, HTTP $status is repeated once");
                $this->assertSame($this->transport->calls[0]['body'], $this->transport->calls[1]['body']);
            }
        }
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testNothingIsRepeatedWhenTheClientHasNoPolicy(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        foreach ([[503, '{"errors":[{"code":"X","message":"boom"}]}', null], [500, '{"errors":[]}', null], [400, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error), Responses::make(200, '{"transactionId":"T2"}')]);

            $e = self::thrown(function () use ($api, $method, $label) {
                $api->$method(self::request($label));
            });

            $this->assertInstanceOf(FedexClientException::class, $e, "HTTP $status");
            $this->assertSame($status, $e->getStatusCode());
            $this->assertCount(1, $this->transport->calls, "HTTP $status is not repeated");
        }
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testA401IsRetriedOnceWithAFreshTokenSameUrlSameBody(string $name, string $method, string $label, string $responseLabel, string $template, string $verb, string $path, bool $changes): void
    {
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $result = $api->$method(self::request($label));
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($calls[0]['method'], $calls[1]['method']);
        $this->assertSame($calls[0]['url'], $calls[1]['url']);
        $this->assertSame($calls[0]['body'], $calls[1]['body']);
        $this->assertSame('T2', $result->transactionId);
    }

    /**
     * Two 401s in a row stop after two requests
     * @return void
     */
    public function testTwo401sInARowStopAfterTwoRequests(): void
    {
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $e = self::thrown(function () use ($api) {
            $api->retrieveConsolidation(self::request('retrieve.request'));
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
            $api->retrieveConsolidation(self::request('retrieve.request'));
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
            $api->retrieveConsolidation(self::request('retrieve.request'));
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
        $api = $this->api([Responses::make(400, '{"transactionId":"624deea6","errors":[{"code":"CONSOLIDATION.NOT.FOUND","message":"Not found."}]}')]);

        $e = self::thrown(function () use ($api) {
            $api->deleteConsolidation(self::request('delete.request'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }

    /**
     * The scalars of a looser type than the one of the schema are sent as they are, at the top level that is checked: a number for a
     * text, and the text that is not a number is still refused
     * @return void
     */
    public function testTheScalarsOfALooserTypeAreSentAsTheyAre(): void
    {
        $request = ['accountNumber' => ['value' => '123456789'], 'jobId' => 12345];
        $api = $this->api();

        $api->getShipmentResults($request);

        $this->assertSame(12345, $this->transport->bodyOf(0)['jobId']);

        $e = self::thrown(function () use ($api) {
            $api->getShipmentResults(['accountNumber' => ['value' => '123456789'], 'jobId' => ['x']]);
        });

        $this->assertInstanceOf(FedexConsolidationException::class, $e);
        $this->assertCount(1, $this->transport->calls);
    }
}
