<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexShip;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexShipException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\ShipSpec as Spec;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the six operations of the Ship API: what they send, how they check the request and the response, the empty
 * requests, the helpers, and what happens when the call fails. Nothing here uses the network
 */
final class FedexShipTest extends TestCase
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
     * A FedexShip over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid answer by default)
     * @param StubAuth|null $auth The authorization
     * @return FedexShip
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null): FedexShip
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{}}'));
        $this->auth = $auth ?? StubAuth::make();

        return new FedexShip($this->auth, 'en_US', 30, 10, new FedexClient($this->auth, 'en_US', 30, 10, $this->transport));
    }

    /**
     * A FedexShip whose transport answers with a body
     * @param string $body Body of the response
     * @param int $status HTTP status
     * @return FedexShip
     */
    private function answering(string $body, int $status = 200): FedexShip
    {
        return $this->api([], Responses::make($status, $body));
    }

    /**
     * The request generated from the spec for a root
     * @param string $label A key of ShipSpec::ROOTS ending in .request
     * @return array
     */
    private static function request(string $label): array
    {
        return Spec::instance($label)[0];
    }

    /**
     * Run an operation
     * @param FedexShip $api The object
     * @param string $method Name of the method
     * @param string|null $id The id of the shipment, for cancelTag()
     * @param mixed $request The request
     * @return \stdClass
     */
    private static function call(FedexShip $api, string $method, ?string $id, $request): \stdClass
    {
        return ($id === null) ? $api->$method($request) : $api->$method($id, $request);
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
     * The six operations: method, request label, response label, id of the shipment (cancelTag only) and whether it changes data in FedEx
     * @return array
     */
    public function operationsProvider(): array
    {
        return [
            'create' => ['create', 'create.request', 'create.response', null, true],
            'validate' => ['validate', 'validate.request', 'validate.response', null, false],
            'cancel' => ['cancel', 'cancel.request', 'cancel.response', null, true],
            'createTag' => ['createTag', 'createtag.request', 'createtag.response', null, true],
            'cancelTag' => ['cancelTag', 'canceltag.request', 'canceltag.response', '794953555571', true],
            'getAsyncResults' => ['getAsyncResults', 'results.request', 'results.response', null, false],
        ];
    }

    // ---------------------------------------------------------------- the endpoints

    /**
     * The endpoints are the paths and methods of the spec
     * @return void
     */
    public function testTheEndpointsAreThePathsAndMethodsOfTheSpec(): void
    {
        $paths = Spec::spec()['paths'];

        $this->assertSame('ship/v1/shipments', FedexShip::ENDPOINT_CREATE);
        $this->assertSame('ship/v1/shipments/packages/validate', FedexShip::ENDPOINT_VALIDATE);
        $this->assertSame('ship/v1/shipments/cancel', FedexShip::ENDPOINT_CANCEL);
        $this->assertSame('ship/v1/shipments/tag', FedexShip::ENDPOINT_CREATE_TAG);
        $this->assertSame('ship/v1/shipments/tag/cancel/', FedexShip::ENDPOINT_CANCEL_TAG);
        $this->assertSame('ship/v1/shipments/results', FedexShip::ENDPOINT_RESULTS);

        $this->assertArrayHasKey('post', $paths['/ship/v1/shipments']);
        $this->assertArrayHasKey('post', $paths['/ship/v1/shipments/packages/validate']);
        $this->assertArrayHasKey('put', $paths['/ship/v1/shipments/cancel']);
        $this->assertArrayHasKey('post', $paths['/ship/v1/shipments/tag']);
        $this->assertArrayHasKey('put', $paths['/ship/v1/shipments/tag/cancel/{shipmentid}']);
        $this->assertArrayHasKey('post', $paths['/ship/v1/shipments/results']);
        $this->assertSame('/ship/v1/shipments/tag/cancel/{shipmentid}', '/' . FedexShip::ENDPOINT_CANCEL_TAG . '{shipmentid}');
    }

    // ---------------------------------------------------------------- what each operation sends

    /**
     * @dataProvider sendsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $verb The HTTP method that has to be used
     * @param string $path The path that has to be called
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testEachOperationSendsOneCallWithTheGeneratedRequest(string $method, string $label, string $verb, string $path, ?string $id): void
    {
        $request = self::request($label);
        $api = $this->api();

        $result = self::call($api, $method, $id, $request);
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame($verb, $calls[0]['method']);
        $this->assertSame($path, self::pathOf($calls[0]));
        $this->assertSame(json_decode(json_encode($request), true), $this->transport->bodyOf(0));
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('T1', $result->transactionId);
    }

    /**
     * The cases of testEachOperationSendsOneCallWithTheGeneratedRequest(): each operation sends one call with the generated request
     * @return array
     */
    public function sendsProvider(): array
    {
        return [
            'create' => ['create', 'create.request', 'POST', '/ship/v1/shipments', null],
            'validate' => ['validate', 'validate.request', 'POST', '/ship/v1/shipments/packages/validate', null],
            'cancel' => ['cancel', 'cancel.request', 'PUT', '/ship/v1/shipments/cancel', null],
            'createTag' => ['createTag', 'createtag.request', 'POST', '/ship/v1/shipments/tag', null],
            'cancelTag' => ['cancelTag', 'canceltag.request', 'PUT', '/ship/v1/shipments/tag/cancel/794953555571', '794953555571'],
            'getAsyncResults' => ['getAsyncResults', 'results.request', 'POST', '/ship/v1/shipments/results', null],
        ];
    }

    /**
     * Create sends the bearer token the locale and a UUID as the customer transaction ID
     * @return void
     */
    public function testCreateSendsTheBearerTokenTheLocaleAndAUuidAsTheCustomerTransactionId(): void
    {
        $api = $this->api();

        $api->create(self::request('create.request'));
        $headers = $this->transport->calls[0]['headers'];

        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('X-locale: en_US', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertNotEmpty(preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $headers));
    }

    /**
     * Every call has its own customer transaction ID
     * @return void
     */
    public function testEveryCallHasItsOwnCustomerTransactionId(): void
    {
        $api = $this->api();

        $api->validate(self::request('validate.request'));
        $api->validate(self::request('validate.request'));
        $ids = array_map(function (array $call) {
            return array_values(preg_grep('/^X-customer-transaction-id: /', $call['headers']))[0];
        }, $this->transport->calls);

        $this->assertCount(2, $ids);
        $this->assertNotSame($ids[0], $ids[1]);
    }

    /**
     * Create takes an object as well as an array
     * @return void
     */
    public function testCreateTakesAnObjectAsWellAsAnArray(): void
    {
        $request = self::request('create.request');
        $api = $this->api();

        $api->create(json_decode(json_encode($request)));

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame(json_decode(json_encode($request), true), $this->transport->bodyOf(0));
    }

    /**
     * A request that is not an array or an object is refused with its own message
     * @return void
     */
    public function testARequestThatIsNotAnArrayOrAnObjectIsRefusedWithItsOwnMessage(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->create('not a request');
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame('The request must be an array or an object', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testAnUnknownKeyIsRefusedBeforeAnythingIsSentNotEvenTheTokenRequest(string $method, string $label, string $responseLabel, ?string $id): void
    {
        $request = self::request($label);
        $request['notInTheSchema'] = 1;
        $api = $this->api();

        $e = self::thrown(function () use ($api, $method, $id, $request) {
            self::call($api, $method, $id, $request);
        });

        $this->assertInstanceOf(FedexShipException::class, $e, self::describe($e));
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);
        $this->assertSame([], $this->auth->forceSeen, 'no token was requested');
    }

    /**
     * @dataProvider emptyRequestsProvider
     * @param string $method Name of the method
     * @param string $template Name of the method that makes the empty request
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testTheEmptyRequestIsRefusedUntilItIsFilledIn(string $method, string $template, ?string $id): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $method, $template, $id) {
            self::call($api, $method, $id, FedexShip::$template());
        });

        $this->assertInstanceOf(FedexShipException::class, $e, self::describe($e));
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testTheEmptyRequestIsRefusedUntilItIsFilledIn(): the empty request is refused until it is filled in
     * @return array
     */
    public function emptyRequestsProvider(): array
    {
        return [
            'create' => ['create', 'newCreateRequest', null],
            'validate' => ['validate', 'newValidateRequest', null],
            'cancel' => ['cancel', 'newCancelRequest', null],
            'createTag' => ['createTag', 'newCreateTagRequest', null],
            'cancelTag' => ['cancelTag', 'newCancelTagRequest', '1'],
            'getAsyncResults' => ['getAsyncResults', 'newResultsRequest', null],
        ];
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testTheAccountNumberIsEnforcedWhereTheSpecRequiresItAndOptionalWhereItDoesNot(string $method, string $label, string $responseLabel, ?string $id): void
    {
        $root = Spec::ROOTS[$label];
        $required = in_array('accountNumber', Spec::components()[$root]['required'] ?? [], true);
        $request = self::request($label);

        $this->assertArrayHasKey('accountNumber', $request, 'the generated request has every field');
        unset($request['accountNumber']);
        $api = $this->api();

        $e = self::thrown(function () use ($api, $method, $id, $request) {
            self::call($api, $method, $id, $request);
        });

        if ($required) {
            $this->assertInstanceOf(FedexShipException::class, $e, "$method requires the account number");
            $this->assertSame([], $this->transport->calls);
        } else {
            $this->assertNull($e, self::describe($e));
            $this->assertCount(1, $this->transport->calls, "$method does not require the account number");
        }
    }

    /**
     * Only the validation leaves the account number optional
     * @return void
     */
    public function testOnlyTheValidationLeavesTheAccountNumberOptional(): void
    {
        $required = [];

        foreach (Spec::ROOTS as $label => $root) {
            if (substr($label, -7) === 'request') {
                $required[$label] = in_array('accountNumber', Spec::components()[$root]['required'] ?? [], true);
            }
        }

        $this->assertSame(['create.request' => true, 'validate.request' => false, 'cancel.request' => true, 'createtag.request' => true, 'canceltag.request' => true, 'results.request' => true], $required);
    }

    // ---------------------------------------------------------------- the empty requests are the required keys of the spec

    /**
     * @dataProvider templatesProvider
     * @param string $method Name of the method that makes the empty request
     * @param string $root The root of the spec
     */
    public function testTheEmptyRequestIsTheRequiredKeysOfTheSpecRecursively(string $method, string $root): void
    {
        $expected = Spec::requiredOnly(Spec::components()[$root], [], [$root]);

        $this->assertSame(SpecInstances::normalize($expected), SpecInstances::normalize(FedexShip::$method()));
    }

    /**
     * The cases of testTheEmptyRequestIsTheRequiredKeysOfTheSpecRecursively(): the empty request is the required keys of the spec recursively
     * @return array
     */
    public function templatesProvider(): array
    {
        return [
            'newCreateRequest' => ['newCreateRequest', 'Full_Schema_Ship'],
            'newValidateRequest' => ['newValidateRequest', 'FullSchema-VerifyShipment'],
            'newCancelRequest' => ['newCancelRequest', 'Full_Schema_Cancel_Shipment'],
            'newCreateTagRequest' => ['newCreateTagRequest', 'Full_Schema_Create_Tag'],
            'newCancelTagRequest' => ['newCancelTagRequest', 'FullSchema-CancelTag'],
            'newResultsRequest' => ['newResultsRequest', 'FullSchema-getConfirmedShipmentAsyncResults'],
        ];
    }

    /**
     * The facts that the tag and async templates rest on are in the spec
     * @return void
     */
    public function testTheFactsThatTheTagAndAsyncTemplatesRestOnAreInTheSpec(): void
    {
        $C = Spec::components();

        $this->assertSame(['accountNumber', 'completedTagDetail', 'serviceType'], $C['FullSchema-CancelTag']['required']);
        $this->assertSame(['confirmationNumber', 'dispatchDate', 'location'], $C['CompletedTagDetail_2']['required']);
        $this->assertSame(['accountNumber', 'jobId'], $C['FullSchema-getConfirmedShipmentAsyncResults']['required']);
        $this->assertSame(['accountNumber', 'requestedShipment'], $C['Full_Schema_Create_Tag']['required']);
        $this->assertArrayNotHasKey('required', $C['PartyAccountNumber_2']);
        $this->assertArrayNotHasKey('required', $C['PartyAccountNumber_tag']);
        $this->assertArrayNotHasKey('required', $C['PartyAccountNumber']);
    }

    /**
     * A create request made by filling in the empty one with the minimum
     * @return array
     */
    private static function minimumCreate(): array
    {
        $min = FedexShip::newCreateRequest();
        $min['requestedShipment']['shipper']['address'] = ['streetLines' => ['10 FedEx Parkway'], 'city' => 'Beverly Hills', 'countryCode' => 'US'];
        $min['requestedShipment']['shipper']['contact']['phoneNumber'] = '1234567890';
        $min['requestedShipment']['recipients'][] = ['address' => ['streetLines' => ['1 Main St'], 'city' => 'Memphis', 'countryCode' => 'US'], 'contact' => ['phoneNumber' => '9012223333']];
        $min['requestedShipment']['pickupType'] = 'USE_SCHEDULED_PICKUP';
        $min['requestedShipment']['serviceType'] = 'FEDEX_GROUND';
        $min['requestedShipment']['packagingType'] = 'YOUR_PACKAGING';
        $min['requestedShipment']['totalWeight'] = 1.5;
        $min['requestedShipment']['shippingChargesPayment']['paymentType'] = 'SENDER';
        $min['requestedShipment']['labelSpecification'] = ['labelStockType' => 'PAPER_4X6', 'imageType' => 'PDF'];
        $min['requestedShipment']['requestedPackageLineItems'][] = ['weight' => ['units' => 'LB', 'value' => 1.5]];
        $min['labelResponseOptions'] = 'URL_ONLY';
        $min['accountNumber']['value'] = '000000000';

        return $min;
    }

    /**
     * The minimum create request is accepted and only what was set is sent
     * @return void
     */
    public function testTheMinimumCreateRequestIsAcceptedAndOnlyWhatWasSetIsSent(): void
    {
        $api = $this->api();

        $api->create(self::minimumCreate());
        $sent = $this->transport->bodyOf(0);

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame(['value' => '000000000'], $sent['accountNumber']);
        $this->assertArrayNotHasKey('mergeLabelDocOption', $sent, 'nulls and empty lists of the template are left out');
    }

    /**
     * Null and an empty object are left out
     * @return void
     */
    public function testNullAndAnEmptyObjectAreLeftOut(): void
    {
        $request = self::request('cancel.request');
        $request['emailShipment'] = null;
        $request['version'] = [];
        $api = $this->api();

        $api->cancel($request);

        $expected = json_decode(json_encode(self::request('cancel.request')), true);
        unset($expected['emailShipment'], $expected['version']);
        $this->assertSame($expected, $this->transport->bodyOf(0));
    }

    // ---------------------------------------------------------------- cancelByTrackingNumber()

    /**
     * Cancel by tracking number sends a put with the two numbers only
     * @return void
     */
    public function testCancelByTrackingNumberSendsAPutWithTheTwoNumbersOnly(): void
    {
        $api = $this->api();

        $api->cancelByTrackingNumber('000000000', '794953555571');

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame('PUT', $this->transport->calls[0]['method']);
        $this->assertSame(['accountNumber' => ['value' => '000000000'], 'trackingNumber' => '794953555571'], $this->transport->bodyOf(0));
    }

    /**
     * Cancel by tracking number sends the other options of the schema and keeps false
     * @return void
     */
    public function testCancelByTrackingNumberSendsTheOtherOptionsOfTheSchemaAndKeepsFalse(): void
    {
        $api = $this->api();

        $api->cancelByTrackingNumber('000000000', '794953555571', ['deletionControl' => 'DELETE_ALL_PACKAGES', 'senderCountryCode' => 'US', 'emailShipment' => false]);

        $this->assertSame(
            SpecInstances::normalize(['accountNumber' => ['value' => '000000000'], 'trackingNumber' => '794953555571', 'deletionControl' => 'DELETE_ALL_PACKAGES', 'senderCountryCode' => 'US', 'emailShipment' => false]),
            SpecInstances::normalize($this->transport->bodyOf(0))
        );
    }

    /**
     * @dataProvider reservedProvider
     * @param string $field An option that the method sets itself
     */
    public function testCancelByTrackingNumberRefusesTheOptionsThatItSetsItself(string $field): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $field) {
            $api->cancelByTrackingNumber('000000000', '794953555571', [$field => 'x']);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testCancelByTrackingNumberRefusesTheOptionsThatItSetsItself(): cancel by tracking number refuses the options that it sets itself
     * @return array
     */
    public function reservedProvider(): array
    {
        return [['accountNumber'], ['trackingNumber']];
    }

    /**
     * Cancel by tracking number refuses a bad option value
     * @return void
     */
    public function testCancelByTrackingNumberRefusesABadOptionValue(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->cancelByTrackingNumber('000000000', '794953555571', ['deletionControl' => 'DELETE_SOME']);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- responses and failures

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testTheResponseGeneratedFromTheSpecForTheOperationIsAccepted(string $method, string $label, string $responseLabel, ?string $id): void
    {
        $api = $this->answering(json_encode(Spec::instance($responseLabel)[0]));

        $result = self::call($api, $method, $id, self::request($label));

        $this->assertInstanceOf(\stdClass::class, $result);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     * @param bool $changes Does the operation change data in FedEx?
     */
    public function testAResponseBrokenUnderOutputIsRefusedByTheDefinitionOfTheOperation(string $method, string $label, string $responseLabel, ?string $id, bool $changes): void
    {
        [$good, $meta] = Spec::instance($responseLabel);
        $leaf = null;

        foreach ($meta['leaves'] as $candidate) {
            if (count($candidate['path']) >= 2 && $candidate['path'][0] === 'output') {
                $leaf = $candidate;
                break;
            }
        }

        $this->assertNotNull($leaf, 'the generated response has a field under output to break');

        $broken = SpecInstances::setAt($good, $leaf['path'], in_array($leaf['type'], ['string', 'boolean'], true) ? 12345 : 'x');
        $api = $this->answering(json_encode($broken));

        $e = self::thrown(function () use ($api, $method, $id, $label) {
            self::call($api, $method, $id, self::request($label));
        });

        $this->assertInstanceOf(FedexShipException::class, $e, self::describe($e));
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertNotEmpty($e->getErrors());
        $this->assertSame($changes, strpos($e->getMessage(), 'may have been done') !== false, 'the warning is given only when the operation changes data in FedEx');
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     * @param bool $changes Does the operation change data in FedEx?
     */
    public function testAResponseThatIsAJsonListIsRefused(string $method, string $label, string $responseLabel, ?string $id, bool $changes): void
    {
        $api = $this->answering('[1,2]');

        $e = self::thrown(function () use ($api, $method, $id, $label) {
            self::call($api, $method, $id, self::request($label));
        });

        $this->assertInstanceOf(FedexShipException::class, $e, self::describe($e));
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertStringStartsWith('Invalid response', $e->getMessage());
        $this->assertSame($changes, strpos($e->getMessage(), 'may have been done') !== false);
        $this->assertSame([], $e->getErrors());
    }

    /**
     * The invalid response of an operation that changes data keeps the response in the exception
     * @return void
     */
    public function testTheInvalidResponseOfAnOperationThatChangesDataKeepsTheResponseInTheException(): void
    {
        foreach (['create' => 'create.request', 'cancel' => 'cancel.request'] as $method => $label) {
            $api = $this->answering(json_encode(['transactionId' => 12345, 'output' => []]));

            $e = self::thrown(function () use ($api, $method, $label) {
                $api->$method(self::request($label));
            });

            $this->assertInstanceOf(FedexShipException::class, $e);
            $this->assertInstanceOf(\stdClass::class, $e->getData());
            $this->assertSame(12345, $e->getData()->transactionId);
            $this->assertStringContainsString('may have been done', $e->getMessage());
            $this->assertCount(1, $this->transport->calls, 'that failure is a single request: no retry');
        }
    }

    /**
     * Validate does not warn when its response is invalid
     * @return void
     */
    public function testValidateDoesNotWarnWhenItsResponseIsInvalid(): void
    {
        $api = $this->answering(json_encode(['transactionId' => 12345, 'output' => []]));

        $e = self::thrown(function () use ($api) {
            $api->validate(self::request('validate.request'));
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertStringNotContainsString('may have been done', $e->getMessage());
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testAFieldThatFedexAddsToTheResponseIsTolerated(string $method, string $label, string $responseLabel, ?string $id): void
    {
        $response = Spec::instance($responseLabel)[0];
        $response['someFieldFedexAddsLater'] = 1;
        $api = $this->answering(json_encode($response));

        $this->assertInstanceOf(\stdClass::class, self::call($api, $method, $id, self::request($label)));
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testA401IsRetriedOnceWithAFreshTokenSameMethodSameUrlSameBody(string $method, string $label, string $responseLabel, ?string $id): void
    {
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);

        $result = self::call($api, $method, $id, self::request($label));
        $calls = $this->transport->calls;

        $this->assertCount(2, $calls);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($calls[0]['method'], $calls[1]['method']);
        $this->assertSame($calls[0]['url'], $calls[1]['url']);
        $this->assertSame($calls[0]['body'], $calls[1]['body']);
        $this->assertSame('T2', $result->transactionId);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testOtherErrorStatusesAreNeverRepeated(string $method, string $label, string $responseLabel, ?string $id): void
    {
        foreach ([400, 500, 503] as $status) {
            $api = $this->api([Responses::make($status, '{"errors":[{"code":"X","message":"boom"}]}'), Responses::make(200, '{"transactionId":"T2"}')]);

            $e = self::thrown(function () use ($api, $method, $id, $label) {
                self::call($api, $method, $id, self::request($label));
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
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testATimeoutIsATransportErrorAndIsNeverRepeated(string $method, string $label, string $responseLabel, ?string $id): void
    {
        $api = $this->api([Responses::make(0, '', 'Operation timed out'), Responses::make(200, '{"transactionId":"T2"}')]);

        $e = self::thrown(function () use ($api, $method, $id, $label) {
            self::call($api, $method, $id, self::request($label));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertCount(1, $this->transport->calls);
    }

    /**
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     */
    public function testATokenFailureIsAnAuthExceptionAndNothingElseIsSent(string $method, string $label, string $responseLabel, ?string $id): void
    {
        $auth = StubAuth::make();
        $auth->throwOnToken = new FedexAuthException('bad credentials', FedexException::EC_UNABLE_TO_AUTHENTICATE);
        $api = $this->api([], null, $auth);

        $e = self::thrown(function () use ($api, $method, $id, $label) {
            self::call($api, $method, $id, self::request($label));
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
        $api = $this->answering('this is not json');

        $e = self::thrown(function () use ($api) {
            $api->create(self::request('create.request'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertCount(1, $this->transport->calls);
    }

    /**
     * An HTTP 400 with fedex errors keeps the status the transaction ID and the errors
     * @return void
     */
    public function testAnHttp400WithFedexErrorsKeepsTheStatusTheTransactionIdAndTheErrors(): void
    {
        $api = $this->answering(json_encode(['transactionId' => 'T4', 'errors' => [['code' => 'SHIPMENT.NOT.ALLOWED', 'message' => 'Not allowed']]]), 400);

        $e = self::thrown(function () use ($api) {
            $api->create(self::request('create.request'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('T4', $e->getTransactionId());
        $this->assertStringContainsString('Not allowed', $e->getErrorsAsString());
        $this->assertCount(1, $this->transport->calls, 'no retry');
    }

    /**
     * Two 401 s in a row stop after two requests
     * @return void
     */
    public function testTwo401sInARowStopAfterTwoRequests(): void
    {
        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(401, '{"errors":[]}')]);

        $e = self::thrown(function () use ($api) {
            $api->create(self::request('create.request'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(401, $e->getStatusCode());
        $this->assertCount(2, $this->transport->calls);
    }

    // ---------------------------------------------------------------- the response of the async results

    /**
     * The async output does not define job ID so any type is tolerated and the create output refuses an integer
     * @return void
     */
    public function testTheAsyncOutputDoesNotDefineJobIdSoAnyTypeIsToleratedAndTheCreateOutputRefusesAnInteger(): void
    {
        $async = Spec::instance('results.response')[0];
        $async['output']['jobId'] = 12345;
        $api = $this->answering(json_encode($async));
        $api->getAsyncResults(self::request('results.request'));

        $create = Spec::instance('create.response')[0];
        $create['output']['jobId'] = 12345;
        $api = $this->answering(json_encode($create));

        $e = self::thrown(function () use ($api) {
            $api->create(self::request('create.request'));
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
    }

    /**
     * The response definitions of the cancel tag and async operations
     * @return void
     */
    public function testTheResponseDefinitionsOfTheCancelTagAndAsyncOperations(): void
    {
        $defs = \Mariopereira\Fedex\Api\FedexShipSchemas::definitions();

        $this->assertSame('@BaseProcessOutputVO_GetOpenShipmentResults', $defs['SHPCResponseVO_GetOpenShipmentResults']['output?']);
        $this->assertSame('@BaseProcessOutputVO', $defs['SHPCResponseVO']['output?']);
        $this->assertSame('@CancelTagOuputVO', $defs['BaseProcessOutputVO']);
    }

    // ---------------------------------------------------------------- create tag

    /**
     * The generated tag request includes the three undefined keys and the pickup detail and they are sent
     * @return void
     */
    public function testTheGeneratedTagRequestIncludesTheThreeUndefinedKeysAndThePickupDetailAndTheyAreSent(): void
    {
        $api = $this->api();

        $api->createTag(self::request('createtag.request'));
        $shipment = $this->transport->bodyOf(0)['requestedShipment'];

        $this->assertArrayHasKey('shipTimestamp', $shipment);
        $this->assertArrayHasKey('shippingLabelType', $shipment);
        $this->assertArrayHasKey('specialServicesRequested', $shipment);
        $this->assertArrayHasKey('readyPickupDateTime', $shipment['pickupDetail']);
    }

    /**
     * A tag request made by filling in the empty one with the minimum
     * @return array
     */
    private static function minimumTag(): array
    {
        $min = FedexShip::newCreateTagRequest();
        $min['requestedShipment']['shipper']['address'] = ['streetLines' => ['10 FedEx Parkway'], 'city' => 'Beverly Hills', 'countryCode' => 'US'];
        $min['requestedShipment']['shipper']['contact']['phoneNumber'] = '1234567890';
        $min['requestedShipment']['recipients'][] = ['address' => ['streetLines' => ['1 Main St'], 'city' => 'Memphis', 'countryCode' => 'US'], 'contact' => ['phoneNumber' => '9012223333']];
        $min['requestedShipment']['pickupType'] = 'USE_SCHEDULED_PICKUP';
        $min['requestedShipment']['serviceType'] = 'FEDEX_GROUND';
        $min['requestedShipment']['packagingType'] = 'YOUR_PACKAGING';
        $min['requestedShipment']['totalWeight'] = 1.5;
        $min['requestedShipment']['shippingChargesPayment']['paymentType'] = 'SENDER';
        $min['requestedShipment']['labelSpecification'] = ['labelStockType' => 'PAPER_4X6', 'imageType' => 'PDF'];
        $min['requestedShipment']['requestedPackageLineItems'][] = ['weight' => ['units' => 'LB', 'value' => 1.5]];
        $min['requestedShipment']['pickupDetail'] = ['readyPickupDateTime' => '2020-07-03T09:00:00Z', 'latestPickupDateTime' => '2020-07-03T17:00:00Z'];
        $min['accountNumber']['value'] = '000000000';

        return $min;
    }

    /**
     * The minimum tag request is accepted without the three undefined keys
     * @return void
     */
    public function testTheMinimumTagRequestIsAcceptedWithoutTheThreeUndefinedKeys(): void
    {
        $api = $this->api();

        $api->createTag(self::minimumTag());

        $this->assertCount(1, $this->transport->calls);
    }

    /**
     * The three undefined keys accept any value and are sent as given
     * @return void
     */
    public function testTheThreeUndefinedKeysAcceptAnyValueAndAreSentAsGiven(): void
    {
        $res = [];

        foreach (['a string' => 'x', 'an integer' => 5, 'a list' => ['a', 'b'], 'an object' => ['k' => 'v'], 'a boolean' => true, 'a decimal' => 1.5] as $label => $value) {
            foreach (['shipTimestamp', 'shippingLabelType', 'specialServicesRequested'] as $key) {
                $request = self::minimumTag();
                $request['requestedShipment'][$key] = $value;
                $api = $this->api();
                $e = self::thrown(function () use ($api, $request) {
                    $api->createTag($request);
                });
                $res["$key as $label"] = ($e === null && $this->transport->bodyOf(0)['requestedShipment'][$key] === json_decode(json_encode($value), true)) ? true : self::describe($e);
            }
        }

        $this->assertAll('the undefined keys of the shipment of a tag', $res);
    }

    /**
     * Without pickup detail the tag request is refused
     * @return void
     */
    public function testWithoutPickupDetailTheTagRequestIsRefused(): void
    {
        $request = self::minimumTag();
        unset($request['requestedShipment']['pickupDetail']);
        $api = $this->api();

        $e = self::thrown(function () use ($api, $request) {
            $api->createTag($request);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertStringContainsString('pickupDetail', implode(' ', $e->getErrors()));
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * @dataProvider pickupKeysProvider
     * @param string $key A key that pickupDetail needs
     */
    public function testWithoutAKeyOfPickupDetailTheTagRequestIsRefused(string $key): void
    {
        $request = self::minimumTag();
        unset($request['requestedShipment']['pickupDetail'][$key]);
        $api = $this->api();

        $e = self::thrown(function () use ($api, $request) {
            $api->createTag($request);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertStringContainsString($key, implode(' ', $e->getErrors()));
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testWithoutAKeyOfPickupDetailTheTagRequestIsRefused(): without a key of pickup detail the tag request is refused
     * @return array
     */
    public function pickupKeysProvider(): array
    {
        return [['readyPickupDateTime'], ['latestPickupDateTime']];
    }

    /**
     * Ship datestamp the date field that the schema does define is accepted
     * @return void
     */
    public function testShipDatestampTheDateFieldThatTheSchemaDoesDefineIsAccepted(): void
    {
        $request = self::minimumTag();
        $request['requestedShipment']['shipDatestamp'] = '2020-07-03';
        $api = $this->api();

        $api->createTag($request);

        $this->assertCount(1, $this->transport->calls);
    }

    /**
     * Any other key that is not in the schema is still refused
     * @return void
     */
    public function testAnyOtherKeyThatIsNotInTheSchemaIsStillRefused(): void
    {
        $request = self::minimumTag();
        $request['requestedShipment']['someOtherKey'] = 1;
        $api = $this->api();

        $e = self::thrown(function () use ($api, $request) {
            $api->createTag($request);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertStringContainsString('someOtherKey', implode(' ', $e->getErrors()));
        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- cancel tag

    /**
     * @dataProvider cancelTagKeysProvider
     * @param string $key A key of the Cancel Tag request that is required
     */
    public function testWithoutARequiredKeyTheCancelTagRequestIsRefused(string $key): void
    {
        $request = self::request('canceltag.request');
        unset($request[$key]);
        $api = $this->api();

        $e = self::thrown(function () use ($api, $request) {
            $api->cancelTag('1', $request);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testWithoutARequiredKeyTheCancelTagRequestIsRefused(): without a required key the cancel tag request is refused
     * @return array
     */
    public function cancelTagKeysProvider(): array
    {
        return [['accountNumber'], ['serviceType'], ['completedTagDetail']];
    }

    /**
     * @dataProvider completedTagKeysProvider
     * @param string $key A key of completedTagDetail that is required
     */
    public function testWithoutAKeyOfTheCompletedTagDetailTheCancelTagRequestIsRefused(string $key): void
    {
        $request = self::request('canceltag.request');
        unset($request['completedTagDetail'][$key]);
        $api = $this->api();

        $e = self::thrown(function () use ($api, $request) {
            $api->cancelTag('1', $request);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertStringContainsString($key, implode(' ', $e->getErrors()));
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testWithoutAKeyOfTheCompletedTagDetailTheCancelTagRequestIsRefused(): without a key of the completed tag detail the cancel tag request is refused
     * @return array
     */
    public function completedTagKeysProvider(): array
    {
        return [['confirmationNumber'], ['location'], ['dispatchDate']];
    }

    /**
     * @dataProvider shipmentIdsProvider
     * @param string $id The id of the shipment
     * @param string $expected How it has to be written in the path
     */
    public function testTheIdOfTheShipmentIsUrlEncodedInThePath(string $id, string $expected): void
    {
        $api = $this->api();

        $api->cancelTag($id, self::request('canceltag.request'));
        $calls = $this->transport->calls;

        $this->assertCount(1, $calls);
        $this->assertSame('/ship/v1/shipments/tag/cancel/' . $expected, self::pathOf($calls[0]));
    }

    /**
     * The cases of testTheIdOfTheShipmentIsUrlEncodedInThePath(): the ID of the shipment is URL encoded in the path
     * @return array
     */
    public function shipmentIdsProvider(): array
    {
        return [
            'a slash' => ['a/b', 'a%2Fb'],
            'a space' => ['a b', 'a%20b'],
            'accents' => ['ação', 'a%C3%A7%C3%A3o'],
            'a query and a fragment' => ['x?y=1#z', 'x%3Fy%3D1%23z'],
            'a percent sign' => ['100%', '100%25'],
            'an encoded dot, encoded again' => ['%2E%2E', '%252E%252E'],
            'three dots' => ['...', '...'],
            'dots inside' => ['a..b', 'a..b'],
            'a tab' => ["tab\there", 'tab%09here'],
            'spaces around' => [' x ', '%20x%20'],
        ];
    }

    /**
     * @dataProvider badShipmentIdsProvider
     * @param string $id An id that is refused
     */
    public function testAnIdThatCannotBeUsedIsRefusedBeforeAnythingIsSentNotEvenTheTokenRequest(string $id): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $id) {
            $api->cancelTag($id, self::request('canceltag.request'));
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], $this->transport->calls);
        $this->assertSame([], $this->auth->forceSeen);
    }

    /**
     * The cases of testAnIdThatCannotBeUsedIsRefusedBeforeAnythingIsSentNotEvenTheTokenRequest(): an ID that cannot be used is refused before anything is sent not even the token request
     * @return array
     */
    public function badShipmentIdsProvider(): array
    {
        return ['empty' => [''], 'a space' => [' '], 'a tab' => ["\t"], 'one dot' => ['.'], 'two dots' => ['..']];
    }

    // ---------------------------------------------------------------- the async results

    /**
     * The async results by job ID sends the account and the job
     * @return void
     */
    public function testTheAsyncResultsByJobIdSendsTheAccountAndTheJob(): void
    {
        $api = $this->api();

        $api->getAsyncResultsByJobId('000000000', '89sxxxxx233ae24ff31xxxxx');

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame('POST', $this->transport->calls[0]['method']);
        $this->assertSame('/ship/v1/shipments/results', self::pathOf($this->transport->calls[0]));
        $this->assertSame(['accountNumber' => ['value' => '000000000'], 'jobId' => '89sxxxxx233ae24ff31xxxxx'], $this->transport->bodyOf(0));
    }

    /**
     * The async results by job ID sends the version option
     * @return void
     */
    public function testTheAsyncResultsByJobIdSendsTheVersionOption(): void
    {
        $api = $this->api();

        $api->getAsyncResultsByJobId('000000000', 'job', ['version' => ['major' => 1, 'minor' => 0]]);

        $this->assertSame(['major' => 1, 'minor' => 0], $this->transport->bodyOf(0)['version']);
    }

    /**
     * @dataProvider reservedByJobProvider
     * @param string $field An option that the method sets itself
     */
    public function testTheAsyncResultsByJobIdRefusesTheOptionsThatItSetsItself(string $field): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $field) {
            $api->getAsyncResultsByJobId('000000000', 'job', [$field => 'x']);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testTheAsyncResultsByJobIdRefusesTheOptionsThatItSetsItself(): the async results by job ID refuses the options that it sets itself
     * @return array
     */
    public function reservedByJobProvider(): array
    {
        return [['accountNumber'], ['jobId']];
    }

    /**
     * The async results by job ID refuses an option that is not in the schema
     * @return void
     */
    public function testTheAsyncResultsByJobIdRefusesAnOptionThatIsNotInTheSchema(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->getAsyncResultsByJobId('000000000', 'job', ['notInTheSchema' => 1]);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- the class

    /**
     * The class the exception and the constructor
     * @return void
     */
    public function testTheClassTheExceptionAndTheConstructor(): void
    {
        $constructor = new \ReflectionMethod(FedexShip::class, '__construct');

        $this->assertTrue(is_subclass_of(FedexShip::class, AbstractFedexApi::class));
        $this->assertTrue(is_subclass_of(FedexShipException::class, FedexException::class));
        $this->assertTrue((new \ReflectionClass(\Mariopereira\Fedex\Api\FedexShipSchemas::class))->isFinal());
        $this->assertSame(['auth', 'locale', 'timeout', 'connectTimeout', 'client'], array_map(function (\ReflectionParameter $p) {
            return $p->getName();
        }, $constructor->getParameters()));
        $this->assertSame(\Mariopereira\Fedex\Api\FedexShipSchemas::definitions(), \Mariopereira\Fedex\Api\FedexShipSchemas::definitions());
    }

    /**
     * Only the operations that do not change data are retried, and only when the client has a retry policy
     * @dataProvider operationsProvider
     * @param string $method Name of the method
     * @param string $label Label of the request
     * @param string $responseLabel Label of the response
     * @param string|null $id The id of the shipment, for cancelTag()
     * @param bool $changes True when the operation changes data in FedEx
     */
    public function testOnlyTheOperationsThatDoNotChangeDataAreRetriedWhenTheClientHasARetryPolicy(string $method, string $label, string $responseLabel, ?string $id, bool $changes): void
    {
        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $transport = new FakeTransport([Responses::make($status, $body, $error)], Responses::make(200, '{"transactionId":"T2"}'));
            $auth = StubAuth::make();
            $client = (new FedexClient($auth, 'en_US', 30, 10, $transport))->setRetryPolicy(3, 0, 0);
            $api = new FedexShip($auth, 'en_US', 30, 10, $client);

            $e = self::thrown(function () use ($api, $method, $id, $label) {
                self::call($api, $method, $id, self::request($label));
            });

            if ($changes) {
                $this->assertInstanceOf(FedexClientException::class, $e, "$method, HTTP $status: it changes data, so it must fail at the first failure");
                $this->assertCount(1, $transport->calls, "$method, HTTP $status is never repeated");
            } else {
                $this->assertNull($e, "$method, HTTP $status: " . self::describe($e));
                $this->assertCount(2, $transport->calls, "$method, HTTP $status is repeated once");
                $this->assertSame($transport->calls[0]['body'], $transport->calls[1]['body']);
            }
        }
    }
}
