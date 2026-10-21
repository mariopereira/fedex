<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Misc;

use Mariopereira\Fedex\Misc\FedexUtils;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the helpers in FedexUtils
 */
final class FedexUtilsTest extends TestCase
{
    // ---------------------------------------------------------------- arrayToObject / objectToArray

    /**
     * Array with a string key becomes an object
     * @return void
     */
    public function testArrayWithAStringKeyBecomesAnObject(): void
    {
        $r = FedexUtils::arrayToObject(['a' => 1]);

        $this->assertInstanceOf(\stdClass::class, $r);
        $this->assertSame(1, $r->a);
    }

    /**
     * Arrays with only numeric keys stay arrays
     * @return void
     */
    public function testArraysWithOnlyNumericKeysStayArrays(): void
    {
        $this->assertSame([1, 2, 3], FedexUtils::arrayToObject([1, 2, 3]));
        $this->assertSame([3 => 'x', 7 => 'y'], FedexUtils::arrayToObject([3 => 'x', 7 => 'y']));
    }

    /**
     * An empty array becomes an empty object
     * @return void
     */
    public function testAnEmptyArrayBecomesAnEmptyObject(): void
    {
        $r = FedexUtils::arrayToObject([]);

        $this->assertInstanceOf(\stdClass::class, $r);
        $this->assertSame([], get_object_vars($r));
    }

    /**
     * Mixed keys become an object
     * @return void
     */
    public function testMixedKeysBecomeAnObject(): void
    {
        $r = FedexUtils::arrayToObject(['a' => 1, 5 => 2]);

        $this->assertInstanceOf(\stdClass::class, $r);
        $this->assertSame(1, $r->a);
        $this->assertSame(2, $r->{'5'});
    }

    /**
     * Nested lists stay arrays and their items become objects
     * @return void
     */
    public function testNestedListsStayArraysAndTheirItemsBecomeObjects(): void
    {
        $r = FedexUtils::arrayToObject(['list' => [['id' => 1], ['id' => 2]]]);

        $this->assertInstanceOf(\stdClass::class, $r);
        $this->assertIsArray($r->list);
        $this->assertCount(2, $r->list);
        $this->assertInstanceOf(\stdClass::class, $r->list[0]);
        $this->assertSame(2, $r->list[1]->id);
    }

    /**
     * A nested empty array becomes an empty object
     * @return void
     */
    public function testANestedEmptyArrayBecomesAnEmptyObject(): void
    {
        $this->assertInstanceOf(\stdClass::class, FedexUtils::arrayToObject(['alerts' => []])->alerts);
    }

    /**
     * Scalars and objects are returned as they are
     * @return void
     */
    public function testScalarsAndObjectsAreReturnedAsTheyAre(): void
    {
        $o = new \stdClass();

        $this->assertSame(5, FedexUtils::arrayToObject(5));
        $this->assertSame('x', FedexUtils::arrayToObject('x'));
        $this->assertNull(FedexUtils::arrayToObject(null));
        $this->assertSame($o, FedexUtils::arrayToObject($o));
    }

    /**
     * Object to array and back keeps the JSON
     * @return void
     */
    public function testObjectToArrayAndBackKeepsTheJson(): void
    {
        $json = '{"a":{"b":[{"c":1},{"d":{"e":"x"}}]},"f":{}}';

        $this->assertSame($json, json_encode(FedexUtils::arrayToObject(FedexUtils::objectToArray(json_decode($json)))));
    }

    /**
     * Object to array converts recursively
     * @return void
     */
    public function testObjectToArrayConvertsRecursively(): void
    {
        $this->assertSame(['a' => ['b' => [1, ['c' => 2]]]], FedexUtils::objectToArray(json_decode('{"a":{"b":[1,{"c":2}]}}')));
        $this->assertSame('x', FedexUtils::objectToArray('x'));
    }

    // ---------------------------------------------------------------- extractErrors

