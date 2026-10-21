<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Http;

use Mariopereira\Fedex\Http\CurlTransport;
use Mariopereira\Fedex\Http\FedexTransport;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the response of the transport
 */
final class FedexTransportResponseTest extends TestCase
{
    /**
     * The getters
     * @return void
     */
    public function testTheGetters(): void
    {
        $r = new FedexTransportResponse(200, '{"a":1}');

        $this->assertSame(200, $r->getStatus());
        $this->assertSame('{"a":1}', $r->getBody());
        $this->assertNull($r->getError());
        $this->assertFalse($r->hasFailed());
    }

    /**
     * The defaults are an empty body and no error
     * @return void
     */
    public function testTheDefaultsAreAnEmptyBodyAndNoError(): void
    {
        $r = new FedexTransportResponse(0);

        $this->assertSame('', $r->getBody());
        $this->assertNull($r->getError());
        $this->assertFalse($r->hasFailed());
    }

    /**
     * An error means that the request failed
     * @return void
     */
    public function testAnErrorMeansThatTheRequestFailed(): void
    {
        $r = new FedexTransportResponse(0, '', 'timed out');

        $this->assertTrue($r->hasFailed());
        $this->assertSame('timed out', $r->getError());
        $this->assertSame(0, $r->getStatus());
    }

    /**
     * An empty string error still means that the request failed
     * @return void
     */
    public function testAnEmptyStringErrorStillMeansThatTheRequestFailed(): void
    {
        $this->assertTrue((new FedexTransportResponse(0, '', ''))->hasFailed());
    }

    /**
     * An HTTP error status is not a failure
     * @return void
     */
    public function testAnHttpErrorStatusIsNotAFailure(): void
    {
        $this->assertFalse((new FedexTransportResponse(500, 'x'))->hasFailed());
    }

    /**
     * CURL transport implements the interface
     * @return void
     */
    public function testCurlTransportImplementsTheInterface(): void
    {
        $this->assertInstanceOf(FedexTransport::class, new CurlTransport());
    }

    /**
     * Without headers there are none
     * @return void
     */
    public function testWithoutHeadersThereAreNone(): void
    {
        $r = new FedexTransportResponse(200, 'x');

        $this->assertSame([], $r->getHeaders());
        $this->assertNull($r->getHeader('Retry-After'));
    }

    /**
     * The names of the headers are lower case and are looked up in any case
     * @return void
     */
    public function testTheNamesOfTheHeadersAreLowerCaseAndAreLookedUpInAnyCase(): void
    {
        $r = new FedexTransportResponse(429, '', null, ['Retry-After' => ' 30 ', 'X-Other' => 'a', ' Content-Type ' => 'application/json']);

        $this->assertSame(['retry-after' => '30', 'x-other' => 'a', 'content-type' => 'application/json'], $r->getHeaders());

        foreach (['Retry-After', 'retry-after', 'RETRY-AFTER', ' Retry-After '] as $name) {
            $this->assertSame('30', $r->getHeader($name), $name);
        }

        $this->assertNull($r->getHeader('Missing'));
    }

    /**
     * The values that are lists are joined and what is not usable is left out
     * @return void
     */
    public function testTheValuesThatAreListsAreJoinedAndWhatIsNotUsableIsLeftOut(): void
    {
        $r = new FedexTransportResponse(200, '', null, [
            'A' => ['one', 'two'],
            'B' => 5,
            'C' => ['x', ['nested'], 'y'],
            'D' => new \stdClass(),
            'E' => null,
            '' => 'no name',
            '  ' => 'blank name',
            'F' => '',
        ]);

        $this->assertSame(['a' => 'one, two', 'b' => '5', 'c' => 'x, y', 'f' => ''], $r->getHeaders());
    }

    /**
     * The headers do not change the other data of the response
     * @return void
     */
    public function testTheHeadersDoNotChangeTheOtherDataOfTheResponse(): void
    {
        $r = new FedexTransportResponse(503, 'body', 'error', ['A' => 'b']);

        $this->assertSame([503, 'body', 'error', true], [$r->getStatus(), $r->getBody(), $r->getError(), $r->hasFailed()]);
    }
}
