<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexLocationsSearch;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexLocationsSearchException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\ExposedLocations;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Reflect;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances as Spec;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the locations search. The definitions are compared with schemas/locations.json, requests and responses are
 * generated from that file and broken one field at a time, and the rules of the criteria and the helpers are checked
 */
final class FedexLocationsSearchTest extends TestCase
{
    use BatchAssertions;

    private const OK_RESPONSE = '{"transactionId":"T1","output":{"totalResults":0,"resultsReturned":0}}';

    /**
     * The transport of the last search object made by api()
     * @var FakeTransport
     */
    private $transport;

    /**
     * The OpenAPI file, decoded
     * @var array|null
     */
    private static $spec;

    /**
     * The request generated from the spec, and what can be broken in it
     * @var array|null
     */
    private static $request;

    /**
     * The response generated from the spec, and what can be broken in it
     * @var array|null
     */
    private static $response;

    /**
     * The OpenAPI file, decoded
     * @return array
     */
    private static function spec(): array
    {
        if (self::$spec === null) {
            self::$spec = json_decode(file_get_contents(__DIR__ . '/../../../schemas/locations.json'), true);
        }

        return self::$spec;
    }

    /**
     * The components of the OpenAPI file
     * @return array
     */
    private static function components(): array
    {
        return self::spec()['components']['schemas'];
    }

    /**
     * The request generated from the spec
     * @return array [the request, what can be broken in it]
     */
    private static function generatedRequest(): array
    {
        if (self::$request === null) {
            $meta = [];
            self::$request = [Spec::generate(self::components(), 'Full_Schema_Find_Location', $meta), $meta];
        }

        return self::$request;
    }

    /**
     * The response generated from the spec
     * @return array [the response, what can be broken in it]
     */
    private static function generatedResponse(): array
    {
        if (self::$response === null) {
            $meta = [];
            self::$response = [Spec::generate(self::components(), 'LoccResponseVO', $meta), $meta];
        }

        return self::$response;
    }

    /**
     * A locations search over a fake transport
     * @param string $body Body of the response of the transport
     * @param int $status HTTP status of the response
     * @return FedexLocationsSearch
     */
    private function api(string $body = self::OK_RESPONSE, int $status = 200): FedexLocationsSearch
    {
        $this->transport = new FakeTransport([], Responses::make($status, $body));
        $auth = StubAuth::make();

        return new ExposedLocations($auth, 'en_US', 30, 10, new FedexClient($auth, 'en_US', 30, 10, $this->transport));
    }

    /**
     * A search whose transport answers with a response made from an array
     * @param array $data The response
     * @return FedexLocationsSearch
     */
    private function respond(array $data): FedexLocationsSearch
    {
        return $this->api(json_encode($data));
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
     * Search with a new object and say what happened
     * @param mixed $request The request
     * @return array The exception (null if none) and the number of requests that were sent
     */
    private function outcome($request): array
    {
        $api = $this->api();
        $e = self::thrown(function () use ($api, $request) {
            $api->search($request);
        });

        return [$e, count($this->transport->calls)];
    }

    /**
     * Search by address with a response that is given, and give the exception
     * @param FedexLocationsSearch $api The search object
     * @return \Throwable|null
     */
    private static function searchFails(FedexLocationsSearch $api): ?\Throwable
    {
        return self::thrown(function () use ($api) {
            $api->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US']);
        });
    }

    /**
     * Describe an exception, for the message of a failed case
     * @param \Throwable|null $e The exception
     * @param string $none What to say when there is none
     * @return string
     */
    private static function describe(?\Throwable $e, string $none): string
    {
        if ($e === null) {
            return $none;
        }

        return get_class($e) . ' ' . $e->getCode() . ' ' . $e->getMessage() . ($e instanceof FedexException ? ' ' . implode(' | ', array_map('strval', $e->getErrors())) : '');
    }

    /**
     * Is this a request that was refused with the code and nothing sent?
     * @param \Throwable|null $e The exception
     * @param int $sent How many requests were sent
     * @param int $code The code that the exception has to have
     * @return bool
     */
    private static function refused(?\Throwable $e, int $sent, int $code = FedexException::EC_BAD_PARAMETER): bool
    {
        return ($e instanceof FedexLocationsSearchException) && ($e->getCode() === $code) && ($sent === 0);
    }

    // ---------------------------------------------------------------- 1. the definitions against the spec

    /**
     * The definitions are the ones of the spec
     * @return void
     */
    public function testTheDefinitionsAreTheOnesOfTheSpec(): void
    {
        $defs = $this->api()->definitions();
        $skip = ['ErrorResponseVO', 'CXSError', 'Parameter', 'ErrorResponseVO401', 'CXSError401', 'ErrorResponseVO403', 'CXSError403', 'ErrorResponseVO404', 'CXSError404', 'ErrorResponseVO500', 'CXSError500', 'ErrorResponseVO503', 'CXSError503'];
        $res = [];

        foreach (self::components() as $name => $c) {
            if (in_array($name, $skip, true)) {
                $res["$name is deliberately not defined"] = !isset($defs[$name]) ? true : 'defined';
                continue;
            }

            if (!isset($defs[$name])) {
                $res["$name defined"] = 'missing';
                continue;
            }

            if (isset($c['$ref'])) {
                $res["$name is an alias of " . basename($c['$ref'])] = ($defs[$name] === '@' . basename($c['$ref'])) ? true : json_encode($defs[$name]);
                continue;
            }

            $d = $defs[$name];
            $specKeys = array_keys($c['properties'] ?? []);
            $defKeys = array_map(function ($k) {
                return rtrim($k, '?');
            }, array_keys($d));
            sort($specKeys);
            sort($defKeys);
            $res["$name: same properties"] = ($specKeys === $defKeys) ? true : 'spec ' . json_encode(array_values(array_diff($specKeys, $defKeys))) . ' / class ' . json_encode(array_values(array_diff($defKeys, $specKeys)));

            $reqSpec = $c['required'] ?? [];
            $reqDef = [];

            foreach (array_keys($d) as $k) {
                if (substr($k, -1) !== '?') {
                    $reqDef[] = $k;
                }
            }

            sort($reqSpec);
            sort($reqDef);
            $res["$name: same required keys"] = ($reqSpec === $reqDef) ? true : 'spec ' . json_encode($reqSpec) . ' / class ' . json_encode($reqDef);

            foreach ($c['properties'] ?? [] as $pn => $p) {
                $node = $d[$pn] ?? ($d[$pn . '?'] ?? null);
                $ref = Spec::refOf($p);

                if ($ref !== null) {
                    $ok = ($node === '@' . $ref);
                } elseif (($p['type'] ?? '') === 'array') {
                    $itemRef = Spec::refOf($p['items'] ?? []);

                    if ($itemRef !== null) {
                        $ok = ($node === ['@' . $itemRef]);
                    } elseif (isset($p['items']['enum'])) {
                        $ok = (is_array($node) && isset($node[0]['@enum']) && $node[0]['@enum'] === $p['items']['enum'] && $node[0]['@type'] === 'string');
                    } else {
                        $ok = ($node === ['string']);
                    }
                } else {
                    $type = ['string' => 'string', 'integer' => 'int', 'number' => 'number', 'boolean' => 'bool'][$p['type']];

                    if (isset($p['enum'])) {
                        $ok = (is_array($node) && $node['@type'] === $type && $node['@enum'] === $p['enum']);
                    } elseif (($p['format'] ?? '') === 'HH:MM:SS') {
                        $ok = (is_array($node) && $node['@type'] === 'string' && isset($node['@pattern']));
                    } else {
                        $ok = ($node === $type);
                    }
                }

                $res["$name.$pn: type, enum and reference"] = $ok ? true : json_encode($node);
            }
        }

        $this->assertAll('every component of the spec, property by property (names, required keys, types, enums, references)', $res);
    }

    // ---------------------------------------------------------------- 2. requests generated from the spec

    /**
     * The generated request fills every field of the spec
     * @return void
     */
    public function testTheGeneratedRequestFillsEveryFieldOfTheSpec(): void
    {
        [, $meta] = self::generatedRequest();

        $this->assertGreaterThanOrEqual(50, count($meta['leaves']));
        $this->assertGreaterThanOrEqual(14, count($meta['objects']));
    }

    /**
     * The generated request is accepted and sent unchanged
     * @return void
     */
    public function testTheGeneratedRequestIsAcceptedAndSentUnchanged(): void
    {
        [$request] = self::generatedRequest();
        $api = $this->api();

        $api->search($request);

        $this->assertSame(Spec::normalize($request), Spec::normalize($this->transport->bodyOf(0)), 'nothing lost and nothing added');
    }

    /**
     * The same request as nested objects is the same
     * @return void
     */
    public function testTheSameRequestAsNestedObjectsIsTheSame(): void
    {
        [$request] = self::generatedRequest();
        $api = $this->api();

        $api->search(json_decode(json_encode($request)));

        $this->assertSame(Spec::normalize($request), Spec::normalize($this->transport->bodyOf(0)));
    }

    /**
     * A wrong type in any leaf of the request is rejected
     * @return void
     */
    public function testAWrongTypeInAnyLeafOfTheRequestIsRejected(): void
    {
        [$request, $meta] = self::generatedRequest();
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
            $bad = ($leaf['type'] === 'string') ? 12345 : 'x';
            [$e, $sent] = $this->outcome(Spec::setAt($request, $leaf['path'], $bad));
            $res['wrong type at ' . Spec::pathName($leaf['path'])] = self::refused($e, $sent) ? true : self::describe($e, 'accepted');
        }

        $this->assertAll('a wrong type in any leaf field of the request', $res);
    }