    /**
     * Errors list is returned as it is
     * @return void
     */
    public function testErrorsListIsReturnedAsItIs(): void
    {
        $resp = json_decode('{"errors":[{"code":"A"}]}');

        $this->assertSame($resp->errors, FedexUtils::extractErrors($resp));
    }

    /**
     * @dataProvider errorsProvider
     * @param string $json Decoded response
     * @param array $expected What extractErrors() has to return
     */
    public function testExtractErrors(string $json, array $expected): void
    {
        $this->assertEquals($expected, FedexUtils::extractErrors(json_decode($json)));
    }

    /**
     * The cases of testExtractErrors(): extract errors
     * @return array
     */
    public function errorsProvider(): array
    {
        return [
            'empty list' => ['{"errors":[]}', []],
            'null' => ['{"errors":null}', []],
            'missing' => ['{"a":1}', []],
            'a string is wrapped' => ['{"errors":"boom"}', ['boom']],
            'an object is wrapped' => ['{"errors":{"code":"A"}}', [(object) ['code' => 'A']]],
            'an integer is dropped' => ['{"errors":5}', []],
            'a boolean is dropped' => ['{"errors":true}', []],
        ];
    }

    /**
     * @dataProvider notAnObjectProvider
     * @param mixed $data What is not a decoded response object
     */
    public function testExtractErrorsOfSomethingThatIsNotAnObject($data): void
    {
        $this->assertSame([], FedexUtils::extractErrors($data));
    }

    /**
     * The cases of testExtractErrorsOfSomethingThatIsNotAnObject() and testExtractStringOfSomethingThatIsNotAnObject(): extract errors of something that is not an object
     * @return array
     */
    public function notAnObjectProvider(): array
    {
        return ['a string' => ['x'], 'a list' => [[1, 2]], 'null' => [null]];
    }

    // ---------------------------------------------------------------- extractString

    /**
     * @dataProvider stringsProvider
     * @param string $json Decoded response
     * @param string|null $expected What extractString() has to return for the field "f"
     */
    public function testExtractString(string $json, ?string $expected): void
    {
        $this->assertSame($expected, FedexUtils::extractString(json_decode($json), 'f'));
    }

    /**
     * The cases of testExtractString(): extract string
     * @return array
     */
    public function stringsProvider(): array
    {
        return [
            'a string is kept' => ['{"f":"t1"}', 't1'],
            'an integer is cast' => ['{"f":123}', '123'],
            'a decimal is cast' => ['{"f":1.5}', '1.5'],
            'an empty string is kept' => ['{"f":""}', ''],
            'a list gives null' => ['{"f":[]}', null],
            'an object gives null' => ['{"f":{}}', null],
            'a boolean gives null' => ['{"f":false}', null],
            'null gives null' => ['{"f":null}', null],
            'a missing field gives null' => ['{"a":1}', null],
        ];
    }

    /**
     * @dataProvider notAnObjectProvider
     * @param mixed $data What is not a decoded response object
     */
    public function testExtractStringOfSomethingThatIsNotAnObject($data): void
    {
        $this->assertNull(FedexUtils::extractString($data, 'f'));
    }

    // ---------------------------------------------------------------- extractInt

    /**
     * @dataProvider integersProvider
     * @param mixed $value The value of the field "f"
     * @param int|null $expected What extractInt() has to return
     */
    public function testExtractInt($value, ?int $expected): void
    {
        $this->assertSame($expected, FedexUtils::extractInt((object) ['f' => $value], 'f'));
    }

