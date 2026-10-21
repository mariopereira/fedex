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
}
