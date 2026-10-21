<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Exception;

use Mariopereira\Fedex\Exception\FedexAddressValidationException;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexClientException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Exception\FedexLocationsSearchException;
use Mariopereira\Fedex\Exception\FedexPostalCodeValidationException;
use Mariopereira\Fedex\Exception\FedexShipException;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the exceptions of the package
 */
final class FedexExceptionTest extends TestCase
{
    /**
     * An exception that carries these errors
     * @param array $errors The errors
     * @param string $class Class of the exception
     * @return string What getErrorsAsString() says
     */
    private function errorsAsString(array $errors, string $class = FedexException::class): string
    {
        return (new $class('m', 0, null, 0, null, $errors))->getErrorsAsString();
    }

    /**
     * No errors give an empty text
     * @return void
     */
    public function testNoErrorsGiveAnEmptyText(): void
    {
        $this->assertSame('', $this->errorsAsString([]));
    }

    /**
     * An error object with its parameters
     * @return void
     */
    public function testAnErrorObjectWithItsParameters(): void
    {
        $errors = json_decode('[{"code":"E1","message":"msg","parameterList":[{"key":"k","value":"v"}]}]');

        $this->assertSame('E1: msg Parameters: k = v', $this->errorsAsString($errors));
    }

    /**
     * An error array
     * @return void
     */
    public function testAnErrorArray(): void
    {
        $this->assertSame('X: m', $this->errorsAsString([['code' => 'X', 'message' => 'm']]));
    }

    /**
     * An error array with its parameters
     * @return void
     */
    public function testAnErrorArrayWithItsParameters(): void
    {
        $this->assertSame(
            'P: q Parameters: k = v',
            $this->errorsAsString([['code' => 'P', 'message' => 'q', 'parameterList' => [['key' => 'k', 'value' => 'v']]]])
        );
    }

    /**
     * A plain string is kept
     * @return void
     */
    public function testAPlainStringIsKept(): void
    {
        $this->assertSame(
            '$.output: required key is missing',
            $this->errorsAsString(['$.output: required key is missing'], FedexPostalCodeValidationException::class)
        );
    }

    /**
     * Objects arrays and strings can be mixed
     * @return void
     */
    public function testObjectsArraysAndStringsCanBeMixed(): void
    {
        $this->assertSame(
            'A: a; B: b; c',
            $this->errorsAsString([json_decode('{"code":"A","message":"a"}'), ['code' => 'B', 'message' => 'b'], 'c'])
        );
    }

    /**
     * The string zero is kept
     * @return void
     */
    public function testTheStringZeroIsKept(): void
    {
        $this->assertSame('0', $this->errorsAsString(['0']));
    }

    /**
     * Null integers and booleans are ignored
     * @return void
     */
    public function testNullIntegersAndBooleansAreIgnored(): void
    {
        $this->assertSame('', $this->errorsAsString([null, 5, true]));
    }

    /**
     * Blank entries are dropped
     * @return void
     */
    public function testBlankEntriesAreDropped(): void
    {
        $this->assertSame('', $this->errorsAsString(['  ', ['code' => '', 'message' => '']]));
    }

    /**
     * Blank entries between others leave no empty item
     * @return void
     */
    public function testBlankEntriesBetweenOthersLeaveNoEmptyItem(): void
    {
        $this->assertSame('a; b', $this->errorsAsString(['a', '  ', ['code' => '', 'message' => ''], 'b']));
        $this->assertSame('a', $this->errorsAsString(['a', '  ']));
    }

    /**
     * The getters return what the constructor received
     * @return void
     */
    public function testTheGettersReturnWhatTheConstructorReceived(): void
    {
        $previous = new \RuntimeException('before');
        $data = (object) ['a' => 1];
        $e = new FedexException('msg', 7, $previous, 404, 'T1', ['x'], $data, 'C1');

        $this->assertSame('msg', $e->getMessage());
        $this->assertSame(7, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame('T1', $e->getTransactionId());
        $this->assertSame(['x'], $e->getErrors());
        $this->assertSame($data, $e->getData());
        $this->assertSame('C1', $e->getCustomerTransactionId());
    }

    /**
     * The defaults
     * @return void
     */
    public function testTheDefaults(): void
    {
        $e = new FedexException();

        $this->assertSame('', $e->getMessage());
        $this->assertSame(0, $e->getCode());
        $this->assertSame(0, $e->getStatusCode());
        $this->assertNull($e->getTransactionId());
        $this->assertSame([], $e->getErrors());
        $this->assertNull($e->getData());
        $this->assertNull($e->getCustomerTransactionId());
    }

    /**
     * The error codes
     * @return void
     */
    public function testTheErrorCodes(): void
    {
        $this->assertSame(1, FedexException::EC_MISSING_PARAMETER);
        $this->assertSame(2, FedexException::EC_BAD_PARAMETER);
        $this->assertSame(100, FedexException::EC_TRANSPORT_ERROR);
        $this->assertSame(101, FedexException::EC_BAD_RESULT_RETURNED);
        $this->assertSame(200, FedexException::EC_UNABLE_TO_AUTHENTICATE);
    }

    /**
     * @dataProvider exceptionClassesProvider
     * @param string $class An exception of the package
     */
    public function testEveryExceptionIsAFedexExceptionThatKeepsEverything(string $class): void
    {
        $e = new $class('msg', 3, null, 500, 'T', ['err'], 'data', 'C');

        $this->assertInstanceOf(FedexException::class, $e);
        $this->assertSame(['msg', 3, 500, 'T', ['err'], 'data', 'C'], [$e->getMessage(), $e->getCode(), $e->getStatusCode(), $e->getTransactionId(), $e->getErrors(), $e->getData(), $e->getCustomerTransactionId()]);
    }

    /**
     * The cases of testEveryExceptionIsAFedexExceptionThatKeepsEverything(): every exception is a fedex exception that keeps everything
     * @return array
     */
    public function exceptionClassesProvider(): array
    {
        return [
            'auth' => [FedexAuthException::class],
            'client' => [FedexClientException::class],
            'postal code validation' => [FedexPostalCodeValidationException::class],
            'locations search' => [FedexLocationsSearchException::class],
            'address validation' => [FedexAddressValidationException::class],
            'ship' => [FedexShipException::class],
        ];
    }

    /**
     * The client exception keeps the seconds of the Retry-After
     * @return void
     */
    public function testTheClientExceptionKeepsTheSecondsOfTheRetryAfter(): void
    {
        $this->assertNull((new FedexClientException('m'))->getRetryAfter());
        $this->assertNull((new FedexClientException('m', 1, null, 503, 'T', ['e'], null, 'C'))->getRetryAfter());
        $this->assertSame(30, (new FedexClientException('m', 1, null, 503, 'T', ['e'], null, 'C', 30))->getRetryAfter());
        $this->assertSame(0, (new FedexClientException('m', 1, null, 429, null, [], null, null, 0))->getRetryAfter());

        $e = new FedexClientException('m', 7, null, 503, 'T', ['e'], ['d'], 'C', 4);

        $this->assertSame(['m', 7, 503, 'T', ['e'], ['d'], 'C'], [$e->getMessage(), $e->getCode(), $e->getStatusCode(), $e->getTransactionId(), $e->getErrors(), $e->getData(), $e->getCustomerTransactionId()], 'the other data are as they were');
    }
}
