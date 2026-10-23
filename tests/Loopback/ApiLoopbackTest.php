<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Loopback;

use Mariopereira\Fedex\Api\FedexAddressValidation;
use Mariopereira\Fedex\Api\FedexBasicTracking;
use Mariopereira\Fedex\Api\FedexConsolidation;
use Mariopereira\Fedex\Api\FedexDutyAndTax;
use Mariopereira\Fedex\Api\FedexLocationsSearch;
use Mariopereira\Fedex\Api\FedexRateTransitTimes;
use Mariopereira\Fedex\Api\FedexServiceAvailability;
use Mariopereira\Fedex\Api\FedexShip;
use Mariopereira\Fedex\Exception\FedexAddressValidationException;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexBasicTrackingException;
use Mariopereira\Fedex\Exception\FedexConsolidationException;
use Mariopereira\Fedex\Exception\FedexDutyAndTaxException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexLocationsSearchException;
use Mariopereira\Fedex\Exception\FedexRateTransitTimesException;
use Mariopereira\Fedex\Exception\FedexServiceAvailabilityException;
use Mariopereira\Fedex\Exception\FedexShipException;
use Mariopereira\Fedex\Tests\Support\ConsolidationSpec;
use Mariopereira\Fedex\Tests\Support\LoopbackServer;
use Mariopereira\Fedex\Tests\Support\LoopbackTestCase;
use Mariopereira\Fedex\Tests\Support\ShipSpec;
use Mariopereira\Fedex\Tests\Support\TrackSpec;

/**
 * Tests of the Locations search, the Address validation and the Ship API with the real cURL, the real FedexAuth and the
 * real FedexClient, against the throwaway server on 127.0.0.1. The server answers with an echo of the request, which the
 * lenient response schemas accept
 *
 * @group loopback
 */
final class ApiLoopbackTest extends LoopbackTestCase
{
    private const UUID_HEADER = '/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * A valid address
     * @return array
     */
    private static function address(): array
    {
        return ['streetLines' => ['7372 PARKRIDGE BLVD', 'APT 286'], 'city' => 'IRVING', 'stateOrProvinceCode' => 'TX', 'postalCode' => '75063-8659', 'countryCode' => 'US'];
    }

    /**
     * Make an API class over the real transports
     * @param string $class The class
     * @param int $apiPort The port for the API calls
     * @param string $tokenRoute The route that stands for the token url
     * @return object
     */
    private static function api(string $class, int $apiPort, string $tokenRoute = '/token-ok')
    {
        [$auth, $client] = self::chain($apiPort, self::$server->port(), $tokenRoute);

        return new $class($auth, 'pt_PT', 30, 10, $client);
    }

    // ---------------------------------------------------------------- locations search

