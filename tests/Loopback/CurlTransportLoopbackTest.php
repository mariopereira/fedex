<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Loopback;

use Mariopereira\Fedex\Http\CurlTransport;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Tests\Support\LoopbackServer;
use Mariopereira\Fedex\Tests\Support\LoopbackTestCase;

/**
 * Tests of CurlTransport with the real cURL, against the throwaway server on 127.0.0.1: what goes on the wire, what comes back,
 * and what happens when the server refuses, is slow or answers with something odd
 *
 * @group loopback
 */
final class CurlTransportLoopbackTest extends LoopbackTestCase
{
    /**
     * Send a request with the usual timeouts
     * @param string $method HTTP method
     * @param string $path Path on the server
     * @param array $headers Header lines
     * @param string|null $body Request body
     * @return FedexTransportResponse
     */
    private function send(string $method, string $path, array $headers = [], ?string $body = null): FedexTransportResponse
    {
        return (new CurlTransport())->send($method, self::$server->url($path), $headers, $body, 30, 10);
    }

    /**
     * The headers of the answer are collected with the names in lower case
     * @return void
     */
    public function testTheHeadersOfTheAnswerAreCollectedWithTheNamesInLowerCase(): void
    {
        $response = $this->send('GET', '/ra/429/7');

        $this->assertSame(429, $response->getStatus());
        $this->assertSame('7', $response->getHeader('Retry-After'));
        $this->assertSame('7', $response->getHeader('retry-after'));
        $this->assertSame('application/json', $response->getHeader('Content-Type'));
        $this->assertSame('one, two', $response->getHeader('X-Twice'), 'a header that comes twice is joined');
        $this->assertSame('a, b', $response->getHeader('X-Mixed'), 'and so is one that comes twice in different cases');
        $this->assertSame((string) strlen($response->getBody()), $response->getHeader('Content-Length'));
        $this->assertSame('close', $response->getHeader('connection'));
        $this->assertArrayNotHasKey('http/1.1 429 too many requests', $response->getHeaders(), 'the status line is not a header');

        foreach (array_keys($response->getHeaders()) as $name) {
            $this->assertSame(strtolower($name), $name);
        }
    }

    /**
     * Only the headers of the last answer are kept, not those of a 100 Continue
     * @return void
     */
    public function testOnlyTheHeadersOfTheLastAnswerAreKeptNotThoseOfA100Continue(): void
    {
        $response = $this->send('POST', '/ra/503/9', ['Expect: 100-continue', 'Content-Type: application/json'], json_encode(['data' => str_repeat('x', 5000)]));

        $this->assertSame(503, $response->getStatus());
        $this->assertSame('9', $response->getHeader('Retry-After'));
        $this->assertNotNull($response->getHeader('Content-Length'));
        $this->assertNull($response->getHeader('X-Interim'), 'the header of the 100 Continue is not kept');
        $this->assertSame([], array_filter(array_keys($response->getHeaders()), function (string $name) {
            return strpos($name, 'http/') === 0;
        }));
    }

    /**
     * A header that is a date is kept as it was sent
     * @return void
     */
    public function testAHeaderThatIsADateIsKeptAsItWasSent(): void
    {
        $date = 'Sun, 06 Nov 1994 08:49:37 GMT';
        $response = $this->send('GET', '/ra/503/' . rawurlencode($date));

        $this->assertSame($date, $response->getHeader('Retry-After'));
    }

    /**
     * An answer without a Retry-After has none and a failure has no headers
     * @return void
     */
    public function testAnAnswerWithoutARetryAfterHasNoneAndAFailureHasNoHeaders(): void
    {
        $this->assertNull($this->send('GET', '/ok')->getHeader('Retry-After'));
        $this->assertNotNull($this->send('GET', '/ok')->getHeader('Content-Type'));

        $failed = (new CurlTransport())->send('GET', 'http://127.0.0.1:' . self::$closedPort . '/ok', [], null, 5, 5);

        $this->assertTrue($failed->hasFailed());
        $this->assertSame([], $failed->getHeaders());
    }

