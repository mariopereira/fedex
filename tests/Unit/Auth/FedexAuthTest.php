<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Auth;

use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\CurlTransport;
use Mariopereira\Fedex\Tests\Support\ExposedAuth;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Reflect;
use Mariopereira\Fedex\Tests\Support\Responses;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the authorization: the token request, the checks on what comes back, and the cached token
 */
final class FedexAuthTest extends TestCase
{
    /**
     * An authorization over a fake transport
     * @param FakeTransport $transport The transport
     * @param bool $sandbox Sandbox or production
     * @return FedexAuth
     */
    private function authWith(FakeTransport $transport, bool $sandbox = true): FedexAuth
    {
        return new FedexAuth($sandbox, 'client_credentials', 'id', 'secret', null, null, $transport);
    }

    /**
     * Call authorize() and give the exception it throws
     * @param FedexAuth $auth The authorization
     * @return \Throwable
     */
    private function authorizeFails(FedexAuth $auth): \Throwable
    {
        try {
            $auth->authorize();
        } catch (\Throwable $e) {
            return $e;
        }

        $this->fail('authorize() had to throw');
    }

    /**
     * The JSON object of a token response, written with the fragments given
     * @param string $accessToken JSON of access_token, or '' to leave it out
     * @param string $tokenType JSON of token_type, or '' to leave it out
     * @param string $expiresIn JSON of expires_in, or '' to leave it out
     * @param string $extra Any other JSON members
     * @return \stdClass
     */
    private static function payload(string $accessToken, string $tokenType, string $expiresIn, string $extra = ''): \stdClass
    {
        $parts = [];

        if ($accessToken !== '') {
            $parts[] = '"access_token":' . $accessToken;
        }

        if ($tokenType !== '') {
            $parts[] = '"token_type":' . $tokenType;
        }

        if ($expiresIn !== '') {
            $parts[] = '"expires_in":' . $expiresIn;
        }

        if ($extra !== '') {
            $parts[] = $extra;
        }

        return json_decode('{' . implode(',', $parts) . '}');
    }

    // ---------------------------------------------------------------- the constructor

    /**
     * @dataProvider badConstructionProvider
     * @param array $arguments Arguments of the constructor
     * @param int $code The code that the exception has to have
     */
    public function testTheConstructorRefusesWhatIsWrong(array $arguments, int $code): void
    {
        try {
            new FedexAuth(...$arguments);
            $this->fail('The constructor had to throw');
        } catch (FedexAuthException $e) {
            $this->assertSame($code, $e->getCode());
        }
    }

    /**
     * The cases of testTheConstructorRefusesWhatIsWrong(): the constructor refuses what is wrong
     * @return array
     */
    public function badConstructionProvider(): array
    {
        return [
            'an invalid grant type' => [[true, 'bogus', 'id', 'secret'], FedexException::EC_BAD_PARAMETER],
            'no client id' => [[true, 'client_credentials', '', 'secret'], FedexException::EC_MISSING_PARAMETER],
            'no client secret' => [[true, 'client_credentials', 'id', ''], FedexException::EC_MISSING_PARAMETER],
            'a child grant without the child key' => [[true, 'client_pc_credentials', 'id', 'secret', '', 'CS'], FedexException::EC_MISSING_PARAMETER],
            'a child grant without the child secret' => [[true, 'csp_credentials', 'id', 'secret', 'CK', null], FedexException::EC_MISSING_PARAMETER],
            'a child key with a grant that does not use it' => [[true, 'client_credentials', 'id', 'secret', 'CK', null], FedexException::EC_BAD_PARAMETER],
            'a child secret with a grant that does not use it' => [[true, 'client_credentials', 'id', 'secret', null, 'CS'], FedexException::EC_BAD_PARAMETER],
        ];
    }

    /**
     * The constructor still validates when a transport is given
     * @return void
     */
    public function testTheConstructorStillValidatesWhenATransportIsGiven(): void
    {
        $this->expectException(FedexAuthException::class);
        $this->expectExceptionCode(FedexException::EC_BAD_PARAMETER);

        new FedexAuth(true, 'bogus', 'id', 'secret', null, null, new FakeTransport());
    }

    /**
     * The default transport is cURL
     * @return void
     */
    public function testTheDefaultTransportIsCurl(): void
    {
        $this->assertInstanceOf(CurlTransport::class, Reflect::prop(new FedexAuth(true, 'client_credentials', 'id', 'secret'), FedexAuth::class, 'transport'));
    }

