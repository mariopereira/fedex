<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\AbstractFedexApi;
use Mariopereira\Fedex\Api\FedexAddressValidation;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexAddressValidationException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Misc\FedexUtils;
use Mariopereira\Fedex\Tests\Support\BatchAssertions;
use Mariopereira\Fedex\Tests\Support\ExposedAddress;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Reflect;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\SpecInstances as Spec;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the address validation. The definitions are compared with schemas/address-validation.json, requests and
 * responses are generated from that file and broken one field at a time, and the rules and the helpers are checked
 */
final class FedexAddressValidationTest extends TestCase
{
    use BatchAssertions;

    private const OK_RESPONSE = '{"transactionId":"T1","output":{"resolvedAddresses":[]}}';

    /**
     * The transport of the last validator made by api()
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
     * An address that is valid
     * @return array
     */
    private static function okAddress(): array
    {
        return ['streetLines' => ['7372 PARKRIDGE BLVD'], 'city' => 'IRVING', 'postalCode' => '75063'];
    }

    /**
     * The components of the OpenAPI file
     * @return array
     */
    private static function components(): array
    {
        if (self::$spec === null) {
            self::$spec = json_decode(file_get_contents(__DIR__ . '/../../../schemas/address-validation.json'), true);
        }

        return self::$spec['components']['schemas'];
    }

    /**
     * The request generated from the spec
     * @return array [the request, what can be broken in it]
     */
    private static function generatedRequest(): array
    {
        if (self::$request === null) {
            $meta = [];
            self::$request = [Spec::generate(self::components(), 'Full_Schema_Validate_Address', $meta), $meta];
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
            self::$response = [Spec::generate(self::components(), 'AdvcResponseVO', $meta), $meta];
        }

        return self::$response;
    }

    /**
     * An address validator over a fake transport
     * @param string $body Body of the response of the transport
     * @param int $status HTTP status of the response
     * @return FedexAddressValidation
     */
    private function api(string $body = self::OK_RESPONSE, int $status = 200): FedexAddressValidation
    {
        $this->transport = new FakeTransport([], Responses::make($status, $body));
        $auth = StubAuth::make();

        return new ExposedAddress($auth, 'en_US', 30, 10, new FedexClient($auth, 'en_US', 30, 10, $this->transport));
    }