    /**
     * A value outside an enum in the request is rejected
     * @return void
     */
    public function testAValueOutsideAnEnumInTheRequestIsRejected(): void
    {
        [$request, $meta] = self::generatedRequest();
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['enum']) {
                continue;
            }

            [$e, $sent] = $this->outcome(Spec::setAt($request, $leaf['path'], 'NOT_A_VALUE'));
            $res['bad enum value at ' . Spec::pathName($leaf['path'])] = (self::refused($e, $sent) && strpos(implode(' ', $e->getErrors()), 'must be one of') !== false) ? true : self::describe($e, 'accepted');
        }

        $this->assertAll('a value outside the enum of the spec', $res);
    }

    /**
     * An unknown key in any object of the request is rejected
     * @return void
     */
    public function testAnUnknownKeyInAnyObjectOfTheRequestIsRejected(): void
    {
        [$request, $meta] = self::generatedRequest();
        $res = [];

        foreach ($meta['objects'] as $o) {
            $mutated = $o['path'] ? Spec::setAt($request, $o['path'], array_merge(Spec::getAt($request, $o['path']), ['maxresults' => 5])) : array_merge($request, ['maxresults' => 5]);
            [$e, $sent] = $this->outcome($mutated);
            $res['unknown key in ' . ($o['path'] ? Spec::pathName($o['path']) : 'the request') . ' (' . $o['name'] . ')'] = (self::refused($e, $sent) && strpos(implode(' ', $e->getErrors()), 'maxresults: unexpected key') !== false) ? true : self::describe($e, 'accepted');
        }

        $this->assertAll('an unknown key in every object of the request, root and nested', $res);
    }

    /**
     * Every required key of the spec is enforced in the request
     * @return void
     */
    public function testEveryRequiredKeyOfTheSpecIsEnforcedInTheRequest(): void
    {
        [$request, $meta] = self::generatedRequest();
        $res = [];

        foreach ($meta['objects'] as $o) {
            foreach ($o['required'] as $key) {
                $path = array_merge($o['path'], [$key]);
                [$e, $sent] = $this->outcome(Spec::unsetAt($request, $path));
                $res['missing ' . Spec::pathName($path)] = (self::refused($e, $sent) && strpos(implode(' ', $e->getErrors()), 'required key is missing') !== false) ? true : self::describe($e, 'accepted');
            }
        }

        $this->assertAll('each required key of the request', $res);
    }

    /**
     * Every optional field of the request can be left out
     * @return void
     */
    public function testEveryOptionalFieldOfTheRequestCanBeLeftOut(): void
    {
        [$request, $meta] = self::generatedRequest();
        $res = [];

        foreach ($meta['objects'] as $o) {
            foreach ($o['keys'] as $key) {
                if (in_array($key, $o['required'], true)) {
                    continue;
                }

                $path = array_merge($o['path'], [$key]);
                [$e, $sent] = $this->outcome(Spec::unsetAt($request, $path));
                $res['without ' . Spec::pathName($path)] = ($e === null && $sent === 1) ? true : self::describe($e, 'not sent');
            }
        }

        $this->assertAll('each optional field of the request', $res);
    }

    // ---------------------------------------------------------------- 3. responses generated from the spec

    /**
     * The generated response fills every field of the spec
     * @return void
     */
    public function testTheGeneratedResponseFillsEveryFieldOfTheSpec(): void
    {
        [, $meta] = self::generatedResponse();

        $this->assertGreaterThan(150, count($meta['leaves']));
    }

    /**
     * The generated response is accepted and returned
     * @return void
     */
    public function testTheGeneratedResponseIsAcceptedAndReturned(): void
    {
        [$response] = self::generatedResponse();

        $out = $this->respond($response)->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US']);
        $detail = $out->output->locationDetailList[0];

        $this->assertInstanceOf(\stdClass::class, $out);
        $this->assertSame($response['output']['locationDetailList'][0]['contactAndAddress']['address']['countryCode'], $detail->contactAndAddress->address->countryCode);
        $this->assertTrue(property_exists($detail->contactAndAddress->contact, 'fedralTaxId'), 'the misspelled names of the spec are used as they are');
        $this->assertTrue(property_exists($detail->contactAndAddress->addressAncillaryDetail, 'addtionalDescriptions'));
        $this->assertTrue(property_exists($detail->storeHours[0], 'dayofweek'));
    }

    /**
     * A wrong type in any leaf of the response is rejected
     * @return void
     */
    public function testAWrongTypeInAnyLeafOfTheResponseIsRejected(): void
    {
        [$response, $meta] = self::generatedResponse();
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
            $bad = ($leaf['type'] === 'string') ? 12345 : 'x';
            $e = self::searchFails($this->respond(Spec::setAt($response, $leaf['path'], $bad)));
            $res['wrong type at ' . Spec::pathName($leaf['path'])] = ($e instanceof FedexLocationsSearchException && $e->getCode() === FedexException::EC_BAD_RESULT_RETURNED && count($e->getErrors()) >= 1) ? true : self::describe($e, 'accepted');
        }

        $this->assertAll('a wrong type in any leaf field of the response', $res);
    }

    /**
     * A value outside an enum in the response is rejected
     * @return void
     */
    public function testAValueOutsideAnEnumInTheResponseIsRejected(): void
    {
        [$response, $meta] = self::generatedResponse();
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
            if (!$leaf['enum']) {
                continue;
            }

            $e = self::searchFails($this->respond(Spec::setAt($response, $leaf['path'], 'NOT_A_VALUE')));
            $res['bad enum value at ' . Spec::pathName($leaf['path'])] = ($e instanceof FedexLocationsSearchException && $e->getCode() === FedexException::EC_BAD_RESULT_RETURNED && strpos(implode(' ', $e->getErrors()), 'must be one of') !== false) ? true : self::describe($e, 'accepted');
        }

        $this->assertAll('a value outside the enum of the spec in the response', $res);
    }

    /**
     * A field that the spec does not have is accepted in every object of the response
     * @return void
     */
    public function testAFieldThatTheSpecDoesNotHaveIsAcceptedInEveryObjectOfTheResponse(): void
    {
        [$response, $meta] = self::generatedResponse();
        $res = [];

        foreach ($meta['objects'] as $o) {
            $mutated = $o['path'] ? Spec::setAt($response, $o['path'], array_merge(Spec::getAt($response, $o['path']), ['newFieldFromFedEx' => 'x'])) : array_merge($response, ['newFieldFromFedEx' => 'x']);
            $e = self::searchFails($this->respond($mutated));
            $res['unknown key in ' . ($o['path'] ? Spec::pathName($o['path']) : 'the response')] = ($e === null) ? true : self::describe($e, '');
        }

        $this->assertAll('an unknown field in the response (FedEx can add fields)', $res);
    }

    /**
     * Every required key of the spec is enforced in the response
     * @return void
     */
    public function testEveryRequiredKeyOfTheSpecIsEnforcedInTheResponse(): void
    {
        [$response, $meta] = self::generatedResponse();
        $res = [];

        foreach ($meta['objects'] as $o) {
            foreach ($o['required'] as $key) {
                $path = array_merge($o['path'], [$key]);
                $e = self::searchFails($this->respond(Spec::unsetAt($response, $path)));
                $res['missing ' . Spec::pathName($path)] = ($e instanceof FedexLocationsSearchException && $e->getCode() === FedexException::EC_BAD_RESULT_RETURNED && strpos(implode(' ', $e->getErrors()), 'required key is missing') !== false) ? true : self::describe($e, 'accepted');
            }
        }

        $this->assertAll('each required key of the response', $res);
    }

    /**
     * Every optional field of the response can be absent
     * @return void
     */
    public function testEveryOptionalFieldOfTheResponseCanBeAbsent(): void
    {
        [$response, $meta] = self::generatedResponse();
        $res = [];

        foreach ($meta['objects'] as $o) {
            foreach ($o['keys'] as $key) {
                if (in_array($key, $o['required'], true)) {
                    continue;
                }

                $path = array_merge($o['path'], [$key]);
                $e = self::searchFails($this->respond(Spec::unsetAt($response, $path)));
                $res['without ' . Spec::pathName($path)] = ($e === null) ? true : self::describe($e, '');
            }
        }

        $this->assertAll('each optional field of the response', $res);
    }

    /**
     * @dataProvider acceptedResponsesProvider
     * @param string $json A response body
     */
    public function testSomeResponsesAreAccepted(string $json): void
    {
        $this->assertNull(self::searchFails($this->api($json)));
    }

    /**
     * The cases of testSomeResponsesAreAccepted(): some responses are accepted
     * @return array
     */
    public function acceptedResponsesProvider(): array
    {
        return [
            'an empty object' => ['{}'],
            'only a transaction id' => ['{"transactionId":"T"}'],
            'an empty output' => ['{"output":{}}'],
        ];
    }

    /**
     * @dataProvider rejectedResponsesProvider
     * @param string $json A response body
     */
    public function testSomeResponsesAreRejectedWithCode101(string $json): void
    {
        $e = self::searchFails($this->api($json));

        $this->assertInstanceOf(FedexLocationsSearchException::class, $e, self::describe($e, 'accepted'));
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
    }

    /**
     * The cases of testSomeResponsesAreRejectedWithCode101(): some responses are rejected with code 101
     * @return array
     */
    public function rejectedResponsesProvider(): array
    {
        return [
            'a JSON list' => ['[1,2]'],
            'a JSON string' => ['"text"'],
            'an empty body (returned as null by the client)' => [''],
            'output that is not an object' => ['{"output":"x"}'],
            'a list that is an object' => ['{"output":{"locationDetailList":{}}}'],
            'a numeric transaction id' => ['{"transactionId":5}'],
        ];
    }

    // ---------------------------------------------------------------- 4. the rules of each criterion

    /**
     * A request for a location
     * @param array $location The location
     * @param array $extra Other fields of the request
     * @return array
     */
    private static function reqOf(array $location, array $extra = []): array
    {
        return array_merge(['location' => $location], $extra);
    }

    /**
     * @dataProvider criterionRulesProvider
     * @param array $request The request
     * @param int|null $code The code of the exception that has to be thrown, null when the request is accepted
     */
    public function testTheRulesOfTheCriteriaCountryCodeAndStreetLines(array $request, ?int $code): void
    {
        [$e, $sent] = $this->outcome($request);

        if ($code === null) {
            $this->assertNull($e, self::describe($e, ''));
            $this->assertSame(1, $sent, 'it had to be sent');
        } else {
            $this->assertTrue(self::refused($e, $sent, $code), self::describe($e, 'it was accepted'));
        }
    }

    /**
     * The cases of testTheRulesOfTheCriteriaCountryCodeAndStreetLines(): the rules of the criteria country code and street lines
     * @return array
     */
    public function criterionRulesProvider(): array
    {
        $missing = FedexException::EC_MISSING_PARAMETER;
        $bad = FedexException::EC_BAD_PARAMETER;
        $coordinates = 'GEOGRAPHIC_COORDINATES';

        return [
            'ADDRESS (default) with a postal code' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '38017']]), null],
            'ADDRESS (default) with a city' => [self::reqOf(['address' => ['countryCode' => 'US', 'city' => 'Memphis']]), null],
            'ADDRESS (explicit) with a city and a postal code' => [self::reqOf(['address' => ['countryCode' => 'US', 'city' => 'Memphis', 'postalCode' => '38017']], ['locationSearchCriterion' => 'ADDRESS']), null],
            'ADDRESS (default) with neither city nor postal code' => [self::reqOf(['address' => ['countryCode' => 'US']]), $missing],
            'ADDRESS (explicit) with neither' => [self::reqOf(['address' => ['countryCode' => 'US']], ['locationSearchCriterion' => 'ADDRESS']), $missing],
            'ADDRESS with blank city and blank postal code' => [self::reqOf(['address' => ['countryCode' => 'US', 'city' => '   ', 'postalCode' => '']]), $missing],
            'ADDRESS with a street only' => [self::reqOf(['address' => ['countryCode' => 'US', 'streetLines' => ['10 FedEx Parkway']]]), $missing],
            'COORDINATES with latitude and longitude, no city or postal code' => [self::reqOf(['address' => ['countryCode' => 'US'], 'longLat' => ['latitude' => '36.1699', 'longitude' => '-115.1398']], ['locationSearchCriterion' => $coordinates]), null],
            'COORDINATES without longLat' => [self::reqOf(['address' => ['countryCode' => 'US']], ['locationSearchCriterion' => $coordinates]), $missing],
            'COORDINATES with latitude only' => [self::reqOf(['address' => ['countryCode' => 'US'], 'longLat' => ['latitude' => '36.1699']], ['locationSearchCriterion' => $coordinates]), $missing],
            'COORDINATES with longitude only' => [self::reqOf(['address' => ['countryCode' => 'US'], 'longLat' => ['longitude' => '-115.1']], ['locationSearchCriterion' => $coordinates]), $missing],
            'COORDINATES with a postal code but no coordinates' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '38017']], ['locationSearchCriterion' => $coordinates]), $missing],
            'PHONE_NUMBER with a phone number, no city or postal code' => [self::reqOf(['address' => ['countryCode' => 'US']], ['locationSearchCriterion' => 'PHONE_NUMBER', 'phoneNumber' => '9015551234']), null],
            'PHONE_NUMBER without a phone number' => [self::reqOf(['address' => ['countryCode' => 'US']], ['locationSearchCriterion' => 'PHONE_NUMBER']), $missing],
            'PHONE_NUMBER with a blank phone number' => [self::reqOf(['address' => ['countryCode' => 'US']], ['locationSearchCriterion' => 'PHONE_NUMBER', 'phoneNumber' => '  ']), $missing],
            'PHONE_NUMBER with a postal code but no phone number' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '38017']], ['locationSearchCriterion' => 'PHONE_NUMBER']), $missing],
            'phone number with the ADDRESS criterion is not used and not required' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '38017']], ['phoneNumber' => '9015551234']), null],
            'criterion not in the list' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '38017']], ['locationSearchCriterion' => 'address']), $bad],
            'no location' => [['locationSearchCriterion' => 'ADDRESS'], $bad],
            'no address' => [self::reqOf(['longLat' => ['latitude' => '1', 'longitude' => '2']]), $bad],
            'no country code' => [self::reqOf(['address' => ['postalCode' => '38017']]), $bad],
            'country code "US"' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '1']]), null],
            'country code "USA"' => [self::reqOf(['address' => ['countryCode' => 'USA', 'postalCode' => '1']]), null],
            'country code "840" (numeric)' => [self::reqOf(['address' => ['countryCode' => '840', 'postalCode' => '1']]), null],
            'country name of 5 characters' => [self::reqOf(['address' => ['countryCode' => 'Japan', 'postalCode' => '1']]), null],
            'country code of 6 characters' => [self::reqOf(['address' => ['countryCode' => 'Brazil', 'postalCode' => '1']]), $bad],
            'blank country code' => [self::reqOf(['address' => ['countryCode' => '  ', 'postalCode' => '1']]), $bad],
            'empty country code' => [self::reqOf(['address' => ['countryCode' => '', 'postalCode' => '1']]), $bad],
            'street lines, two' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '1', 'streetLines' => ['10 FedEx Parkway', 'Suite 302']]]), null],
            'street lines, five (the API ignores more than three)' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '1', 'streetLines' => ['a', 'b', 'c', 'd', 'e']]]), null],
            'an empty street line' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '1', 'streetLines' => ['10 FedEx Parkway', '']]]), $bad],
            'a blank street line' => [self::reqOf(['address' => ['countryCode' => 'US', 'postalCode' => '1', 'streetLines' => ['  ']]]), $bad],
        ];
    }

    /**
     * @dataProvider fieldsProvider
     * @param array $extra Fields added to a valid request
     * @param bool $accepted Is the request accepted?
     */
    public function testFieldByFieldTypesEnumsAndThePattern(array $extra, bool $accepted): void
    {
        $base = ['location' => ['address' => ['countryCode' => 'US', 'postalCode' => '38017']]];

        [$e, $sent] = $this->outcome(array_replace_recursive($base, $extra));

        if ($accepted) {
            $this->assertNull($e, self::describe($e, ''));
            $this->assertSame(1, $sent, 'it had to be sent');
        } else {
            $this->assertTrue(self::refused($e, $sent), self::describe($e, 'it was accepted'));
        }
    }

    /**
     * The cases of testFieldByFieldTypesEnumsAndThePattern(): field by field types enums and the pattern
     * @return array
     */
    public function fieldsProvider(): array
    {
        $time = function (string $value) {
            return ['constraints' => ['dropOffTimeNeeded' => $value]];
        };

        return [
            'dropOffTimeNeeded 16:30:00' => [$time('16:30:00'), true],
            'dropOffTimeNeeded 00:00:00' => [$time('00:00:00'), true],
            'dropOffTimeNeeded 23:59:59' => [$time('23:59:59'), true],
            'dropOffTimeNeeded 24:00:00' => [$time('24:00:00'), false],
            'dropOffTimeNeeded 7:30:00' => [$time('7:30:00'), false],
            'dropOffTimeNeeded 16:30' => [$time('16:30'), false],
            'dropOffTimeNeeded 16:60:00' => [$time('16:60:00'), false],
            'dropOffTimeNeeded 16:30:00Z' => [$time('16:30:00Z'), false],
            'dropOffTimeNeeded with a leading space' => [$time(' 16:30:00'), false],
            'dropOffTimeNeeded with a trailing newline' => [$time("16:30:00\n"), false],
            'maxResults 5' => [['locationsSummaryRequestControlParameters' => ['maxResults' => 5]], true],
            'maxResults "5" (a string)' => [['locationsSummaryRequestControlParameters' => ['maxResults' => '5']], false],
            'maxResults 5.0 (a float)' => [['locationsSummaryRequestControlParameters' => ['maxResults' => 5.0]], false],
            'distance 3.5 MI' => [['locationsSummaryRequestControlParameters' => ['distance' => ['units' => 'MI', 'value' => 3.5]]], true],
            'distance 10 KM (an integer is a number)' => [['locationsSummaryRequestControlParameters' => ['distance' => ['units' => 'KM', 'value' => 10]]], true],
            'distance units in lower case' => [['locationsSummaryRequestControlParameters' => ['distance' => ['units' => 'km']]], false],
            'distance value as a string' => [['locationsSummaryRequestControlParameters' => ['distance' => ['value' => '3.5']]], false],
            'sameState true' => [['sameState' => true], true],
            'sameState false' => [['sameState' => false], true],
            'sameState "true" (a string)' => [['sameState' => 'true'], false],
            'sameState 1' => [['sameState' => 1], false],
            'excludeUnavailableLocations "true" (a string, as in the spec)' => [['constraints' => ['excludeUnavailableLocations' => 'true']], true],
            'excludeUnavailableLocations true (a boolean)' => [['constraints' => ['excludeUnavailableLocations' => true]], false],
            'carrierCodes FDXE and FDXG' => [['carrierCodes' => ['FDXE', 'FDXG']], true],
            'carrierCodes with FXSP (not in this spec)' => [['carrierCodes' => ['FXSP']], false],
            'carrierCodes as a string' => [['carrierCodes' => 'FDXE'], false],
            'carrierCodes in lower case' => [['carrierCodes' => ['fdxe']], false],
            'locationTypes two valid' => [['locationTypes' => ['FEDEX_OFFICE', 'FEDEX_ONSITE']], true],
            'locationTypes with a response-only type (FEDEX_STAFFED)' => [['locationTypes' => ['FEDEX_STAFFED']], false],
            'locationAttrTypes with DROP_BOX' => [['locationAttrTypes' => ['DROP_BOX', 'PACK_AND_SHIP']], true],
            'locationAttrTypes with a response-only attribute' => [['locationAttrTypes' => ['SATURDAY_DROPOFFS']], false],
            'sort DISTANCE ASCENDING' => [['sort' => ['criteria' => 'DISTANCE', 'order' => 'ASCENDING']], true],
            'sort order DOWN' => [['sort' => ['order' => 'DOWN']], false],
            'multipleMatchesAction RETURN_FIRST' => [['multipleMatchesAction' => 'RETURN_FIRST'], true],
            'multipleMatchesAction RETURN_NONE' => [['multipleMatchesAction' => 'RETURN_NONE'], false],
            'packageAttributes with weight, dimensions and options' => [['packageAttributes' => [['weight' => ['units' => 'LB', 'value' => 10.5], 'dimensions' => ['length' => 20, 'width' => 10, 'height' => 5, 'units' => 'IN'], 'serviceOptions' => ['DRY_ICE']]]], true],
            'packageAttributes dimension as a float' => [['packageAttributes' => [['dimensions' => ['length' => 20.5]]]], false],
            'locationCapabilities one item' => [['locationCapabilities' => [['carrierCode' => 'FDXE', 'serviceType' => 'PRIORITY_OVERNIGHT', 'transferOfPossessionType' => 'DROPOFF', 'serviceCategory' => 'EXPRESS_PARCEL', 'daysOfWeek' => ['MON', 'TUE']]]], true],
            'locationCapabilities item that is a string' => [['locationCapabilities' => ['FDXE']], false],
            'totalDeclaredValue 100 USD' => [['totalDeclaredValue' => ['amount' => 100, 'currency' => 'USD']], true],
            'totalCustomsValue amount as a string' => [['totalCustomsValue' => ['amount' => '100']], false],
            'paymentType SENDER' => [['paymentType' => 'SENDER'], true],
            'paymentType sender' => [['paymentType' => 'sender'], false],
            'trackingInfo with the three fields' => [['trackingInfo' => ['uniqueTrackingId' => '789645', 'trackingNumber' => '123456789012', 'shipDate' => '2030-01-15']], true],
            'getCall false' => [['getCall' => false], true],
            'includeHoliday true' => [['includeHoliday' => true], true],
            'dropoffTime and dropOffServiceType' => [['dropoffTime' => '09:30:00', 'dropOffServiceType' => 'GROUND'], true],
            'locationContentOptions valid' => [['constraints' => ['locationContentOptions' => ['HOLIDAYS', 'TIMEZONE_OFFSET']]], true],
            'locationContentOptions invalid' => [['constraints' => ['locationContentOptions' => ['WEATHER']]], false],
            'address residential true and classification' => [['location' => ['address' => ['residential' => true, 'addressClassification' => 'RESIDENTIAL', 'addressClassificationConfidence' => 'PERMANENT']]], true],
            'address classification in lower case' => [['location' => ['address' => ['addressClassification' => 'residential']]], false],
            'address residential as a string' => [['location' => ['address' => ['residential' => 'yes']]], false],
        ];
    }

    /**
     * False and zero are sent
     * @return void
     */
    public function testFalseAndZeroAreSent(): void
    {
        $api = $this->api();

        $api->search(['location' => ['address' => ['countryCode' => 'US', 'postalCode' => '38017']], 'sameState' => false, 'includeHoliday' => false, 'locationsSummaryRequestControlParameters' => ['maxResults' => 0, 'distance' => ['value' => 0]]]);
        $body = $this->transport->bodyOf(0);

        $this->assertFalse($body['sameState']);
        $this->assertFalse($body['includeHoliday']);
        $this->assertSame(0, $body['locationsSummaryRequestControlParameters']['maxResults']);
        $this->assertSame(0, $body['locationsSummaryRequestControlParameters']['distance']['value']);
    }

    /**
     * Nulls empty lists and objects that end up empty are left out
     * @return void
     */
    public function testNullsEmptyListsAndObjectsThatEndUpEmptyAreLeftOut(): void
    {
        $base = ['location' => ['address' => ['countryCode' => 'US', 'postalCode' => '38017']]];
        $api = $this->api();

        $api->search(array_replace_recursive($base, ['phoneNumber' => null, 'sort' => ['criteria' => null], 'carrierCodes' => [], 'locationTypes' => [null], 'constraints' => ['locationContentOptions' => []], 'locationsSummaryRequestControlParameters' => ['distance' => ['units' => null, 'value' => null]]]));

        $this->assertSame($base, $this->transport->bodyOf(0));
    }

    /**
     * Null entries are removed from a list and it stays a JSON list
     * @return void
     */
    public function testNullEntriesAreRemovedFromAListAndItStaysAJsonList(): void
    {
        $api = $this->api();

        $api->search(['location' => ['address' => ['countryCode' => 'US', 'postalCode' => '38017', 'streetLines' => ['a', null, 'b', null]]]]);

        $this->assertStringContainsString('"streetLines":["a","b"]', $this->transport->calls[0]['body']);
    }

    /**
     * @dataProvider notARequestProvider
     * @param mixed $value Something that is not a request
     */
    public function testARequestThatIsNotAnArrayOrAnObjectIsRejectedBeforeAnythingIsSent($value): void
    {
        [$e, $sent] = $this->outcome($value);

        $this->assertTrue(self::refused($e, $sent));
        $this->assertSame('The request must be an array or an object', $e->getMessage());
    }

    /**
     * The cases of testARequestThatIsNotAnArrayOrAnObjectIsRejectedBeforeAnythingIsSent(): a request that is not an array or an object is rejected before anything is sent
     * @return array
     */
    public function notARequestProvider(): array
    {
        return ['a string' => ['text'], 'an integer' => [5], 'null' => [null], 'a boolean' => [true]];
    }

    /**
     * An empty request is rejected
     * @return void
     */
    public function testAnEmptyRequestIsRejected(): void
    {
        [$e] = $this->outcome([]);

        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertStringContainsString('$.location: required key is missing', $e->getErrorsAsString());
    }

    /**
     * The errors name each problem and carry no request data
     * @return void
     */
    public function testTheErrorsNameEachProblemAndCarryNoRequestData(): void
    {
        [$e] = $this->outcome(['maxresults' => 5, 'location' => ['address' => ['countryCode' => 'US', 'postalCode' => '1', 'zip' => '2']]]);

        $this->assertCount(2, $e->getErrors());
        $this->assertStringContainsString('$.maxresults: unexpected key', $e->getErrorsAsString());
        $this->assertStringContainsString('$.location.address.zip: unexpected key', $e->getErrorsAsString());
        $this->assertNull($e->getData(), 'the request may hold personal data: it is not kept in the exception');
    }

    // ---------------------------------------------------------------- 5. the helpers

    /**
     * Search by address sends the criterion the address and the options
     * @return void
     */
    public function testSearchByAddressSendsTheCriterionTheAddressAndTheOptions(): void
    {
        $api = $this->api();

        $api->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US'], ['locationsSummaryRequestControlParameters' => ['maxResults' => 5], 'locationTypes' => ['FEDEX_OFFICE']]);

        $this->assertSame(['locationsSummaryRequestControlParameters' => ['maxResults' => 5], 'locationTypes' => ['FEDEX_OFFICE'], 'locationSearchCriterion' => 'ADDRESS', 'location' => ['address' => ['postalCode' => '38017', 'countryCode' => 'US']]], $this->transport->bodyOf(0));
    }

    /**
     * @dataProvider reservedByAddressProvider
     * @param string $field An option that searchByAddress() sets itself
     * @param array $option The option
     */
    public function testSearchByAddressRefusesTheOptionsThatItSetsItself(string $field, array $option): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $option) {
            $api->searchByAddress(['postalCode' => '1', 'countryCode' => 'US'], $option);
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)));
        $this->assertStringContainsString("'$field'", $e->getMessage());
        $this->assertStringContainsString('searchByAddress()', $e->getMessage());
    }

    /**
     * The cases of testSearchByAddressRefusesTheOptionsThatItSetsItself(): search by address refuses the options that it sets itself
     * @return array
     */
    public function reservedByAddressProvider(): array
    {
        return ['location' => ['location', ['location' => []]], 'locationSearchCriterion' => ['locationSearchCriterion', ['locationSearchCriterion' => 'ADDRESS']]];
    }

    /**
     * @dataProvider badAddressesProvider
     * @param array $address An address that is not valid
     * @param int $code The code of the exception
     */
    public function testSearchByAddressRefusesABadAddress(array $address, int $code): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $address) {
            $api->searchByAddress($address);
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls), $code), self::describe($e, 'accepted'));
    }

    /**
     * The cases of testSearchByAddressRefusesABadAddress(): search by address refuses a bad address
     * @return array
     */
    public function badAddressesProvider(): array
    {
        return [
            'without city or postal code' => [['countryCode' => 'US'], FedexException::EC_MISSING_PARAMETER],
            'without a country code' => [['postalCode' => '38017'], FedexException::EC_BAD_PARAMETER],
            'with an unknown field' => [['postalCode' => '38017', 'countryCode' => 'US', 'zip' => 'x'], FedexException::EC_BAD_PARAMETER],
        ];
    }

    /**
     * Search by coordinates sends the coordinates as strings
     * @return void
     */
    public function testSearchByCoordinatesSendsTheCoordinatesAsStrings(): void
    {
        $api = $this->api();

        $api->searchByCoordinates(36.1699, -115.1398, 'US');

        $this->assertSame(['locationSearchCriterion' => 'GEOGRAPHIC_COORDINATES', 'location' => ['address' => ['countryCode' => 'US'], 'longLat' => ['latitude' => '36.1699', 'longitude' => '-115.1398']]], $this->transport->bodyOf(0));
    }

    /**
     * @dataProvider coordinatesProvider
     * @param mixed $given The number given
     * @param string $expected The string that is sent
     */
    public function testTheConversionOfNumbersToStrings($given, string $expected): void
    {
        $api = $this->api();

        $api->searchByCoordinates($given, $given, 'US');
        $longLat = $this->transport->bodyOf(0)['location']['longLat'];

        $this->assertSame($expected, $longLat['latitude']);
        $this->assertSame($expected, $longLat['longitude']);
    }

    /**
     * The cases of testTheConversionOfNumbersToStrings(): the conversion of numbers to strings
     * @return array
     */
    public function coordinatesProvider(): array
    {
        return [
            'integer 5' => [5, '5'],
            'integer -3' => [-3, '-3'],
            'integer 10' => [10, '10'],
            'integer 0' => [0, '0'],
            'float 0.5' => [0.5, '0.5'],
            'float -0.25' => [-0.25, '-0.25'],
            'float 12.0' => [12.0, '12'],
            'float 100.10' => [100.10, '100.1'],
            'float 0.1 + 0.2' => [0.1 + 0.2, '0.3'],
            'float with many decimals' => [36.16990001, '36.16990001'],
            'string "12.5"' => ['12.5', '12.5'],
            'string " 12.5 "' => [' 12.5 ', '12.5'],
            'string "-7"' => ['-7', '-7'],
            'string "1e2"' => ['1e2', '1e2'],
            'the largest integer' => [PHP_INT_MAX, '9223372036854775807'],
        ];
    }

    /**
     * @dataProvider notNumbersProvider
     * @param mixed $given Something that is not a number
     */
    public function testWhatIsNotANumberIsRefused($given): void
    {
        $api = $this->api();
        $e = self::thrown(function () use ($api, $given) {
            $api->searchByCoordinates($given, 1.5, 'US');
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)), self::describe($e, 'accepted'));
        $this->assertSame('The latitude must be a number', $e->getMessage());

        $api = $this->api();
        $e = self::thrown(function () use ($api, $given) {
            $api->searchByCoordinates(1.5, $given, 'US');
        });

        $this->assertSame('The longitude must be a number', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testWhatIsNotANumberIsRefused(): what is not a number is refused
     * @return array
     */
    public function notNumbersProvider(): array
    {
        return [
            'a word' => ['abc'],
            'null' => [null],
            'a boolean' => [true],
            'an array' => [[]],
            'NAN' => [NAN],
            'INF' => [INF],
            'an empty string' => [''],
            'a comma decimal' => ['12,5'],
            'degrees text' => ['36.17° N'],
        ];
    }

    /**
     * The conversion does not depend on the locale
     * @return void
     */
    public function testTheConversionDoesNotDependOnTheLocale(): void
    {
        $locale = null;

        foreach (['de_DE.UTF-8', 'de_DE', 'German_Germany.1252', 'German', 'pt_PT.UTF-8', 'Portuguese_Portugal.1252', 'fr_FR.UTF-8', 'French_France.1252'] as $candidate) {
            if (@setlocale(LC_ALL, $candidate)) {
                $locale = $candidate;
                break;
            }
        }

        if ($locale === null) {
            $this->markTestSkipped('no locale with a decimal comma is installed here');
        }

        try {
            $this->assertTrue(PHP_VERSION_ID >= 80000 || strpos((string) 1.5, ',') !== false, 'under this locale PHP writes a comma, so the test is meaningful');
            $api = $this->api();

            $api->searchByCoordinates(36.1699, -115.1398, 'US');
            $longLat = $this->transport->bodyOf(0)['location']['longLat'];

            $this->assertSame('36.1699', $longLat['latitude']);
            $this->assertSame('-115.1398', $longLat['longitude']);
        } finally {
            setlocale(LC_ALL, 'C');
        }
    }

    /**
     * Search by coordinates merges the options and refuses location
     * @return void
     */
    public function testSearchByCoordinatesMergesTheOptionsAndRefusesLocation(): void
    {
        $api = $this->api();
        $api->searchByCoordinates(1, 2, 'US', ['sort' => ['criteria' => 'DISTANCE']]);
        $this->assertSame(['criteria' => 'DISTANCE'], $this->transport->bodyOf(0)['sort']);

        $api = $this->api();
        $e = self::thrown(function () use ($api) {
            $api->searchByCoordinates(1, 2, 'US', ['location' => []]);
        });

        $this->assertStringContainsString("'location'", $e->getMessage());
        $this->assertStringContainsString('searchByCoordinates()', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * Search by phone number
     * @return void
     */
    public function testSearchByPhoneNumber(): void
    {
        $api = $this->api();
        $api->searchByPhoneNumber('9015551234', 'US');
        $this->assertSame(['locationSearchCriterion' => 'PHONE_NUMBER', 'phoneNumber' => '9015551234', 'location' => ['address' => ['countryCode' => 'US']]], $this->transport->bodyOf(0));

        $api = $this->api();
        $api->searchByPhoneNumber('9015551234', 'US', ['locationsSummaryRequestControlParameters' => ['maxResults' => 3]]);
        $this->assertSame(['maxResults' => 3], $this->transport->bodyOf(0)['locationsSummaryRequestControlParameters']);
    }

    /**
     * @dataProvider reservedByPhoneProvider
     * @param string $field An option that searchByPhoneNumber() sets itself
     */
    public function testSearchByPhoneNumberRefusesTheOptionsThatItSetsItself(string $field): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $field) {
            $api->searchByPhoneNumber('9015551234', 'US', [$field => 'x']);
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)));
        $this->assertStringContainsString("'$field'", $e->getMessage());
    }

    /**
     * The cases of testSearchByPhoneNumberRefusesTheOptionsThatItSetsItself(): search by phone number refuses the options that it sets itself
     * @return array
     */
    public function reservedByPhoneProvider(): array
    {
        return [['phoneNumber'], ['location'], ['locationSearchCriterion']];
    }

    /**
     * @dataProvider blankProvider
     * @param string $blank A blank phone number
     */
    public function testSearchByPhoneNumberRefusesABlankPhoneNumber(string $blank): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $blank) {
            $api->searchByPhoneNumber($blank, 'US');
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls), FedexException::EC_MISSING_PARAMETER));
    }

    /**
     * The cases of testSearchByPhoneNumberRefusesABlankPhoneNumber(): search by phone number refuses a blank phone number
     * @return array
     */
    public function blankProvider(): array
    {
        return [[''], ['   ']];
    }

    /**
     * Search by phone number refuses an empty country code
     * @return void
     */
    public function testSearchByPhoneNumberRefusesAnEmptyCountryCode(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->searchByPhoneNumber('9015551234', '');
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)));
    }

    /**
     * An option that is not a field of the request is refused
     * @return void
     */
    public function testAnOptionThatIsNotAFieldOfTheRequestIsRefused(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->searchByPhoneNumber('9015551234', 'US', ['maxResults' => 3]);
        });

        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertStringContainsString('$.maxResults: unexpected key', $e->getErrorsAsString(), 'maxResults belongs inside locationsSummaryRequestControlParameters');
    }

    // ---------------------------------------------------------------- 6. newRequest()

    /**
     * Compare an empty structure with the spec
     * @param array $template The empty structure
     * @param string $component The component of the spec that it stands for
     * @param array $path Where the structure is
     * @param array $problems The differences found
     * @return void
     */
    private function compareShape(array $template, string $component, array $path, array &$problems): void
    {
        $C = self::components();
        $specKeys = array_keys($C[$component]['properties']);
        $templateKeys = array_keys($template);
        sort($specKeys);
        sort($templateKeys);

        if ($specKeys !== $templateKeys) {
            $problems[] = Spec::pathName($path) . ' keys differ: ' . json_encode(array_values(array_diff($specKeys, $templateKeys))) . ' / ' . json_encode(array_values(array_diff($templateKeys, $specKeys)));

            return;
        }

        foreach ($C[$component]['properties'] as $name => $property) {
            $ref = Spec::refOf($property);

            if ($ref !== null && isset($C[$ref]['properties'])) {
                if (!is_array($template[$name])) {
                    $problems[] = Spec::pathName(array_merge($path, [$name])) . ' should be an object';
                    continue;
                }

                $this->compareShape($template[$name], $ref, array_merge($path, [$name]), $problems);
            } elseif (($property['type'] ?? '') === 'array') {
                if ($template[$name] !== []) {
                    $problems[] = Spec::pathName(array_merge($path, [$name])) . ' should be an empty list';
                }
            } elseif ($template[$name] !== null) {
                $problems[] = Spec::pathName(array_merge($path, [$name])) . ' should be null';
            }
        }
    }

    /**
     * New request has every field of the spec at every level
     * @return void
     */
    public function testNewRequestHasEveryFieldOfTheSpecAtEveryLevel(): void
    {
        $problems = [];

        $this->compareShape(FedexLocationsSearch::newRequest(), 'Full_Schema_Find_Location', [], $problems);

        $this->assertSame([], array_slice($problems, 0, 5));
    }

    /**
     * New request leaves nothing after pruning and is fresh every time
     * @return void
     */
    public function testNewRequestLeavesNothingAfterPruningAndIsFreshEveryTime(): void
    {
        $this->assertNull(FedexUtils::pruneEmpty(FedexLocationsSearch::newRequest()));

        $request = FedexLocationsSearch::newRequest();
        $request['location']['address']['countryCode'] = 'XX';

        $this->assertNull(FedexLocationsSearch::newRequest()['location']['address']['countryCode']);
    }

    /**
     * The empty structure sent as it is is rejected for the missing location
     * @return void
     */
    public function testTheEmptyStructureSentAsItIsIsRejectedForTheMissingLocation(): void
    {
        [$e, $sent] = $this->outcome(FedexLocationsSearch::newRequest());

        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertStringContainsString('$.location: required key is missing', $e->getErrorsAsString());
        $this->assertSame(0, $sent);
    }

    /**
     * The example of the README
     * @return void
     */
    public function testTheExampleOfTheReadme(): void
    {
        $request = FedexLocationsSearch::newRequest();
        $request['location']['address']['postalCode'] = '38017';
        $request['location']['address']['countryCode'] = 'US';
        $request['sort']['criteria'] = 'DISTANCE';
        $request['locationTypes'][] = 'FEDEX_OFFICE';
        $request['locationCapabilities'][] = ['carrierCode' => 'FDXE', 'transferOfPossessionType' => 'DROPOFF'];
        $api = $this->api();

        $api->search($request);

        $this->assertSame(
            Spec::normalize(['location' => ['address' => ['postalCode' => '38017', 'countryCode' => 'US']], 'sort' => ['criteria' => 'DISTANCE'], 'locationTypes' => ['FEDEX_OFFICE'], 'locationCapabilities' => [['carrierCode' => 'FDXE', 'transferOfPossessionType' => 'DROPOFF']]]),
            Spec::normalize($this->transport->bodyOf(0))
        );
    }

    // ---------------------------------------------------------------- 7. on the wire, errors and construction

    /**
     * The request on the wire with the real authorization and client
     * @return void
     */
    public function testTheRequestOnTheWireWithTheRealAuthorizationAndClient(): void
    {
        $authTransport = new FakeTransport([Responses::token(['access_token' => 'LOCTOKEN'])]);
        $apiTransport = new FakeTransport([], Responses::make(200, '{"transactionId":"T9","customerTransactionId":"C9","output":{"totalResults":1,"resultsReturned":1}}'));
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $authTransport);
        $api = new FedexLocationsSearch($auth, 'pt_PT', 30, 10, new FedexClient($auth, 'pt_PT', 30, 10, $apiTransport));

        $res = $api->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US']);
        $call = $apiTransport->calls[0];

        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/location/v1/locations', $call['url']);
        $this->assertContains('Authorization: Bearer LOCTOKEN', $call['headers']);
        $this->assertContains('Accept: application/json', $call['headers']);
        $this->assertContains('X-locale: pt_PT', $call['headers']);
        $this->assertContains('Content-Type: application/json', $call['headers']);
        $this->assertNotEmpty(preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $call['headers']));
        $this->assertSame(['locationSearchCriterion' => 'ADDRESS', 'location' => ['address' => ['postalCode' => '38017', 'countryCode' => 'US']]], $apiTransport->bodyOf(0));
        $this->assertSame('T9', $res->transactionId);
        $this->assertSame('C9', $res->customerTransactionId);
        $this->assertSame(1, $res->output->totalResults);

        $api->searchByPhoneNumber('9015551234', 'US');

        $this->assertCount(1, $authTransport->calls, 'one token request for two searches');
        $this->assertCount(2, $apiTransport->calls);
    }

    /**
     * The four hundred of the spec
     * @return void
     */
    public function testTheFourHundredOfTheSpec(): void
    {
        $e = self::thrown(function () {
            $this->api('{"transactionId":"624deea6","customerTransactionId":"AnyCo_order123456789","errors":[{"code":"ERROR.LONGLAT.REQUIRED","message":"Longitude and Latitude are required."}]}', 400)->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US']);
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertSame('AnyCo_order123456789', $e->getCustomerTransactionId());
        $this->assertSame('ERROR.LONGLAT.REQUIRED: Longitude and Latitude are required.', $e->getErrorsAsString());
    }

    /**
     * @dataProvider errorStatusesProvider
     * @param int $status HTTP status
     * @param int $code The code of the exception
     */
    public function testErrorStatuses(int $status, int $code): void
    {
        $e = self::searchFails($this->api('{"transactionId":"T","errors":[{"code":"X","message":"m"}]}', $status));

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame($code, $e->getCode());
        $this->assertSame($status, $e->getStatusCode());
    }

    /**
     * The cases of testErrorStatuses(): error statuses
     * @return array
     */
    public function errorStatusesProvider(): array
    {
        return [
            '401' => [401, FedexException::EC_UNABLE_TO_AUTHENTICATE],
            '403' => [403, FedexException::EC_TRANSPORT_ERROR],
            '404' => [404, FedexException::EC_TRANSPORT_ERROR],
            '500' => [500, FedexException::EC_TRANSPORT_ERROR],
            '503' => [503, FedexException::EC_TRANSPORT_ERROR],
        ];
    }

    /**
     * A network failure
     * @return void
     */
    public function testANetworkFailure(): void
    {
        $this->transport = new FakeTransport([], Responses::make(0, '', 'Connection refused'));
        $auth = StubAuth::make();
        $api = new FedexLocationsSearch($auth, 'en_US', 30, 10, new FedexClient($auth, 'en_US', 30, 10, $this->transport));

        $e = self::searchFails($api);

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame('Transport error: Connection refused', $e->getMessage());
        $this->assertSame(0, $e->getStatusCode());
    }

    /**
     * An invalid response carries the error paths and the response
     * @return void
     */
    public function testAnInvalidResponseCarriesTheErrorPathsAndTheResponse(): void
    {
        $e = self::searchFails($this->api('{"output":{"locationDetailList":[{"contactAndAddress":{"address":{"streetLines":"x","countryCode":"US"}}}]}}'));

        $this->assertInstanceOf(FedexLocationsSearchException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertSame('Invalid response received', $e->getMessage());
        $this->assertStringContainsString('$.output.locationDetailList[0].contactAndAddress.address.streetLines: expected list', $e->getErrorsAsString());
        $this->assertInstanceOf(\stdClass::class, $e->getData());
    }

    /**
     * Without a client one is built from the arguments
     * @return void
     */
    public function testWithoutAClientOneIsBuiltFromTheArguments(): void
    {
        $auth = StubAuth::make();
        $built = Reflect::prop(new FedexLocationsSearch($auth, '  de_DE ', 99, 99), AbstractFedexApi::class, 'client');

        $this->assertInstanceOf(FedexClient::class, $built);
        $this->assertSame('de_DE', Reflect::prop($built, FedexClient::class, 'locale'));
        $this->assertSame(30, Reflect::prop($built, FedexClient::class, 'timeout'));
        $this->assertSame(10, Reflect::prop($built, FedexClient::class, 'connectTimeout'));
        $this->assertSame($auth, Reflect::prop($built, FedexClient::class, 'auth'));
    }

    /**
     * An injected client is the one used
     * @return void
     */
    public function testAnInjectedClientIsTheOneUsed(): void
    {
        $auth = StubAuth::make();
        $injected = new FedexClient($auth, 'fr_FR', 12, 7, new FakeTransport());

        $this->assertSame($injected, Reflect::prop(new FedexLocationsSearch($auth, 'en_US', 30, 10, $injected), AbstractFedexApi::class, 'client'));
    }

    /**
     * The classes the constructor and the constants
     * @return void
     */
    public function testTheClassesTheConstructorAndTheConstants(): void
    {
        $constructor = new \ReflectionMethod(FedexLocationsSearch::class, '__construct');
        $this->assertSame(['auth', 'locale', 'timeout', 'connectTimeout', 'client'], array_map(function (\ReflectionParameter $p) {
            return $p->getName();
        }, $constructor->getParameters()));
        $this->assertTrue($constructor->getParameters()[4]->allowsNull());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());

        $this->assertTrue(is_subclass_of(FedexLocationsSearch::class, AbstractFedexApi::class));
        $this->assertTrue(is_subclass_of(FedexLocationsSearchException::class, FedexException::class));

        $signature = function (string $class) {
            return implode(',', array_map(function (\ReflectionParameter $p) {
                return ($p->hasType() ? $p->getType()->getName() : '') . ' ' . $p->getName() . '=' . var_export($p->isDefaultValueAvailable() ? $p->getDefaultValue() : '?', true);
            }, (new \ReflectionMethod($class, '__construct'))->getParameters()));
        };
        $this->assertSame($signature(FedexClientException::class), $signature(FedexLocationsSearchException::class), 'the exception constructor has the same signature as FedexClientException');

        $this->assertSame('ADDRESS', FedexLocationsSearch::CRITERION_ADDRESS);
        $this->assertSame('GEOGRAPHIC_COORDINATES', FedexLocationsSearch::CRITERION_GEOGRAPHIC_COORDINATES);
        $this->assertSame('PHONE_NUMBER', FedexLocationsSearch::CRITERION_PHONE_NUMBER);
        $this->assertSame('location/v1/locations', FedexLocationsSearch::ENDPOINT);

        $enum = self::components()['Full_Schema_Find_Location']['properties']['locationSearchCriterion']['enum'];
        $this->assertSame($enum, [FedexLocationsSearch::CRITERION_ADDRESS, FedexLocationsSearch::CRITERION_GEOGRAPHIC_COORDINATES, FedexLocationsSearch::CRITERION_PHONE_NUMBER], 'the criteria constants are exactly the enum of the spec');
        $this->assertArrayHasKey('/' . FedexLocationsSearch::ENDPOINT, self::spec()['paths']);
    }

    /**
     * A transient failure is retried when the client has a retry policy, and not otherwise
     * @return void
     */
    public function testATransientFailureIsRetriedOnlyWhenTheClientHasARetryPolicy(): void
    {
        foreach ([true, false] as $retries) {
            $transport = new FakeTransport([Responses::make(503, '{"errors":[]}'), Responses::make(0, '', 'Operation timed out')], Responses::make(200, self::OK_RESPONSE));
            $auth = StubAuth::make();
            $client = new FedexClient($auth, 'en_US', 30, 10, $transport);

            if ($retries) {
                $client->setRetryPolicy(3, 0, 0);
            }

            $api = new ExposedLocations($auth, 'en_US', 30, 10, $client);
            $error = null;

            try {
                $api->searchByAddress(['postalCode' => '38017', 'countryCode' => 'US']);
            } catch (FedexClientException $e) {
                $error = $e;
            }

            if ($retries) {
                $this->assertNull($error);
                $this->assertCount(3, $transport->calls);
            } else {
                $this->assertInstanceOf(FedexClientException::class, $error);
                $this->assertSame(503, $error->getStatusCode());
                $this->assertCount(1, $transport->calls);
            }
        }
    }
}