    /**
     * The cases of testExtractInt(): extract int
     * @return array
     */
    public function integersProvider(): array
    {
        $max = (string) PHP_INT_MAX;
        $min = (string) PHP_INT_MIN;
        $overMax = (string) PHP_INT_MAX . '0';

        return [
            'an integer' => [5, 5],
            'zero' => [0, 0],
            'a negative integer' => [-7, -7],
            'a numeric string' => ['3600', 3600],
            'a plus sign' => ['+12', 12],
            'a minus sign' => ['-12', -12],
            'minus zero' => ['-0', 0],
            'white space around it' => [" \t12\r\n ", 12],
            'the largest integer' => [$max, PHP_INT_MAX],
            'the smallest integer' => [$min, PHP_INT_MIN],
            'one more than the largest integer' => [$overMax, null],
            'a leading zero' => ['007', null],
            'a decimal in a string' => ['1.5', null],
            'an exponent' => ['1e3', null],
            'text after the number' => ['12abc', null],
            'a trailing new line is white space, not a second line' => ["12\n", 12],
            'an empty string' => ['', null],
            'only white space' => ['  ', null],
            'a sign alone' => ['-', null],
            'a decimal number' => [1.5, null],
            'a decimal number without a fraction' => [3.0, null],
            'true' => [true, null],
            'a list' => [[1], null],
            'null' => [null, null],
        ];
    }

    /**
     * Extract int of a missing field or something that is not an object
     * @return void
     */
    public function testExtractIntOfAMissingFieldOrSomethingThatIsNotAnObject(): void
    {
        $this->assertNull(FedexUtils::extractInt((object) ['a' => 1], 'f'));
        $this->assertNull(FedexUtils::extractInt('x', 'f'));
        $this->assertNull(FedexUtils::extractInt([5], 'f'));
        $this->assertNull(FedexUtils::extractInt(null, 'f'));
    }

    // ---------------------------------------------------------------- pruneEmpty

    /**
     * @dataProvider pruneProvider
     * @param mixed $value The value to prune
     * @param mixed $expected The result
     */
    public function testPruneEmpty($value, $expected): void
    {
        $this->assertSame($expected, FedexUtils::pruneEmpty($value));
    }

    /**
     * The cases of testPruneEmpty(): prune empty
     * @return array
     */
    public function pruneProvider(): array
    {
        return [
            'null' => [null, null],
            'an empty array' => [[], null],
            'only nulls' => [['a' => null, 'b' => null], null],
            'only empty arrays' => [['a' => [], 'b' => [[], []]], null],
            'a scalar is returned as it is' => ['x', 'x'],
            'zero is kept' => [0, 0],
            'false is kept' => [false, false],
            'an empty string is kept' => ['', ''],
            'the string "0" is kept' => ['0', '0'],
            'false, 0, empty and "0" inside a map are kept' => [['a' => false, 'b' => 0, 'c' => '', 'd' => '0'], ['a' => false, 'b' => 0, 'c' => '', 'd' => '0']],
            'a list is renumbered' => [[null, 1, [], 2], [1, 2]],
            'a list that loses its first items starts at 0' => [[null, null, 'x'], ['x']],
            'nulls are removed from a map' => [['a' => 1, 'b' => null], ['a' => 1]],
            'nested' => [['a' => ['b' => null, 'c' => 0], 'd' => ['e' => []]], ['a' => ['c' => 0]]],
            'a map that only looks like a list keeps its keys' => [[1 => 'a', 2 => 'b'], [1 => 'a', 2 => 'b']],
            'a list inside a map' => [['l' => [null, ['k' => null], ['k' => 1]]], ['l' => [['k' => 1]]]],
        ];
    }

    // ---------------------------------------------------------------- uuidV4

    /**
     * UUID v 4 has the format of a version 4 UUID
     * @return void
     */
    public function testUuidV4HasTheFormatOfAVersion4Uuid(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', FedexUtils::uuidV4());
    }

    /**
     * UUID v 4 is different every time
     * @return void
     */
    public function testUuidV4IsDifferentEveryTime(): void
    {
        $ids = [];

        for ($i = 0; $i < 200; $i++) {
            $ids[FedexUtils::uuidV4()] = true;
        }

        $this->assertCount(200, $ids);
    }

    // ---------------------------------------------------------------- isDateValid

    /**
     * @dataProvider datesProvider
     * @param string $date The date
     * @param string $format Its format
     * @param bool $expected Is it valid?
     */
    public function testIsDateValid(string $date, string $format, bool $expected): void
    {
        $this->assertSame($expected, FedexUtils::isDateValid($date, $format));
    }