    /**
     * In sandbox
     * @return void
     */
    public function testInSandbox(): void
    {
        $this->assertTrue((new FedexAuth(true, 'client_credentials', 'id', 'secret'))->inSandbox());
        $this->assertFalse((new FedexAuth(false, 'client_credentials', 'id', 'secret'))->inSandbox());
    }

    // ---------------------------------------------------------------- the token request

    /**
     * The token request in sandbox
     * @return void
     */
    public function testTheTokenRequestInSandbox(): void
    {
        $transport = new FakeTransport([Responses::token()]);
        $auth = $this->authWith($transport);

        $auth->authorize();
        $call = $transport->calls[0];
        parse_str($call['body'], $body);

        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/oauth/token', $call['url']);
        $this->assertSame(['Content-Type: application/x-www-form-urlencoded'], $call['headers']);
        $this->assertSame(['grant_type' => 'client_credentials', 'client_id' => 'id', 'client_secret' => 'secret'], $body);
        $this->assertSame(30, $call['timeout']);
        $this->assertSame(10, $call['connectTimeout']);
        $this->assertTrue($auth->hasToken());
        $this->assertSame('TOK', $auth->getToken());
        $this->assertSame('CXS', $auth->getTokenData(false)->scope);
    }

    /**
     * The token request in production
     * @return void
     */
    public function testTheTokenRequestInProduction(): void
    {
        $transport = new FakeTransport([Responses::token()]);

        $this->authWith($transport, false)->authorize();

        $this->assertSame('https://apis.fedex.com/oauth/token', $transport->calls[0]['url']);
    }

    /**
     * The timeouts reach the transport
     * @return void
     */
    public function testTheTimeoutsReachTheTransport(): void
    {
        $transport = new FakeTransport([Responses::token()]);

        $this->authWith($transport)->setTimeouts(20, 5)->authorize();

        $this->assertSame(20, $transport->calls[0]['timeout']);
        $this->assertSame(5, $transport->calls[0]['connectTimeout']);
    }

    /**
     * The timeouts have defaults are fluent and are validated
     * @return void
     */
    public function testTheTimeoutsHaveDefaultsAreFluentAndAreValidated(): void
    {
        $auth = ExposedAuth::make();

        $this->assertSame([30, 10], $auth->timeouts());
        $this->assertSame($auth, $auth->setTimeouts(20, 5));
        $this->assertSame([20, 5], $auth->timeouts());

        foreach ([[0, 5], [5, 0], [-1, -1]] as [$timeout, $connect]) {
            try {
                $auth->setTimeouts($timeout, $connect);
                $this->fail("setTimeouts($timeout, $connect) had to be refused");
            } catch (FedexAuthException $e) {
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            }
        }

        $this->assertSame([20, 5], $auth->timeouts(), 'the timeouts are unchanged after a refusal');
    }

    // ---------------------------------------------------------------- the child key

    /**
     * @dataProvider childGrantsProvider
     * @param string $grant A grant type that uses the child key
     */
    public function testTheChildKeyIsSentWithItsDefaultName(string $grant): void
    {
        $auth = new ExposedAuth(true, $grant, 'id', 'secret', 'CK', 'CS');
        $body = $auth->callBody();

        $this->assertSame('CK', $body['child_key']);
        $this->assertArrayNotHasKey('child_Key', $body);
        $this->assertSame('CS', $body['child_secret']);
        $this->assertSame($grant, $body['grant_type']);
        $this->assertSame('id', $body['client_id']);
        $this->assertSame('secret', $body['client_secret']);
        $this->assertSame(
            http_build_query(['grant_type' => $grant, 'client_id' => 'id', 'client_secret' => 'secret', 'child_key' => 'CK', 'child_secret' => 'CS']),
            $auth->rawBody(),
            'the format of the body is the one it always had'
        );
    }