    /**
     * A get returns the status and the body
     * @return void
     */
    public function testAGetReturnsTheStatusAndTheBody(): void
    {
        $response = $this->send('GET', '/ok', ['Accept: application/json', 'X-One: 1']);
        $request = self::$server->requests()[0];

        $this->assertInstanceOf(FedexTransportResponse::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('{"ok":true}', $response->getBody());
        $this->assertFalse($response->hasFailed());
        $this->assertNull($response->getError());
        $this->assertSame('GET /ok HTTP/1.1', strtok($request['head'], "\r\n"));
        $this->assertContains('Accept: application/json', LoopbackServer::headerLines($request));
        $this->assertContains('X-One: 1', LoopbackServer::headerLines($request));
        $this->assertSame('', $request['body']);
        $this->assertEmpty(preg_grep('/^Content-Length:/i', LoopbackServer::headerLines($request)), 'no body and no Content-Length');
    }

    /**
     * A post arrives intact with its content length and its headers
     * @return void
     */
    public function testAPostArrivesIntactWithItsContentLengthAndItsHeaders(): void
    {
        $body = '{"u":"çã€","n":[1,2,3]}';

        $response = $this->send('POST', '/echo', ['Content-Type: application/json', 'X-Colon: a:b:c'], $body);
        $request = self::$server->requests()[0];

        $this->assertSame($body, $request['body'], 'UTF-8 arrives intact');
        $this->assertContains('Content-Length: ' . strlen($body), LoopbackServer::headerLines($request), 'the byte length');
        $this->assertContains('X-Colon: a:b:c', LoopbackServer::headerLines($request), 'header values with colons survive');
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('POST', json_decode($response->getBody())->method);
    }

    /**
     * @dataProvider methodsProvider
     * @param string $given The method as it is given
     * @param string $expected The method that goes on the wire
     */
    public function testTheMethodIsUpperCasedAndSentAsGiven(string $given, string $expected): void
    {
        $this->send($given, '/echo', [], $expected === 'DELETE' ? null : '{}');

        $this->assertSame("$expected /echo HTTP/1.1", strtok(self::$server->requests()[0]['head'], "\r\n"));
    }

    /**
     * The cases of testTheMethodIsUpperCasedAndSentAsGiven(): the method is upper cased and sent as given
     * @return array
     */
    public function methodsProvider(): array
    {
        return ['PUT' => ['PUT', 'PUT'], 'DELETE' => ['DELETE', 'DELETE'], 'patch' => ['patch', 'PATCH'], 'Put' => ['Put', 'PUT']];
    }

    /**
     * An empty string body is a post with a content length of zero
     * @return void
     */
    public function testAnEmptyStringBodyIsAPostWithAContentLengthOfZero(): void
    {
        $this->send('POST', '/echo', [], '');

        $this->assertContains('Content-Length: 0', LoopbackServer::headerLines(self::$server->requests()[0]));
    }

    /**
     * @dataProvider errorStatusesProvider
     * @param int $status An error status
     */
    public function testAnHttpErrorStatusIsReturnedWithItsBodyAndIsNotAFailure(int $status): void
    {
        $response = $this->send('GET', '/status/' . $status);

        $this->assertSame($status, $response->getStatus());
        $this->assertFalse($response->hasFailed());
        $this->assertSame('ERR.' . $status, json_decode($response->getBody())->errors[0]->code);
    }

    /**
     * The cases of testAnHttpErrorStatusIsReturnedWithItsBodyAndIsNotAFailure(): an HTTP error status is returned with its body and is not a failure
     * @return array
     */
    public function errorStatusesProvider(): array
    {
        return [[400], [401], [403], [404], [429], [500], [503]];
    }

    /**
     * A 204 has an empty body and is not a failure
     * @return void
     */
    public function testA204HasAnEmptyBodyAndIsNotAFailure(): void
    {
        $response = $this->send('GET', '/nobody');

        $this->assertSame(204, $response->getStatus());
        $this->assertSame('', $response->getBody());
        $this->assertFalse($response->hasFailed());
    }

    /**
     * A 302 is returned as it is and not followed
     * @return void
     */
    public function testA302IsReturnedAsItIsAndNotFollowed(): void
    {
        $response = $this->send('GET', '/redirect');

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('', $response->getBody());
        $this->assertSame(['GET /redirect'], self::$server->seen());
    }

    /**
     * A body of three hundred and twenty kilobytes is read completely
     * @return void
     */
    public function testABodyOfThreeHundredAndTwentyKilobytesIsReadCompletely(): void
    {
        $expected = json_encode(['data' => str_repeat('0123456789abcdef', 20000)]);

        $response = $this->send('GET', '/big');

        $this->assertSame(200, $response->getStatus());
        $this->assertSame($expected, $response->getBody(), strlen($expected) . ' bytes, byte for byte');
    }

    /**
     * A body that is not JSON is returned untouched
     * @return void
     */
    public function testABodyThatIsNotJsonIsReturnedUntouched(): void
    {
        $response = $this->send('GET', '/bad-json');

        $this->assertSame('this is not json', $response->getBody());
        $this->assertSame(200, $response->getStatus());
    }

    /**
     * A 502 with an HTML body
     * @return void
     */
    public function testA502WithAnHtmlBody(): void
    {
        $response = $this->send('GET', '/html502');

        $this->assertSame(502, $response->getStatus());
        $this->assertStringContainsString('Bad Gateway', $response->getBody());
        $this->assertFalse($response->hasFailed());
    }

    /**
     * A connection that is refused fails with status zero and an error text
     * @return void
     */
    public function testAConnectionThatIsRefusedFailsWithStatusZeroAndAnErrorText(): void
    {
        $response = (new CurlTransport())->send('GET', 'http://127.0.0.1:' . self::$closedPort . '/x', [], null, 30, 10);

        $this->assertTrue($response->hasFailed());
        $this->assertSame(0, $response->getStatus());
        $this->assertSame('', $response->getBody());
        $this->assertStringContainsStringIgnoringCase('connect', (string) $response->getError());
    }

    /**
     * A timeout fails quickly with status zero
     * @return void
     */
    public function testATimeoutFailsQuicklyWithStatusZero(): void
    {
        $start = microtime(true);

        $response = (new CurlTransport())->send('GET', self::$server->url('/slow'), [], null, 1, 1);
        $elapsed = microtime(true) - $start;

        $this->assertTrue($response->hasFailed());
        $this->assertSame(0, $response->getStatus());
        $this->assertStringContainsStringIgnoringCase('timed out', (string) $response->getError());
        $this->assertLessThan(2.5, $elapsed, 'a 1 second timeout against a 3 second answer does not hang');

        self::waitForTheSlowRoute();
    }

    /**
     * The same transport can be reused
     * @return void
     */
    public function testTheSameTransportCanBeReused(): void
    {
        $transport = new CurlTransport();

        $first = $transport->send('GET', self::$server->url('/ok'), [], null, 30, 10);
        $second = $transport->send('GET', self::$server->url('/ok'), [], null, 30, 10);

        $this->assertSame(200, $first->getStatus());
        $this->assertSame(200, $second->getStatus());
    }

    // ---------------------------------------------------------------- the same bytes as the cURL options that the code used before the transport existed

    /**
     * The options that FedexAuth::authorize() used before the transport existed
     * @param string $url The url
     * @param string $body The form body
     * @return array The status and the body of the response
     */
    private static function oldAuth(string $url, string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (PHP_VERSION_ID < 80000) {
            curl_close($curl);
        }

        return [$status, $response];
    }

    /**
     * The options that FedexClient::perform() used before the transport existed
     * @param string $method The method
     * @param string $url The url
     * @param array $headers The header lines
     * @param string|null $body The body
     * @return array The status and the body of the response
     */
    private static function oldClient(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        $options = [CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10];

        if (!is_null($body)) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if (PHP_VERSION_ID < 80000) {
            curl_close($curl);
        }

        return [$status, $response];
    }

    /**
     * @dataProvider oldRequestsProvider
     * @param string $kind 'auth' for the token request, or 'client' for an API call
     * @param string $method The method
     * @param array $headers The header lines
     * @param string|null $body The body
     */
    public function testTheRawRequestIsIdenticalToTheOneOfTheOldCurlOptions(string $kind, string $method, array $headers, ?string $body): void
    {
        $url = self::$server->url('/echo');
        $transport = new CurlTransport();

        self::$server->forget();
        $old = ($kind === 'auth') ? self::oldAuth($url, (string) $body) : self::oldClient($method, $url, $headers, $body);
        $before = self::$server->requests();

        self::$server->forget();
        $response = $transport->send($method, $url, $headers, $body, 30, 10);
        $after = self::$server->requests();

        $this->assertCount(1, $before);
        $this->assertCount(1, $after);
        $this->assertSame($before[0], $after[0], 'the raw request bytes');
        $this->assertSame($old, [$response->getStatus(), $response->getBody()], 'and the response');
    }

    /**
     * The cases of testTheRawRequestIsIdenticalToTheOneOfTheOldCurlOptions(): the raw request is identical to the one of the old cURL options
     * @return array
     */
    public function oldRequestsProvider(): array
    {
        $json = ['Authorization: Bearer T', 'Accept: application/json', 'X-locale: en_US', 'Content-Type: application/json'];

        return [
            'the token request (form body)' => ['auth', 'POST', ['Content-Type: application/x-www-form-urlencoded'], 'grant_type=client_pc_credentials&client_id=id&client_secret=secret&child_key=CK&child_secret=CS'],
            'an API POST with a JSON body' => ['client', 'POST', $json, '{"a":"çã"}'],
            'an API GET without a body' => ['client', 'GET', $json, null],
            'an API PUT with an empty body' => ['client', 'put', $json, ''],
            'an API DELETE without a body' => ['client', 'DELETE', $json, null],
        ];
    }
}
