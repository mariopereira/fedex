<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexBasicTracking;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexBasicTrackingException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use Mariopereira\Fedex\Tests\Support\TrackSpec as Spec;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Basic Tracking API: what each operation sends, how it checks the request and the response, and what it
 * repeats and what it never repeats
 */
final class FedexBasicTrackingTest extends TestCase
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
     * A FedexBasicTracking over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexBasicTracking
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexBasicTracking
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexBasicTracking($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The request generated from the spec for a root
     * @param string $label A key of TrackSpec::ROOTS ending in .request
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
     * The six operations: method, request label, response label, template method, path and whether it sends emails
     * @return array
     */
    public function operationsProvider(): array
    {
        return [
            'trackByTrackingNumbers' => ['trackByTrackingNumbers', 'trackingnumbers.request', 'trackingnumbers.response', 'newTrackingNumbersRequest', '/track/v1/trackingnumbers', false],
            'trackAssociatedShipments' => ['trackAssociatedShipments', 'associated.request', 'associated.response', 'newAssociatedShipmentsRequest', '/track/v1/associatedshipments', false],
            'trackByReferences' => ['trackByReferences', 'references.request', 'references.response', 'newReferencesRequest', '/track/v1/referencenumbers', false],
            'trackByTcn' => ['trackByTcn', 'tcn.request', 'tcn.response', 'newTcnRequest', '/track/v1/tcn', false],
            'getTrackingDocuments' => ['getTrackingDocuments', 'documents.request', 'documents.response', 'newDocumentsRequest', '/track/v1/trackingdocuments', false],
            'sendNotification' => ['sendNotification', 'notification.request', 'notification.response', 'newNotificationRequest', '/track/v1/notifications', true],
        ];
    }

    /**
     * The class extends the base of the API classes and has its own exception
     * @return void
     */
    public function testTheClassExtendsTheBaseOfTheApiClassesAndHasItsOwnException(): void
    {
        $this->assertInstanceOf(AbstractFedexApi::class, $this->api());
        $this->assertInstanceOf(FedexException::class, new FedexBasicTrackingException('x'));
        $this->assertSame(30, FedexBasicTracking::MAX_TRACKING_NUMBERS);
    }

    /**
     * The endpoints are the paths of the spec, all of them POST
     * @return void
     */
    public function testTheEndpointsAreThePathsOfTheSpecAllOfThemPost(): void
    {
        $paths = Spec::spec()['paths'];

        foreach ([
            FedexBasicTracking::ENDPOINT_TRACKING_NUMBERS => 'track/v1/trackingnumbers',
            FedexBasicTracking::ENDPOINT_ASSOCIATED => 'track/v1/associatedshipments',
            FedexBasicTracking::ENDPOINT_REFERENCES => 'track/v1/referencenumbers',
            FedexBasicTracking::ENDPOINT_TCN => 'track/v1/tcn',
            FedexBasicTracking::ENDPOINT_DOCUMENTS => 'track/v1/trackingdocuments',
            FedexBasicTracking::ENDPOINT_NOTIFICATION => 'track/v1/notifications',
        ] as $constant => $path) {
            $this->assertSame($path, $constant);
            $this->assertSame(['post'], array_keys($paths['/' . $path]), $path);
        }
    }

    // ---------------------------------------------------------------- what each operation sends

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testEachOperationSendsOnePostWithTheGeneratedRequest(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $request = self::request($label);
        $api = $this->api();

        $result = $api->$method($request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame('POST', $calls[0]['method']);
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

        $api->trackByTcn(self::request('tcn.request'));
        $api->trackByTcn(self::request('tcn.request'));
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

        $api->trackByTcn(self::request('tcn.request'));

        $this->assertSame('https://apis.fedex.com/track/v1/tcn', $this->transport->calls[0]['url']);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testAnOperationTakesAnObjectAsWellAsAnArray(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $request = self::request($label);
        $api = $this->api();

        $api->$method(json_decode(json_encode($request)));

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame(json_decode(json_encode($request), true), $this->transport->bodyOf(0));
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testARequestThatIsNotAnArrayOrAnObjectIsRefusedWithItsOwnMessage(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $api = $this->api();

        foreach ([null, 'text', 5, 1.5, true] as $request) {
            $e = self::thrown(function () use ($api, $method, $request) {
                $api->$method($request);
            });

            $this->assertInstanceOf(FedexBasicTrackingException::class, $e, var_export($request, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The request must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testAnUnknownKeyIsRefusedBeforeAnythingIsSentNotEvenTheTokenRequest(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $request = self::request($label);
        $request['notInTheSchema'] = 1;
        $api = $this->api();

        $e = self::thrown(function () use ($api, $method, $request) {
            $api->$method($request);
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e, self::describe($e));
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame('Invalid request', $e->getMessage());
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);
        $this->assertSame([], $this->auth->forceSeen, 'no token was requested');
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testTheEmptyRequestIsRefusedUntilItIsFilledIn(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $method, $template) {
            $api->$method(FedexBasicTracking::$template());
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e, self::describe($e));
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testTheEmptyRequestIsTheRequiredKeysOfTheSpecRecursively(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $this->assertSame(Spec::requiredOnly(Spec::components()[Spec::ROOTS[$label]], [Spec::ROOTS[$label]]), FedexBasicTracking::$template());
    }

    /**
     * Every required field that is missing is refused and nothing is sent
     * @return void
     */
    public function testEveryRequiredFieldThatIsMissingIsRefusedAndNothingIsSent(): void
    {
        $results = [];

        foreach ($this->operationsProvider() as [$method, $label]) {
            [$request, $meta] = Spec::instance($label);

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
                    $e = self::thrown(function () use ($api, $method, $copy) {
                        $api->$method($copy);
                    });
                    $results["$method " . implode('.', array_merge($object['path'], [$key]))] = ($e instanceof FedexBasicTrackingException && $this->transport->calls === []) ? true : self::describe($e);
                }
            }
        }

        $this->assertAll('the required fields', $results);
    }

    /**
     * A value of the wrong type in any leaf of a request is refused and nothing is sent
     * @return void
     */
    public function testAValueOfTheWrongTypeInAnyLeafOfARequestIsRefusedAndNothingIsSent(): void
    {
        $results = [];

        foreach ($this->operationsProvider() as [$method, $label]) {
            [$request, $meta] = Spec::instance($label);

            foreach ($meta['leaves'] as $leaf) {
                if (!$leaf['typed']) {
                    continue;
                }

                $copy = json_decode(json_encode($request), true);
                $node = &$copy;

                foreach ($leaf['path'] as $step) {
                    $node = &$node[$step];
                }

                $node = ['string' => 12345, 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'][$leaf['type']];
                unset($node);
                $api = $this->api();
                $e = self::thrown(function () use ($api, $method, $copy) {
                    $api->$method($copy);
                });
                $results["$method " . implode('.', $leaf['path'])] = ($e instanceof FedexBasicTrackingException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the values of the wrong type', $results);
    }

    /**
     * A value outside an enum of a request is refused
     * @return void
     */
    public function testAValueOutsideAnEnumOfARequestIsRefused(): void
    {
        $cases = [
            ['trackByTrackingNumbers', 'trackingnumbers.request', ['trackingInfo', 0, 'trackingNumberInfo', 'carrierCode']],
            ['trackAssociatedShipments', 'associated.request', ['associatedType']],
            ['trackAssociatedShipments', 'associated.request', ['masterTrackingNumberInfo', 'trackingNumberInfo', 'carrierCode']],
        ];

        foreach ($cases as [$method, $label, $path]) {
            $request = self::request($label);
            $node = &$request;

            foreach ($path as $step) {
                $node = &$node[$step];
            }

            $node = 'NOT_AN_ENUM_VALUE';
            unset($node);
            $api = $this->api();
            $e = self::thrown(function () use ($api, $method, $request) {
                $api->$method($request);
            });

            $this->assertInstanceOf(FedexBasicTrackingException::class, $e, implode('.', $path));
            $this->assertStringContainsString(end($path), implode(' ', $e->getErrors()), 'the error names the field');
            $this->assertSame([], $this->transport->calls);
        }
    }

    /**
     * The values that the spec only describes are sent as given
     * @return void
     */
    public function testTheValuesThatTheSpecOnlyDescribesAreSentAsGiven(): void
    {
        $api = $this->api();
        $request = self::request('references.request');
        $request['referencesInformation']['type'] = 'SOMETHING_FEDEX_ADDS_LATER';
        $documents = self::request('documents.request');
        $documents['trackDocumentDetail']['documentType'] = 'BILL_OF_LADING';
        $documents['trackDocumentDetail']['documentFormat'] = 'PDF';

        $api->trackByReferences($request);
        $api->getTrackingDocuments($documents);

        $this->assertSame('SOMETHING_FEDEX_ADDS_LATER', $this->transport->bodyOf(0)['referencesInformation']['type']);
        $this->assertSame('BILL_OF_LADING', $this->transport->bodyOf(1)['trackDocumentDetail']['documentType']);
    }

    /**
     * Null and empty lists are left out of the request
     * @return void
     */
    public function testNullAndEmptyListsAreLeftOut(): void
    {
        $api = $this->api();
        $request = self::request('trackingnumbers.request');
        $expected = json_decode(json_encode($request), true);
        $request['trackingInfo'][0]['shipDateBegin'] = null;
        $request['trackingInfo'][0]['trackingNumberInfo']['carrierCode'] = null;
        unset($expected['trackingInfo'][0]['shipDateBegin'], $expected['trackingInfo'][0]['trackingNumberInfo']['carrierCode']);
        $request['trackingInfo'][0]['trackingNumberInfo']['trackingNumberUniqueId'] = [];
        unset($expected['trackingInfo'][0]['trackingNumberInfo']['trackingNumberUniqueId']);

        $api->trackByTrackingNumbers($request);

        $this->assertSame($expected, $this->transport->bodyOf(0));
    }

    // ---------------------------------------------------------------- the tracking numbers

    /**
     * A request with thirty tracking numbers is sent, and one with thirty one is refused
     * @return void
     */
    public function testARequestWithThirtyTrackingNumbersIsSentAndOneWithThirtyOneIsRefused(): void
    {
        $info = function (int $count) {
            return array_map(function (int $i) {
                return ['trackingNumberInfo' => ['trackingNumber' => (string) (100000000000 + $i)]];
            }, range(1, $count));
        };
        $api = $this->api();

        $api->trackByTrackingNumbers(['includeDetailedScans' => true, 'trackingInfo' => $info(30)]);

        $this->assertCount(30, $this->transport->bodyOf(0)['trackingInfo']);

        $api = $this->api();
        $e = self::thrown(function () use ($api, $info) {
            $api->trackByTrackingNumbers(['includeDetailedScans' => true, 'trackingInfo' => $info(31)]);
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame('A request can have at most 30 tracking numbers', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
        $this->assertSame([], $this->auth->forceSeen, 'no token was requested');
    }

    /**
     * Tracking one number sends it with the detailed scans by default
     * @return void
     */
    public function testTrackingOneNumberSendsItWithTheDetailedScansByDefault(): void
    {
        $api = $this->api();

        $result = $api->trackByNumber('123456789012');
        $api->trackByNumber('123456789012', false);

        $this->assertSame('T1', $result->transactionId);
        $this->assertSame(['includeDetailedScans' => true, 'trackingInfo' => [['trackingNumberInfo' => ['trackingNumber' => '123456789012']]]], $this->transport->bodyOf(0));
        $this->assertFalse($this->transport->bodyOf(1)['includeDetailedScans']);
        $this->assertSame('/track/v1/trackingnumbers', self::pathOf($this->transport->calls[0]));
    }

    /**
     * Tracking several numbers takes numbers and entries of the schema
     * @return void
     */
    public function testTrackingSeveralNumbersTakesNumbersAndEntriesOfTheSchema(): void
    {
        $api = $this->api();

        $api->trackByNumbers([
            '111111111111',
            ['trackingNumberInfo' => ['trackingNumber' => '222222222222', 'carrierCode' => 'FDXE'], 'shipDateBegin' => '2020-03-29', 'shipDateEnd' => '2020-04-01'],
            '333333333333',
        ], false);

        $this->assertSame([
            'includeDetailedScans' => false,
            'trackingInfo' => [
                ['trackingNumberInfo' => ['trackingNumber' => '111111111111']],
                ['trackingNumberInfo' => ['trackingNumber' => '222222222222', 'carrierCode' => 'FDXE'], 'shipDateBegin' => '2020-03-29', 'shipDateEnd' => '2020-04-01'],
                ['trackingNumberInfo' => ['trackingNumber' => '333333333333']],
            ],
        ], $this->transport->bodyOf(0));
    }

    /**
     * A tracking number that is not text, or is empty, is refused and nothing is sent
     * @return void
     */
    public function testATrackingNumberThatIsNotTextOrIsEmptyIsRefusedAndNothingIsSent(): void
    {
        foreach ([[''], ['  '], [123456789012], [null], [true], ['111111111111', 5], [new \stdClass()]] as $numbers) {
            $api = $this->api();
            $e = self::thrown(function () use ($api, $numbers) {
                $api->trackByNumbers($numbers);
            });

            $this->assertInstanceOf(FedexBasicTrackingException::class, $e, json_encode($numbers));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('Each tracking number must be text that is not empty, or an array of the schema', $e->getMessage());
            $this->assertSame([], $this->transport->calls);
        }
    }

    /**
     * No tracking number, and too many, are refused
     * @return void
     */
    public function testNoTrackingNumberAndTooManyAreRefused(): void
    {
        $api = $this->api();
        $e = self::thrown(function () use ($api) {
            $api->trackByNumbers([]);
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e);
        $this->assertNotEmpty($e->getErrors());

        $e = self::thrown(function () use ($api) {
            $api->trackByNumbers(array_map('strval', range(100000000001, 100000000031)));
        });

        $this->assertSame('A request can have at most 30 tracking numbers', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * An entry of the schema that is not valid is refused by the schema
     * @return void
     */
    public function testAnEntryOfTheSchemaThatIsNotValidIsRefusedByTheSchema(): void
    {
        $api = $this->api();
        $e = self::thrown(function () use ($api) {
            $api->trackByNumbers([['trackingNumberInfo' => ['trackingNumber' => '1', 'carrierCode' => 'XXXX']]]);
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e);
        $this->assertSame('Invalid request', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the references

    /**
     * The references accept shipDateEnd as well as the shipDateEndDate that the schema requires
     * @return void
     */
    public function testTheReferencesAcceptShipDateEndAsWellAsTheShipDateEndDateThatTheSchemaRequires(): void
    {
        $base = ['referencesInformation' => ['value' => '56754674567546754', 'accountNumber' => '123456789', 'shipDateBegin' => '2019-02-13', 'shipDateEndDate' => '2019-02-13']];
        $api = $this->api();

        $api->trackByReferences($base);
        $both = $base;
        $both['referencesInformation']['shipDateEnd'] = '2019-02-14';
        $api->trackByReferences($both);

        $this->assertSame($base, $this->transport->bodyOf(0));
        $this->assertSame($both, $this->transport->bodyOf(1));
    }

    /**
     * The references without shipDateEndDate are refused, even with shipDateEnd, because the schema requires it
     * @return void
     */
    public function testTheReferencesWithoutShipDateEndDateAreRefusedEvenWithShipDateEnd(): void
    {
        $request = ['referencesInformation' => ['value' => '56754674567546754', 'accountNumber' => '123456789', 'shipDateBegin' => '2019-02-13', 'shipDateEnd' => '2019-02-13']];
        $api = $this->api();

        $e = self::thrown(function () use ($api, $request) {
            $api->trackByReferences($request);
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e);
        $this->assertStringContainsString('shipDateEndDate', implode(' ', $e->getErrors()));
        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the responses

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testTheResponseGeneratedFromTheSpecForTheOperationIsAccepted(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $api = $this->api([], Responses::make(200, json_encode(self::request($responseLabel))));

        $result = $api->$method(self::request($label));

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals(json_decode(json_encode(self::request($responseLabel))), $result);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testAFieldThatFedexAddsToTheResponseIsTolerated(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $response = self::request($responseLabel);
        $response['addedByFedex'] = ['x' => 1];
        $response['output']['alsoAdded'] = true;
        $api = $this->api([], Responses::make(200, json_encode($response)));

        $result = $api->$method(self::request($label));

        $this->assertTrue($result->addedByFedex->x === 1 && $result->output->alsoAdded === true);
    }

    /**
     * A response with a value of the wrong type in any leaf is refused with the response in the exception
     * @return void
     */
    public function testAResponseWithAValueOfTheWrongTypeInAnyLeafIsRefusedWithTheResponseInTheException(): void
    {
        $results = [];

        foreach ($this->operationsProvider() as [$method, $label, $responseLabel, , , $sendsEmails]) {
            [$response, $meta] = Spec::instance($responseLabel);

            foreach ($meta['leaves'] as $leaf) {
                if (!$leaf['typed']) {
                    continue;
                }

                $copy = json_decode(json_encode($response), true);
                $node = &$copy;

                foreach ($leaf['path'] as $step) {
                    $node = &$node[$step];
                }

                $node = ['string' => 12345, 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'][$leaf['type']];
                unset($node);
                $api = $this->api([], Responses::make(200, json_encode($copy)));
                $e = self::thrown(function () use ($api, $method, $label) {
                    $api->$method(self::request($label));
                });
                $ok = ($e instanceof FedexBasicTrackingException)
                    && ($e->getCode() === FedexException::EC_BAD_RESULT_RETURNED)
                    && ($e->getData() == json_decode(json_encode($copy)))
                    && !empty($e->getErrors())
                    && (strpos($e->getMessage(), 'may have been sent') !== false) === $sendsEmails;
                $results["$method " . implode('.', $leaf['path'])] = $ok ? true : self::describe($e);
            }
        }

        $this->assertAll('the responses with a value of the wrong type', $results);
    }

    /**
     * Only the notification warns that it may have been done when its response is invalid
     * @return void
     */
    public function testOnlyTheNotificationWarnsThatItMayHaveBeenDoneWhenItsResponseIsInvalid(): void
    {
        foreach ($this->operationsProvider() as [$method, $label, , , , $sendsEmails]) {
            foreach ([Responses::make(200, '[1,2]'), Responses::make(200, '{"output":"text"}')] as $answer) {
                $api = $this->api([], $answer);
                $e = self::thrown(function () use ($api, $method, $label) {
                    $api->$method(self::request($label));
                });

                $this->assertInstanceOf(FedexBasicTrackingException::class, $e, $method);
                $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
                $this->assertSame($sendsEmails, strpos($e->getMessage(), '(the notification may have been sent by FedEx: check it before repeating it)') !== false, $method . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * An address without the field that the spec requires and never defines is accepted, and with it of any type too
     * @return void
     */
    public function testAnAddressWithoutTheFieldThatTheSpecRequiresAndNeverDefinesIsAccepted(): void
    {
        foreach ([null, 'a text', 5] as $verification) {
            $address = ['streetLines' => ['1 Main St'], 'countryCode' => 'US'];

            if ($verification !== null) {
                $address['addressVerificationId'] = $verification;
            }

            $response = ['transactionId' => 'T', 'output' => ['completeTrackResults' => [['trackingNumber' => '1', 'trackResults' => [['trackingNumberInfo' => ['trackingNumber' => '1'], 'recipientInformation' => ['address' => $address]]]]]]];
            $api = $this->api([], Responses::make(200, json_encode($response)));

            $this->assertInstanceOf(\stdClass::class, $api->trackByNumber('1'), json_encode($verification));
        }
    }

    /**
     * A delay type and a commit message that the spec lists wrongly are accepted, and a delay type that is not a text is not
     * @return void
     */
    public function testTheDelayAndCommitValuesThatTheSpecListsWronglyAreAccepted(): void
    {
        $result = function (array $trackResult) {
            return ['transactionId' => 'T', 'output' => ['completeTrackResults' => [['trackingNumber' => '1', 'trackResults' => [$trackResult + ['trackingNumberInfo' => ['trackingNumber' => '1']]]]]]];
        };

        $api = $this->api([], Responses::make(200, json_encode($result(['serviceCommitMessage' => ['type' => 'USPS_DELIVERING'], 'latestStatusDetail' => ['delayDetail' => ['type' => 'WEATHER', 'subType' => 'WIND']]]))));

        $this->assertInstanceOf(\stdClass::class, $api->trackByNumber('1'));

        $api = $this->api([], Responses::make(200, json_encode($result(['latestStatusDetail' => ['delayDetail' => ['type' => 'SUNSHINE']]]))));
        $e = self::thrown(function () use ($api) {
            $api->trackByNumber('1');
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e, 'the other enums are still checked');

        $api = $this->api([], Responses::make(200, json_encode($result(['latestStatusDetail' => ['delayDetail' => ['subType' => 5]]]))));
        $e = self::thrown(function () use ($api) {
            $api->trackByNumber('1');
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e, 'and the text is still text');
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testAFailureThatIsTransientIsRepeatedOnlyByTheOperationsThatCanBeRepeated(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], Responses::make(200, '{"transactionId":"T2"}'), null, true);

            $e = self::thrown(function () use ($api, $method, $label) {
                $api->$method(self::request($label));
            });

            if ($sendsEmails) {
                $this->assertInstanceOf(FedexClientException::class, $e, "$method, HTTP $status: it sends emails, so it must fail at the first failure");
                $this->assertCount(1, $this->transport->calls, "$method, HTTP $status is never repeated");
            } else {
                $this->assertNull($e, "$method, HTTP $status: " . self::describe($e));
                $this->assertCount(2, $this->transport->calls, "$method, HTTP $status is repeated once");
                $this->assertSame($this->transport->calls[0]['body'], $this->transport->calls[1]['body']);
            }
        }
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testNothingIsRepeatedWhenTheClientHasNoPolicy(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
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
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string $template Name of the method that makes the empty request
     * @param string $path The path that has to be called
     * @param bool $sendsEmails True when the operation sends emails
     */
    public function testA401IsRetriedOnceWithAFreshTokenSameUrlSameBody(string $method, string $label, string $responseLabel, string $template, string $path, bool $sendsEmails): void
    {
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $result = $api->$method(self::request($label));
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
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
            $api->trackByTcn(self::request('tcn.request'));
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
            $api->trackByTcn(self::request('tcn.request'));
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
            $api->trackByTcn(self::request('tcn.request'));
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
        $api = $this->api([Responses::make(400, '{"transactionId":"624deea6","errors":[{"code":"TRACKING.TRACKINGNUMBER.EMPTY","message":"Please provide tracking number."}]}')]);

        $e = self::thrown(function () use ($api) {
            $api->trackByTcn(self::request('tcn.request'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }

    /**
     * Several numbers are tracked with the detailed scans by default
     * @return void
     */
    public function testSeveralNumbersAreTrackedWithTheDetailedScansByDefault(): void
    {
        $api = $this->api();

        $api->trackByNumbers(['111111111111', '222222222222']);

        $this->assertTrue($this->transport->bodyOf(0)['includeDetailedScans']);
        $this->assertCount(2, $this->transport->bodyOf(0)['trackingInfo']);
    }

    /**
     * A response that is not an object is refused with the response and no errors
     * @return void
     */
    public function testAResponseThatIsNotAnObjectIsRefusedWithTheResponseAndNoErrors(): void
    {
        foreach ($this->operationsProvider() as [$method, $label, , , , $sendsEmails]) {
            foreach (['[1,2]' => [1, 2], '5' => 5, '"text"' => 'text', 'true' => true, '' => null] as $body => $decoded) {
                $api = $this->api([], Responses::make(200, (string) $body));
                $e = self::thrown(function () use ($api, $method, $label) {
                    $api->$method(self::request($label));
                });

                $this->assertInstanceOf(FedexBasicTrackingException::class, $e, "$method $body");
                $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
                $this->assertSame('Invalid response' . ($sendsEmails ? ' (the notification may have been sent by FedEx: check it before repeating it)' : ''), $e->getMessage());
                $this->assertSame([], $e->getErrors(), 'there is nothing to list: it is not the shape that is wrong');
                $this->assertSame($decoded, $e->getData(), 'the response is in the exception');
            }
        }
    }
}