    /**
     * The cases of testIsDateValid(): is date valid
     * @return array
     */
    public function datesProvider(): array
    {
        return [
            'a real date' => ['2019-09-06', 'Y-m-d', true],
            'a leap day' => ['2020-02-29', 'Y-m-d', true],
            'a day that does not exist' => ['2019-02-30', 'Y-m-d', false],
            'a month that does not exist' => ['2019-13-01', 'Y-m-d', false],
            'without leading zeros' => ['2019-9-6', 'Y-m-d', false],
            'with a time' => ['2019-09-06T10:00:00', 'Y-m-d', false],
            'text' => ['abc', 'Y-m-d', false],
            'empty' => ['', 'Y-m-d', false],
            'another format' => ['06/09/2019', 'd/m/Y', true],
        ];
    }

    // ---------------------------------------------------------------- closeCurl

    /**
     * Close cURL raises nothing and returns nothing
     * @return void
     */
    public function testCloseCurlRaisesNothingAndReturnsNothing(): void
    {
        $handle = curl_init('https://example.invalid/'); // creating a handle does not connect

        $this->assertNull(FedexUtils::closeCurl($handle));

        if (PHP_VERSION_ID < 80000) {
            $this->assertFalse(is_resource($handle), 'before PHP 8.0 the handle is closed');
        } else {
            $this->assertInstanceOf(\CurlHandle::class, $handle, 'since PHP 8.0 the handle is an object that needs no closing');
            $this->assertSame(0, curl_getinfo($handle, CURLINFO_HTTP_CODE));
        }
    }

    /**
     * Several handles can be closed in a row
     * @return void
     */
    public function testSeveralHandlesCanBeClosedInARow(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $handle = curl_init('https://example.invalid/' . $i);
            curl_setopt($handle, CURLOPT_TIMEOUT, 1);
            FedexUtils::closeCurl($handle);
        }