    /**
     * A validator whose transport answers with a response made from an array
     * @param array $data The response
     * @return FedexAddressValidation
     */
    private function respond(array $data): FedexAddressValidation
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
     * Resolve a request with a new validator and say what happened
     * @param mixed $request The request
     * @return array The exception (null if none) and the number of requests that were sent
     */
    private function outcome($request): array
    {
        $api = $this->api();
        $e = self::thrown(function () use ($api, $request) {
            $api->resolve($request);
        });

        return [$e, count($this->transport->calls)];
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
     * Is this a request that was refused (EC_BAD_PARAMETER) with nothing sent?
     * @param \Throwable|null $e The exception
     * @param int $sent How many requests were sent
     * @return bool
     */
    private static function refused(?\Throwable $e, int $sent): bool
    {
        return ($e instanceof FedexAddressValidationException) && ($e->getCode() === FedexException::EC_BAD_PARAMETER) && ($sent === 0);
    }

    // ---------------------------------------------------------------- 1. the definitions against the spec

    /**
     * The definitions are the ones of the spec
     * @return void
     */
    public function testTheDefinitionsAreTheOnesOfTheSpec(): void
    {
        $C = self::components();
        $api = $this->api();
        $request = ['Full_Schema_Validate_Address', 'AddressResolutionControlParameters', 'ResolveContactAndAddress', 'Address'];
        $response = ['AdvcResponseVO', 'BaseProcessOutputVO', 'AddressResolutionOutputVOV3', 'ResolvedAddress', 'Alert', 'CustomerMessage', 'ResolutionToken', 'ParsedPostalCode'];

        // the places where the class deliberately differs from the file (decided with the owner)
        $deviations = [
            'Address.stateOrProvinceCode' => ['@type' => 'string', '@max' => 2],
            'Address.postalCode' => 'string|int',
            'Address.countryCode' => ['@type' => 'string', '@pattern' => '/^[A-Za-z]{2}$/D'],
            'Full_Schema_Validate_Address.addressesToValidate' => ['@type' => ['@ResolveContactAndAddress'], '@min' => 1, '@max' => 100],
            'ResolvedAddress.cityToken' => ['string|@ResolutionToken'],
        ];
        $res = [];

        foreach (['request' => [$request, $api->inputSchemas()], 'response' => [$response, $api->outputDefinitions()]] as $side => [$names, $defs]) {
            $defined = array_keys($defs);
            $expected = $names;
            sort($defined);
            sort($expected);
            $res["$side: exactly these components are defined"] = ($defined === $expected) ? true : json_encode(array_keys($defs));

            foreach ($names as $name) {
                $c = $C[$name];
                $d = $defs[$name];

                if (isset($c['$ref'])) {
                    $res["$name is an alias of " . basename($c['$ref'])] = ($d === '@' . basename($c['$ref'])) ? true : json_encode($d);
                    continue;
                }

                if (!isset($c['properties'])) {
                    $res["$name has no schema in the file: any value"] = ($d === 'mixed') ? true : json_encode($d);
                    continue;
                }

                $specKeys = array_keys($c['properties']);
                $defKeys = array_map(function ($k) {
                    return rtrim($k, '?');
                }, array_keys($d));
                sort($specKeys);
                sort($defKeys);
                $res["$name: same properties"] = ($specKeys === $defKeys) ? true : 'spec/class ' . json_encode(array_diff($specKeys, $defKeys)) . json_encode(array_diff($defKeys, $specKeys));

                $reqSpec = $c['required'] ?? [];
                $reqDef = array_values(array_filter(array_keys($d), function ($k) {
                    return substr($k, -1) !== '?';
                }));
                sort($reqSpec);
                sort($reqDef);
                $res["$name: same required keys"] = ($reqSpec === $reqDef) ? true : 'spec ' . json_encode($reqSpec) . ' / class ' . json_encode($reqDef);

                foreach ($c['properties'] as $pn => $p) {
                    $node = $d[$pn] ?? ($d[$pn . '?'] ?? null);

                    if (isset($deviations["$name.$pn"])) {
                        $res["$name.$pn: the decided difference"] = ($node === $deviations["$name.$pn"]) ? true : json_encode($node);
                        continue;
                    }

                    if ($name === 'Full_Schema_Validate_Address' && $pn === 'inEffectAsOfTimestamp') {
                        $res["$name.$pn: type string, date pattern and calendar check"] = (is_array($node) && $node['@type'] === 'string' && $node['@pattern'] === '/^\\d{4}-\\d{2}-\\d{2}$/D' && is_callable($node['@check'])) ? true : json_encode(array_keys((array) $node));
                        continue;
                    }

                    if ($name === 'ResolvedAddress' && $pn === 'attributes') {
                        $res["$name.$pn: only an object (decided)"] = ($node === 'object') ? true : json_encode($node);
                        continue;
                    }

                    $ref = Spec::refOf($p);

                    if ($ref !== null) {
                        $res["$name.$pn: reference"] = ($node === '@' . $ref) ? true : json_encode($node);
                    } elseif (($p['type'] ?? '') === 'array') {
                        $itemRef = Spec::refOf($p['items'] ?? []);

                        if ($itemRef !== null) {
                            $ok = ($node === ['@' . $itemRef]);
                        } elseif (isset($p['items']['enum'])) {
                            $ok = is_array($node) && $node[0]['@enum'] === $p['items']['enum'];
                        } else {
                            $ok = ($node === ['string']);
                        }

                        $res["$name.$pn: list"] = $ok ? true : json_encode($node);
                    } else {
                        $type = ['string' => 'string', 'integer' => 'int', 'number' => 'number', 'boolean' => 'bool'][$p['type']];

                        if (isset($p['enum'])) {
                            $ok = is_array($node) && $node['@type'] === $type && $node['@enum'] === $p['enum'];
                        } else {
                            $ok = ($node === $type);
                        }

                        $res["$name.$pn: type and enum"] = $ok ? true : json_encode($node);
                    }
                }
            }
        }

        $this->assertAll('every component the request and the response use, property by property', $res);
    }

    /**
     * Components of the file that the API does not use are not defined
     * @return void
     */
    public function testComponentsOfTheFileThatTheApiDoesNotUseAreNotDefined(): void
    {
        $api = $this->api();
        $res = [];

        foreach (['ParsedPersonName', 'EMailDetail', 'SocialMediaDetail', 'PhoneNumberDetail', 'PhoneNumber', 'CompanyName', 'ErrorResponseVO', 'CXSError', 'Parameter', 'Address_Validation_US', 'body'] as $name) {
            $res["$name is not defined"] = (!isset($api->inputSchemas()[$name]) && !isset($api->outputDefinitions()[$name])) ? true : 'defined';
        }

        $this->assertAll('components of the file that the API does not use', $res);
    }

    // ---------------------------------------------------------------- 2. the five examples of the spec

    /**
     * The five request examples of the spec are accepted and sent unchanged
     * @return void
     */
    public function testTheFiveRequestExamplesOfTheSpecAreAcceptedAndSentUnchanged(): void
    {
        $C = self::components();
        $res = [];

        foreach (['Address_Validation_Canada', 'Address_Validation_US', 'Address_Validation_for_Germany', 'Address_Validation_for_Italy', 'Address_Validation_for_UK'] as $name) {
            $example = $C[$name]['example'];
            $api = $this->api();
            $e = self::thrown(function () use ($api, $example) {
                $api->resolve($example);
            });
            $res[$name] = ($e === null && Spec::normalize($this->transport->bodyOf(0)) === Spec::normalize($example)) ? true : self::describe($e, json_encode($this->transport->bodyOf(0)));
        }

        $this->assertAll('the examples of the spec', $res);
    }

    // ---------------------------------------------------------------- 3. requests generated from the spec

    /**
     * The generated request fills every field of the spec
     * @return void
     */
    public function testTheGeneratedRequestFillsEveryFieldOfTheSpec(): void
    {
        [, $meta] = self::generatedRequest();

        $this->assertGreaterThanOrEqual(8, count($meta['leaves']));
        $this->assertGreaterThanOrEqual(4, count($meta['objects']));
    }

    /**
     * The generated request is accepted and sent unchanged
     * @return void
     */
    public function testTheGeneratedRequestIsAcceptedAndSentUnchanged(): void
    {
        [$request] = self::generatedRequest();
        $api = $this->api();

        $api->resolve($request);

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

        $api->resolve(json_decode(json_encode($request)));

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
            $name = Spec::pathName($leaf['path']);

            // a list is always a wrong type for a scalar field
            $mutated = Spec::setAt($request, $leaf['path'], ['x']);
            [$e, $sent] = $this->outcome($mutated);
            $res["an array at $name"] = self::refused($e, $sent) ? true : self::describe($e, 'accepted');

            $bad = ($leaf['type'] === 'string') ? 12345 : 'x';
            $mutated = Spec::setAt($request, $leaf['path'], $bad);
            [$e, $sent] = $this->outcome($mutated);

            if ($leaf['path'] === ['addressesToValidate', 0, 'address', 'postalCode']) {
                // the postal code may be a string or an integer (the Italy example of the file)
                $res["integer at $name (the postal code may be an integer)"] = ($e === null && $sent === 1) ? true : self::describe($e, 'not sent');
            } else {
                $res["wrong scalar type at $name"] = self::refused($e, $sent) ? true : self::describe($e, 'accepted');
            }
        }

        $this->assertAll('a wrong type in any leaf field of the request', $res);
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

        $this->assertAll('an unknown key in every object of the request', $res);
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

    // ---------------------------------------------------------------- 4. responses generated from the spec

    /**
     * The generated response fills every field of the spec
     * @return void
     */
    public function testTheGeneratedResponseFillsEveryFieldOfTheSpec(): void
    {
        [, $meta] = self::generatedResponse();

        $this->assertGreaterThanOrEqual(45, count($meta['leaves']));
        $this->assertGreaterThanOrEqual(6, count($meta['objects']));
    }

    /**
     * The generated response is accepted and returned
     * @return void
     */
    public function testTheGeneratedResponseIsAcceptedAndReturned(): void
    {
        [$response] = self::generatedResponse();

        $out = $this->respond($response)->validate(self::okAddress());

        $this->assertInstanceOf(\stdClass::class, $out);
        $this->assertSame($response['output']['resolvedAddresses'][0]['city'], $out->output->resolvedAddresses[0]->city);
        $this->assertTrue(isset($out->output->resolvedAddresses[0]->attributes->POBox), 'the attributes are there');
        $this->assertTrue(property_exists($out->output->resolvedAddresses[0]->parsedPostalCode, 'addOn'), 'the parsed postal code is there');
    }

    /**
     * A wrong type in any leaf of the response is rejected except where the content is free
     * @return void
     */
    public function testAWrongTypeInAnyLeafOfTheResponseIsRejectedExceptWhereTheContentIsFree(): void
    {
        [$response, $meta] = self::generatedResponse();
        $res = [];

        foreach ($meta['leaves'] as $leaf) {
            $free = $leaf['free'] || in_array('attributes', $leaf['path'], true);
            $bad = ($leaf['type'] === 'string') ? 12345 : 'x';
            $api = $this->respond(Spec::setAt($response, $leaf['path'], $bad));
            $e = self::thrown(function () use ($api) {
                $api->validate(self::okAddress());
            });

            if ($free) {
                $res['a different type at ' . Spec::pathName($leaf['path']) . ' (free by decision)'] = ($e === null) ? true : self::describe($e, '');
            } else {
                $res['wrong type at ' . Spec::pathName($leaf['path'])] = ($e instanceof FedexAddressValidationException && $e->getCode() === FedexException::EC_BAD_RESULT_RETURNED && count($e->getErrors()) >= 1) ? true : self::describe($e, 'accepted');
            }
        }

        $this->assertAll('a wrong type in the leaves of the response (attributes and customerMessage are free)', $res);
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

            $api = $this->respond(Spec::setAt($response, $leaf['path'], 'NOT_A_VALUE'));
            $e = self::thrown(function () use ($api) {
                $api->validate(self::okAddress());
            });
            $res['bad enum value at ' . Spec::pathName($leaf['path'])] = ($e instanceof FedexAddressValidationException && $e->getCode() === FedexException::EC_BAD_RESULT_RETURNED && strpos(implode(' ', $e->getErrors()), 'must be one of') !== false) ? true : self::describe($e, 'accepted');
        }

        $this->assertAll('a value outside the enum (classification and alertType)', $res);
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
            $api = $this->respond($mutated);
            $e = self::thrown(function () use ($api) {
                $api->validate(self::okAddress());
            });
            $res['unknown key in ' . ($o['path'] ? Spec::pathName($o['path']) : 'the response')] = ($e === null) ? true : self::describe($e, '');
        }

