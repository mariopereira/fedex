<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Api;

use Mariopereira\Fedex\Api\FedexPostalCodeValidation;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexPostalCodeValidationException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Tests\Support\ExposedPostal;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\HookPostal;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the postal code validation: the checks on the parameters, the request, the version and the response
 */
final class FedexPostalCodeValidationTest extends TestCase
{
    private const DATE = '2026-10-21';

    private const OK_RESPONSE = '{"transactionId":"T","output":{"countryCode":"US"}}';

    /**
     * The transport of the last validator made by validator()
     * @var FakeTransport
     */
    private $transport;

    /**
     * A postal code validator over a fake transport
     * @param string $class Class to make: FedexPostalCodeValidation or a subclass of it
     * @param string $body Body of the response of the transport
     * @param bool $sandbox Sandbox or production
     * @param mixed ...$versionArguments Major, minor and patch (1, 1 and 1 when they are not given)
     * @return FedexPostalCodeValidation
     */
    private function validator(string $class = FedexPostalCodeValidation::class, string $body = self::OK_RESPONSE, bool $sandbox = true, ...$versionArguments): FedexPostalCodeValidation
    {
        $this->transport = new FakeTransport([], Responses::make(200, $body));
        $auth = new StubAuth($sandbox, 'client_credentials', 'id', 'secret');
        $client = new FedexClient($auth, 'en_US', 30, 10, $this->transport);
        $version = array_pad($versionArguments, 3, 1);

        return new $class($auth, 'en_US', 30, 10, $version[0], $version[1], $version[2], $client);
    }

