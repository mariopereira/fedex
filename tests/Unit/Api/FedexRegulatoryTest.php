<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexRegulatory;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexRegulatoryException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\LooseScalars;
use Mariopereira\Fedex\Tests\Support\RegulatorySpec as Spec;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the Regulatory API: what storing profiles sends (a list), how it checks the request and the response, the method for
 * one profile, and that it is never repeated (it stores data in FedEx)
 */
final class FedexRegulatoryTest extends TestCase
{
    use BatchAssertions;
    use LooseScalars;

    /**
     * The message of an invalid response: the profiles may have been stored
     * @var string
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
     * A FedexRegulatory over a fake transport
     * @param array $queue The answers to give first, in order
     * @param FedexTransportResponse|null $default The answer to give after that (a valid one by default)
     * @param StubAuth|null $auth The authorization
     * @param bool $retries True to make the client retry (3 attempts, no wait)
     * @return FedexRegulatory
     */
    private function api(array $queue = [], ?FedexTransportResponse $default = null, ?StubAuth $auth = null, bool $retries = false): FedexRegulatory
    {
        $this->transport = new FakeTransport($queue, $default ?? Responses::make(200, '{"transactionId":"T1","output":{"message":"Regulatory compliance information submitted"}}'));
        $this->auth = $auth ?? StubAuth::make();
        $client = new FedexClient($this->auth, 'en_US', 30, 10, $this->transport);

        if ($retries) {
            $client->setRetryPolicy(3, 0, 0);
        }

        return new FedexRegulatory($this->auth, 'en_US', 30, 10, $client);
    }

    /**
     * The request examples of the path of the spec
     * @return array The examples by name
     */
    private static function examples(): array
    {
        return array_map(function ($example) {
            return $example['value'];
        }, Spec::spec()['paths']['/regulatory/v1/profiles']['post']['requestBody']['content']['application/json']['examples']);
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
        $this->assertInstanceOf(FedexException::class, new FedexRegulatoryException('x'));
        $this->assertSame('regulatory/v1/profiles', FedexRegulatory::ENDPOINT);
        $this->assertSame(25, FedexRegulatory::MAX_PROFILES);
        $this->assertArrayHasKey('/' . FedexRegulatory::ENDPOINT, Spec::spec()['paths']);
    }

    // ---------------------------------------------------------------- what is sent

