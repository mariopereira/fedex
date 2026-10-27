<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Http;

use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Responses;
use Mariopereira\Fedex\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the multipart/form-data requests of the client, and of the absolute urls (an API with its own host)
 */
final class FedexClientMultipartTest extends TestCase
{
    /**
     * A file part
     * @param array $override Keys to replace
     * @return array
     */
    private static function file(array $override = []): array
    {
        return array_merge(['name' => 'attachment', 'filename' => 'invoice.pdf', 'contentType' => 'application/pdf', 'content' => "%PDF\r\n\0bytes"], $override);
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
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    // ---------------------------------------------------------------- the body

    /**
     * The body has the text fields, then the files, each one between boundaries, and the closing boundary
     * @return void
     */
    public function testTheBodyHasTheFieldsThenTheFilesAndTheClosingBoundary(): void
    {
        $body = FedexClient::multipartBody('B0UND', ['document' => '{"a":1}', 'rules' => '{"b":2}'], [self::file(), self::file(['name' => 'other', 'filename' => 'b.txt', 'contentType' => 'text/plain', 'content' => 'x'])]);

        $this->assertSame(
            "--B0UND\r\nContent-Disposition: form-data; name=\"document\"\r\n\r\n{\"a\":1}\r\n"
            . "--B0UND\r\nContent-Disposition: form-data; name=\"rules\"\r\n\r\n{\"b\":2}\r\n"
            . "--B0UND\r\nContent-Disposition: form-data; name=\"attachment\"; filename=\"invoice.pdf\"\r\nContent-Type: application/pdf\r\n\r\n%PDF\r\n\0bytes\r\n"
            . "--B0UND\r\nContent-Disposition: form-data; name=\"other\"; filename=\"b.txt\"\r\nContent-Type: text/plain\r\n\r\nx\r\n"
            . "--B0UND--\r\n",
            $body
        );
    }

    /**
     * A body without parts is only the closing boundary
     * @return void
     */
    public function testABodyWithoutPartsIsOnlyTheClosingBoundary(): void
    {
        $this->assertSame("--B--\r\n", FedexClient::multipartBody('B', [], []));
    }

    /**
     * A name, a file name or a media type that is empty, or has a quote or a line break, is refused
     * @return void
     */
    public function testANameAFileNameOrAMediaTypeThatWouldBreakTheHeadersIsRefused(): void
    {
        $cases = [
            'field name empty' => [['' => 'x'], []],
            'field name with a quote' => [['a"b' => 'x'], []],
            'field name with a line feed' => [["a\nb" => 'x'], []],
            'file name empty' => [[], [self::file(['name' => ''])]],
            'file name with a carriage return' => [[], [self::file(['name' => "a\rb"])]],
            'file filename empty' => [[], [self::file(['filename' => ''])]],
            'file filename with a quote' => [[], [self::file(['filename' => 'a".pdf'])]],
            'file filename with a line feed' => [[], [self::file(['filename' => "a\n.pdf"])]],
            'file contentType empty' => [[], [self::file(['contentType' => ''])]],
            'file contentType with a line break' => [[], [self::file(['contentType' => "text/plain\r\nX-Evil: 1"])]],
        ];

        foreach ($cases as $label => [$fields, $files]) {
            $e = self::thrown(function () use ($fields, $files) {
                FedexClient::multipartBody('B', $fields, $files);
            });

            $this->assertInstanceOf(FedexClientException::class, $e, $label);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode(), $label);
            $this->assertMatchesRegularExpression('/^The (name|filename|contentType) of a part of a multipart request must not be empty, nor have a quote or a line break$/', $e->getMessage(), $label);
        }
    }

    /**
     * A file without one of its keys, or with a key that is not a text, is refused
     * @return void
     */
    public function testAFileWithoutOneOfItsKeysOrWithAKeyThatIsNotATextIsRefused(): void
    {
        foreach (['name', 'filename', 'contentType', 'content'] as $key) {
            $missing = self::file();
            unset($missing[$key]);

            foreach (['missing' => $missing, 'not a text' => self::file([$key => 12])] as $how => $file) {
                $e = self::thrown(function () use ($file) {
                    FedexClient::multipartBody('B', [], [$file]);
                });

                $this->assertInstanceOf(FedexClientException::class, $e, "$key $how");
                $this->assertSame("Each file of a multipart request must have the text '{$key}'", $e->getMessage());
            }
        }

        $e = self::thrown(function () {
            FedexClient::multipartBody('B', [], ['not an array']);
        });
        $this->assertSame("Each file of a multipart request must have the text 'name'", $e->getMessage());
    }

    // ---------------------------------------------------------------- the request

