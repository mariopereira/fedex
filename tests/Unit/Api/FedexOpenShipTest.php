<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexOpenShip;
use Mariopereira\Fedex\Api\Schemas\FedexOpenShipSchemas;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexOpenShipException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\OpenShipSpec as Spec;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Open Ship API: what each operation sends, how it checks the request and the response at the top level, what
 * it reports with checkRequest() and checkResponse(), and what it repeats and what it never repeats
 */
final class FedexOpenShipTest extends TestCase
{
    use BatchAssertions;
    use LooseScalars;

    /**
     * The note of the messages of the operations that change data in FedEx
     */
    private const NOTE = ' (the operation may have been done by FedEx: check it before repeating it)';

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
     * A FedexOpenShip over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexOpenShip
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexOpenShip
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexOpenShip($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The request generated from the spec for a root
     * @param string $label A key of OpenShipSpec::ROOTS
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
     * The ten operations: operation name, method, request label, response label, template method, HTTP method, path and whether it changes data
     * @return array
     */
    public function operationsProvider(): array
    {
        return [
            'create' => ['create', 'createOpenShipment', 'create.request', 'create.response', 'newCreateRequest', 'POST', '/ship/v1/openshipments/create', true],
            'modify' => ['modify', 'modifyOpenShipment', 'modify.request', 'modify.response', 'newModifyRequest', 'PUT', '/ship/v1/openshipments/', true],
            'confirm' => ['confirm', 'confirmOpenShipment', 'confirm.request', 'confirm.response', 'newConfirmRequest', 'POST', '/ship/v1/openshipments/', true],
            'modifyPackage' => ['modifyPackage', 'modifyPackage', 'modifyPackage.request', 'modifyPackage.response', 'newModifyPackageRequest', 'PUT', '/ship/v1/openshipments/packages', true],
            'addPackages' => ['addPackages', 'addPackages', 'addPackages.request', 'addPackages.response', 'newAddPackagesRequest', 'POST', '/ship/v1/openshipments/packages', true],
            'deletePackages' => ['deletePackages', 'deletePackages', 'deletePackages.request', 'deletePackages.response', 'newDeletePackagesRequest', 'PUT', '/ship/v1/openshipments/packages/delete', true],
            'retrievePackage' => ['retrievePackage', 'retrievePackage', 'retrievePackage.request', 'retrievePackage.response', 'newRetrievePackageRequest', 'POST', '/ship/v1/openshipments/packages/retrieve', false],
            'delete' => ['delete', 'deleteOpenShipment', 'delete.request', 'delete.response', 'newDeleteRequest', 'PUT', '/ship/v1/openshipments/delete', true],
            'retrieve' => ['retrieve', 'retrieveOpenShipment', 'retrieve.request', 'retrieve.response', 'newRetrieveRequest', 'POST', '/ship/v1/openshipments/retrieve', false],
            'results' => ['results', 'getResults', 'results.request', 'results.response', 'newResultsRequest', 'POST', '/ship/v1/openshipments/results', false],
        ];
    }

    /**
     * The class extends the base of the API classes and has its own exception
     * @return void
     */
    public function testTheClassExtendsTheBaseOfTheApiClassesAndHasItsOwnException(): void
    {
        $this->assertInstanceOf(AbstractFedexApi::class, $this->api());
        $this->assertInstanceOf(FedexException::class, new FedexOpenShipException('x'));
    }

    /**
     * The operations are the paths and methods of the spec (the trailing slash of the modification and the confirmation
     * included), and each one is described as it has to be
     * @return void
     */
    public function testTheOperationsAreThePathsAndMethodsOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];
        $seen = [];

        foreach ($this->operationsProvider() as [$name, , $label, $responseLabel, , $verb, $path, $changes]) {
            $operation = FedexOpenShip::OPERATIONS[$name];

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
        $this->assertSame(array_keys(FedexOpenShip::OPERATIONS), array_column($this->operationsProvider(), 0));
        $this->assertSame(['retrievePackage', 'retrieve', 'results'], array_keys(array_filter(FedexOpenShip::OPERATIONS, function ($operation) {
            return !$operation[4];
        })), 'only the three that change nothing can be repeated');
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
        $this->assertSame('https://apis-sandbox.fedex.com' . $path, $calls[0]['url']);
        $this->assertSame(json_decode(json_encode($request), true), $this->transport->bodyOf(0));
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('T1', $result->transactionId);
    }

