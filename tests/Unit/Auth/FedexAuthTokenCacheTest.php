<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Auth;

use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Tests\Support\ArrayTokenCache;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Responses;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the token cache of the authorization: when a token is taken from it, when it is stored, and what happens
 * when it fails
 */
final class FedexAuthTokenCacheTest extends TestCase
{
    /**
     * An authorization over a fake transport
     * @param FakeTransport $transport The transport of the token request
     * @param ArrayTokenCache|null $cache The cache, none by default
     * @param bool $sandbox Sandbox or production
     * @param string $clientId The client id
     * @param string $clientSecret The client secret
     * @return FedexAuth
     */
    private function auth(FakeTransport $transport, ?ArrayTokenCache $cache = null, bool $sandbox = true, string $clientId = 'id', string $clientSecret = 'secret'): FedexAuth
    {
        $auth = new FedexAuth($sandbox, 'client_credentials', $clientId, $clientSecret, null, null, $transport);

        return $cache ? $auth->setTokenCache($cache) : $auth;
    }

    /**
     * A transport that gives tokens, one after the other
     * @param string[] $tokens The access tokens to give
     * @param array $override Fields of the token response to replace in all of them
     * @return FakeTransport
     */
    private static function tokens(array $tokens, array $override = []): FakeTransport
    {
        return new FakeTransport(array_map(function (string $token) use ($override) {
            return Responses::token(array_merge(['access_token' => $token], $override));
        }, $tokens));
    }

    /**
     * An entry as FedexAuth stores it
     * @param array $override Fields to replace
     * @return array
     */
    private static function entry(array $override = []): array
    {
        return array_merge(['v' => 1, 'token' => 'CACHED', 'type' => 'bearer', 'scope' => 'CXS', 'expiration' => time() + 1800], $override);
    }

    /**
     * The key of the only entry of a cache
     * @param ArrayTokenCache $cache The cache
     * @return string
     */
    private static function onlyKey(ArrayTokenCache $cache): string
    {
        self::assertCount(1, $cache->entries);

        return (string) array_key_first($cache->entries);
    }

    /**
     * The data of the authorization have no cache until one is set
     * @return void
     */
    public function testThereIsNoCacheUntilOneIsSet(): void
    {
        $transport = self::tokens(['T1', 'T2']);

        $this->assertSame('T1', $this->auth($transport)->getToken());
        $this->assertSame('T2', $this->auth($transport)->getToken(), 'another object asks for its own token');
        $this->assertCount(2, $transport->calls);
        $this->assertNull($this->auth($transport)->getLastCacheError());
    }

    /**
     * The first object requests the token and stores an entry with the data of the token
     * @return void
     */
    public function testTheFirstObjectRequestsTheTokenAndStoresAnEntryWithItsData(): void
    {
        $cache = new ArrayTokenCache();
        $transport = self::tokens(['T1'], ['expires_in' => 3600, 'scope' => 'CXS']);

        $this->assertSame('T1', $this->auth($transport, $cache)->getToken());

        $key = self::onlyKey($cache);
        $entry = $cache->entries[$key];

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
        $this->assertSame(['v', 'token', 'type', 'scope', 'expiration'], array_keys($entry));
        $this->assertSame([1, 'T1', 'bearer', 'CXS'], [$entry['v'], $entry['token'], $entry['type'], $entry['scope']]);
        $this->assertIsInt($entry['expiration']);
        $this->assertEqualsWithDelta(time() + 3600 - 60, $entry['expiration'], 5, 'the expiration has the margin of a minute');
        $this->assertCount(1, $cache->ttls);
        $this->assertEqualsWithDelta(3540, $cache->ttls[0], 5);
        $this->assertSame(['get', 'set'], $cache->operations(), 'a miss, then the token is stored');
        $this->assertNull($this->auth($transport)->getLastCacheError());
    }

    /**
     * Another object with the same cache requests nothing
     * @return void
     */
    public function testAnotherObjectWithTheSameCacheRequestsNothing(): void
    {
        $cache = new ArrayTokenCache();
        $first = self::tokens(['T1']);
        $second = self::tokens(['T2']);

        $this->auth($first, $cache)->getToken();
        $auth = $this->auth($second, $cache);

        $this->assertSame('T1', $auth->getToken());
        $this->assertSame('T1', $auth->getToken(), 'and it keeps it');
        $this->assertCount(0, $second->calls);
        $this->assertSame(['get', 'set', 'get'], $cache->operations(), 'the cache is read once');

        $data = $this->auth($second, $cache)->getTokenData();

        $this->assertSame(['T1', 'bearer', 'CXS'], [$data->token, $data->type, $data->scope]);
        $this->assertEqualsWithDelta(3540, $data->duration, 5);
    }