    /**
     * @dataProvider childGrantsProvider
     * @param string $grant A grant type that uses the child key
     */
    public function testTheChildKeyCanBeSentWithTheNameOfTheSchema(string $grant): void
    {
        $auth = new ExposedAuth(true, $grant, 'id', 'secret', 'CK', 'CS');

        $this->assertSame($auth, $auth->setChildKeyField(FedexAuth::CHILD_KEY_FIELD_SCHEMA));
        $body = $auth->callBody();

        $this->assertSame('CK', $body['child_Key']);
        $this->assertArrayNotHasKey('child_key', $body);
        $this->assertSame('CS', $body['child_secret']);
        $this->assertArrayNotHasKey('child_Secret', $body);

        $auth->setChildKeyField(FedexAuth::CHILD_KEY_FIELD_LOWER);
        $body = $auth->callBody();
        $this->assertSame('CK', $body['child_key']);
        $this->assertArrayNotHasKey('child_Key', $body);

        $auth->setChildKeyField('child_Key')->setChildKeyField('child_key')->setChildKeyField('child_Key');
        $body = $auth->callBody();
        $this->assertSame('CK', $body['child_Key']);
        $this->assertArrayNotHasKey('child_key', $body, 'switching again and again still sends one name only');
    }

    /**
     * @dataProvider childGrantsProvider
     * @param string $grant A grant type that uses the child key
     */
    public function testAnInvalidNameForTheChildKeyIsRefusedAndNothingChanges(string $grant): void
    {
        $auth = new ExposedAuth(true, $grant, 'id', 'secret', 'CK', 'CS');

        foreach (['CHILD_KEY', 'Child_key', 'child_KEY', '', 'child_key ', ' child_key', 'childKey', 'child_Key,child_key'] as $name) {
            try {
                $auth->setChildKeyField($name);
                $this->fail("The name '$name' had to be refused");
            } catch (FedexAuthException $e) {
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            }
        }

        $body = $auth->callBody();
        $this->assertSame('CK', $body['child_key']);
        $this->assertArrayNotHasKey('child_Key', $body);
    }

    /**
     * The cases of testTheChildKeyIsSentWithItsDefaultName(), testTheChildKeyCanBeSentWithTheNameOfTheSchema() and testAnInvalidNameForTheChildKeyIsRefusedAndNothingChanges(): the child key is sent with its default name
     * @return array
     */
    public function childGrantsProvider(): array
    {
        return [['client_pc_credentials'], ['csp_credentials']];
    }

    /**
     * No child fields are sent with the client credentials grant
     * @return void
     */
    public function testNoChildFieldsAreSentWithTheClientCredentialsGrant(): void
    {
        $auth = ExposedAuth::make();

        $this->assertSame(http_build_query(['grant_type' => 'client_credentials', 'client_id' => 'id', 'client_secret' => 'secret']), $auth->rawBody());

        $auth->setChildKeyField(FedexAuth::CHILD_KEY_FIELD_SCHEMA);
        $this->assertSame(http_build_query(['grant_type' => 'client_credentials', 'client_id' => 'id', 'client_secret' => 'secret']), $auth->rawBody());
    }

    /**
     * The child key reaches the transport with the chosen name
     * @return void
     */
    public function testTheChildKeyReachesTheTransportWithTheChosenName(): void
    {
        foreach ([FedexAuth::CHILD_KEY_FIELD_LOWER, FedexAuth::CHILD_KEY_FIELD_SCHEMA] as $field) {
            $transport = new FakeTransport([Responses::token()]);
            $auth = new FedexAuth(true, 'client_pc_credentials', 'id', 'secret', 'CK', 'CS', $transport);

            $auth->setChildKeyField($field)->authorize();
            parse_str($transport->calls[0]['body'], $sent);

            $this->assertSame('CK', $sent[$field]);
            $this->assertSame('CS', $sent['child_secret']);
            $this->assertCount(1, array_intersect(['child_key', 'child_Key'], array_keys($sent)));
        }
    }

    // ---------------------------------------------------------------- what the server answers

    /**
     * @dataProvider failuresProvider
     * @param int $status HTTP status
     * @param string $body Response body
     * @param int $code Code of the exception
     * @param string|null $transactionId Transaction id of the exception
     * @param string $errors The errors, as a text
     */
    public function testAFailureAnswerIsAnAuthException(int $status, string $body, int $code, ?string $transactionId, string $errors): void
    {
        $auth = $this->authWith(new FakeTransport([Responses::make($status, $body)]));

        $e = $this->authorizeFails($auth);

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame($code, $e->getCode());
        $this->assertSame($status, $e->getStatusCode());
        $this->assertSame($transactionId, $e->getTransactionId());
        $this->assertSame($errors, $e->getErrorsAsString());
        $this->assertFalse($auth->hasToken());
    }