    /**
     * Call validate() and give what happened
     * @param array $arguments Arguments of validate()
     * @return array The body that was sent (null if nothing was), the exception that was thrown (null if none) and the validator
     */
    private function attempt(array $arguments): array
    {
        $api = $this->validator();

        try {
            $api->validate(...$arguments);
            $thrown = null;
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        return [$this->transport->bodyOf(0), $thrown, $api];
    }

    /**
     * Make a response from an array, as it comes from json_decode()
     * @param array $data The response
     * @return \stdClass
     */
    private static function response(array $data): \stdClass
    {
        return json_decode(json_encode($data));
    }

    /**
     * A copy of an array without the item at a path
     * @param array $data The array
     * @param array $path The keys that lead to the item
     * @return array
     */
    private static function without(array $data, array $path): array
    {
        $ref = &$data;

        foreach (array_slice($path, 0, -1) as $key) {
            $ref = &$ref[$key];
        }

        unset($ref[end($path)]);

        return $data;
    }

    /**
     * A response with every field of the schema
     * @return array
     */
    private static function fullResponse(): array
    {
        return [
            'transactionId' => 'T-1',
            'customerTransactionId' => 'C-1',
            'output' => [
                'countryCode' => 'US',
                'cityFirstInitials' => 'M',
                'stateOrProvinceCode' => 'TN',
                'alerts' => [['code' => 'N', 'alertType' => 'NOTE', 'message' => 'm', 'parameterList' => [['key' => 'k', 'value' => 'v']]]],
                'locationDescriptions' => [['locationId' => 'MAAPD', 'locationNumber' => '6955', 'serviceArea' => 'AA', 'airportId' => 'BLR']],
                'cleanedPostalCode' => '38017',
            ],
        ];
    }

    /**
     * Give a response to the check of the response and say what it did
     * @param mixed $response The response
     * @return string 'ACCEPTED', or the errors that rejected it
     */
    private function verdict($response): string
    {
        $api = $this->validator(ExposedPostal::class);

        try {
            $api->callValidate($response);

            return 'ACCEPTED';
        } catch (FedexPostalCodeValidationException $e) {
            return implode(' | ', $e->getErrors()) ?: $e->getMessage();
        }
    }

    // ---------------------------------------------------------------- the request

    /**
     * The endpoint
     * @return void
     */
    public function testTheEndpoint(): void
    {
        $this->assertSame('country/v1/postal/validate', FedexPostalCodeValidation::ENDPOINT);
    }

    /**
     * The request that is sent
     * @return void
     */
    public function testTheRequestThatIsSent(): void
    {
        $api = $this->validator(FedexPostalCodeValidation::class, json_encode(self::fullResponse()));

        $result = $api->validate('FDXG', 'US', 'TN', '38017', self::DATE, null, true);
        $call = $this->transport->calls[0];
        $body = $this->transport->bodyOf(0);

        $this->assertCount(1, $this->transport->calls);
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/country/v1/postal/validate', $call['url']);
        $this->assertStringNotContainsString('address/v1', $call['url']);
        $this->assertSame(
            ['carrierCode' => 'FDXG', 'countryCode' => 'US', 'stateOrProvinceCode' => 'TN', 'postalCode' => '38017', 'shipDate' => self::DATE, 'checkForMismatch' => true, 'version' => ['major' => 1, 'minor' => 1, 'patch' => 1]],
            $body
        );
        $this->assertSame('38017', $result->output->cleanedPostalCode);
    }

    /**
     * The customer transaction ID is a UUID
     * @return void
     */
    public function testTheCustomerTransactionIdIsAUuid(): void
    {
        $api = $this->validator();

        $api->validate('FDXG', 'US', 'TN', '38017', self::DATE);

        $this->assertNotEmpty(preg_grep('/^X-customer-transaction-id: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $this->transport->calls[0]['headers']));
    }

    /**
     * The production URL
     * @return void
     */
    public function testTheProductionUrl(): void
    {
        $api = $this->validator(FedexPostalCodeValidation::class, self::OK_RESPONSE, false);

        $api->validate('FDXE', 'US', 'TN', '38017', self::DATE);

        $this->assertSame('https://apis.fedex.com/country/v1/postal/validate', $this->transport->calls[0]['url']);
    }

    /**
     * The signature of validate
     * @return void
     */
    public function testTheSignatureOfValidate(): void
    {
        $method = new \ReflectionMethod(FedexPostalCodeValidation::class, 'validate');
        $names = array_map(function (\ReflectionParameter $p) {
            return $p->getName();
        }, $method->getParameters());

        $this->assertSame(['carrierCode', 'countryCode', 'stateOrProvinceCode', 'postalCode', 'shipDate', 'routingCode', 'checkForMismatch', 'city'], $names);
        $this->assertTrue($method->getParameters()[7]->isOptional());
        $this->assertTrue($method->getParameters()[7]->allowsNull());
        $this->assertSame(5, $method->getNumberOfRequiredParameters());
    }

    // ---------------------------------------------------------------- carrier code

    /**
     * The carrier codes are the enum of the schema
     * @return void
     */
    public function testTheCarrierCodesAreTheEnumOfTheSchema(): void
    {
        $spec = json_decode(file_get_contents(__DIR__ . '/../../../schemas/postal-code.json'), true);
        $enum = $spec['components']['schemas']['FullSchema-ValidatePostal']['properties']['carrierCode']['enum'];

        $this->assertSame($enum, FedexPostalCodeValidation::CARRIER_CODES, 'same values in the same order');
    }

    /**
     * @dataProvider validCarriersProvider
     * @param string $code A valid carrier code
     */
    public function testAValidCarrierCodeIsSentUnchanged(string $code): void
    {
        [$body, $error] = $this->attempt([$code, 'US', 'TN', '38017', self::DATE]);

        $this->assertNull($error);
        $this->assertSame($code, $body['carrierCode']);
    }

    /**
     * The cases of testAValidCarrierCodeIsSentUnchanged(): a valid carrier code is sent unchanged
     * @return array
     */
    public function validCarriersProvider(): array
    {
        return [['FDXE'], ['FDXG'], ['FXSP'], ['FDXC'], ['FXCC']];
    }

    /**
     * @dataProvider carriersToNormalizeProvider
     * @param string $given What was given
     * @param string $expected What is sent
     */
    public function testTheCarrierCodeIsNormalized(string $given, string $expected): void
    {
        [$body, $error] = $this->attempt([$given, 'US', 'TN', '38017', self::DATE]);

        $this->assertNull($error);
        $this->assertSame($expected, $body['carrierCode']);
    }

    /**
     * The cases of testTheCarrierCodeIsNormalized(): the carrier code is normalized
     * @return array
     */
    public function carriersToNormalizeProvider(): array
    {
        return [['fdxg', 'FDXG'], [' FdxE ', 'FDXE'], ["\tfxsp\n", 'FXSP'], ['fdxc', 'FDXC'], ['FxCc', 'FXCC']];
    }

    /**
     * @dataProvider invalidCarriersProvider
     * @param string $code A carrier code that is not valid
     */
    public function testAnInvalidCarrierCodeIsRefusedAndNothingIsSent(string $code): void
    {
        [, $error] = $this->attempt([$code, 'US', 'TN', '38017', self::DATE]);

        $this->assertInstanceOf(FedexPostalCodeValidationException::class, $error);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $error->getCode());
        $this->assertStringContainsString('valid codes: FDXE, FDXG, FXSP, FDXC, FXCC', $error->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testAnInvalidCarrierCodeIsRefusedAndNothingIsSent(): an invalid carrier code is refused and nothing is sent
     * @return array
     */
    public function invalidCarriersProvider(): array
    {
        return [['XXXX'], ['FEDEX'], ['FDX'], ['FDXGG'], ['UPS'], ['0'], ['1234'], ['FD XG'], ['FDXG,FDXE']];
    }

    /**
     * @dataProvider blankProvider
     * @param string $blank A blank carrier code
     */
    public function testABlankCarrierCodeIsMissing(string $blank): void
    {
        [, $error] = $this->attempt([$blank, 'US', 'TN', '38017', self::DATE]);

        $this->assertInstanceOf(FedexPostalCodeValidationException::class, $error);
        $this->assertSame(FedexException::EC_MISSING_PARAMETER, $error->getCode());
        $this->assertSame('Missing carrier code', $error->getMessage());
        $this->assertSame([], $this->transport->calls);
    }

    /**
     * The cases of testABlankCarrierCodeIsMissing(): a blank carrier code is missing
     * @return array
     */
    public function blankProvider(): array
    {
        return [[''], ['  '], ["\t"], ["\n"]];
    }

    // ---------------------------------------------------------------- city

    /**
     * The city is sent when given
     * @return void
     */
    public function testTheCityIsSentWhenGiven(): void
    {
        [$body, $error] = $this->attempt(['FDXG', 'US', 'TN', '38017', self::DATE, null, null, 'MEMPHIS']);

        $this->assertNull($error);
        $this->assertSame('MEMPHIS', $body['city']);
        $this->assertSame(['FDXG', 'US', 'TN', '38017', self::DATE], [$body['carrierCode'], $body['countryCode'], $body['stateOrProvinceCode'], $body['postalCode'], $body['shipDate']]);
    }

    /**
     * The city is trimmed
     * @return void
     */
    public function testTheCityIsTrimmed(): void
    {
        [$body] = $this->attempt(['FDXG', 'US', 'TN', '38017', self::DATE, null, null, '  Memphis  ']);

        $this->assertSame('Memphis', $body['city']);
    }

    /**
     * The city travels with the routing code and the mismatch flag
     * @return void
     */
    public function testTheCityTravelsWithTheRoutingCodeAndTheMismatchFlag(): void
    {
        [$body] = $this->attempt(['FDXG', 'US', 'TN', '38017', self::DATE, 'R1', true, 'Memphis']);

        $this->assertSame('Memphis', $body['city']);
        $this->assertSame('R1', $body['routingCode']);
        $this->assertTrue($body['checkForMismatch']);
    }

    /**
     * @dataProvider noCityProvider
     * @param string|null $none Something that is not a city
     */
    public function testNoCityIsNeededOutsideAustralia($none): void
    {
        [$body, $error] = $this->attempt(['FDXG', 'US', 'TN', '38017', self::DATE, null, null, $none]);

        $this->assertNull($error);
        $this->assertArrayNotHasKey('city', $body);
    }

    /**
     * The cases of testNoCityIsNeededOutsideAustralia(): no city is needed outside australia
     * @return array
     */
    public function noCityProvider(): array
    {
        return [[null], [''], ['   '], ["\t"]];
    }

    /**
     * @dataProvider australiaProvider
     * @param string $country Australia, written in some way
     */
    public function testAustraliaNeedsACity(string $country): void
    {
        foreach ([null, '', '   '] as $none) {
            [, $error, $api] = $this->attempt(['FDXE', $country, 'NSW', '2000', self::DATE, null, null, $none]);

            $this->assertInstanceOf(FedexPostalCodeValidationException::class, $error);
            $this->assertSame(FedexException::EC_MISSING_PARAMETER, $error->getCode());
            $this->assertSame('Missing city (required when the country code is AU)', $error->getMessage());
            $this->assertSame([], $this->transport->calls);
        }

        [$body, $error] = $this->attempt(['FDXE', $country, 'NSW', '2000', self::DATE, null, null, 'Sydney']);

        $this->assertNull($error);
        $this->assertSame('Sydney', $body['city']);
    }

    /**
     * The cases of testAustraliaNeedsACity(): australia needs a city
     * @return array
     */
    public function australiaProvider(): array
    {
        return [['AU'], ['au'], [' Au '], ["aU\n"]];
    }

    /**
     * @dataProvider otherCountriesProvider
     * @param string $country A country that is not Australia
     */
    public function testOnlyAustraliaNeedsACity(string $country): void
    {
        [$body, $error] = $this->attempt(['FDXE', $country, '', '12345', self::DATE]);

        $this->assertNull($error);
        $this->assertArrayNotHasKey('city', $body);
    }

    /**
     * The cases of testOnlyAustraliaNeedsACity(): only australia needs a city
     * @return array
     */
    public function otherCountriesProvider(): array
    {
        return [['US'], ['DE'], ['CA'], ['GB'], ['AUS'], ['A']];
    }

    // ---------------------------------------------------------------- the order of the checks

    /**
     * The carrier is reported before the city
     * @return void
     */
    public function testTheCarrierIsReportedBeforeTheCity(): void
    {
        [, $error] = $this->attempt(['XXXX', 'AU', 'NSW', '2000', self::DATE]);

        $this->assertSame(FedexException::EC_BAD_PARAMETER, $error->getCode());
    }

    /**
     * The other checks are unchanged
     * @return void
     */
    public function testTheOtherChecksAreUnchanged(): void
    {
        [, $error] = $this->attempt(['FDXE', 'AU', 'NSW', '', self::DATE]);
        $this->assertSame('Missing postal code', $error->getMessage(), 'the postal code is reported before the city');

        [, $error] = $this->attempt(['FDXE', 'AU', 'NSW', '2000', '2026-13-45']);
        $this->assertSame('Ship date must be a valid YYYY-MM-DD date', $error->getMessage());

        [, $error] = $this->attempt(['FDXE', '', 'NSW', '2000', self::DATE]);
        $this->assertSame('Missing country code', $error->getMessage());

        [, $error] = $this->attempt(['FDXE', 'US', 'TN', '38017', '']);
        $this->assertSame('Missing ship date', $error->getMessage());
    }

    /**
     * A subclass can override the protected hooks
     * @return void
     */
    public function testASubclassCanOverrideTheProtectedHooks(): void
    {
        $api = $this->validator(HookPostal::class);

        $api->validate('fdxg', 'AU', 'NSW', '2000', self::DATE, null, null, ' Sydney ');
        $body = $this->transport->bodyOf(0);

        $this->assertSame(['FDXG', 'Sydney'], $api->seen['check'], 'checkParameters() sees the normalized carrier and the trimmed city');
        $this->assertSame(['FDXG', 'Sydney'], $api->seen['body'], 'body() receives the normalized values');
        $this->assertSame('Sydney', $body['city']);
        $this->assertSame('FDXG', $body['carrierCode']);
    }

    // ---------------------------------------------------------------- the version

    /**
     * Send a validation and give the version in the request
     * @param FedexPostalCodeValidation $api The validator
     * @return array|null The version, or null when the request has none
     */
    private function versionSent(FedexPostalCodeValidation $api): ?array
    {
        $api->validate('FDXE', 'US', 'TX', '75063', self::DATE);
        $calls = $this->transport->calls;

        return json_decode($calls[count($calls) - 1]['body'], true)['version'] ?? null;
    }

    /**
     * The version is one one one by default
     * @return void
     */
    public function testTheVersionIsOneOneOneByDefault(): void
    {
        $api = $this->validator();
        $api->validate('FDXE', 'US', 'TX', '75063', self::DATE);
        $raw = $this->transport->calls[0]['body'];

        $this->assertSame(['major' => 1, 'minor' => 1, 'patch' => 1], json_decode($raw, true)['version']);
        $this->assertStringEndsWith('"version":{"major":1,"minor":1,"patch":1}}', $raw, 'the version comes last, in the order major, minor, patch');
    }

    /**
     * The version from the constructor
     * @return void
     */
    public function testTheVersionFromTheConstructor(): void
    {
        $this->assertSame(['major' => 2, 'minor' => 3, 'patch' => 4], $this->versionSent($this->validator(FedexPostalCodeValidation::class, self::OK_RESPONSE, true, 2, 3, 4)));
    }

    /**
     * The constructor keeps its parameters
     * @return void
     */
    public function testTheConstructorKeepsItsParameters(): void
    {
        $method = new \ReflectionMethod(FedexPostalCodeValidation::class, '__construct');
        $parameters = $method->getParameters();

        $this->assertSame(['auth', 'locale', 'timeout', 'connectTimeout', 'majorVersion', 'minorVersion', 'patch', 'client'], array_map(function (\ReflectionParameter $p) {
            return $p->getName();
        }, $parameters));

        foreach ([4, 5, 6] as $i) {
            $this->assertTrue($parameters[$i]->allowsNull());
            $this->assertSame(1, $parameters[$i]->getDefaultValue());
        }
    }

    /**
     * Null leaves the version out
     * @return void
     */
    public function testNullLeavesTheVersionOut(): void
    {
        $api = $this->validator(FedexPostalCodeValidation::class, self::OK_RESPONSE, true, null, null, null);
        $api->validate('FDXE', 'US', 'TX', '75063', self::DATE);

        $this->assertSame(['carrierCode' => 'FDXE', 'countryCode' => 'US', 'stateOrProvinceCode' => 'TX', 'postalCode' => '75063', 'shipDate' => self::DATE], $this->transport->bodyOf(0));
        $this->assertStringNotContainsString('version', $this->transport->calls[0]['body']);
    }

    /**
     * @dataProvider partialVersionsProvider
     * @param int|null $major The major number
     * @param int|null $minor The minor number
     * @param int|null $patch The patch number
     * @param array $expected The version that has to be sent
     */
    public function testOnlyTheNumbersThatAreSetAreSent(?int $major, ?int $minor, ?int $patch, array $expected): void
    {
        $this->assertSame($expected, $this->versionSent($this->validator(FedexPostalCodeValidation::class, self::OK_RESPONSE, true, $major, $minor, $patch)));
    }

    /**
     * The cases of testOnlyTheNumbersThatAreSetAreSent(): only the numbers that are set are sent
     * @return array
     */
    public function partialVersionsProvider(): array
    {
        return [
            'major only' => [3, null, null, ['major' => 3]],
            'minor only' => [null, 2, null, ['minor' => 2]],
            'patch only' => [null, null, 5, ['patch' => 5]],
            'major and patch' => [1, null, 2, ['major' => 1, 'patch' => 2]],
            'minor and patch' => [null, 1, 1, ['minor' => 1, 'patch' => 1]],
            'zeros are numbers' => [0, 0, 0, ['major' => 0, 'minor' => 0, 'patch' => 0]],
            'a zero alone' => [null, null, 0, ['patch' => 0]],
        ];
    }

    /**
     * Set API version
     * @return void
     */
    public function testSetApiVersion(): void
    {
        $api = $this->validator();

        $this->assertSame($api, $api->setAPIversion(5, 6, 7), 'it is fluent');
        $this->assertSame(['major' => 5, 'minor' => 6, 'patch' => 7], $this->versionSent($api));

        $api->setAPIversion(null, null, null);
        $this->assertNull($this->versionSent($api), 'with three nulls the version is left out');
        $this->assertCount(2, $this->transport->calls, 'and the call still goes out');

        $api->setAPIversion(1, 1, 1);
        $this->assertSame(['major' => 1, 'minor' => 1, 'patch' => 1], $this->versionSent($api));

        $api->setAPIversion(4, null, 6);
        $this->assertSame(['major' => 4, 'patch' => 6], $this->versionSent($api));
    }

    /**
     * @dataProvider validVersionsProvider
     * @param array $arguments Arguments of setAPIversion()
     */
    public function testZeroIsAValidNumberForSetApiVersion(array $arguments): void
    {
        $api = $this->validator(FedexPostalCodeValidation::class, self::OK_RESPONSE, true, 2, 3, 4);

        $api->setAPIversion(...$arguments);

        $this->assertSame(array_filter(['major' => $arguments[0], 'minor' => $arguments[1], 'patch' => $arguments[2]], function ($n) {
            return $n !== null;
        }), $this->versionSent($api));
    }

    /**
     * The cases of testZeroIsAValidNumberForSetApiVersion(): zero is a valid number for set API version
     * @return array
     */
    public function validVersionsProvider(): array
    {
        return [[[0, 0, 0]], [[0, 1, 1]], [[1, 0, 1]], [[1, 1, 0]], [[0, null, null]], [[null, 0, null]]];
    }

    /**
     * @dataProvider negativeVersionsProvider
     * @param array $arguments Arguments of setAPIversion(), one of them negative
     */
    public function testANegativeNumberIsRefusedAndTheVersionDoesNotChange(array $arguments): void
    {
        $api = $this->validator(FedexPostalCodeValidation::class, self::OK_RESPONSE, true, 2, 3, 4);

        try {
            $api->setAPIversion(...$arguments);
            $this->fail('A negative number had to be refused');
        } catch (FedexPostalCodeValidationException $e) {
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        }

        $this->assertSame(['major' => 2, 'minor' => 3, 'patch' => 4], $this->versionSent($api));
    }

    /**
     * The cases of testANegativeNumberIsRefusedAndTheVersionDoesNotChange(): a negative number is refused and the version does not change
     * @return array
     */
    public function negativeVersionsProvider(): array
    {
        return [[[-1, 1, 1]], [[1, -1, 1]], [[1, 1, -1]], [[-1, null, null]], [[null, -1, null]], [[null, null, -1]], [[0, 0, -1]]];
    }

    /**
     * Set API version accepts null
     * @return void
     */
    public function testSetApiVersionAcceptsNull(): void
    {
        $method = new \ReflectionMethod(FedexPostalCodeValidation::class, 'setAPIversion');

        $this->assertCount(3, $method->getParameters());

        foreach ($method->getParameters() as $parameter) {
            $this->assertTrue($parameter->allowsNull());
        }

        $this->assertSame(FedexPostalCodeValidation::class, $method->getReturnType()->getName());
    }

    /**
     * Without a version the checks still run
     * @return void
     */
    public function testWithoutAVersionTheChecksStillRun(): void
    {
        $api = $this->validator(FedexPostalCodeValidation::class, self::OK_RESPONSE, true, null, null, null);

        try {
            $api->validate('XXXX', 'US', 'TX', '75063', self::DATE);
            $this->fail('The carrier code had to be refused');
        } catch (FedexPostalCodeValidationException $e) {
            $this->assertSame([], $this->transport->calls);
        }

        $api->validate('fdxe', 'AU', 'NSW', '2000', self::DATE, null, null, 'Sydney');

        $this->assertSame(
            ['carrierCode' => 'FDXE', 'countryCode' => 'AU', 'stateOrProvinceCode' => 'NSW', 'postalCode' => '2000', 'shipDate' => self::DATE, 'city' => 'Sydney'],
            $this->transport->bodyOf(0)
        );
    }

    // ---------------------------------------------------------------- the response

    /**
     * A response with every field of the schema is accepted
     * @return void
     */
    public function testAResponseWithEveryFieldOfTheSchemaIsAccepted(): void
    {
        $this->assertSame('ACCEPTED', $this->verdict(self::response(self::fullResponse())));
    }

    /**
     * Every field of the response is optional
     * @return void
     */
    public function testEveryFieldOfTheResponseIsOptional(): void
    {
        $this->assertSame('ACCEPTED', $this->verdict(json_decode('{}')), 'an empty object');
        $this->assertSame('ACCEPTED', $this->verdict(self::response(['transactionId' => 'T'])), 'only the transaction id');
        $this->assertSame('ACCEPTED', $this->verdict(self::response(['output' => ['alerts' => [], 'locationDescriptions' => []]])), 'empty lists');
        $this->assertSame('ACCEPTED', $this->verdict(self::response(['output' => ['cleanPostalCode' => '1']])), 'a field that the schema does not have is tolerated');
    }

    /**
     * @dataProvider optionalFieldsProvider
     * @param array $path The path of a field that can be missing
     */
    public function testAFieldThatTheSchemaDoesNotRequireCanBeMissing(array $path): void
    {
        $this->assertSame('ACCEPTED', $this->verdict(self::response(self::without(self::fullResponse(), $path))));
    }

    /**
     * The cases of testAFieldThatTheSchemaDoesNotRequireCanBeMissing(): a field that the schema does not require can be missing
     * @return array
     */
    public function optionalFieldsProvider(): array
    {
        return [
            'transactionId' => [['transactionId']],
            'customerTransactionId' => [['customerTransactionId']],
            'output.countryCode' => [['output', 'countryCode']],
            'output.cityFirstInitials' => [['output', 'cityFirstInitials']],
            'output.stateOrProvinceCode' => [['output', 'stateOrProvinceCode']],
            'output.alerts' => [['output', 'alerts']],
            'output.locationDescriptions' => [['output', 'locationDescriptions']],
            'output.cleanedPostalCode' => [['output', 'cleanedPostalCode']],
            'alert code' => [['output', 'alerts', 0, 'code']],
            'alert alertType' => [['output', 'alerts', 0, 'alertType']],
            'alert parameterList' => [['output', 'alerts', 0, 'parameterList']],
            'alert message' => [['output', 'alerts', 0, 'message']],
        ];
    }

    /**
     * A parameter without key and value is accepted
     * @return void
     */
    public function testAParameterWithoutKeyAndValueIsAccepted(): void
    {
        $response = array_replace_recursive(self::fullResponse(), ['output' => ['alerts' => [['parameterList' => [[]]]]]]);

        $this->assertSame('ACCEPTED', $this->verdict(self::response($response)));
    }

    /**
     * A JSON list is not a response
     * @return void
     */
    public function testAJsonListIsNotAResponse(): void
    {
        $this->assertSame('Invalid response', $this->verdict(json_decode('[]')));
        $this->assertSame('Invalid response', $this->verdict('oops'));
    }

    /**
     * An alert type outside the enum is refused
     * @return void
     */
    public function testAnAlertTypeOutsideTheEnumIsRefused(): void
    {
        $verdict = $this->verdict(self::response(array_replace_recursive(self::fullResponse(), ['output' => ['alerts' => [['alertType' => 'INFO']]]])));

        $this->assertStringContainsString('must be one of ["NOTE","WARNING"]', $verdict);
    }

    /**
     * @dataProvider requiredLocationFieldsProvider
     * @param string $field A field that every location description needs
     */
    public function testALocationDescriptionNeedsItsFields(string $field): void
    {
        $verdict = $this->verdict(self::response(self::without(self::fullResponse(), ['output', 'locationDescriptions', 0, $field])));

        $this->assertStringContainsString("$field: required key is missing", $verdict);
    }

    /**
     * The cases of testALocationDescriptionNeedsItsFields(): a location description needs its fields
     * @return array
     */
    public function requiredLocationFieldsProvider(): array
    {
        return [['locationId'], ['locationNumber'], ['serviceArea'], ['airportId']];
    }

    /**
     * Values of the wrong type are refused
     * @return void
     */
    public function testValuesOfTheWrongTypeAreRefused(): void
    {
        $this->assertStringContainsString(
            'cleanedPostalCode: expected string, got integer',
            $this->verdict(self::response(array_replace_recursive(self::fullResponse(), ['output' => ['cleanedPostalCode' => 38017]])))
        );

        $this->assertStringContainsString('transactionId', $this->verdict(self::response(array_replace_recursive(self::fullResponse(), ['transactionId' => ['x']]))));
        $this->assertNotSame('ACCEPTED', $this->verdict(self::response(array_replace_recursive(self::fullResponse(), ['output' => ['stateOrProvinceCode' => null]]))), 'null where the schema says string');
        $this->assertNotSame('ACCEPTED', $this->verdict(self::response(array_replace_recursive(self::fullResponse(), ['output' => 'oops']))), 'an output that is not an object');
    }

    /**
     * A rejected response carries the errors
     * @return void
     */
    public function testARejectedResponseCarriesTheErrors(): void
    {
        $api = $this->validator(ExposedPostal::class);

        try {
            $api->callValidate(self::response(array_replace_recursive(self::fullResponse(), ['output' => ['alerts' => [['alertType' => 'INFO']]]])));
            $this->fail('The response had to be refused');
        } catch (FedexPostalCodeValidationException $e) {
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertCount(1, $e->getErrors());
            $this->assertStringContainsString('alertType', $e->getErrorsAsString());
        }
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

            $api = new FedexPostalCodeValidation($auth, 'en_US', 30, 10, 1, 1, 1, $client);
            $error = null;

            try {
                $api->validate('FDXG', 'US', 'TN', '38017', self::DATE);
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