        $this->addToAssertionCount(1);
    }

    // ---------------------------------------------------------------- the small helpers

    /**
     * Grant types
     * @return void
     */
    public function testGrantTypes(): void
    {
        $this->assertTrue(FedexUtils::isValidGrantType('client_credentials'));
        $this->assertTrue(FedexUtils::isValidGrantType('client_pc_credentials'));
        $this->assertTrue(FedexUtils::isValidGrantType('csp_credentials'));
        $this->assertFalse(FedexUtils::isValidGrantType('password'));
        $this->assertFalse(FedexUtils::isValidGrantType(''));

        $this->assertFalse(FedexUtils::grantNeedsChildAuth('client_credentials'));
        $this->assertTrue(FedexUtils::grantNeedsChildAuth('client_pc_credentials'));
        $this->assertTrue(FedexUtils::grantNeedsChildAuth('csp_credentials'));
    }

    /**
     * The URL depends on the mode
     * @return void
     */
    public function testTheUrlDependsOnTheMode(): void
    {
        $this->assertSame('https://apis-sandbox.fedex.com/', FedexUtils::getUrl(true));
        $this->assertSame('https://apis.fedex.com/', FedexUtils::getUrl(false));
    }

    /**
     * HTTP status text
     * @return void
     */
    public function testHttpStatusText(): void
    {
        $this->assertSame('OK', FedexUtils::httpStatusText(200));
        $this->assertSame('200: OK', FedexUtils::httpStatusText(200, true));
        $this->assertSame('Unauthorized', FedexUtils::httpStatusText(401));
        $this->assertSame('Not Found', FedexUtils::httpStatusText(404));
        $this->assertSame('Internal Server Error', FedexUtils::httpStatusText(500));
        $this->assertSame('Unknown HTTP status code: 999', FedexUtils::httpStatusText(999));
        $this->assertSame('999: Unknown HTTP status code', FedexUtils::httpStatusText(999, true));
    }

    /**
     * The seconds of a Retry-After are read
     * @return void
     */
    public function testTheSecondsOfARetryAfterAreRead(): void
    {
        $this->assertSame(120, FedexUtils::parseRetryAfter('120'));
        $this->assertSame(0, FedexUtils::parseRetryAfter('0'));
        $this->assertSame(5, FedexUtils::parseRetryAfter(" 5\t"));
        $this->assertSame(7, FedexUtils::parseRetryAfter('0000000007'));
        $this->assertSame(9999999999, FedexUtils::parseRetryAfter('9999999999'));
    }

    /**
     * A Retry-After that is not valid is ignored
     * @return void
     */
    public function testARetryAfterThatIsNotValidIsIgnored(): void
    {
        foreach ([null, '', '   ', '-1', '+5', '1.5', '1e3', '5 s', 'abc', '99999999999', "5\n", "5\r\n", '0x10', '१२', 'Sun', 'Sun, 06 Nov 1994'] as $value) {
            $this->assertNull(FedexUtils::parseRetryAfter($value), var_export($value, true));
        }
    }

    /**
     * The three date formats of HTTP are read as seconds from now
     * @return void
     */
    public function testTheThreeDateFormatsOfHttpAreReadAsSecondsFromNow(): void
    {
        $at = 784111777; // 1994-11-06 08:49:37 UTC

        $this->assertSame(30, FedexUtils::parseRetryAfter('Sun, 06 Nov 1994 08:49:37 GMT', $at - 30));
        $this->assertSame(30, FedexUtils::parseRetryAfter('Sunday, 06-Nov-94 08:49:37 GMT', $at - 30));
        $this->assertSame(30, FedexUtils::parseRetryAfter('Sun Nov  6 08:49:37 1994', $at - 30));
        $this->assertSame(30, FedexUtils::parseRetryAfter('Sun Nov 6 08:49:37 1994', $at - 30), 'a single space is read too');
        $this->assertSame(14 * 86400 + 30, FedexUtils::parseRetryAfter('Sun Nov 20 08:49:37 1994', $at - 30));
        $this->assertSame(30, FedexUtils::parseRetryAfter("  Sun, 06 Nov 1994 08:49:37 GMT\t", $at - 30));
    }

    /**
     * A date in the past is no wait, and a date is read against the current time by default
     * @return void
     */
    public function testADateInThePastIsNoWaitAndADateIsReadAgainstTheCurrentTimeByDefault(): void
    {
        $at = 784111777;

        $this->assertSame(0, FedexUtils::parseRetryAfter('Sun, 06 Nov 1994 08:49:37 GMT', $at));
        $this->assertSame(0, FedexUtils::parseRetryAfter('Sun, 06 Nov 1994 08:49:37 GMT', $at + 5000));
        $this->assertSame(0, FedexUtils::parseRetryAfter('Sun, 06 Nov 1994 08:49:37 GMT'));

        $seconds = FedexUtils::parseRetryAfter(gmdate('D, d M Y H:i:s', time() + 100) . ' GMT');

        $this->assertGreaterThanOrEqual(98, $seconds);
        $this->assertLessThanOrEqual(100, $seconds);
    }

    /**
     * A date that is not a real one is ignored
     * @return void
     */
    public function testADateThatIsNotARealOneIsIgnored(): void
    {
        $dates = [
            'Mon, 06 Nov 1994 08:49:37 GMT',
            'Sun, 31 Feb 1994 08:49:37 GMT',
            'Sun, 06 Nov 1994 25:49:37 GMT',
            'Sun, 06 Nov 1994 08:49:37 UTC',
            'Sun, 06 Nov 1994 08:49:37 gmt',
            'Sun, 06 Nov 1994 08:49:37 +0000',
            'Sun, 06 Nov 1994 08:49:37',
            'Monday, 06-Nov-94 08:49:37 GMT',
            'Sun Nov  6 08:49:37 1995',
            '1994-11-06T08:49:37Z',
            'Sun, 06 Nov 1994 08:49:37 GMT trailing',
        ];

        foreach ($dates as $date) {
            $this->assertNull(FedexUtils::parseRetryAfter($date, 0), $date);
        }
    }
}