    /**
     * The request that is sent: the url, the headers with the boundary, and the body made with that boundary
     * @return void
     */
    public function testTheRequestThatIsSent(): void
    {
        $transport = new FakeTransport([], Responses::make(201, '{"ok":true}'));
        $client = new FedexClient(StubAuth::make(), 'en_US', 30, 10, $transport);

        $result = $client->requestMultipart('POST', '/some/path', ['document' => '{}'], [self::file()], ['X-Extra' => 'yes'], 'CTID-1');
        $call = $transport->calls[0];

        $this->assertTrue($result->ok);
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://apis-sandbox.fedex.com/some/path', $call['url']);
        $this->assertMatchesRegularExpression('/^Content-Type: multipart\/form-data; boundary=(----FedexBoundary[0-9a-f]{32})$/', $call['headers'][3]);
        preg_match('/boundary=(.*)$/', $call['headers'][3], $m);
        $this->assertSame(
            ['Authorization: Bearer TOK', 'Accept: application/json', 'X-locale: en_US', 'Content-Type: multipart/form-data; boundary=' . $m[1], 'X-Extra: yes', 'X-customer-transaction-id: CTID-1'],
            $call['headers']
        );
        $this->assertSame(FedexClient::multipartBody($m[1], ['document' => '{}'], [self::file()]), $call['body']);
        $this->assertSame(30, $call['timeout']);
    }

    /**
     * Every request has a new boundary
     * @return void
     */
    public function testEveryRequestHasANewBoundary(): void
    {
        $transport = new FakeTransport([], Responses::make(201, '{}'));
        $client = new FedexClient(StubAuth::make(), 'en_US', 30, 10, $transport);

        $client->requestMultipart('POST', 'x', [], [self::file()]);
        $client->requestMultipart('POST', 'x', [], [self::file()]);

        $this->assertNotSame($transport->calls[0]['headers'][3], $transport->calls[1]['headers'][3]);
    }

    /**
     * A part that is not valid is refused before anything is sent
     * @return void
     */
    public function testAPartThatIsNotValidIsRefusedBeforeAnythingIsSent(): void
    {
        $transport = new FakeTransport([], Responses::make(201, '{}'));
        $client = new FedexClient(StubAuth::make(), 'en_US', 30, 10, $transport);

        $e = self::thrown(function () use ($client) {
            $client->requestMultipart('POST', 'x', [], [self::file(['filename' => 'a"b'])]);
        });

        $this->assertInstanceOf(FedexClientException::class, $e);
        $this->assertSame([], $transport->calls);
    }

    /**
     * A multipart request is sent again once after a 401, with a fresh token and the same body
     * @return void
     */
    public function testAMultipartRequestIsSentAgainOnceAfterA401(): void
    {
        $transport = new FakeTransport([Responses::make(401, '{"errors":[]}'), Responses::make(201, '{"ok":"second"}')]);
        $auth = StubAuth::make();
        $client = new FedexClient($auth, 'en_US', 30, 10, $transport);

        $result = $client->requestMultipart('POST', 'x', ['document' => '{}'], [self::file()]);

        $this->assertSame('second', $result->ok);
        $this->assertCount(2, $transport->calls);
        $this->assertSame($transport->calls[0]['body'], $transport->calls[1]['body']);
        $this->assertSame($transport->calls[0]['headers'][3], $transport->calls[1]['headers'][3]);
        $this->assertSame([false, true], $auth->forceSeen);
    }

    /**
     * With a retry policy, a multipart request is not retried after a busy server unless it is safe to retry
     * @return void
     */
    public function testAMultipartRequestIsNotRetriedUnlessItIsSafeToRetry(): void
    {
        foreach ([false => 1, true => 2] as $safe => $calls) {
            $transport = new FakeTransport([Responses::make(503, '{"errors":[]}')], Responses::make(201, '{"ok":1}'));
            $client = (new FedexClient(StubAuth::make(), 'en_US', 30, 10, $transport))->setRetryPolicy(3, 1, 1)->setRetryHooks(function () {
            }, null);

            self::thrown(function () use ($client, $safe) {
                $client->requestMultipart('POST', 'x', [], [self::file()], [], null, (bool) $safe);
            });

            $this->assertCount($calls, $transport->calls, $safe ? 'safe' : 'not safe');
        }
    }

    // ---------------------------------------------------------------- absolute urls

    /**
     * An absolute https url is used as it is, by request() and requestMultipart(), with its query
     * @return void
     */
    public function testAnAbsoluteHttpsUrlIsUsedAsItIs(): void
    {
        $transport = new FakeTransport([], Responses::make(201, '{}'));
        $client = new FedexClient(StubAuth::make(), 'en_US', 30, 10, $transport);

        $client->request('POST', 'https://other.example/v1/x', ['a' => 1], ['q' => 1]);
        $client->requestMultipart('POST', 'https://other.example/v1/y', [], [self::file()]);
        $client->request('POST', 'http://other.example/v1/z');

        $this->assertSame('https://other.example/v1/x?q=1', $transport->calls[0]['url']);
        $this->assertSame('https://other.example/v1/y', $transport->calls[1]['url']);
        $this->assertSame('https://apis-sandbox.fedex.com/http://other.example/v1/z', $transport->calls[2]['url'], 'only https is taken as absolute');
    }
}