    /**
     * Storing profiles sends one post with the list of the generated profiles, and the headers of the spec
     * @return void
     */
    public function testStoringProfilesSendsOnePostWithTheListAndTheHeadersOfTheSpec(): void
    {
        $profiles = Spec::instance('profiles.request')[0];
        $api = $this->api();

        $result = $api->createProfiles($profiles);
        $call = $this->transport->calls[0];

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/regulatory/v1/profiles', $call['url']);
        $this->assertEquals($profiles, $this->transport->bodyOf(0));
        $this->assertSame('[', substr($call['body'], 0, 1), 'the body is a JSON list');
        $this->assertSame('Regulatory compliance information submitted', $result->output->message);
        $this->assertContains('Authorization: Bearer TOK', $call['headers']);
        $this->assertContains('X-locale: en_US', $call['headers']);
        $this->assertContains('Content-Type: application/json', $call['headers']);
        $this->assertCount(1, preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $call['headers']));
    }

    /**
     * The six request examples of the spec are sent as they are
     * @return void
     */
    public function testTheSixRequestExamplesOfTheSpecAreSentAsTheyAre(): void
    {
        $results = [];

        foreach (self::examples() as $name => $example) {
            $api = $this->api();
            $e = self::thrown(function () use ($api, $example) {
                $api->createProfiles($example);
            });
            $results[$name] = ($e === null && $this->transport->bodyOf(0) === $example) ? true : self::describe($e);
        }

        $this->assertCount(6, $results);
        $this->assertAll('the request examples', $results);
    }

    /**
     * One profile is sent as a list of one, and objects are taken as well as arrays
     * @return void
     */
    public function testOneProfileIsSentAsAListOfOneAndObjectsAreTaken(): void
    {
        $profile = self::examples()['EU De Minimis'][0];
        $api = $this->api();

        $api->createProfile($profile);
        $api->createProfile(json_decode(json_encode($profile)));
        $api->createProfiles([json_decode(json_encode($profile)), $profile]);

        $this->assertSame([$profile], $this->transport->bodyOf(0));
        $this->assertSame([$profile], $this->transport->bodyOf(1));
        $this->assertSame([$profile, $profile], $this->transport->bodyOf(2));

        $withNull = json_decode(json_encode($profile));
        $withNull->unused = null;
        $api->createProfile($withNull);

        $this->assertSame([$profile], $this->transport->bodyOf(3), 'a null in an object is left out too');
    }

    /**
     * A profile that is not an array or an object is refused with its own message, and the keys of the list are not sent
     * @return void
     */
    public function testAProfileThatIsNotAnArrayOrAnObjectIsRefusedAndTheKeysOfTheListAreNotSent(): void
    {
        $api = $this->api();

        foreach ([null, 'text', 5, true] as $profile) {
            $e = self::thrown(function () use ($api, $profile) {
                $api->createProfile($profile);
            });

            $this->assertInstanceOf(FedexRegulatoryException::class, $e, var_export($profile, true));
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The profile must be an array or an object', $e->getMessage());
        }

        $this->assertSame([], $this->transport->calls);

        $profile = self::examples()['cpscExample1'][0];
        $api->createProfiles(['first' => $profile, 7 => $profile]);

        $this->assertSame([$profile, $profile], $this->transport->bodyOf(0));
    }

    /**
     * The empty profile is the required keys of the spec, and is refused until it is filled in
     * @return void
     */
    public function testTheEmptyProfileIsTheRequiredKeysOfTheSpecAndIsRefusedUntilItIsFilledIn(): void
    {
        $this->assertSame(['regulationCode' => null, 'productId' => null, 'productIdType' => null, 'countryOfImport' => null, 'details' => []], FedexRegulatory::newProfile());
        $required = Spec::components()['RegulatoryProfileRequest_inner']['required'];
        $keys = array_keys(FedexRegulatory::newProfile());
        sort($required);
        sort($keys);

        $this->assertSame($required, $keys, 'the keys of the empty profile are the required keys of the spec');

        $api = $this->api();
        $e = self::thrown(function () use ($api) {
            $api->createProfile(FedexRegulatory::newProfile());
        });

        $this->assertInstanceOf(FedexRegulatoryException::class, $e);
        $this->assertSame([], $this->transport->calls);

        $profile = FedexRegulatory::newProfile();
        $profile['regulationCode'] = 'EU_DE_MINIMIS';
        $profile['productId'] = 'TSHIRT-L-001';
        $profile['productIdType'] = 'SKU';
        $profile['countryOfImport'] = 'FR';
        $profile['details'][] = ['nonStandardManufacturerProductId' => 'SH123456-L', 'standardManufacturerProductId' => 'GTIN 00000006', 'merchantProductId' => '12345'];
        $api->createProfile($profile);

        $this->assertSame(self::examples()['EU De Minimis'], $this->transport->bodyOf(0), 'it is the EU De Minimis example of the spec');
    }

    /**
     * No profile, more than 25 profiles and a profile without details are refused before anything is sent
     * @return void
     */
    public function testNoProfileMoreThan25ProfilesAndAProfileWithoutDetailsAreRefused(): void
    {
        $api = $this->api();
        $one = self::examples()['cpscExample1'][0];
        $cases = [
            'no profile' => [],
            '26 profiles' => array_fill(0, 26, $one),
            'a profile without details' => [['details' => []] + $one],
            'a profile whose details are all empty' => [['details' => [['merchantProductId' => null]]] + $one],
        ];
        $results = [];

        foreach ($cases as $name => $profiles) {
            $e = self::thrown(function () use ($api, $profiles) {
                $api->createProfiles($profiles);
            });
            $results[$name] = ($e instanceof FedexRegulatoryException && $e->getMessage() === 'Invalid request' && $e->getErrors() !== []) ? true : self::describe($e);
        }

        $this->assertAll('the requests that are refused', $results);
        $this->assertSame([], $this->transport->calls);

        $api->createProfiles(array_fill(0, 25, $one));

        $this->assertCount(25, $this->transport->bodyOf(0), '25 profiles are accepted');
    }

    /**
     * An unknown key is sent, and null and empty lists are left out
     * @return void
     */
    public function testAnUnknownKeyIsSentAndNullAndEmptyListsAreLeftOut(): void
    {
        $profile = self::examples()['cpscExample1'][0];
        $api = $this->api();

        $api->createProfile($profile + ['notInTheSchema' => 1, 'unused' => null, 'empty' => []]);

        $this->assertSame([$profile + ['notInTheSchema' => 1]], $this->transport->bodyOf(0));
    }

    /**
     * Every required field that is missing is refused and nothing is sent
     * @return void
     */
    public function testEveryRequiredFieldThatIsMissingIsRefusedAndNothingIsSent(): void
    {
        [$request, $meta] = Spec::instance('profiles.request');
        $results = [];

        foreach ($meta['objects'] as $object) {
            foreach ($object['required'] as $key) {
                $copy = SpecInstances::unsetAt($request, array_merge($object['path'], [$key]));
                $api = $this->api();
                $e = self::thrown(function () use ($api, $copy) {
                    $api->createProfiles($copy);
                });
                $results[implode('.', array_merge($object['path'], [$key]))] = ($e instanceof FedexRegulatoryException && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertNotEmpty($results);
        $this->assertAll('the required fields', $results);
    }

    /**
     * A value of the wrong type, too long or outside an enum in any leaf is refused and nothing is sent
     * @return void
     */
    public function testAValueOfTheWrongTypeTooLongOrOutsideAnEnumIsRefusedAndNothingIsSent(): void
    {
        [$request, $meta] = Spec::instance('profiles.request');
        $wrong = ['string' => ['x'], 'integer' => 'text', 'number' => 'text', 'boolean' => 'text'];
        $results = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['typed']) {
                continue;
            }

            $values = [$wrong[$leaf['type']]];

            if ($leaf['enum'] !== null) {
                $values[] = 'NOT_A_VALUE';
            }

            if (($leaf['max'] ?? null) !== null && $leaf['type'] === 'string' && end($leaf['path']) !== 'phoneNumber') {
                $values[] = str_repeat('x', $leaf['max'] + 1);
            }

            foreach ($values as $value) {
                $copy = SpecInstances::setAt($request, $leaf['path'], $value);
                $api = $this->api();
                $e = self::thrown(function () use ($api, $copy) {
                    $api->createProfiles($copy);
                });
                $results[implode('.', $leaf['path']) . ' ' . substr(json_encode($value), 0, 12)] = ($e instanceof FedexRegulatoryException && $e->getErrors() !== [] && $this->transport->calls === []) ? true : self::describe($e);
            }
        }

        $this->assertAll('the values that are refused', $results);
    }

    /**
     * The scalars of a looser type than the one of the schema are sent as they are
     * @return void
     */
    public function testTheScalarsOfALooserTypeAreSentAsTheyAre(): void
    {
        [$request, $meta] = Spec::instance('profiles.request');

        $this->assertLooseScalarsAreSent($request, $meta['leaves'], function (array $r) {
            $api = $this->api();
            $api->createProfiles($r);

            return $this->transport->bodyOf(0);
        });
    }

    // ---------------------------------------------------------------- the responses

    /**
     * The generated response is accepted, a field that FedEx adds is tolerated, and an invalid response keeps the response and says that the profiles may have been stored
     * @return void
     */
    public function testTheResponses(): void
    {
        $profiles = self::examples()['cpscExample1'];
        $generated = Spec::instance('profiles.response')[0];
        $api = $this->api([], Responses::make(200, json_encode($generated + ['addedByFedex' => 1])));

        $this->assertSame(1, $api->createProfiles($profiles)->addedByFedex);

        foreach (['{"output":{"message":5}}' => 'Invalid response received', '{"output":"text"}' => 'Invalid response received', '[1,2]' => 'Invalid response', '"text"' => 'Invalid response'] as $body => $message) {
            $api = $this->api([], Responses::make(200, $body));
            $e = self::thrown(function () use ($api, $profiles) {
                $api->createProfiles($profiles);
            });

            $this->assertInstanceOf(FedexRegulatoryException::class, $e, $body);
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame($message . self::NOTE, $e->getMessage(), $body);
            $this->assertEquals(json_decode($body), $e->getData());
        }
    }

    // ---------------------------------------------------------------- what is repeated

    /**
     * A failure that is transient is not repeated, even with a policy, because storing profiles changes data in FedEx
     * @return void
     */
    public function testAFailureThatIsTransientIsNotRepeatedEvenWithAPolicy(): void
    {
        $profiles = self::examples()['cpscExample1'];

        foreach ([[503, '{"errors":[]}', null], [429, '{"errors":[]}', null], [504, '{"errors":[]}', null], [0, '', 'Operation timed out']] as [$status, $body, $error]) {
            $api = $this->api([Responses::make($status, $body, $error)], null, null, true);
            $e = self::thrown(function () use ($api, $profiles) {
                $api->createProfiles($profiles);
            });

            $this->assertInstanceOf(FedexClientException::class, $e, "HTTP $status");
            $this->assertCount(1, $this->transport->calls, "HTTP $status is not repeated");
        }
    }

    /**
     * A 401 is retried once with a fresh token, a token that cannot be had sends nothing, a response that is not JSON and a 400 keep what FedEx said
     * @return void
     */
    public function testA401NoTokenNotJsonAndA400(): void
    {
        $profiles = self::examples()['cpscExample2'];

        $api = $this->api([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"transactionId":"T2"}')]);
        $this->assertSame('T2', $api->createProfiles($profiles)->transactionId);
        $this->assertSame([false, true], $this->auth->forceSeen);
        $this->assertSame($this->transport->calls[0]['body'], $this->transport->calls[1]['body']);

        $auth = StubAuth::make();
        $auth->throwOnToken = new FedexAuthException('token failed', FedexException::EC_UNABLE_TO_AUTHENTICATE, null, 401);
        $api = $this->api([], null, $auth);
        $e = self::thrown(function () use ($api, $profiles) {
            $api->createProfiles($profiles);
        });
        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame([], $this->transport->calls);

        $api = $this->api([Responses::make(200, 'not json')]);
        $e = self::thrown(function () use ($api, $profiles) {
            $api->createProfiles($profiles);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertCount(1, $this->transport->calls);

        $api = $this->api([Responses::make(400, '{"transactionId":"624deea6","errors":[{"code":"REGULATORYPROFILE.GTINCODE.LENGTH","message":"GtinUpcCode must not exceed 14 characters."}]}')]);
        $e = self::thrown(function () use ($api, $profiles) {
            $api->createProfiles($profiles);
        });
        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertCount(1, $e->getErrors());
    }
}