    /**
     * Asking without refreshing reads the cache and requests nothing
     * @return void
     */
    public function testAskingWithoutRefreshingReadsTheCacheAndRequestsNothing(): void
    {
        $cache = new ArrayTokenCache();
        $transport = self::tokens(['T1']);

        $this->assertNull($this->auth($transport, $cache)->getTokenData(false), 'nothing in the cache');
        $this->assertCount(0, $transport->calls);

        $this->auth($transport, $cache)->getToken();
        $transport->calls = [];

        $this->assertSame('T1', $this->auth($transport, $cache)->getTokenData(false)->token);
        $this->assertCount(0, $transport->calls);
    }

    /**
     * The check of whether the object has a token does not look at the cache
     * @return void
     */
    public function testTheCheckOfWhetherTheObjectHasATokenDoesNotLookAtTheCache(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['T1']), $cache)->getToken();
        $auth = $this->auth(self::tokens(['T2']), $cache);

        $this->assertFalse($auth->hasToken());
        $this->assertSame([['get', self::onlyKey($cache)], ['set', self::onlyKey($cache)]], array_slice($cache->log, 0, 2));
        $this->assertCount(2, $cache->log, 'hasToken() did not read it');

        $auth->getToken();

        $this->assertTrue($auth->hasToken());
    }

    /**
     * An entry that has expired is not used and is replaced
     * @return void
     */
    public function testAnEntryThatHasExpiredIsNotUsedAndIsReplaced(): void
    {
        foreach ([time() - 1000, time() - 1, time()] as $expiration) {
            $cache = new ArrayTokenCache();
            $this->auth(self::tokens(['T0']), $cache)->getToken();
            $cache->entries[self::onlyKey($cache)] = self::entry(['expiration' => $expiration]);
            $transport = self::tokens(['T1']);

            $this->assertSame('T1', $this->auth($transport, $cache)->getToken(), "expiration $expiration");
            $this->assertCount(1, $transport->calls);
            $this->assertSame('T1', $cache->entries[self::onlyKey($cache)]['token']);
            $this->assertNull($this->auth($transport)->getLastCacheError(), 'an expired entry is not a problem');
        }
    }

    /**
     * An entry that is not valid is not used, is replaced and is reported
     * @return void
     */
    public function testAnEntryThatIsNotValidIsNotUsedIsReplacedAndIsReported(): void
    {
        $cases = [
            'another version' => self::entry(['v' => 2]),
            'no version' => array_diff_key(self::entry(), ['v' => 1]),
            'no token' => array_diff_key(self::entry(), ['token' => 1]),
            'an empty token' => self::entry(['token' => '']),
            'a token that is a number' => self::entry(['token' => 12345]),
            'no type' => array_diff_key(self::entry(), ['type' => 1]),
            'an empty type' => self::entry(['type' => '']),
            'a scope that is a number' => self::entry(['scope' => 5]),
            'no expiration' => array_diff_key(self::entry(), ['expiration' => 1]),
            'an expiration as text' => self::entry(['expiration' => (string) (time() + 1800)]),
            'an expiration with decimals' => self::entry(['expiration' => time() + 1800.5]),
            'an empty entry' => [],
        ];

        foreach ($cases as $label => $entry) {
            $cache = new ArrayTokenCache();
            $auth = $this->auth(self::tokens(['T1']), $cache);
            $cache->entries[hash('sha256', implode("\0", ['sandbox', 'client_credentials', 'id', 'secret', '', '']))] = $entry;

            $this->assertSame('T1', $auth->getToken(), $label);
            $this->assertSame('The token cache entry is not valid', $auth->getLastCacheError(), $label);
            $this->assertSame('T1', $cache->entries[self::onlyKey($cache)]['token'], "$label: replaced");
        }
    }

    /**
     * Every field of the entry is the one that the object takes
     * @return void
     */
    public function testEveryFieldOfTheEntryIsTheOneThatTheObjectTakes(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['T0']), $cache)->getToken();
        $cache->entries[self::onlyKey($cache)] = self::entry(['token' => 'FROM-CACHE', 'type' => 'Other', 'scope' => 'S1 S2', 'expiration' => time() + 1000]);

        $data = $this->auth(self::tokens(['T1']), $cache)->getTokenData();

        $this->assertSame(['FROM-CACHE', 'Other', 'S1 S2'], [$data->token, $data->type, $data->scope]);
        $this->assertEqualsWithDelta(1000, $data->duration, 5);
    }

    /**
     * An entry without a scope is valid
     * @return void
     */
    public function testAnEntryWithoutAScopeIsValid(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['T0']), $cache)->getToken();
        $cache->entries[self::onlyKey($cache)] = self::entry(['scope' => null]);
        $transport = self::tokens(['T1']);
        $auth = $this->auth($transport, $cache);

        $data = $auth->getTokenData();

        $this->assertSame(['CACHED', null], [$data->token, $data->scope]);
        $this->assertCount(0, $transport->calls);
        $this->assertNull($auth->getLastCacheError());
    }

    /**
     * Forcing a refresh removes the entry, never reads it and stores the new token
     * @return void
     */
    public function testForcingARefreshRemovesTheEntryNeverReadsItAndStoresTheNewToken(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['OLD']), $cache)->getToken();
        $key = self::onlyKey($cache);
        $cache->log = [];
        $transport = self::tokens(['NEW']);
        $auth = $this->auth($transport, $cache);

        $this->assertSame('NEW', $auth->getToken(true));
        $this->assertSame([['delete', $key], ['set', $key]], $cache->log, 'removed, never read, then stored');
        $this->assertSame('NEW', $cache->entries[$key]['token']);
        $this->assertCount(1, $transport->calls);
    }

    /**
     * A forced refresh does not use the entry even when it cannot be removed
     * @return void
     */
    public function testAForcedRefreshDoesNotUseTheEntryEvenWhenItCannotBeRemoved(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['OLD']), $cache)->getToken();
        $cache->failOn['delete'] = new \RuntimeException('disk full');
        $auth = $this->auth(self::tokens(['NEW']), $cache);

        $this->assertSame('NEW', $auth->getToken(true));
        $this->assertSame('The token could not be removed from the cache: RuntimeException: disk full', $auth->getLastCacheError());
    }

    /**
     * A token that FedEx rejects is replaced in the cache
     * @return void
     */
    public function testATokenThatFedexRejectsIsReplacedInTheCache(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['OLD']), $cache)->getToken();
        $authTransport = self::tokens(['NEW']);
        $auth = $this->auth($authTransport, $cache);
        $apiTransport = new FakeTransport([Responses::make(401, '{"errors":[]}'), Responses::make(200, '{"ok":true}')]);
        $client = new FedexClient($auth, 'en_US', 30, 10, $apiTransport);

        $result = $client->request('GET', 'x');

        $this->assertTrue($result->ok);
        $this->assertContains('Authorization: Bearer OLD', $apiTransport->calls[0]['headers'], 'the cached token is tried first');
        $this->assertContains('Authorization: Bearer NEW', $apiTransport->calls[1]['headers']);
        $this->assertCount(1, $authTransport->calls);
        $this->assertSame('NEW', $cache->entries[self::onlyKey($cache)]['token']);

        $next = $this->auth(self::tokens(['UNUSED']), $cache);

        $this->assertSame('NEW', $next->getToken(), 'the next process gets the new one');
    }

    /**
     * Calling authorize() requests a token and stores it over the entry
     * @return void
     */
    public function testCallingAuthorizeRequestsATokenAndStoresItOverTheEntry(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['OLD']), $cache)->getToken();
        $transport = self::tokens(['NEW']);

        $this->auth($transport, $cache)->authorize();

        $this->assertCount(1, $transport->calls);
        $this->assertSame('NEW', $cache->entries[self::onlyKey($cache)]['token']);
    }

    /**
     * The tokens of another environment or other credentials are never shared
     * @return void
     */
    public function testTheTokensOfAnotherEnvironmentOrOtherCredentialsAreNeverShared(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['BASE']), $cache)->getToken();

        $variants = [
            'production' => [false, 'id', 'secret'],
            'another id' => [true, 'id2', 'secret'],
            'another secret' => [true, 'id', 'secret2'],
        ];

        foreach ($variants as $label => [$sandbox, $id, $secret]) {
            $transport = self::tokens(['OTHER-' . $label]);

            $this->assertSame('OTHER-' . $label, $this->auth($transport, $cache, $sandbox, $id, $secret)->getToken(), $label);
            $this->assertCount(1, $transport->calls, $label);
        }

        $this->assertCount(4, $cache->entries, 'four different keys');

        $same = self::tokens(['UNUSED']);

        $this->assertSame('BASE', $this->auth($same, $cache)->getToken(), 'the same credentials share it');
        $this->assertCount(0, $same->calls);
    }

    /**
     * The credentials of a grant with a child are part of the key
     * @return void
     */
    public function testTheChildCredentialsArePartOfTheKey(): void
    {
        $cache = new ArrayTokenCache();
        $make = function (string $childKey, string $childSecret, string $token) use ($cache) {
            $auth = new FedexAuth(true, 'client_pc_credentials', 'id', 'secret', $childKey, $childSecret, self::tokens([$token]));

            return $auth->setTokenCache($cache)->getToken();
        };

        $this->assertSame('A', $make('ck', 'cs', 'A'));
        $this->assertSame('A', $make('ck', 'cs', 'X'), 'the same child credentials');
        $this->assertSame('B', $make('ck2', 'cs', 'B'));
        $this->assertSame('C', $make('ck', 'cs2', 'C'));
        $this->assertCount(3, $cache->entries);
    }

    /**
     * Nothing that identifies the credentials can be read from the key
     * @return void
     */
    public function testNothingThatIdentifiesTheCredentialsCanBeReadFromTheKey(): void
    {
        $cache = new ArrayTokenCache();
        $this->auth(self::tokens(['T1']), $cache, true, 'MY-CLIENT-ID', 'MY-CLIENT-SECRET')->getToken();

        $key = self::onlyKey($cache);
        $stored = json_encode($cache->entries);

        $this->assertStringNotContainsString('MY-CLIENT', $key);
        $this->assertStringNotContainsString('MY-CLIENT', $stored, 'neither the id nor the secret are stored');
        $this->assertSame(hash('sha256', implode("\0", ['sandbox', 'client_credentials', 'MY-CLIENT-ID', 'MY-CLIENT-SECRET', '', ''])), $key);
    }

    /**
     * A cache that cannot be read does not stop the authorization
     * @return void
     */
    public function testACacheThatCannotBeReadDoesNotStopTheAuthorization(): void
    {
        $cache = new ArrayTokenCache();
        $cache->failOn['get'] = new \RuntimeException('connection lost');
        $transport = self::tokens(['SECRET-TOKEN']);
        $auth = $this->auth($transport, $cache);

        $this->assertSame('SECRET-TOKEN', $auth->getToken());
        $this->assertSame('The token cache could not be read: RuntimeException: connection lost', $auth->getLastCacheError());
        $this->assertStringNotContainsString('SECRET-TOKEN', (string) $auth->getLastCacheError());
        $this->assertSame('SECRET-TOKEN', $cache->entries[self::onlyKey($cache)]['token'], 'and the token is stored');
    }

    /**
     * A cache that cannot be written does not stop the authorization
     * @return void
     */
    public function testACacheThatCannotBeWrittenDoesNotStopTheAuthorization(): void
    {
        $cache = new ArrayTokenCache();
        $cache->failOn['set'] = new \RuntimeException('disk full');
        $auth = $this->auth(self::tokens(['SECRET-TOKEN']), $cache);

        $this->assertSame('SECRET-TOKEN', $auth->getToken());
        $this->assertSame('The token could not be stored in the cache: RuntimeException: disk full', $auth->getLastCacheError());
        $this->assertStringNotContainsString('SECRET-TOKEN', (string) $auth->getLastCacheError());
        $this->assertSame('SECRET-TOKEN', $auth->getToken(), 'the object keeps the token');
    }

    /**
     * An error, and not only an exception, of the cache is caught
     * @return void
     */
    public function testAnErrorOfTheCacheIsCaughtToo(): void
    {
        $cache = new ArrayTokenCache();
        $cache->failOn['get'] = new \TypeError('wrong type');
        $auth = $this->auth(self::tokens(['T1']), $cache);

        $this->assertSame('T1', $auth->getToken());
        $this->assertSame('The token cache could not be read: TypeError: wrong type', $auth->getLastCacheError());
    }

    /**
     * Setting the cache again clears the problem, and null stops the use of the cache
     * @return void
     */
    public function testSettingTheCacheAgainClearsTheProblemAndNullStopsTheUseOfTheCache(): void
    {
        $cache = new ArrayTokenCache();
        $cache->failOn['get'] = new \RuntimeException('x');
        $auth = $this->auth(self::tokens(['T1', 'T2']), $cache);
        $auth->getToken();

        $this->assertNotNull($auth->getLastCacheError());
        $this->assertSame($auth, $auth->setTokenCache(null));
        $this->assertNull($auth->getLastCacheError());

        $cache->log = [];
        $auth->getToken(true);

        $this->assertSame([], $cache->log, 'the cache is not used any more');
    }
}
