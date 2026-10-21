<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Http\FedexClient;
use PHPUnit\Framework\TestCase;

/**
 * Base of the loopback tests: the real cURL, the real FedexAuth and FedexClient and the real API classes, talking to a
 * throwaway server on 127.0.0.1 that starts before the tests of a class and stops after them. Nothing leaves the machine
 */
abstract class LoopbackTestCase extends TestCase
{
    /**
     * The server of the tests of the class
     * @var LoopbackServer
     */
    protected static $server;

    /**
     * A port that nothing listens on, for the "connection refused" cases
     * @var int
     */
    protected static $closedPort;

    /**
     * Start the server
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('curl') || !function_exists('proc_open')) {
            self::markTestSkipped('the loopback tests need the curl extension and proc_open');
        }

        self::$server = LoopbackServer::start();
        self::$closedPort = LoopbackServer::freePort();
    }

    /**
     * Stop the server
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            self::$server->stop();
            self::$server = null;
        }
    }

    /**
     * Forget what the server received during the previous test
     * @return void
     */
    protected function setUp(): void
    {
        self::$server->forget();
    }

    /**
     * Wait for the "/slow" route of the server to finish answering (it takes 3 seconds), so that it is free for the next call
     * @return void
     */
    protected static function waitForTheSlowRoute(): void
    {
        usleep(3200000);
    }

    /**
     * Run something and give the exception it throws
     * @param callable $fn What to run
     * @return \Throwable|null
     */
    protected static function thrown(callable $fn): ?\Throwable
    {
        try {
            $fn();

            return null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * An authorization and a client that talk to the server, both with the real cURL
     * @param int $apiPort The port of the server for the API calls
     * @param int $authPort The port of the server for the token request
     * @param string $tokenRoute The route of the server that stands for the token url
     * @param int $timeout Timeout of the client in seconds
     * @param int $connectTimeout Connect timeout of the client in seconds
     * @param string $locale Locale of the client
     * @return array The authorization, the client, the transport of the token request and the transport of the API calls
     */
    protected static function chain(int $apiPort, int $authPort, string $tokenRoute = '/token-ok', int $timeout = 30, int $connectTimeout = 10, string $locale = 'pt_PT'): array
    {
        $authTransport = new RewriteTransport($authPort);
        $authTransport->tokenRoute = $tokenRoute;
        $auth = new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $authTransport);
        $apiTransport = new RewriteTransport($apiPort);

        return [$auth, new FedexClient($auth, $locale, $timeout, $connectTimeout, $apiTransport), $authTransport, $apiTransport];
    }
}