        $this->assertAll('an unknown field in the response', $res);
    }

    /**
     * Every field of the response can be absent
     * @return void
     */
    public function testEveryFieldOfTheResponseCanBeAbsent(): void
    {
        [$response, $meta] = self::generatedResponse();
        $res = [];

        foreach ($meta['objects'] as $o) {
            foreach ($o['keys'] as $key) {
                $path = array_merge($o['path'], [$key]);

                if (in_array('attributes', $path, true) && $path !== ['output', 'resolvedAddresses', 0, 'attributes']) {
                    continue;
                }

                $api = $this->respond(Spec::unsetAt($response, $path));
                $e = self::thrown(function () use ($api) {
                    $api->validate(self::okAddress());
                });
                $res['without ' . Spec::pathName($path)] = ($e === null) ? true : self::describe($e, '');
            }
        }

        $this->assertAll('nothing is required in the response', $res);
    }

    /**
     * @dataProvider acceptedResponsesProvider
     * @param string $json A response body
     */
    public function testSomeResponsesAreAccepted(string $json): void
    {
        $this->api($json)->validate(self::okAddress());

        $this->addToAssertionCount(1);
    }

    /**
     * The cases of testSomeResponsesAreAccepted(): some responses are accepted
     * @return array
     */
    public function acceptedResponsesProvider(): array
    {
        return [
            'an empty object' => ['{}'],
            'an empty output' => ['{"output":{}}'],
            'empty lists' => ['{"output":{"resolvedAddresses":[],"alerts":[]}}'],
        ];
    }

    /**
     * @dataProvider rejectedResponsesProvider
     * @param string $json A response body
     */
    public function testSomeResponsesAreRejectedWithCode101(string $json): void
    {
        $e = self::thrown(function () use ($json) {
            $this->api($json)->validate(self::okAddress());
        });

        $this->assertInstanceOf(FedexAddressValidationException::class, $e, self::describe($e, 'accepted'));
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
            'an empty body (null)' => [''],
            'output that is not an object' => ['{"output":"x"}'],
            'a list that is an object' => ['{"output":{"resolvedAddresses":{}}}'],
            'a numeric transaction id' => ['{"transactionId":5}'],
        ];
    }

    /**
     * @dataProvider oddFieldsProvider
     * @param array $resolved The fields of a resolved address
     * @param bool $accepted Is it accepted?
     */
    public function testTheOddFieldsOfTheResponseAsDecided(array $resolved, bool $accepted): void
    {
        $api = $this->api(json_encode(['output' => ['resolvedAddresses' => [$resolved]]]));

        $e = self::thrown(function () use ($api) {
            $api->validate(self::okAddress());
        });

        if ($accepted) {
            $this->assertNull($e, self::describe($e, ''));
        } else {
            $this->assertInstanceOf(FedexAddressValidationException::class, $e, 'it had to be rejected');
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        }
    }

    /**
     * The cases of testTheOddFieldsOfTheResponseAsDecided(): the odd fields of the response as decided
     * @return array
     */
    public function oddFieldsProvider(): array
    {
        return [
            'cityToken as strings (the example of the file)' => [['cityToken' => ['TOK-1X3256']], true],
            'cityToken as objects (the schema of the file)' => [['cityToken' => [['changed' => false, 'value' => 'SAN JUAN']]], true],
            'cityToken mixed' => [['cityToken' => ['TOK-1', ['changed' => true, 'value' => 'X']]], true],
            'cityToken item that is a number' => [['cityToken' => [5]], false],
            'cityToken item that is an object with a wrong field type' => [['cityToken' => [['changed' => 'yes']]], false],
            'cityToken that is not a list' => [['cityToken' => 'TOK'], false],
            'postalCodeToken as an object' => [['postalCodeToken' => ['changed' => false, 'value' => '00926']], true],
            'postalCodeToken as a string (only cityToken was widened)' => [['postalCodeToken' => 'TOK'], false],
            'attributes with the documented keys' => [['attributes' => ['POBox' => false, 'DPV' => true, 'AddressType' => 'STANDARDIZED']], true],
            'attributes with other keys and other types' => [['attributes' => ['SomethingNew' => 1, 'POBox' => 'false']], true],
            'attributes empty object' => [['attributes' => new \stdClass()], true],
            'attributes as a string' => [['attributes' => 'x'], false],
            'attributes as a list' => [['attributes' => [1, 2]], false],
            'customerMessage with strings' => [['customerMessage' => ['INVALID.SUITE.NUMBER', 'PARTIAL.STREET.MATCH']], true],
            'customerMessage with objects and numbers' => [['customerMessage' => [['code' => 'X'], 5, null]], true],
            'customerMessage as a string (not a list)' => [['customerMessage' => 'X'], false],
            'classification RESIDENTIAL' => [['classification' => 'RESIDENTIAL'], true],
            'classification in lower case' => [['classification' => 'residential'], false],
            'booleans as booleans' => [['postOfficeBox' => false, 'normalizedStatusNameDPV' => true, 'ruralRouteHighwayContract' => false, 'generalDelivery' => false], true],
            'a boolean as a string' => [['postOfficeBox' => 'false'], false],
        ];
    }

    // ---------------------------------------------------------------- 5. field rules of the request

    /**
     * @dataProvider rulesProvider
     * @param array $request The request
     * @param bool $accepted Is it accepted?
     */
    public function testFieldByField(array $request, bool $accepted): void
    {
        [$e, $sent] = $this->outcome($request);

        if ($accepted) {
            $this->assertNull($e, self::describe($e, ''));
            $this->assertSame(1, $sent, 'it had to be sent');
        } else {
            $this->assertTrue(self::refused($e, $sent), self::describe($e, 'it was accepted'));
        }
    }

    /**
     * The cases of testFieldByField(): field by field
     * @return array
     */
    public function rulesProvider(): array
    {
        $ok = self::okAddress();
        $req = function (array $address, array $top = []) {
            return array_merge(['addressesToValidate' => [['address' => $address]]], $top);
        };

        return [
            'minimal address' => [$req($ok), true],
            'full address' => [$req($ok + ['stateOrProvinceCode' => 'TX', 'countryCode' => 'US']), true],
            'missing streetLines' => [$req(array_diff_key($ok, ['streetLines' => 1])), false],
            'missing city' => [$req(array_diff_key($ok, ['city' => 1])), false],
            'missing postalCode' => [$req(array_diff_key($ok, ['postalCode' => 1])), false],
            'postalCode "75063-8659"' => [$req(array_replace($ok, ['postalCode' => '75063-8659'])), true],
            'postalCode as an integer (Italy example)' => [$req(array_replace($ok, ['postalCode' => 187])), true],
            'postalCode as a float' => [$req(array_replace($ok, ['postalCode' => 187.5])), false],
            'postalCode as a boolean' => [$req(array_replace($ok, ['postalCode' => true])), false],
            'postalCode as an array' => [$req(array_replace($ok, ['postalCode' => ['1']])), false],
            'state "TX"' => [$req($ok + ['stateOrProvinceCode' => 'TX']), true],
            'state "" (Germany example)' => [$req($ok + ['stateOrProvinceCode' => '']), true],
            'state "T"' => [$req($ok + ['stateOrProvinceCode' => 'T']), true],
            'state "TEX" (more than 2 characters)' => [$req($ok + ['stateOrProvinceCode' => 'TEX']), false],
            'state as an integer' => [$req($ok + ['stateOrProvinceCode' => 5]), false],
            'country "US"' => [$req($ok + ['countryCode' => 'US']), true],
            'country "gb" (lower case)' => [$req($ok + ['countryCode' => 'gb']), true],
            'country "USA"' => [$req($ok + ['countryCode' => 'USA']), false],
            'country "U"' => [$req($ok + ['countryCode' => 'U']), false],
            'country "1A"' => [$req($ok + ['countryCode' => '1A']), false],
            'country " US"' => [$req($ok + ['countryCode' => ' US']), false],
            'country "US\n"' => [$req($ok + ['countryCode' => "US\n"]), false],
            'country ""' => [$req($ok + ['countryCode' => '']), false],
            'streetLines with 3 lines' => [$req(array_replace($ok, ['streetLines' => ['a', 'b', 'c']])), true],
            'streetLines with 5 lines (the API ignores more than 3)' => [$req(array_replace($ok, ['streetLines' => ['a', 'b', 'c', 'd', 'e']])), true],
            'streetLines empty list' => [$req(array_replace($ok, ['streetLines' => []])), false],
            'streetLines with an empty line' => [$req(array_replace($ok, ['streetLines' => ['a', '']])), false],
            'streetLines with a blank line' => [$req(array_replace($ok, ['streetLines' => ['  ']])), false],
            'streetLines with a number' => [$req(array_replace($ok, ['streetLines' => [123]])), false],
            'streetLines as a string' => [$req(array_replace($ok, ['streetLines' => 'a'])), false],
            'inEffectAsOfTimestamp "2019-09-06"' => [$req($ok, ['inEffectAsOfTimestamp' => '2019-09-06']), true],
            'inEffectAsOfTimestamp "2020-02-29" (leap day)' => [$req($ok, ['inEffectAsOfTimestamp' => '2020-02-29']), true],
            'inEffectAsOfTimestamp "2019-02-30" (not a date)' => [$req($ok, ['inEffectAsOfTimestamp' => '2019-02-30']), false],
            'inEffectAsOfTimestamp "2019-02-29" (not a leap year)' => [$req($ok, ['inEffectAsOfTimestamp' => '2019-02-29']), false],
            'inEffectAsOfTimestamp "2019-9-6"' => [$req($ok, ['inEffectAsOfTimestamp' => '2019-9-6']), false],
            'inEffectAsOfTimestamp with a time' => [$req($ok, ['inEffectAsOfTimestamp' => '2019-09-06T10:00:00']), false],
            'inEffectAsOfTimestamp with a leading space' => [$req($ok, ['inEffectAsOfTimestamp' => ' 2019-09-06']), false],
            'inEffectAsOfTimestamp with a trailing newline' => [$req($ok, ['inEffectAsOfTimestamp' => "2019-09-06\n"]), false],
            'inEffectAsOfTimestamp as an integer' => [$req($ok, ['inEffectAsOfTimestamp' => 20190906]), false],
            'inEffectAsOfTimestamp empty string' => [$req($ok, ['inEffectAsOfTimestamp' => '']), false],
            'includeResolutionTokens true' => [$req($ok, ['validateAddressControlParameters' => ['includeResolutionTokens' => true]]), true],
            'includeResolutionTokens false' => [$req($ok, ['validateAddressControlParameters' => ['includeResolutionTokens' => false]]), true],
            'includeResolutionTokens "true" (a string)' => [$req($ok, ['validateAddressControlParameters' => ['includeResolutionTokens' => 'true']]), false],
            'validateAddressControlParameters with an unknown key' => [$req($ok, ['validateAddressControlParameters' => ['tokens' => true]]), false],
            'clientReferenceId "A1"' => [['addressesToValidate' => [['address' => $ok, 'clientReferenceId' => 'A1']]], true],
            'clientReferenceId as a number' => [['addressesToValidate' => [['address' => $ok, 'clientReferenceId' => 1]]], false],
            'item without the address' => [['addressesToValidate' => [['clientReferenceId' => 'A1']]], false],
            'item with an unknown key' => [['addressesToValidate' => [['address' => $ok, 'reference' => 'x']]], false],
            'address with an unknown key' => [$req($ok + ['zip' => '1']), false],
            'no addressesToValidate' => [['inEffectAsOfTimestamp' => '2019-09-06'], false],
            'addressesToValidate empty list' => [['addressesToValidate' => []], false],
            'addressesToValidate as an object (not a list)' => [['addressesToValidate' => ['address' => $ok]], false],
            'addressesToValidate item that is a string' => [['addressesToValidate' => ['text']], false],
            'an unknown top-level key' => [$req($ok, ['maxresults' => 5]), false],
        ];
    }

    /**
     * The number of addresses from one to one hundred
     * @return void
     */
    public function testTheNumberOfAddressesFromOneToOneHundred(): void
    {
        $res = [];

        foreach ([1, 2, 50, 100] as $n) {
            $items = array_fill(0, $n, ['address' => self::okAddress()]);
            [$e, $sent] = $this->outcome(['addressesToValidate' => $items]);
            $res["$n addresses are accepted"] = ($e === null && count($this->transport->bodyOf(0)['addressesToValidate']) === $n) ? true : self::describe($e, 'wrong body');
        }

        foreach ([101, 150] as $n) {
            $items = array_fill(0, $n, ['address' => self::okAddress()]);
            [$e, $sent] = $this->outcome(['addressesToValidate' => $items]);
            $res["$n addresses are rejected (the limit is 100)"] = (self::refused($e, $sent) && strpos($e->getErrorsAsString(), 'item count must be <= 100') !== false) ? true : self::describe($e, 'accepted');
        }

        $this->assertAll('the number of addresses', $res);
    }

    /**
     * False is sent and only null and empty lists are left out
     * @return void
     */
    public function testFalseIsSentAndOnlyNullAndEmptyListsAreLeftOut(): void
    {
        $api = $this->api();
        $api->resolve(['addressesToValidate' => [['address' => self::okAddress()]], 'validateAddressControlParameters' => ['includeResolutionTokens' => false]]);

        $this->assertFalse($this->transport->bodyOf(0)['validateAddressControlParameters']['includeResolutionTokens']);
    }

    /**
     * Nulls and empty objects are left out and the street lines stay a JSON list
     * @return void
     */
    public function testNullsAndEmptyObjectsAreLeftOutAndTheStreetLinesStayAJsonList(): void
    {
        $api = $this->api();

        $api->resolve(['inEffectAsOfTimestamp' => null, 'validateAddressControlParameters' => ['includeResolutionTokens' => null], 'addressesToValidate' => [['address' => ['streetLines' => ['a', null, 'b'], 'city' => 'x', 'postalCode' => 1, 'stateOrProvinceCode' => null, 'countryCode' => null], 'clientReferenceId' => null]]]);

        $this->assertSame('{"addressesToValidate":[{"address":{"streetLines":["a","b"],"city":"x","postalCode":1}}]}', $this->transport->calls[0]['body']);
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

        $this->assertStringContainsString('$.addressesToValidate: required key is missing', $e->getErrorsAsString());
    }

    /**
     * The errors name each problem with its path and carry no request data
     * @return void
     */
    public function testTheErrorsNameEachProblemWithItsPathAndCarryNoRequestData(): void
    {
        [$e] = $this->outcome(['maxresults' => 5, 'addressesToValidate' => [['address' => ['streetLines' => ['a'], 'city' => 'x', 'postalCode' => '1', 'zip' => '2']]]]);

        $this->assertCount(2, $e->getErrors());
        $this->assertStringContainsString('$.maxresults: unexpected key', $e->getErrorsAsString());
        $this->assertStringContainsString('$.addressesToValidate[0].address.zip: unexpected key', $e->getErrorsAsString());
        $this->assertNull($e->getData(), 'the request holds addresses: it is not kept in the exception');
    }

    /**
     * An empty street line is reported with the number of the address
     * @return void
     */
    public function testAnEmptyStreetLineIsReportedWithTheNumberOfTheAddress(): void
    {
        $ok = self::okAddress();

        [$e, $sent] = $this->outcome(['addressesToValidate' => [['address' => $ok], ['address' => array_replace($ok, ['streetLines' => ['ok', ' ']])]]]);

        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame('The street lines cannot be empty (address 2)', $e->getMessage());
        $this->assertSame(0, $sent);
    }

    // ---------------------------------------------------------------- 6. the helpers

    /**
     * Validate wraps the address
     * @return void
     */
    public function testValidateWrapsTheAddress(): void
    {
        $api = $this->api();

        $out = $api->validate(self::okAddress());

        $this->assertInstanceOf(\stdClass::class, $out);
        $this->assertSame('T1', $out->transactionId);
        $this->assertSame(['addressesToValidate' => [['address' => self::okAddress()]]], $this->transport->bodyOf(0));
    }

    /**
     * Validate takes an object as well
     * @return void
     */
    public function testValidateTakesAnObjectAsWell(): void
    {
        $api = $this->api();

        $api->validate((object) self::okAddress());

        $this->assertSame(['addressesToValidate' => [['address' => self::okAddress()]]], $this->transport->bodyOf(0));
    }

    /**
     * Validate merges the options
     * @return void
     */
    public function testValidateMergesTheOptions(): void
    {
        $api = $this->api();

        $api->validate(self::okAddress(), ['inEffectAsOfTimestamp' => '2019-09-06', 'validateAddressControlParameters' => ['includeResolutionTokens' => true]]);

        $this->assertSame(['inEffectAsOfTimestamp' => '2019-09-06', 'validateAddressControlParameters' => ['includeResolutionTokens' => true], 'addressesToValidate' => [['address' => self::okAddress()]]], $this->transport->bodyOf(0));
    }

    /**
     * Validate refuses the option that it sets itself
     * @return void
     */
    public function testValidateRefusesTheOptionThatItSetsItself(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->validate(self::okAddress(), ['addressesToValidate' => []]);
        });

        $this->assertInstanceOf(FedexAddressValidationException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertStringContainsString("'addressesToValidate'", $e->getMessage());
        $this->assertStringContainsString('validateArray()', $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * Validate refuses an option that is not a field of the request
     * @return void
     */
    public function testValidateRefusesAnOptionThatIsNotAFieldOfTheRequest(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->validate(self::okAddress(), ['maxResults' => 3]);
        });

        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertStringContainsString('$.maxResults: unexpected key', $e->getErrorsAsString());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * @dataProvider badAddressesProvider
     * @param array $address An address that is not valid
     */
    public function testValidateRefusesABadAddressAndSendsNothing(array $address): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $address) {
            $api->validate($address);
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)), self::describe($e, 'accepted'));
    }

    /**
     * The cases of testValidateRefusesABadAddressAndSendsNothing(): validate refuses a bad address and sends nothing
     * @return array
     */
    public function badAddressesProvider(): array
    {
        $ok = self::okAddress();

        return [
            'without a city' => [array_diff_key($ok, ['city' => 1])],
            'without a postal code' => [array_diff_key($ok, ['postalCode' => 1])],
            'without street lines' => [array_replace($ok, ['streetLines' => []])],
            'with a country code of 3 letters' => [array_replace($ok, ['countryCode' => 'USA'])],
            'with a state of 3 characters' => [array_replace($ok, ['stateOrProvinceCode' => 'TEX'])],
            'with an empty street line' => [array_replace($ok, ['streetLines' => ['']])],
            'empty' => [[]],
        ];
    }

    /**
     * @dataProvider notAnAddressProvider
     * @param mixed $bad Something that is not an address
     */
    public function testValidateRefusesWhatIsNotAnAddress($bad): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api, $bad) {
            $api->validate($bad);
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)));
        $this->assertSame('Each address must be an array or an object (address 1)', $e->getMessage());
    }

    /**
     * The cases of testValidateRefusesWhatIsNotAnAddress(): validate refuses what is not an address
     * @return array
     */
    public function notAnAddressProvider(): array
    {
        return ['a string' => ['text'], 'a number' => [5], 'null' => [null]];
    }

    /**
     * Validate array sends one item per address in order
     * @return void
     */
    public function testValidateArraySendsOneItemPerAddressInOrder(): void
    {
        $ok = self::okAddress();
        $second = ['streetLines' => ['24 Grosvenor Square'], 'city' => 'London', 'postalCode' => 'W1A 2LQ', 'countryCode' => 'GB'];
        $third = (object) ['streetLines' => ['Via Vittorio Veneto, 121'], 'city' => 'Roma', 'postalCode' => 187, 'countryCode' => 'IT'];
        $api = $this->api();

        $api->validateArray([$ok, $second, $third]);
        $body = $this->transport->bodyOf(0);

        $this->assertCount(3, $body['addressesToValidate']);
        $this->assertSame(['address' => $ok], $body['addressesToValidate'][0]);
        $this->assertSame(['address' => $second], $body['addressesToValidate'][1]);
        $this->assertSame(187, $body['addressesToValidate'][2]['address']['postalCode']);
        $this->assertArrayNotHasKey('clientReferenceId', $body['addressesToValidate'][0]);
    }

    /**
     * Validate array ignores the keys of the list
     * @return void
     */
    public function testValidateArrayIgnoresTheKeysOfTheList(): void
    {
        $api = $this->api();

        $api->validateArray(['home' => self::okAddress(), 'work' => self::okAddress()]);

        $this->assertSame([0, 1], array_keys($this->transport->bodyOf(0)['addressesToValidate']));
    }

    /**
     * Validate array merges the options
     * @return void
     */
    public function testValidateArrayMergesTheOptions(): void
    {
        $api = $this->api();

        $api->validateArray([self::okAddress()], ['inEffectAsOfTimestamp' => '2020-02-29']);

        $this->assertSame('2020-02-29', $this->transport->bodyOf(0)['inEffectAsOfTimestamp']);
    }

    /**
     * Validate array with no addresses is refused
     * @return void
     */
    public function testValidateArrayWithNoAddressesIsRefused(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->validateArray([]);
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)));
        $this->assertStringContainsString('addressesToValidate: required key is missing', $e->getErrorsAsString());
    }

    /**
     * Validate array refuses an element that is not an address with its number
     * @return void
     */
    public function testValidateArrayRefusesAnElementThatIsNotAnAddressWithItsNumber(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->validateArray([self::okAddress(), 'not an address', self::okAddress()]);
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)));
        $this->assertSame('Each address must be an array or an object (address 2)', $e->getMessage());
    }

    /**
     * Validate array accepts one hundred addresses and refuses one hundred and one
     * @return void
     */
    public function testValidateArrayAcceptsOneHundredAddressesAndRefusesOneHundredAndOne(): void
    {
        $api = $this->api();
        $api->validateArray(array_fill(0, 100, self::okAddress()));
        $this->assertCount(100, $this->transport->bodyOf(0)['addressesToValidate']);

        $api = $this->api();
        $e = self::thrown(function () use ($api) {
            $api->validateArray(array_fill(0, 101, self::okAddress()));
        });
        $this->assertTrue(self::refused($e, count($this->transport->calls)));
    }

    /**
     * One bad address refuses the whole request
     * @return void
     */
    public function testOneBadAddressRefusesTheWholeRequest(): void
    {
        $api = $this->api();
        $second = ['streetLines' => ['24 Grosvenor Square'], 'city' => null, 'postalCode' => 'W1A 2LQ', 'countryCode' => 'GB'];

        $e = self::thrown(function () use ($api, $second) {
            $api->validateArray([self::okAddress(), $second]);
        });

        $this->assertTrue(self::refused($e, count($this->transport->calls)));
        $this->assertStringContainsString('$.addressesToValidate[1].address.city: required key is missing', $e->getErrorsAsString());
    }

    /**
     * Validate array refuses the option that it sets itself
     * @return void
     */
    public function testValidateArrayRefusesTheOptionThatItSetsItself(): void
    {
        $api = $this->api();

        $e = self::thrown(function () use ($api) {
            $api->validateArray([self::okAddress()], ['addressesToValidate' => []]);
        });

        $this->assertStringContainsString("'addressesToValidate'", $e->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    // ---------------------------------------------------------------- 7. newRequest(), newAddress() and resolve()

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
     * New request has every field of the spec
     * @return void
     */
    public function testNewRequestHasEveryFieldOfTheSpec(): void
    {
        $problems = [];

        $this->compareShape(FedexAddressValidation::newRequest(), 'Full_Schema_Validate_Address', [], $problems);

        $this->assertSame([], $problems);
    }

    /**
     * New address has every field of an item of the spec
     * @return void
     */
    public function testNewAddressHasEveryFieldOfAnItemOfTheSpec(): void
    {
        $problems = [];

        $this->compareShape(FedexAddressValidation::newAddress(), 'ResolveContactAndAddress', [], $problems);

        $this->assertSame([], $problems);
    }

    /**
     * The empty structures leave nothing after pruning
     * @return void
     */
    public function testTheEmptyStructuresLeaveNothingAfterPruning(): void
    {
        $this->assertNull(FedexUtils::pruneEmpty(FedexAddressValidation::newRequest()));
        $this->assertNull(FedexUtils::pruneEmpty(FedexAddressValidation::newAddress()));
    }

    /**
     * The empty structures are fresh every time
     * @return void
     */
    public function testTheEmptyStructuresAreFreshEveryTime(): void
    {
        $address = FedexAddressValidation::newAddress();
        $address['address']['city'] = 'X';

        $this->assertNull(FedexAddressValidation::newAddress()['address']['city']);
    }

    /**
     * The empty request sent as it is is rejected
     * @return void
     */
    public function testTheEmptyRequestSentAsItIsIsRejected(): void
    {
        [$e, $sent] = $this->outcome(FedexAddressValidation::newRequest());

        $this->assertStringContainsString('$.addressesToValidate: required key is missing', $e->getErrorsAsString());
        $this->assertSame(0, $sent);
    }

    /**
     * The example of the README
     * @return void
     */
    public function testTheExampleOfTheReadme(): void
    {
        $request = FedexAddressValidation::newRequest();
        $item = FedexAddressValidation::newAddress();
        $item['address']['streetLines'][] = '7372 PARKRIDGE BLVD';
        $item['address']['streetLines'][] = 'APT 286';
        $item['address']['city'] = 'IRVING';
        $item['address']['stateOrProvinceCode'] = 'TX';
        $item['address']['postalCode'] = '75063-8659';
        $item['address']['countryCode'] = 'US';
        $item['clientReferenceId'] = 'order-123';
        $request['addressesToValidate'][] = $item;
        $request['validateAddressControlParameters']['includeResolutionTokens'] = true;
        $api = $this->api();

        $api->resolve($request);

        $this->assertSame(
            Spec::normalize(['validateAddressControlParameters' => ['includeResolutionTokens' => true], 'addressesToValidate' => [['address' => ['streetLines' => ['7372 PARKRIDGE BLVD', 'APT 286'], 'city' => 'IRVING', 'stateOrProvinceCode' => 'TX', 'postalCode' => '75063-8659', 'countryCode' => 'US'], 'clientReferenceId' => 'order-123']]]),
            Spec::normalize($this->transport->bodyOf(0))
        );
    }

    // ---------------------------------------------------------------- 8. on the wire, errors and construction

    /**
     * The request on the wire with the real authorization and client
     * @return void
     */
    public function testTheRequestOnTheWireWithTheRealAuthorizationAndClient(): void
    {
        $authTransport = new FakeTransport([Responses::token(['access_token' => 'ADDRTOKEN'])]);
        $apiTransport = new FakeTransport([], Responses::make(200, '{"transactionId":"T9","customerTransactionId":"C9","output":{"resolvedAddresses":[{"city":"IRVING","classification":"BUSINESS","cityToken":["TOK-1X3256"]}],"alerts":[{"code":"X","alertType":"NOTE","message":"m"}]}}'));
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $authTransport);
        $api = new FedexAddressValidation($auth, 'pt_PT', 30, 10, new FedexClient($auth, 'pt_PT', 30, 10, $apiTransport));

        $res = $api->validate(self::okAddress(), ['validateAddressControlParameters' => ['includeResolutionTokens' => true]]);
        $call = $apiTransport->calls[0];

        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/address/v1/addresses/resolve', $call['url']);
        $this->assertContains('Authorization: Bearer ADDRTOKEN', $call['headers']);
        $this->assertContains('Accept: application/json', $call['headers']);
        $this->assertContains('X-locale: pt_PT', $call['headers']);
        $this->assertContains('Content-Type: application/json', $call['headers']);
        $this->assertNotEmpty(preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $call['headers']));
        $this->assertSame(['validateAddressControlParameters' => ['includeResolutionTokens' => true], 'addressesToValidate' => [['address' => self::okAddress()]]], $apiTransport->bodyOf(0));
        $this->assertSame('T9', $res->transactionId);
        $this->assertSame('C9', $res->customerTransactionId);
        $this->assertSame('BUSINESS', $res->output->resolvedAddresses[0]->classification);
        $this->assertSame('NOTE', $res->output->alerts[0]->alertType);

        $api->validate(self::okAddress());

        $this->assertCount(1, $authTransport->calls, 'one token request for two validations');
        $this->assertCount(2, $apiTransport->calls);
    }

    /**
     * The four hundred of the spec
     * @return void
     */
    public function testTheFourHundredOfTheSpec(): void
    {
        $e = self::thrown(function () {
            $this->api('{"transactionId":"624deea6","customerTransactionId":"AnyCo_order123456789","errors":[{"code":"STANDARDIZED.ADDRESS.NOTFOUND","message":"Standardized address is not found."}]}', 400)->validate(self::okAddress());
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('624deea6', $e->getTransactionId());
        $this->assertSame('AnyCo_order123456789', $e->getCustomerTransactionId());
        $this->assertSame('STANDARDIZED.ADDRESS.NOTFOUND: Standardized address is not found.', $e->getErrorsAsString());
    }

    /**
     * @dataProvider errorStatusesProvider
     * @param int $status HTTP status
     * @param int $code The code of the exception
     */
    public function testErrorStatuses(int $status, int $code): void
    {
        $e = self::thrown(function () use ($status) {
            $this->api('{"transactionId":"T","errors":[{"code":"X","message":"m"}]}', $status)->validate(self::okAddress());
        });

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
        $api = new FedexAddressValidation($auth, 'en_US', 30, 10, new FedexClient($auth, 'en_US', 30, 10, $this->transport));

        $e = self::thrown(function () use ($api) {
            $api->validate(self::okAddress());
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame('Transport error: Connection refused', $e->getMessage());
        $this->assertSame(0, $e->getStatusCode());
    }

    /**
     * An invalid response carries the error path and the response
     * @return void
     */
    public function testAnInvalidResponseCarriesTheErrorPathAndTheResponse(): void
    {
        $e = self::thrown(function () {
            $this->api('{"output":{"resolvedAddresses":[{"classification":"HOUSE"}]}}')->validate(self::okAddress());
        });

        $this->assertInstanceOf(FedexAddressValidationException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertSame('Invalid response received', $e->getMessage());
        $this->assertStringContainsString('$.output.resolvedAddresses[0].classification: must be one of', $e->getErrorsAsString());
        $this->assertInstanceOf(\stdClass::class, $e->getData());
    }

    /**
     * Without a client one is built from the arguments
     * @return void
     */
    public function testWithoutAClientOneIsBuiltFromTheArguments(): void
    {
        $auth = StubAuth::make();
        $built = Reflect::prop(new FedexAddressValidation($auth, '  de_DE ', 99, 99), AbstractFedexApi::class, 'client');

        $this->assertInstanceOf(FedexClient::class, $built);
        $this->assertSame('de_DE', Reflect::prop($built, FedexClient::class, 'locale'));
        $this->assertSame(30, Reflect::prop($built, FedexClient::class, 'timeout'));
        $this->assertSame(10, Reflect::prop($built, FedexClient::class, 'connectTimeout'));
        $this->assertSame($auth, Reflect::prop($built, FedexClient::class, 'auth'));
    }

    /**
     * The constructor and the signatures of the methods
     * @return void
     */
    public function testTheConstructorAndTheSignaturesOfTheMethods(): void
    {
        $constructor = new \ReflectionMethod(FedexAddressValidation::class, '__construct');
        $this->assertSame(['auth', 'locale', 'timeout', 'connectTimeout', 'client'], array_map(function (\ReflectionParameter $p) {
            return $p->getName();
        }, $constructor->getParameters()));
        $this->assertTrue($constructor->getParameters()[4]->allowsNull());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());

        $validate = new \ReflectionMethod(FedexAddressValidation::class, 'validate');
        $this->assertFalse($validate->getParameters()[0]->hasType(), 'the address has no class type: it is an array or an object');
        $this->assertSame('options', $validate->getParameters()[1]->getName());
        $this->assertSame(1, $validate->getNumberOfRequiredParameters());

        $validateArray = new \ReflectionMethod(FedexAddressValidation::class, 'validateArray');
        $this->assertSame('array', $validateArray->getParameters()[0]->getType()->getName());
        $this->assertSame('options', $validateArray->getParameters()[1]->getName());
        $this->assertSame(1, $validateArray->getNumberOfRequiredParameters());

        $this->assertTrue((new \ReflectionMethod(FedexAddressValidation::class, 'inputSchemas'))->isPublic());
        $this->assertSame(['Full_Schema_Validate_Address', 'AddressResolutionControlParameters', 'ResolveContactAndAddress', 'Address'], array_keys($this->api()->inputSchemas()));
    }

    /**
     * The classes and the endpoint
     * @return void
     */
    public function testTheClassesAndTheEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(FedexAddressValidation::class, AbstractFedexApi::class));
        $this->assertTrue(is_subclass_of(FedexAddressValidationException::class, FedexException::class));
        $this->assertSame('address/v1/addresses/resolve', FedexAddressValidation::ENDPOINT);
        $spec = json_decode(file_get_contents(__DIR__ . '/../../../schemas/address-validation.json'), true);
        $this->assertArrayHasKey('/' . FedexAddressValidation::ENDPOINT, $spec['paths']);
    }
}
