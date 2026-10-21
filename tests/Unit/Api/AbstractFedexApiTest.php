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
use Mariopereira\Fedex\Api\FedexPostalCodeValidation;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Http\CurlTransport;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Reflect;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of what the API classes share: the client that they use, given to them or built by them
 */
final class AbstractFedexApiTest extends TestCase
{
    private const RESPONSE = '{"transactionId":"T","output":{"countryCode":"US","cleanedPostalCode":"38017"}}';

    /**
     * The injected client is the one that is used
     * @return void
     */
    public function testTheInjectedClientIsTheOneThatIsUsed(): void
    {
        $transport = new FakeTransport([], Responses::make(200, self::RESPONSE));
        $client = new FedexClient(StubAuth::make(), 'pt_PT', 12, 7, $transport);
        $postal = new FedexPostalCodeValidation(StubAuth::make(), 'de_DE', 25, 9, 1, 1, 1, $client);

        $result = $postal->validate('FDXG', 'US', 'TN', '38017', '2026-10-21');
        $call = $transport->calls[0];

        $this->assertSame($client, Reflect::prop($postal, AbstractFedexApi::class, 'client'));
        $this->assertCount(1, $transport->calls);
        $this->assertSame('38017', $result->output->cleanedPostalCode);
        $this->assertContains('X-locale: pt_PT', $call['headers'], 'the injected client keeps its own locale, not the one given to the API class');
        $this->assertSame(12, $call['timeout']);
        $this->assertSame(7, $call['connectTimeout']);
        $this->assertSame('https://apis-sandbox.fedex.com/country/v1/postal/validate', $call['url']);
        $this->assertSame('FDXG', $transport->bodyOf(0)['carrierCode']);
    }

    /**
     * The injected client is used by the address validation too
     * @return void
     */
    public function testTheInjectedClientIsUsedByTheAddressValidationToo(): void
    {
        $client = new FedexClient(StubAuth::make(), 'en_US', 30, 10, new FakeTransport());
        $address = new FedexAddressValidation(StubAuth::make(), 'en_US', 30, 10, $client);

        $this->assertSame($client, Reflect::prop($address, AbstractFedexApi::class, 'client'));
    }

    /**
     * Without a client one is built from the arguments
     * @return void
     */
    public function testWithoutAClientOneIsBuiltFromTheArguments(): void
    {
        $auth = StubAuth::make();
        $postal = new FedexPostalCodeValidation($auth, '  de_DE  ', 99, 99);

        $built = Reflect::prop($postal, AbstractFedexApi::class, 'client');

        $this->assertInstanceOf(FedexClient::class, $built);
        $this->assertSame('de_DE', Reflect::prop($built, FedexClient::class, 'locale'), 'the locale is trimmed');
        $this->assertSame(30, Reflect::prop($built, FedexClient::class, 'timeout'), 'the timeouts are capped');
        $this->assertSame(10, Reflect::prop($built, FedexClient::class, 'connectTimeout'));
        $this->assertInstanceOf(CurlTransport::class, Reflect::prop($built, FedexClient::class, 'transport'));
        $this->assertSame($auth, Reflect::prop($built, FedexClient::class, 'auth'), 'the same authorization is used');
    }

    /**
     * @dataProvider apiClassesProvider
     * @param string $class An API class
     */
    public function testTheClientIsTheLastOptionalNullableParameterOfTheConstructor(string $class): void
    {
        $parameters = (new \ReflectionMethod($class, '__construct'))->getParameters();
        $last = $parameters[count($parameters) - 1];

        $this->assertSame('client', $last->getName());
        $this->assertTrue($last->isOptional());
        $this->assertTrue($last->allowsNull());
    }

    /**
     * The cases of testTheClientIsTheLastOptionalNullableParameterOfTheConstructor(): the client is the last optional nullable parameter of the constructor
     * @return array
     */
    public function apiClassesProvider(): array
    {
        return [[FedexPostalCodeValidation::class], [FedexAddressValidation::class]];
    }

    /**
     * The old positional construction still works
     * @return void
     */
    public function testTheOldPositionalConstructionStillWorks(): void
    {
        $this->assertInstanceOf(FedexPostalCodeValidation::class, new FedexPostalCodeValidation(StubAuth::make(), 'en_US', 30, 10, 2, 3, 4));
    }

    /**
     * The whole chain with fake transports
     * @return void
     */
    public function testTheWholeChainWithFakeTransports(): void
    {
        $authTransport = new FakeTransport([Responses::token(['access_token' => 'CHAINTOKEN'])]);
        $apiTransport = new FakeTransport([], Responses::make(200, self::RESPONSE));
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $authTransport);
        $postal = new FedexPostalCodeValidation($auth, 'en_US', 30, 10, 1, 1, 1, new FedexClient($auth, 'en_US', 30, 10, $apiTransport));

        $postal->validate('fdxe', 'AU', 'NSW', '2000', '2026-10-21', null, null, 'Sydney');
        $postal->validate('FDXE', 'US', 'TN', '38017', '2026-10-21');
        $body = $apiTransport->bodyOf(0);

        $this->assertCount(1, $authTransport->calls, 'one token request: the token is cached');
        $this->assertCount(2, $apiTransport->calls);
        $this->assertContains('Authorization: Bearer CHAINTOKEN', $apiTransport->calls[0]['headers']);
        $this->assertSame('FDXE', $body['carrierCode']);
        $this->assertSame('Sydney', $body['city']);
        $this->assertSame('AU', $body['countryCode']);
    }
}