    /**
     * The cases of testAFailureAnswerIsAnAuthException(): a failure answer is an auth exception
     * @return array
     */
    public function failuresProvider(): array
    {
        $auth = FedexException::EC_UNABLE_TO_AUTHENTICATE;
        $transport = FedexException::EC_TRANSPORT_ERROR;

        return [
            '401 with a JSON error body' => [401, '{"transactionId":"T1","errors":[{"code":"NOT.AUTHORIZED.ERROR","message":"bad credentials"}]}', $auth, 'T1', 'NOT.AUTHORIZED.ERROR: bad credentials'],
            '401 with no body' => [401, '', $auth, null, ''],
            '401 with an html body' => [401, '<html>nope</html>', $auth, null, ''],
            '400 with a JSON error body' => [400, '{"transactionId":"T2","errors":[{"code":"BAD","message":"m"}]}', $transport, 'T2', 'BAD: m'],
            '500 with a JSON error body' => [500, '{"transactionId":"T3","errors":[{"code":"INTERNAL.SERVER.ERROR","message":"oops"}]}', $transport, 'T3', 'INTERNAL.SERVER.ERROR: oops'],
            '503 with no body' => [503, '', $transport, null, ''],
            '502 with an html body' => [502, '<html>bad gateway</html>', $transport, null, ''],
            '500 with errors sent as a plain string' => [500, '{"errors":"boom"}', $transport, null, 'boom'],
            '500 with transactionId as a list' => [500, '{"transactionId":["x"],"errors":[]}', $transport, null, ''],
            '429 with errors as an object' => [429, '{"errors":{"code":"RATE","message":"slow down"}}', $transport, null, 'RATE: slow down'],
        ];
    }

    /**
     * An invalid JSON answer is reported without keeping the body
     * @return void
     */
    public function testAnInvalidJsonAnswerIsReportedWithoutKeepingTheBody(): void
    {
        $e = $this->authorizeFails($this->authWith(new FakeTransport([Responses::make(200, 'not json at all')])));

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertStringStartsWith('Invalid data received from server (', $e->getMessage());
        $this->assertSame(200, $e->getStatusCode());
        $this->assertNull($e->getData(), 'the raw body is not kept in the exception');
    }

    /**
     * @dataProvider notAnObjectProvider
     * @param string $body A JSON value that is not an object
     */
    public function testAJsonValueThatIsNotAnObjectIsRefused(string $body): void
    {
        $e = $this->authorizeFails($this->authWith(new FakeTransport([Responses::make(200, $body)])));

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
    }

    /**
     * The cases of testAJsonValueThatIsNotAnObjectIsRefused(): a JSON value that is not an object is refused
     * @return array
     */
    public function notAnObjectProvider(): array
    {
        return [['"just a string"'], ['[1,2]'], ['5'], ['true']];
    }

    /**
     * A bad access token is refused and redacted
     * @return void
     */
    public function testABadAccessTokenIsRefusedAndRedacted(): void
    {
        $auth = $this->authWith(new FakeTransport([Responses::token(['access_token' => ['SECRETVALUE']])]));

        $e = $this->authorizeFails($auth);

        $this->assertSame('Invalid data received from server (access_token)', $e->getMessage());
        $this->assertStringNotContainsString('SECRETVALUE', json_encode($e->getData()));
    }