    /**
     * Search by coordinates on the wire
     * @return void
     */
    public function testSearchByCoordinatesOnTheWire(): void
    {
        $locations = self::api(FedexLocationsSearch::class, self::$server->port());

        $result = $locations->searchByCoordinates(35.1495, -90.049, 'US', ['locationsSummaryRequestControlParameters' => ['maxResults' => 5], 'locationTypes' => ['FEDEX_OFFICE']]);
        $requests = self::$server->requests();
        $headers = LoopbackServer::headerLines($requests[1]);

        $this->assertSame(['POST /token-ok', 'POST /location/v1/locations'], self::$server->seen());
        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('X-locale: pt_PT', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('Accept: application/json', $headers);
        $this->assertNotEmpty(preg_grep(self::UUID_HEADER, $headers));
        $this->assertSame(
            ['locationsSummaryRequestControlParameters' => ['maxResults' => 5], 'locationTypes' => ['FEDEX_OFFICE'], 'locationSearchCriterion' => 'GEOGRAPHIC_COORDINATES', 'location' => ['address' => ['countryCode' => 'US'], 'longLat' => ['latitude' => '35.1495', 'longitude' => '-90.049']]],
            json_decode($requests[1]['body'], true),
            'the criterion, the coordinates as strings, the country and the options'
        );
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), $headers);
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('POST', $result->method);
        $this->assertSame('/location/v1/locations', $result->target);
    }

    /**
     * Two searches make one token request
     * @return void
     */
    public function testTwoSearchesMakeOneTokenRequest(): void
    {
        $locations = self::api(FedexLocationsSearch::class, self::$server->port());

        $locations->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US']);
        $locations->searchByPhoneNumber('9015551234', 'US');

        $this->assertSame(['POST /token-ok', 'POST /location/v1/locations', 'POST /location/v1/locations'], self::$server->seen());
    }

    /**
     * A request made with new request sends only what was filled in
     * @return void
     */
    public function testARequestMadeWithNewRequestSendsOnlyWhatWasFilledIn(): void
    {
        $locations = self::api(FedexLocationsSearch::class, self::$server->port());
        $request = FedexLocationsSearch::newRequest();
        $request['location']['address']['city'] = 'Memphis';
        $request['location']['address']['countryCode'] = 'US';
        $request['sameState'] = false;
        $request['locationCapabilities'][] = ['carrierCode' => 'FDXE', 'transferOfPossessionType' => 'DROPOFF'];

        $locations->search($request);

        $this->assertSame(
            ['location' => ['address' => ['city' => 'Memphis', 'countryCode' => 'US']], 'sameState' => false, 'locationCapabilities' => [['carrierCode' => 'FDXE', 'transferOfPossessionType' => 'DROPOFF']]],
            json_decode(self::$server->requests()[1]['body'], true)
        );
    }

    /**
     * An invalid search never reaches the network not even the token request
     * @return void
     */
    public function testAnInvalidSearchNeverReachesTheNetworkNotEvenTheTokenRequest(): void
    {
        $locations = self::api(FedexLocationsSearch::class, self::$server->port());

        $e = self::thrown(function () use ($locations) {
            $locations->search(['location' => ['address' => ['countryCode' => 'US']]]);
        });

        $this->assertInstanceOf(FedexLocationsSearchException::class, $e);
        $this->assertSame(FedexException::EC_MISSING_PARAMETER, $e->getCode());
        $this->assertSame([], self::$server->seen());
    }

    /**
     * A search with bad credentials makes one token request and no search
     * @return void
     */
    public function testASearchWithBadCredentialsMakesOneTokenRequestAndNoSearch(): void
    {
        $locations = self::api(FedexLocationsSearch::class, self::$server->port(), '/token-401');

        $e = self::thrown(function () use ($locations) {
            $locations->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US']);
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_UNABLE_TO_AUTHENTICATE, $e->getCode());
        $this->assertSame(['POST /token-401'], self::$server->seen());
    }

    /**
     * A search when the connection is refused
     * @return void
     */
    public function testASearchWhenTheConnectionIsRefused(): void
    {
        $locations = self::api(FedexLocationsSearch::class, self::$closedPort);

        $e = self::thrown(function () use ($locations) {
            $locations->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US']);
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertStringStartsWith('Transport error: ', $e->getMessage());
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
    }

    // ---------------------------------------------------------------- address validation

    /**
     * Validate an address on the wire
     * @return void
     */
    public function testValidateAnAddressOnTheWire(): void
    {
        $validation = self::api(FedexAddressValidation::class, self::$server->port());

        $result = $validation->validate(self::address(), ['validateAddressControlParameters' => ['includeResolutionTokens' => true]]);
        $requests = self::$server->requests();
        $headers = LoopbackServer::headerLines($requests[1]);

        $this->assertSame(['POST /token-ok', 'POST /address/v1/addresses/resolve'], self::$server->seen());
        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('X-locale: pt_PT', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('Accept: application/json', $headers);
        $this->assertNotEmpty(preg_grep(self::UUID_HEADER, $headers));
        $this->assertSame(['validateAddressControlParameters' => ['includeResolutionTokens' => true], 'addressesToValidate' => [['address' => self::address()]]], json_decode($requests[1]['body'], true));
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), $headers);
        $this->assertSame('POST', $result->method);
        $this->assertSame('/address/v1/addresses/resolve', $result->target);
    }

    /**
     * Several addresses and another call make one token request
     * @return void
     */
    public function testSeveralAddressesAndAnotherCallMakeOneTokenRequest(): void
    {
        $validation = self::api(FedexAddressValidation::class, self::$server->port());
        $second = ['streetLines' => ['24 Grosvenor Square'], 'city' => 'London', 'postalCode' => 'W1A 2LQ', 'countryCode' => 'GB'];
        $third = (object) ['streetLines' => ['Via Vittorio Veneto, 121'], 'city' => 'Roma', 'postalCode' => 187, 'countryCode' => 'IT'];

        $validation->validateArray([self::address(), $second, $third]);
        $validation->validate(self::address());
        $body = json_decode(self::$server->requests()[1]['body'], true);

        $this->assertSame(['POST /token-ok', 'POST /address/v1/addresses/resolve', 'POST /address/v1/addresses/resolve'], self::$server->seen());
        $this->assertCount(3, $body['addressesToValidate']);
        $this->assertSame('GB', $body['addressesToValidate'][1]['address']['countryCode']);
        $this->assertSame(187, $body['addressesToValidate'][2]['address']['postalCode'], 'the Italian postal code as an integer');
    }

    /**
     * A request made with new request and new address
     * @return void
     */
    public function testARequestMadeWithNewRequestAndNewAddress(): void
    {
        $validation = self::api(FedexAddressValidation::class, self::$server->port());
        $request = FedexAddressValidation::newRequest();
        $item = FedexAddressValidation::newAddress();
        $item['address']['streetLines'][] = '7372 PARKRIDGE BLVD';
        $item['address']['city'] = 'IRVING';
        $item['address']['postalCode'] = '75063-8659';
        $item['clientReferenceId'] = 'order-123';
        $request['addressesToValidate'][] = $item;
        $request['inEffectAsOfTimestamp'] = '2019-09-06';

        $validation->resolve($request);

        $this->assertSame(
            ['inEffectAsOfTimestamp' => '2019-09-06', 'addressesToValidate' => [['address' => ['streetLines' => ['7372 PARKRIDGE BLVD'], 'city' => 'IRVING', 'postalCode' => '75063-8659'], 'clientReferenceId' => 'order-123']]],
            json_decode(self::$server->requests()[1]['body'], true)
        );
    }

    /**
     * An invalid address never reaches the network not even the token request
     * @return void
     */
    public function testAnInvalidAddressNeverReachesTheNetworkNotEvenTheTokenRequest(): void
    {
        $validation = self::api(FedexAddressValidation::class, self::$server->port());

        $e = self::thrown(function () use ($validation) {
            $validation->validate(['postalCode' => '1']);
        });

        $this->assertInstanceOf(FedexAddressValidationException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], self::$server->seen());
    }

    /**
     * One hundred and one addresses are refused and one hundred go in one request
     * @return void
     */
    public function testOneHundredAndOneAddressesAreRefusedAndOneHundredGoInOneRequest(): void
    {
        $validation = self::api(FedexAddressValidation::class, self::$server->port());

        $e = self::thrown(function () use ($validation) {
            $validation->validateArray(array_fill(0, 101, self::address()));
        });

        $this->assertInstanceOf(FedexAddressValidationException::class, $e);
        $this->assertSame([], self::$server->seen());

        $validation->validateArray(array_fill(0, 100, self::address()));

        $this->assertSame(['POST /token-ok', 'POST /address/v1/addresses/resolve'], self::$server->seen());
        $this->assertCount(100, json_decode(self::$server->requests()[1]['body'], true)['addressesToValidate']);
    }

    /**
     * Address validation with bad credentials and with a refused connection
     * @return void
     */
    public function testAddressValidationWithBadCredentialsAndWithARefusedConnection(): void
    {
        $validation = self::api(FedexAddressValidation::class, self::$server->port(), '/token-401');

        $e = self::thrown(function () use ($validation) {
            $validation->validate(self::address());
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_UNABLE_TO_AUTHENTICATE, $e->getCode());
        $this->assertSame(['POST /token-401'], self::$server->seen());

        $validation = self::api(FedexAddressValidation::class, self::$closedPort);

        $e = self::thrown(function () use ($validation) {
            $validation->validate(self::address());
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertStringStartsWith('Transport error: ', $e->getMessage());
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
    }

    // ---------------------------------------------------------------- the Ship API

    /**
     * A request generated from the spec
     * @param string $label A key of ShipSpec::ROOTS ending in .request
     * @return array
     */
    private static function request(string $label): array
    {
        return ShipSpec::instance($label)[0];
    }

    /**
     * Create a shipment on the wire
     * @return void
     */
    public function testCreateAShipmentOnTheWire(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());
        $request = self::request('create.request');

        $result = $ship->create($request);
        $requests = self::$server->requests();
        $headers = LoopbackServer::headerLines($requests[1]);

        $this->assertSame(['POST /token-ok', 'POST /ship/v1/shipments'], self::$server->seen());
        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('X-locale: pt_PT', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('Accept: application/json', $headers);
        $this->assertNotEmpty(preg_grep(self::UUID_HEADER, $headers));
        $this->assertSame(json_decode(json_encode($request), true), json_decode($requests[1]['body'], true));
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), $headers);
        $this->assertSame('POST', $result->method);
        $this->assertSame('/ship/v1/shipments', $result->target);
    }

    /**
     * Validate a shipment is a post to its own path
     * @return void
     */
    public function testValidateAShipmentIsAPostToItsOwnPath(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());

        $result = $ship->validate(self::request('validate.request'));

        $this->assertSame(['POST /token-ok', 'POST /ship/v1/shipments/packages/validate'], self::$server->seen());
        $this->assertSame('/ship/v1/shipments/packages/validate', $result->target);
    }

    /**
     * Cancel by tracking number is a real put with its body and length
     * @return void
     */
    public function testCancelByTrackingNumberIsARealPutWithItsBodyAndLength(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());

        $result = $ship->cancelByTrackingNumber('000000000', '794953555571', ['deletionControl' => 'DELETE_ALL_PACKAGES']);
        $requests = self::$server->requests();
        $body = json_decode($requests[1]['body'], true);
        ksort($body);

        $this->assertSame(['POST /token-ok', 'PUT /ship/v1/shipments/cancel'], self::$server->seen(), 'the token request is a POST, the cancellation a PUT');
        $this->assertSame(['accountNumber' => ['value' => '000000000'], 'deletionControl' => 'DELETE_ALL_PACKAGES', 'trackingNumber' => '794953555571'], $body);
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), LoopbackServer::headerLines($requests[1]));
        $this->assertContains('Content-Type: application/json', LoopbackServer::headerLines($requests[1]));
        $this->assertSame('PUT', $result->method);
        $this->assertSame('/ship/v1/shipments/cancel', $result->target);
    }

    /**
     * Two operations with one object make one token request
     * @return void
     */
    public function testTwoOperationsWithOneObjectMakeOneTokenRequest(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());

        $ship->validate(self::request('validate.request'));
        $ship->cancelByTrackingNumber('000000000', '794953555571');

        $this->assertSame(['POST /token-ok', 'POST /ship/v1/shipments/packages/validate', 'PUT /ship/v1/shipments/cancel'], self::$server->seen());
    }

    /**
     * An invalid shipment request never reaches the network not even the token request
     * @return void
     */
    public function testAnInvalidShipmentRequestNeverReachesTheNetworkNotEvenTheTokenRequest(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());
        $request = self::request('create.request');
        $request['requestedShipment']['totalPackageCount'] = 'many';

        $e = self::thrown(function () use ($ship, $request) {
            $ship->create($request);
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], self::$server->seen());
    }

    /**
     * A shipment with bad credentials and with a refused connection
     * @return void
     */
    public function testAShipmentWithBadCredentialsAndWithARefusedConnection(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port(), '/token-401');

        $e = self::thrown(function () use ($ship) {
            $ship->create(self::request('create.request'));
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_UNABLE_TO_AUTHENTICATE, $e->getCode());
        $this->assertSame(['POST /token-401'], self::$server->seen(), 'one token request and no shipment request');

        $ship = self::api(FedexShip::class, self::$closedPort);

        $e = self::thrown(function () use ($ship) {
            $ship->create(self::request('create.request'));
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertStringStartsWith('Transport error: ', $e->getMessage());
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
    }

    /**
     * Create a tag is a post to the tag path
     * @return void
     */
    public function testCreateATagIsAPostToTheTagPath(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());

        $result = $ship->createTag(self::request('createtag.request'));

        $this->assertSame(['POST /token-ok', 'POST /ship/v1/shipments/tag'], self::$server->seen());
        $this->assertSame('/ship/v1/shipments/tag', $result->target);
    }

    /**
     * Cancel a tag is a real put with the shipment ID in the path
     * @return void
     */
    public function testCancelATagIsARealPutWithTheShipmentIdInThePath(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());
        $request = self::request('canceltag.request');

        $result = $ship->cancelTag('794953555571', $request);
        $requests = self::$server->requests();

        $this->assertSame(['POST /token-ok', 'PUT /ship/v1/shipments/tag/cancel/794953555571'], self::$server->seen());
        $this->assertSame(json_decode(json_encode($request), true), json_decode($requests[1]['body'], true));
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), LoopbackServer::headerLines($requests[1]));
        $this->assertSame('PUT', $result->method);
    }

    /**
     * @dataProvider shipmentIdsProvider
     * @param string $id The id of the shipment
     * @param string $encoded How a real cURL sends it in the path
     */
    public function testTheIdOfTheShipmentReachesTheServerEncodedInThePath(string $id, string $encoded): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());

        $ship->cancelTag($id, self::request('canceltag.request'));

        $this->assertSame(['POST /token-ok', 'PUT /ship/v1/shipments/tag/cancel/' . $encoded], self::$server->seen());
    }

    /**
     * The cases of testTheIdOfTheShipmentReachesTheServerEncodedInThePath(): the ID of the shipment reaches the server encoded in the path
     * @return array
     */
    public function shipmentIdsProvider(): array
    {
        return [
            'a slash and a space' => ['a/b c', 'a%2Fb%20c'],
            'accents' => ['ação', 'a%C3%A7%C3%A3o'],
            'a query and a fragment' => ['x?y=1#z', 'x%3Fy%3D1%23z'],
            'a percent sign' => ['100%', '100%25'],
            'an encoded dot, encoded again' => ['%2E%2E', '%252E%252E'],
        ];
    }

    /**
     * @dataProvider dotIdsProvider
     * @param string $id An id that cURL would remove from the path
     */
    public function testAnIdThatCurlWouldTurnIntoAnotherEndpointIsRefusedAndNothingReachesTheServer(string $id): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());

        $e = self::thrown(function () use ($ship, $id) {
            $ship->cancelTag($id, self::request('canceltag.request'));
        });

        $this->assertInstanceOf(FedexShipException::class, $e);
        $this->assertSame([], self::$server->seen());
    }

    /**
     * The cases of testAnIdThatCurlWouldTurnIntoAnotherEndpointIsRefusedAndNothingReachesTheServer(): an ID that cURL would turn into another endpoint is refused and nothing reaches the server
     * @return array
     */
    public function dotIdsProvider(): array
    {
        return [['..'], ['.']];
    }

    /**
     * The async results are a post with the account and the job ID
     * @return void
     */
    public function testTheAsyncResultsAreAPostWithTheAccountAndTheJobId(): void
    {
        $ship = self::api(FedexShip::class, self::$server->port());

        $result = $ship->getAsyncResultsByJobId('000000000', '89sxxxxx233ae24ff31xxxxx');

        $this->assertSame(['POST /token-ok', 'POST /ship/v1/shipments/results'], self::$server->seen());
        $this->assertSame(['accountNumber' => ['value' => '000000000'], 'jobId' => '89sxxxxx233ae24ff31xxxxx'], json_decode(self::$server->requests()[1]['body'], true));
        $this->assertSame('/ship/v1/shipments/results', $result->target);
    }

    // ---------------------------------------------------------------- retry with backoff

    /**
     * A 503 is retried on the wire only for a request that is safe to repeat
     * @return void
     */
    public function testA503IsRetriedOnTheWireOnlyForARequestThatIsSafeToRepeat(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());
        $client->setRetryPolicy(3, 0, 0);

        $e = self::thrown(function () use ($client) {
            $client->request('POST', 'status/503', ['a' => 1], [], [], null, true);
        });
        $requests = self::$server->requests();

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(503, $e->getStatusCode());
        $this->assertSame(['POST /token-ok', 'POST /status/503', 'POST /status/503', 'POST /status/503'], self::$server->seen(), 'one token request and three attempts');
        $this->assertSame($requests[1]['body'], $requests[3]['body']);

        self::$server->forget();

        $e = self::thrown(function () use ($client) {
            $client->request('POST', 'status/503', ['a' => 1]);
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(['POST /status/503'], self::$server->seen(), 'a request that is not marked as safe is sent once');
    }

    /**
     * A refused connection is retried and the token is requested once
     * @return void
     */
    public function testARefusedConnectionIsRetriedAndTheTokenIsRequestedOnce(): void
    {
        [, $client, , $apiTransport] = self::chain(self::$closedPort, self::$server->port());
        $client->setRetryPolicy(3, 0, 0);

        $e = self::thrown(function () use ($client) {
            $client->request('POST', 'status/200', ['a' => 1], [], [], null, true);
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
        $this->assertCount(3, $apiTransport->calls);
        $this->assertSame(['POST /token-ok'], self::$server->seen());
    }


    /**
     * A 429 with a Retry-After is waited for and then retried on the wire
     * @return void
     */
    public function testA429WithARetryAfterIsWaitedForAndThenRetriedOnTheWire(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());
        $waits = [];
        $client->setRetryPolicy(3, 0, 0)->setRetryHooks(function (int $ms) use (&$waits) {
            $waits[] = $ms;
        }, null);

        $result = $client->request('POST', 'ra-flaky', ['a' => 1], [], [], null, true);

        $this->assertSame(true, $result->ok);
        $this->assertSame(2, $result->attempt);
        $this->assertSame([1000], $waits, 'the one second that the server asked for');
        $this->assertSame(['POST /token-ok', 'POST /ra-flaky', 'POST /ra-flaky'], self::$server->seen());
        self::$server->get('/ra-flaky-reset');
    }

    /**
     * A Retry-After that is too long ends the retries on the wire
     * @return void
     */
    public function testARetryAfterThatIsTooLongEndsTheRetriesOnTheWire(): void
    {
        [, $client] = self::chain(self::$server->port(), self::$server->port());
        $client->setRetryPolicy(3, 0, 0)->setRetryHooks(function (int $ms) {
            throw new \RuntimeException('it must not wait');
        }, null);

        $e = self::thrown(function () use ($client) {
            $client->request('POST', 'ra/503/31', ['a' => 1], [], [], null, true);
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(503, $e->getStatusCode());
        $this->assertSame(31, $e->getRetryAfter());
        $this->assertSame(['POST /token-ok', 'POST /ra/503/31'], self::$server->seen());
    }

    // ---------------------------------------------------------------- basic tracking

    /**
     * Track by one tracking number on the wire
     * @return void
     */
    public function testTrackByOneTrackingNumberOnTheWire(): void
    {
        $tracking = self::api(FedexBasicTracking::class, self::$server->port());

        $result = $tracking->trackByNumber('123456789012');
        $requests = self::$server->requests();
        $headers = LoopbackServer::headerLines($requests[1]);

        $this->assertSame(['POST /token-ok', 'POST /track/v1/trackingnumbers'], self::$server->seen());
        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('X-locale: pt_PT', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertNotEmpty(preg_grep(self::UUID_HEADER, $headers));
        $this->assertSame(
            ['includeDetailedScans' => true, 'trackingInfo' => [['trackingNumberInfo' => ['trackingNumber' => '123456789012']]]],
            json_decode($requests[1]['body'], true)
        );
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), $headers);
        $this->assertSame('POST', $result->method);
        $this->assertSame('/track/v1/trackingnumbers', $result->target);
    }

    /**
     * Every tracking operation is a post to its own path
     * @return void
     */
    public function testEveryTrackingOperationIsAPostToItsOwnPath(): void
    {
        $tracking = self::api(FedexBasicTracking::class, self::$server->port());

        $tracking->trackAssociatedShipments(TrackSpec::instance('associated.request')[0]);
        $tracking->trackByReferences(TrackSpec::instance('references.request')[0]);
        $tracking->trackByTcn(TrackSpec::instance('tcn.request')[0]);
        $tracking->getTrackingDocuments(TrackSpec::instance('documents.request')[0]);
        $tracking->sendNotification(TrackSpec::instance('notification.request')[0]);

        $this->assertSame(
            ['POST /token-ok', 'POST /track/v1/associatedshipments', 'POST /track/v1/referencenumbers', 'POST /track/v1/tcn', 'POST /track/v1/trackingdocuments', 'POST /track/v1/notifications'],
            self::$server->seen(),
            'one token request for the five operations'
        );
    }

    /**
     * An invalid tracking request never reaches the network, not even the token request
     * @return void
     */
    public function testAnInvalidTrackingRequestNeverReachesTheNetworkNotEvenTheTokenRequest(): void
    {
        $tracking = self::api(FedexBasicTracking::class, self::$server->port());

        $e = self::thrown(function () use ($tracking) {
            $tracking->trackByNumbers(array_map('strval', range(100000000001, 100000000031)));
        });

        $this->assertInstanceOf(FedexBasicTrackingException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], self::$server->seen());
    }

    // ---------------------------------------------------------------- consolidation

    /**
     * Retrieve a consolidation by its key on the wire
     * @return void
     */
    public function testRetrieveAConsolidationByItsKeyOnTheWire(): void
    {
        $consolidation = self::api(FedexConsolidation::class, self::$server->port());
        $key = ['consolidationType' => 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'index' => '12345', 'shipDatestamp' => '2022-03-21'];

        $result = $consolidation->retrieveByKey('123456789', $key);
        $requests = self::$server->requests();
        $headers = LoopbackServer::headerLines($requests[1]);

        $this->assertSame(['POST /token-ok', 'POST /ship/v1/consolidations/retrieve'], self::$server->seen());
        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('X-locale: pt_PT', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertNotEmpty(preg_grep(self::UUID_HEADER, $headers));
        $this->assertSame(['accountNumber' => ['value' => '123456789'], 'consolidationKey' => $key], json_decode($requests[1]['body'], true));
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), $headers);
        $this->assertSame('/ship/v1/consolidations/retrieve', $result->target);
    }

    /**
     * Modify and delete are real puts with their bodies and lengths, and create is a post
     * @return void
     */
    public function testModifyAndDeleteAreRealPutsWithTheirBodiesAndLengthsAndCreateIsAPost(): void
    {
        $consolidation = self::api(FedexConsolidation::class, self::$server->port());
        $modify = ConsolidationSpec::instance('modify.request')[0];
        $delete = ConsolidationSpec::instance('delete.request')[0];
        $create = ConsolidationSpec::instance('create.request')[0];

        $consolidation->modifyConsolidation($modify);
        $consolidation->deleteConsolidation($delete);
        $consolidation->createConsolidation($create);
        $requests = self::$server->requests();

        $this->assertSame(['POST /token-ok', 'PUT /ship/v1/consolidations', 'PUT /ship/v1/consolidations/delete', 'POST /ship/v1/consolidations'], self::$server->seen());

        foreach ([[1, $modify], [2, $delete], [3, $create]] as [$i, $sent]) {
            $this->assertSame(json_decode(json_encode($sent), true), json_decode($requests[$i]['body'], true));
            $this->assertContains('Content-Length: ' . strlen($requests[$i]['body']), LoopbackServer::headerLines($requests[$i]));
        }
    }

    /**
     * An invalid consolidation request never reaches the network, not even the token request
     * @return void
     */
    public function testAnInvalidConsolidationRequestNeverReachesTheNetworkNotEvenTheTokenRequest(): void
    {
        $consolidation = self::api(FedexConsolidation::class, self::$server->port());
        $request = ConsolidationSpec::instance('retrieve.request')[0];
        $request['accountNumber'] = 'not an object';

        $e = self::thrown(function () use ($consolidation, $request) {
            $consolidation->retrieveConsolidation($request);
        });

        $this->assertInstanceOf(FedexConsolidationException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], self::$server->seen());
    }

    // ---------------------------------------------------------------- estimated duty and tax

    /**
     * An estimate on the wire has the body, the length and the two content types
     * @return void
     */
    public function testAnEstimateOnTheWireHasTheBodyTheLengthAndTheTwoContentTypes(): void
    {
        $api = self::api(FedexDutyAndTax::class, self::$server->port());
        $shoes = ['description' => 'Athletic Shoes', 'quantity' => 1, 'unitPrice' => ['amount' => 10.39, 'currencyCode' => 'USD']];

        $result = $api->estimateForCommodities('IN', 'US', [$shoes]);
        $requests = self::$server->requests();
        $headers = LoopbackServer::headerLines($requests[1]);

        $this->assertSame(['POST /token-ok', 'POST /dutytax/v1/estimate'], self::$server->seen());
        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('X-locale: pt_PT', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('x-content-type: application/json', $headers);
        $this->assertNotEmpty(preg_grep(self::UUID_HEADER, $headers));
        $this->assertSame(['shipper' => ['countryCode' => 'IN'], 'recipient' => ['countryCode' => 'US'], 'commodities' => [$shoes]], json_decode($requests[1]['body'], true));
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), $headers);
        $this->assertSame('/dutytax/v1/estimate', $result->target);
    }

    /**
     * An invalid estimate never reaches the network, not even the token request
     * @return void
     */
    public function testAnInvalidEstimateNeverReachesTheNetworkNotEvenTheTokenRequest(): void
    {
        $api = self::api(FedexDutyAndTax::class, self::$server->port());

        $e = self::thrown(function () use ($api) {
            $api->estimateForCommodities('IND', 'US', [['description' => 'x', 'quantity' => 1, 'unitPrice' => ['amount' => 1, 'currencyCode' => 'USD']]]);
        });

        $this->assertInstanceOf(FedexDutyAndTaxException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], self::$server->seen());
    }

    // ---------------------------------------------------------------- service availability

    /**
     * The three availability operations on the wire have the body, the length and the content type
     * @return void
     */
    public function testTheAvailabilityOperationsOnTheWireHaveTheBodyTheLengthAndTheContentType(): void
    {
        $us = ['countryCode' => 'US'];
        $calls = [
            '/availability/v1/transittimes' => function (FedexServiceAvailability $api) use ($us) {
                return $api->getTransitTimesBetween(['postalCode' => '38117', 'countryCode' => 'US'], ['postalCode' => '75201', 'countryCode' => 'US'], '10.5', 'LB', 'YOUR_PACKAGING', ['FDXE']);
            },
            '/availability/v1/packageandserviceoptions' => function (FedexServiceAvailability $api) use ($us) {
                return $api->getPackageAndServiceOptionsBetween($us, ['countryCode' => 'CA']);
            },
            '/availability/v1/specialserviceoptions' => function (FedexServiceAvailability $api) use ($us) {
                return $api->getSpecialServiceOptionsBetween($us, ['countryCode' => 'CA'], 5);
            },
        ];

        foreach ($calls as $path => $call) {
            $api = self::api(FedexServiceAvailability::class, self::$server->port());

            $result = $call($api);
            $requests = self::$server->requests();
            $request = end($requests);
            $headers = LoopbackServer::headerLines($request);

            $this->assertSame('/' . ltrim($path, '/'), $result->target);
            $this->assertContains('Authorization: Bearer TOK', $headers, $path);
            $this->assertContains('X-locale: pt_PT', $headers, $path);
            $this->assertContains('Content-Type: application/json', $headers, $path);
            $this->assertNotEmpty(preg_grep(self::UUID_HEADER, $headers), $path);
            $this->assertContains('Content-Length: ' . strlen($request['body']), $headers, $path);
            $this->assertArrayHasKey('requestedShipment', json_decode($request['body'], true), $path);
        }
    }

    /**
     * The body of the transit times on the wire is the one that was asked for, with the number as it was given
     * @return void
     */
    public function testTheBodyOfTheTransitTimesOnTheWireIsTheOneThatWasAskedFor(): void
    {
        $api = self::api(FedexServiceAvailability::class, self::$server->port());

        $api->getTransitTimesBetween(['postalCode' => '38117', 'countryCode' => 'US'], ['postalCode' => 'M5V2T6', 'countryCode' => 'CA'], '10.5', 'KG', 'FEDEX_BOX', ['FDXE', 'FDXG']);
        $requests = self::$server->requests();

        $this->assertSame(['POST /token-ok', 'POST /availability/v1/transittimes'], self::$server->seen());
        $this->assertSame([
            'requestedShipment' => [
                'shipper' => ['address' => ['postalCode' => '38117', 'countryCode' => 'US']],
                'recipients' => [['address' => ['postalCode' => 'M5V2T6', 'countryCode' => 'CA']]],
                'packagingType' => 'FEDEX_BOX',
                'requestedPackageLineItems' => [['weight' => ['units' => 'KG', 'value' => '10.5']]],
            ],
            'carrierCodes' => ['FDXE', 'FDXG'],
        ], json_decode($requests[1]['body'], true));
    }

    /**
     * An invalid availability request never reaches the network, not even the token request
     * @return void
     */
    public function testAnInvalidAvailabilityRequestNeverReachesTheNetworkNotEvenTheTokenRequest(): void
    {
        $api = self::api(FedexServiceAvailability::class, self::$server->port());

        $e = self::thrown(function () use ($api) {
            $api->getTransitTimesBetween(['countryCode' => 'US'], ['countryCode' => 'CA'], 'heavy');
        });

        $this->assertInstanceOf(FedexServiceAvailabilityException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], self::$server->seen());
    }

    // ---------------------------------------------------------------- rates and transit times

    /**
     * A rates request on the wire has the body, the length and the content type, with the postal code and the weight as they were given
     * @return void
     */
    public function testARatesRequestOnTheWireHasTheBodyTheLengthAndTheContentType(): void
    {
        $api = self::api(FedexRateTransitTimes::class, self::$server->port());

        $result = $api->getRatesBetween('123456789', ['postalCode' => '38117', 'countryCode' => 'US'], ['postalCode' => 75201, 'countryCode' => 'US'], '10.5', 'KG', 'FEDEX_BOX', 'USE_SCHEDULED_PICKUP', ['carrierCodes' => ['FDXE']]);
        $requests = self::$server->requests();
        $headers = LoopbackServer::headerLines($requests[1]);

        $this->assertSame(['POST /token-ok', 'POST /rate/v1/rates/quotes'], self::$server->seen());
        $this->assertContains('Authorization: Bearer TOK', $headers);
        $this->assertContains('X-locale: pt_PT', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertNotEmpty(preg_grep(self::UUID_HEADER, $headers));
        $this->assertContains('Content-Length: ' . strlen($requests[1]['body']), $headers);
        $this->assertSame([
            'carrierCodes' => ['FDXE'],
            'accountNumber' => ['value' => '123456789'],
            'requestedShipment' => [
                'shipper' => ['address' => ['postalCode' => '38117', 'countryCode' => 'US']],
                'recipient' => ['address' => ['postalCode' => 75201, 'countryCode' => 'US']],
                'pickupType' => 'USE_SCHEDULED_PICKUP',
                'packagingType' => 'FEDEX_BOX',
                'requestedPackageLineItems' => [['weight' => ['units' => 'KG', 'value' => '10.5']]],
            ],
        ], json_decode($requests[1]['body'], true));
        $this->assertSame('/rate/v1/rates/quotes', $result->target);
    }

    /**
     * An invalid rates request never reaches the network, not even the token request
     * @return void
     */
    public function testAnInvalidRatesRequestNeverReachesTheNetworkNotEvenTheTokenRequest(): void
    {
        $api = self::api(FedexRateTransitTimes::class, self::$server->port());

        $e = self::thrown(function () use ($api) {
            $api->getRatesBetween('123456789', ['postalCode' => '38117', 'countryCode' => 'US'], ['postalCode' => '75201', 'countryCode' => 'US'], 'heavy');
        });

        $this->assertInstanceOf(FedexRateTransitTimesException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame([], self::$server->seen());
    }
}