    /**
     * The production url is used when the authorization is not the sandbox
     * @return void
     */
    public function testTheProductionUrlIsUsedWhenTheAuthorizationIsNotTheSandbox(): void
    {
        $api = $this->api([], null, new StubAuth(false, 'client_credentials', 'id', 'secret'));

        $api->confirmOpenShipment(self::request('confirm.request'));

        $this->assertSame('https://apis.fedex.com/ship/v1/openshipments/', $this->transport->calls[0]['url']);
    }

    /**
     * Each operation sends the bearer token, the locale and a UUID as the customer transaction ID, and every call has its own
     * @return void
     */
    public function testEachOperationSendsTheBearerTokenTheLocaleAndAUuidThatIsDifferentInEveryCall(): void
    {
        $api = $this->api();

        $api->retrieveOpenShipment(self::request('retrieve.request'));
        $api->createOpenShipment(self::request('create.request'));
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

            $results[$sample] = ($e === null && $sent === FedexUtils::pruneEmpty($example)) ? true : self::describe($e);
        }

        $this->assertCount(2, $results);
        $this->assertAll('the sample payloads', $results);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     */
    public function testAnOperationTakesAnObjectAsWellAsAnArray(string $name, string $method, string $label): void
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
     */
    public function testARequestThatIsNotAnArrayOrAnObjectIsRefusedWithItsOwnMessage(string $name, string $method): void
    {
        $api = $this->api();

        foreach ([null, 'text', 5, 1.5, true] as $request) {
            $e = self::thrown(function () use ($api, $method, $request) {
                $api->$method($request);
            });

            $this->assertInstanceOf(FedexOpenShipException::class, $e, var_export($request, true));
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
     */
    public function testAnUnknownKeyAtTheTopLevelIsSentBecauseFedexDecidesAboutIt(string $name, string $method, string $label): void
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
     */
    public function testNestedContentThatTheSchemaDoesNotKnowIsSentAsGiven(string $name, string $method, string $label): void
    {
        $request = self::request($label);
        $changed = 0;

        foreach (['requestedShipment', 'labelSpecification', 'requestedPackageLineItem', 'trackingId', 'accountNumber'] as $key) {
            if (isset($request[$key]) && is_array($request[$key])) {
                $request[$key]['notInTheSchema'] = ['deep' => [1, 2, 3]];
                $request[$key]['labelStockType'] = 12345;
                $changed++;
            }
        }

        $api = $this->api();

        $api->$method($request);

        $this->assertGreaterThan(0, $changed);
        $this->assertSame(json_decode(json_encode($request), true), $this->transport->bodyOf(0), 'sent as given: FedEx checks what is inside');
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     */
    public function testTheEmptyRequestIsRefusedUntilItIsFilledIn(string $name, string $method, string $label, string $responseLabel, string $template): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $method, $template) {
            $api->$method(FedexOpenShip::$template());
        });

        $this->assertInstanceOf(FedexOpenShipException::class, $e, self::describe($e));
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
     */
    public function testTheEmptyRequestHasTheRequiredFieldsOfTheTopLevelOfTheSpec(string $name, string $method, string $label, string $responseLabel, string $template): void
    {
        $root = Spec::components()[Spec::ROOTS[$label]];
        $shallow = FedexOpenShipSchemas::shallowDefinitions()[Spec::renamed(Spec::ROOTS[$label])];
        $expected = [];

        foreach ($root['required'] as $key) {
            if (!isset($root['properties'][$key])) {
                continue; // required and never defined (trackingId of the retrieval): optional in the class
            }

            $node = $shallow[$key];
            $expected[$key] = ($key === 'accountNumber') ? ['value' => null] : (($node === 'object' || $node === 'array') ? [] : null);
        }

        $actual = FedexOpenShip::$template();
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
            $root = Spec::components()[Spec::ROOTS[$label]];

            foreach ($root['required'] as $key) {
                if (!isset($root['properties'][$key])) {
                    continue;
                }

                $request = self::request($label);
                unset($request[$key]);
                $api = $this->api();
                $e = self::thrown(function () use ($api, $method, $request) {
                    $api->$method($request);
                });
                $results["$name without $key"] = ($e instanceof FedexOpenShipException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertCount(29, $results, 'the required fields of the ten requests, without the trackingId that the retrieval never defines');
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
            $shallow = FedexOpenShipSchemas::shallowDefinitions()[Spec::renamed(Spec::ROOTS[$label])];

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
                $results["$name $field as " . json_encode($wrong)] = ($e instanceof FedexOpenShipException && $this->transport->calls === []) ? true : self::describe($e);
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
        $cases = [
            ['confirmOpenShipment', 'confirm.request', 'labelResponseOptions'],
            ['createOpenShipment', 'create.request', 'openShipmentAction'],
            ['getResults', 'results.request', 'resultMethodType'],
        ];

        foreach ($cases as [$method, $label, $field]) {
            $api = $this->api();
            $request = self::request($label);
            $request[$field] = 'NOT_AN_ENUM_VALUE';

            $e = self::thrown(function () use ($api, $method, $request) {
                $api->$method($request);
            });

            $this->assertInstanceOf(FedexOpenShipException::class, $e, $field);
            $this->assertStringContainsString($field, implode(' ', $e->getErrors()));
            $this->assertSame([], $this->transport->calls);
        }

        $api = $this->api();
        $request = self::request('modify.request');
        $request['openShipmentAction'] = 'PROVIDE_DOCUMENTS_INCREMENTALLY';

        $e = self::thrown(function () use ($api, $request) {
            $api->modifyOpenShipment($request);
        });

        $this->assertInstanceOf(FedexOpenShipException::class, $e, 'the modification has no PROVIDE_DOCUMENTS_INCREMENTALLY');
    }

    /**
     * Null and empty lists and objects are left out of the request
     * @return void
     */
    public function testNullAndEmptyListsAndObjectsAreLeftOut(): void
    {
        $api = $this->api();
        $request = self::request('addPackages.request');
        $expected = $request;
        $request['openShipmentAction'] = null;
        $request['version'] = [];
        $request['extra'] = [];
        unset($expected['openShipmentAction'], $expected['version']);

        $api->addPackages($request);

        $this->assertSame(json_decode(json_encode($expected), true), $this->transport->bodyOf(0));
    }

    // ---------------------------------------------------------------- the convenience methods

    /**
     * Each method that takes a job id or an index makes its request
     * @return void
     */
    public function testEachMethodThatTakesAJobIdOrAnIndexMakesItsRequest(): void
    {
        $api = $this->api();

        $api->getResultsByJobId('123456789', 'JOB1', 'CONFIRM');
        $api->retrieveByIndex('123456789', 'Test1234', ['version' => ['major' => 1]]);
        $api->deleteByIndex('123456789', 'Test1234');
        $calls = $this->transport->calls;

        $this->assertSame(['POST /ship/v1/openshipments/results', 'POST /ship/v1/openshipments/retrieve', 'PUT /ship/v1/openshipments/delete'], array_map(function (array $call) {
            return $call['method'] . ' ' . self::pathOf($call);
        }, $calls));
        $this->assertSame(['accountNumber' => ['value' => '123456789'], 'jobId' => 'JOB1', 'resultMethodType' => 'CONFIRM'], $this->transport->bodyOf(0));
        $this->assertSame(['version' => ['major' => 1], 'accountNumber' => ['value' => '123456789'], 'index' => 'Test1234'], $this->transport->bodyOf(1));
        $this->assertSame(['accountNumber' => ['value' => '123456789'], 'index' => 'Test1234'], $this->transport->bodyOf(2));
    }

    /**
     * A result method type outside the enum is refused, as in the request
     * @return void
     */
    public function testAResultMethodTypeOutsideTheEnumIsRefused(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->getResultsByJobId('123456789', 'JOB1', 'DELETE');
        });

        $this->assertInstanceOf(FedexOpenShipException::class, $e);
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The options that the method sets itself are refused
     * @return void
     */
    public function testTheOptionsThatTheMethodSetsItselfAreRefused(): void
    {
        $api = $this->api();
        $cases = [
            ['getResultsByJobId', ['1', 'J', 'CREATE'], ['accountNumber', 'jobId', 'resultMethodType']],
            ['retrieveByIndex', ['1', 'I'], ['accountNumber', 'index']],
            ['deleteByIndex', ['1', 'I'], ['accountNumber', 'index']],
        ];

        foreach ($cases as [$method, $arguments, $fields]) {
            foreach ($fields as $field) {
                $e = self::thrown(function () use ($api, $method, $arguments, $field) {
                    $api->$method(...array_merge($arguments, [[$field => 'x']]));
                });

                $this->assertInstanceOf(FedexOpenShipException::class, $e, "$method $field");
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
     */
    public function testTheResponseGeneratedFromTheSpecForTheOperationIsAccepted(string $name, string $method, string $label, string $responseLabel): void
    {
        $api = $this->api([], Responses::make(200, json_encode(self::request($responseLabel))));

        $result = $api->$method(self::request($label));

        $this->assertEquals(json_decode(json_encode(self::request($responseLabel))), $result);
        $this->assertSame([], FedexOpenShip::checkResponse($name, $result), 'and the whole of the schema accepts it');
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     */
    public function testAFieldThatFedexAddsToTheResponseIsTolerated(string $name, string $method, string $label, string $responseLabel): void
    {
        $response = self::request($responseLabel);
        $response['addedByFedex'] = ['x' => 1];
        $response['output']['alsoAdded'] = true;
        $api = $this->api([], Responses::make(200, json_encode($response)));

        $result = $api->$method(self::request($label));

        $this->assertTrue($result->addedByFedex->x === 1 && $result->output->alsoAdded === true);
        $this->assertSame([], FedexOpenShip::checkResponse($name, $result));
    }

    /**
     * A response with something wrong deep inside is returned, and the whole check reports it
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     */
    public function testAResponseWithSomethingWrongDeepInsideIsReturnedAndTheWholeCheckReportsIt(string $name, string $method, string $label, string $responseLabel): void
    {
        [$response, $meta] = Spec::instance($responseLabel);
        $leaf = null;

        foreach ($meta['leaves'] as $candidate) {
            if ($candidate['typed'] && $candidate['type'] === 'string' && count($candidate['path']) >= 2 && $candidate['path'][0] === 'output') {
                $leaf = $candidate;
                break;
            }
        }

        $this->assertNotNull($leaf, 'every response has a text inside its output');

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
        $this->assertNotEmpty(FedexOpenShip::checkResponse($name, $result), implode('.', $leaf['path']));
    }

    /**
     * A response with a wrong top level is refused with the response in the exception, and only an operation that changes
     * data warns
     * @return void
     */
    public function testAResponseWithAWrongTopLevelIsRefusedWithTheResponseInTheException(): void
    {
        foreach ($this->operationsProvider() as [$name, $method, $label, , , , , $changes]) {
            foreach (['{"output":"text"}', '{"transactionId":5}', '{"customerTransactionId":["x"]}'] as $body) {
                $api = $this->api([], Responses::make(200, $body));
                $e = self::thrown(function () use ($api, $method, $label) {
                    $api->$method(self::request($label));
                });

                $this->assertInstanceOf(FedexOpenShipException::class, $e, "$name $body");
                $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
                $this->assertSame('Invalid response received' . ($changes ? self::NOTE : ''), $e->getMessage());
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

                $this->assertInstanceOf(FedexOpenShipException::class, $e, "$name $body");
                $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
                $this->assertSame('Invalid response' . ($changes ? self::NOTE : ''), $e->getMessage());
                $this->assertSame([], $e->getErrors());
                $this->assertSame($decoded, $e->getData());
            }
        }
    }

    // ---------------------------------------------------------------- checkRequest() and checkResponse()

    /**
     * The sample of the confirmation passes the whole schema; the sample of the packages sends its numbers as text, which
     * the whole check reports and the sending accepts
     * @return void
     */
    public function testTheCheckOfARequestReportsTheProblemsOfANestedFieldThatTheOperationWouldSend(): void
    {
        $confirm = json_decode(json_encode(Spec::components()['MinimumSamplePayload-confirmOpenShipment']['example']), true);
        $packages = json_decode(json_encode(Spec::components()['MinimumSamplePayload-addPackagesInOpenShipment']['example']), true);

        $this->assertSame([], FedexOpenShip::checkRequest('confirm', $confirm));
        $this->assertSame([
            '$.requestedPackageLineItems[0].declaredValue.amount: expected number, got string',
            '$.requestedPackageLineItems[0].weight.value: expected number, got string',
        ], FedexOpenShip::checkRequest('addPackages', $packages));

        $api = $this->api();

        $this->assertInstanceOf(\stdClass::class, $api->addPackages($packages), 'and the operation sends it anyway');
        $this->assertSame($packages, $this->transport->bodyOf(0));
    }

    /**
     * The check of a request reports what the collection of FedEx usually leaves out: the label specification of a shipment
     * @return void
     */
    public function testTheCheckOfARequestReportsAMissingFieldDeepInside(): void
    {
        $request = self::request('create.request');
        unset($request['requestedShipment']['labelSpecification']);

        $this->assertContains('$.requestedShipment.labelSpecification: required key is missing', FedexOpenShip::checkRequest('create', $request));

        $api = $this->api();
        $api->createOpenShipment($request);

        $this->assertCount(1, $this->transport->calls, 'the top level is right, so it is sent');
    }

    /**
     * The check of a request leaves out null and empty lists as the sending does, and takes an object
     * @return void
     */
    public function testTheCheckOfARequestLeavesOutNullAndEmptyListsAsTheSendingDoesAndTakesAnObject(): void
    {
        $request = ['accountNumber' => ['value' => '1'], 'jobId' => 'J', 'resultMethodType' => 'CREATE', 'version' => [], 'extra' => null];

        $this->assertSame([], FedexOpenShip::checkRequest('results', $request), 'null and the empty list are left out');
        $this->assertSame(['$.extra: unexpected key'], FedexOpenShip::checkRequest('results', ['extra' => 1] + $request), 'but a value is not');
        $this->assertSame([], FedexOpenShip::checkRequest('results', json_decode('{"accountNumber":{"value":"1"},"jobId":"J","resultMethodType":"MODIFY"}')));
        $this->assertNotEmpty(FedexOpenShip::checkRequest('results', ['jobId' => 5]));
    }

    /**
     * An operation that is not known, and something that is not a request or a response, are refused
     * @return void
     */
    public function testAnOperationThatIsNotKnownAndSomethingThatIsNotARequestOrAResponseAreRefused(): void
    {
        foreach (['checkRequest', 'checkResponse'] as $method) {
            $e = self::thrown(function () use ($method) {
                FedexOpenShip::$method('nothing', []);
            });

            $this->assertInstanceOf(FedexOpenShipException::class, $e, $method);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame("Unknown operation 'nothing' (use one of: create, modify, confirm, modifyPackage, addPackages, deletePackages, retrievePackage, delete, retrieve, results)", $e->getMessage());

            foreach ([null, 'text', 5] as $value) {
                $e = self::thrown(function () use ($method, $value) {
                    FedexOpenShip::$method('create', $value);
                });

                $this->assertInstanceOf(FedexOpenShipException::class, $e);
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

        $this->assertSame(['$.output.alerts[0].code: expected string, got integer'], FedexOpenShip::checkResponse('create', $response));
        $this->assertSame([], FedexOpenShip::checkResponse('create', ['transactionId' => 'T', 'added' => 1]));
        $this->assertSame([], FedexOpenShip::checkResponse('create', json_decode('{"transactionId":"T"}')));
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
                $this->assertSame($this->transport->calls[0]['headers'], $this->transport->calls[1]['headers'], 'the same customer transaction id');
            }
        }
    }

    /**
     * @dataProvider operationsProvider
     * @param string $name Name of the operation
     * @param string $method Name of the method
     * @param string $label Label of the request
     */
    public function testNothingIsRepeatedWhenTheClientHasNoPolicy(string $name, string $method, string $label): void
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
     */
    public function testA401IsRetriedOnceWithAFreshTokenSameUrlSameBody(string $name, string $method, string $label): void
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
            $api->retrieveOpenShipment(self::request('retrieve.request'));
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
            $api->createOpenShipment(self::request('create.request'));
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
            $api->retrieveOpenShipment(self::request('retrieve.request'));
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
        $api = $this->api([Responses::make(400, '{"transactionId":"624deea6","errors":[{"code":"OPENSHIPMENT.NOT.FOUND","message":"Not found."}]}')]);

        $e = self::thrown(function () use ($api) {
            $api->deleteOpenShipment(self::request('delete.request'));
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
        $api = $this->api();

        $api->getResults(['accountNumber' => ['value' => '123456789'], 'jobId' => 12345, 'resultMethodType' => 'CREATE']);
        $api->deleteOpenShipment(['accountNumber' => ['value' => '123456789'], 'index' => 1234]);

        $this->assertSame(12345, $this->transport->bodyOf(0)['jobId']);
        $this->assertSame(1234, $this->transport->bodyOf(1)['index']);

        $e = self::thrown(function () use ($api) {
            $api->getResults(['accountNumber' => ['value' => '123456789'], 'jobId' => ['x'], 'resultMethodType' => 'CREATE']);
        });

        $this->assertInstanceOf(FedexOpenShipException::class, $e);
        $this->assertCount(2, $this->transport->calls);
    }
}