    /**
     * A transport failure is reported and no token is stored
     * @return void
     */
    public function testATransportFailureIsReportedAndNoTokenIsStored(): void
    {
        $auth = $this->authWith(new FakeTransport([Responses::make(0, '', 'Failed to connect to host port 443: Connection refused')]));

        $e = $this->authorizeFails($auth);

        $this->assertSame('Transport error: Failed to connect to host port 443: Connection refused', $e->getMessage());
        $this->assertSame(FedexException::EC_TRANSPORT_ERROR, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
        $this->assertFalse($auth->hasToken());
    }

    /**
     * A timeout is reported as a transport error
     * @return void
     */
    public function testATimeoutIsReportedAsATransportError(): void
    {
        $e = $this->authorizeFails($this->authWith(new FakeTransport([Responses::make(0, '', 'Operation timed out')])));

        $this->assertStringStartsWith('Transport error: Operation timed out', $e->getMessage());
    }

    // ---------------------------------------------------------------- the cached token

    /**
     * The token is cached and can be renewed
     * @return void
     */
    public function testTheTokenIsCachedAndCanBeRenewed(): void
    {
        $transport = new FakeTransport([
            Responses::token(),
            Responses::token(['access_token' => 'TOK2']),
            Responses::token(['access_token' => 'TOK3', 'expires_in' => 30]),
        ]);
        $auth = $this->authWith($transport);

        $this->assertSame('TOK', $auth->getToken());
        $this->assertCount(1, $transport->calls);

        $this->assertSame('TOK', $auth->getToken(), 'the second call uses the cache');
        $this->assertCount(1, $transport->calls);

        $this->assertSame('TOK2', $auth->getToken(true), 'a new token can be forced');
        $this->assertCount(2, $transport->calls);

        $this->assertSame('TOK3', $auth->getToken(true));
        $this->assertFalse($auth->hasToken(), 'a token that lives less than the 60 s margin is renewed on the next call');
    }

    /**
     * No token is given when refreshing is not wanted
     * @return void
     */
    public function testNoTokenIsGivenWhenRefreshingIsNotWanted(): void
    {
        $transport = new FakeTransport([Responses::token()]);
        $auth = $this->authWith($transport);

        $this->assertNull($auth->getTokenData(false));
        $this->assertSame([], $transport->calls);
    }

    /**
     * Clear token forgets the token
     * @return void
     */
    public function testClearTokenForgetsTheToken(): void
    {
        $auth = ExposedAuth::make();
        $auth->callSetToken(self::payload('"TOK"', '"bearer"', '3600', '"scope":"CXS"'));

        $this->assertTrue($auth->hasToken());
        $this->assertSame($auth, $auth->clearToken());
        $this->assertFalse($auth->hasToken());
        $this->assertSame([null, null, 0, null, 0], $auth->stored());
    }

    // ---------------------------------------------------------------- the checks on a token

    /**
     * @dataProvider tokensProvider
     * @param string $accessToken JSON of access_token, or '' to leave it out
     * @param string $tokenType JSON of token_type, or '' to leave it out
     * @param string $expiresIn JSON of expires_in, or '' to leave it out
     * @param string[] $invalid The fields that are invalid: an empty list means that the token is accepted
     */
    public function testWhichTokensAreAccepted(string $accessToken, string $tokenType, string $expiresIn, array $invalid): void
    {
        $auth = ExposedAuth::make();
        $auth->callSetToken(self::payload('"OLD"', '"bearer"', '3600')); // a rejected token must not leave this one behind
        $data = self::payload($accessToken, $tokenType, $expiresIn);

        try {
            $auth->callSetToken($data);
            $accepted = true;
        } catch (FedexAuthException $e) {
            $accepted = false;
        }

        if ($invalid === []) {
            $this->assertTrue($accepted, 'the token had to be accepted');
            [$token, $type, $duration] = $auth->stored();
            $this->assertSame('TOK', $token);
            $this->assertIsString($type);
            $this->assertIsInt($duration);
            $this->assertGreaterThanOrEqual(1, $duration);

            return;
        }

        $this->assertFalse($accepted, 'the token had to be refused');
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertSame('Invalid data received from server (' . implode(', ', $invalid) . ')', $e->getMessage());
        $this->assertFalse($auth->hasToken(), 'the previous token is not left stored');
        $this->assertNull($auth->stored()[0]);
        $this->assertStringNotContainsString('SECRETVALUE', json_encode($e->getData()));

        if (isset($data->access_token)) {
            $this->assertSame('[redacted]', $e->getData()->access_token);
        }

        if (property_exists($data, 'token_type')) {
            $this->assertEquals($data->token_type, $e->getData()->token_type, 'the other fields are untouched');
        }
    }

    /**
     * The cases of testWhichTokensAreAccepted(): which tokens are accepted
     * @return array
     */
    public function tokensProvider(): array
    {
        $at = '"TOK"';
        $tt = '"bearer"';
        $ei = '3600';

        return [
            'all valid' => [$at, $tt, $ei, []],
            'expires_in as a numeric string' => [$at, $tt, '"3600"', []],
            'expires_in as a padded string' => [$at, $tt, '" 3600 "', []],
            'expires_in 1' => [$at, $tt, '1', []],
            'expires_in 30 (inside the 60 s margin)' => [$at, $tt, '30', []],
            'expires_in 86400' => [$at, $tt, '86400', []],
            'token_type other than bearer' => [$at, '"Bearer"', $ei, []],

            'access_token missing' => ['', $tt, $ei, ['access_token']],
            'access_token null' => ['null', $tt, $ei, ['access_token']],
            'access_token empty' => ['""', $tt, $ei, ['access_token']],
            'access_token a number' => ['123', $tt, $ei, ['access_token']],
            'access_token true' => ['true', $tt, $ei, ['access_token']],
            'access_token a list' => ['["SECRETVALUE"]', $tt, $ei, ['access_token']],
            'access_token an object' => ['{"k":"SECRETVALUE"}', $tt, $ei, ['access_token']],

            'token_type missing' => [$at, '', $ei, ['token_type']],
            'token_type null' => [$at, 'null', $ei, ['token_type']],
            'token_type empty' => [$at, '""', $ei, ['token_type']],
            'token_type a number' => [$at, '123', $ei, ['token_type']],
            'token_type true' => [$at, 'true', $ei, ['token_type']],
            'token_type a list' => [$at, '["x"]', $ei, ['token_type']],
            'token_type an object' => [$at, '{"a":1}', $ei, ['token_type']],

            'expires_in missing' => [$at, $tt, '', ['expires_in']],
            'expires_in null' => [$at, $tt, 'null', ['expires_in']],
            'expires_in 0' => [$at, $tt, '0', ['expires_in']],
            'expires_in -5' => [$at, $tt, '-5', ['expires_in']],
            'expires_in "0"' => [$at, $tt, '"0"', ['expires_in']],
            'expires_in "-5"' => [$at, $tt, '"-5"', ['expires_in']],
            'expires_in 3600.0' => [$at, $tt, '3600.0', ['expires_in']],
            'expires_in 3600.5' => [$at, $tt, '3600.5', ['expires_in']],
            'expires_in 1e3' => [$at, $tt, '1e3', ['expires_in']],
            'expires_in true' => [$at, $tt, 'true', ['expires_in']],
            'expires_in false' => [$at, $tt, 'false', ['expires_in']],
            'expires_in "abc"' => [$at, $tt, '"abc"', ['expires_in']],
            'expires_in empty' => [$at, $tt, '""', ['expires_in']],
            'expires_in "3600.5"' => [$at, $tt, '"3600.5"', ['expires_in']],
            'expires_in a list' => [$at, $tt, '["x"]', ['expires_in']],
            'expires_in an object' => [$at, $tt, '{"a":1}', ['expires_in']],
            'expires_in overflowing' => [$at, $tt, '99999999999999999999', ['expires_in']],
            'expires_in overflowing, as a string' => [$at, $tt, '"99999999999999999999"', ['expires_in']],

            'two invalid: access_token and expires_in' => ['""', $tt, '0', ['access_token', 'expires_in']],
            'two invalid: token_type and expires_in' => [$at, 'null', '"abc"', ['token_type', 'expires_in']],
            'all three invalid' => ['["S"]', '{"a":1}', 'true', ['access_token', 'token_type', 'expires_in']],
            'an empty object' => ['', '', '', ['access_token', 'token_type', 'expires_in']],
        ];
    }

    /**
     * The token is redacted and the original object is not modified
     * @return void
     */
    public function testTheTokenIsRedactedAndTheOriginalObjectIsNotModified(): void
    {
        $data = (object) ['access_token' => 'SUPERSECRETTOKEN', 'scope' => 'CXS', 'expires_in' => 3600]; // token_type is missing

        try {
            ExposedAuth::make()->callSetToken($data);
            $this->fail('The token had to be refused');
        } catch (FedexAuthException $e) {
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('[redacted]', $e->getData()->access_token);
            $this->assertSame('CXS', $e->getData()->scope);
            $this->assertSame(3600, $e->getData()->expires_in);
            $this->assertSame('SUPERSECRETTOKEN', $data->access_token, 'the original object is untouched');
            $this->assertStringNotContainsString('SUPERSECRETTOKEN', json_encode($e->getData()));
        }
    }

    /**
     * A missing access token needs no redaction
     * @return void
     */
    public function testAMissingAccessTokenNeedsNoRedaction(): void
    {
        try {
            ExposedAuth::make()->callSetToken((object) ['token_type' => 'bearer']);
            $this->fail('The token had to be refused');
        } catch (FedexAuthException $e) {
            $this->assertFalse(isset($e->getData()->access_token));
        }
    }

    /**
     * The original object with an access token of the wrong type is not modified
     * @return void
     */
    public function testTheOriginalObjectWithAnAccessTokenOfTheWrongTypeIsNotModified(): void
    {
        $data = self::payload('["SECRETVALUE"]', '"bearer"', '3600');

        try {
            ExposedAuth::make()->callSetToken($data);
            $this->fail('The token had to be refused');
        } catch (FedexAuthException $e) {
            $this->assertSame(['SECRETVALUE'], $data->access_token);
        }
    }

    /**
     * A valid token is stored
     * @return void
     */
    public function testAValidTokenIsStored(): void
    {
        $auth = ExposedAuth::make();

        $auth->callSetToken((object) ['access_token' => 'T', 'token_type' => 'bearer', 'expires_in' => 3600, 'scope' => 'CXS']);

        $this->assertTrue($auth->hasToken());
        $this->assertSame('T', $auth->getToken());
    }

    /**
     * The duration comes from the validated integer
     * @return void
     */
    public function testTheDurationComesFromTheValidatedInteger(): void
    {
        $auth = ExposedAuth::make();

        $auth->callSetToken(self::payload('"TOK"', '"bearer"', '"3600"'));
        $duration = $auth->getTokenData(false)->duration;

        $this->assertGreaterThanOrEqual(3538, $duration);
        $this->assertLessThanOrEqual(3540, $duration, 'the 60 s margin is taken from the 3600');
    }

    // ---------------------------------------------------------------- the scope

    /**
     * @dataProvider scopesProvider
     * @param string $json JSON of the scope
     * @param string|null $expected The scope that has to be stored
     */
    public function testTheScopeIsStoredOnlyWhenItIsText(string $json, ?string $expected): void
    {
        $auth = ExposedAuth::make();

        $auth->callSetToken(json_decode('{"access_token":"TOK","token_type":"bearer","expires_in":3600,"scope":' . $json . '}'));
        $data = $auth->getTokenData(false);

        $this->assertSame($expected, $data->scope);
        $this->assertTrue($auth->hasToken());
        $this->assertSame('TOK', $auth->getToken());
        $this->assertSame('bearer', $data->type);
        $this->assertGreaterThan(3400, $data->duration);
    }

    /**
     * The cases of testTheScopeIsStoredOnlyWhenItIsText(): the scope is stored only when it is text
     * @return array
     */
    public function scopesProvider(): array
    {
        return [
            'text' => ['"CXS"', 'CXS'],
            'empty text' => ['""', ''],
            'text with a space' => ['"CXS other"', 'CXS other'],
            'an integer' => ['123', '123'],
            'a decimal' => ['1.5', '1.5'],
            'true' => ['true', null],
            'false' => ['false', null],
            'null' => ['null', null],
            'a list' => ['["CXS"]', null],
            'an empty list' => ['[]', null],
            'an object' => ['{"a":1}', null],
            'an empty object' => ['{}', null],
        ];
    }

    /**
     * The scope is not required
     * @return void
     */
    public function testTheScopeIsNotRequired(): void
    {
        $auth = ExposedAuth::make();

        $auth->callSetToken(self::payload('"TOK"', '"bearer"', '3600'));
        $data = $auth->getTokenData(false);

        $this->assertTrue($auth->hasToken());
        $this->assertNull($data->scope);
        $this->assertSame('bearer', $data->type);
        $this->assertGreaterThan(3000, $data->duration);
        $this->assertLessThanOrEqual(3600, $data->duration);
    }

    /**
     * The scope of a previous token does not leak into the new one
     * @return void
     */
    public function testTheScopeOfAPreviousTokenDoesNotLeakIntoTheNewOne(): void
    {
        $auth = ExposedAuth::make();

        $auth->callSetToken(self::payload('"TOK"', '"bearer"', '3600', '"scope":"CXS"'));
        $this->assertSame('CXS', $auth->getTokenData(false)->scope);

        $auth->callSetToken(self::payload('"TOK3"', '"bearer"', '3600'));
        $this->assertNull($auth->getTokenData(false)->scope);

        $auth->callSetToken(self::payload('"TOK4"', '"bearer"', '3600', '"scope":"CXS"'));
        $auth->callSetToken(self::payload('"TOK5"', '"bearer"', '3600', '"scope":["x"]'));
        $this->assertNull($auth->getTokenData(false)->scope);
        $this->assertSame('TOK5', $auth->getToken());
    }
}
