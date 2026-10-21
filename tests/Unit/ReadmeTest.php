<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests that the examples of the README still match the package. The code of the README is never run: it is parsed with
 * the grammar of the PHP that runs the tests (so with PHP 7.4 an example that needs a newer PHP fails), and what it uses
 * of the package (classes, methods, constants, the number of arguments, the paths of the repository) is looked up
 */
final class ReadmeTest extends TestCase
{
    /**
     * Methods that the examples call and that are not in the package, with the reason. Empty: every call is the package's
     * @var string[]
     */
    private const FOREIGN_METHODS = [];

    /**
     * The classes that the examples define themselves, so that they are not looked for in the package
     * @var string[]|null
     */
    private static $definedHere;

    /**
     * The text of the README, with line feeds
     * @return string
     */
    private static function readme(): string
    {
        return str_replace("\r\n", "\n", file_get_contents(dirname(__DIR__, 2) . '/README.md'));
    }

    /**
     * The blocks of PHP code of the README
     * @return array[] The line of the README where the block starts, and its code
     */
    private static function blocks(): array
    {
        $readme = self::readme();
        preg_match_all('/^```php\n(.*?)^```/ms', $readme, $found, PREG_OFFSET_CAPTURE);
        $blocks = [];

        foreach ($found[1] as [$code, $offset]) {
            $blocks[] = [substr_count(substr($readme, 0, $offset), "\n") + 1, $code];
        }

        return $blocks;
    }

    /**
     * The code of a block as a file of PHP
     * @param string $code The code of the block
     * @return string
     */
    private static function asFile(string $code): string
    {
        return (strpos($code, '<?php') === 0) ? $code : "<?php\n" . $code;
    }

    /**
     * The classes of the package: the short name and the full name of each
     * @return string[]
     */
    private static function classes(): array
    {
        static $classes = null;

        if ($classes === null) {
            $classes = [];
            $src = dirname(__DIR__, 2) . '/src';

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $relative = str_replace(['\\', '.php'], ['/', ''], substr((string) $file, strlen($src) + 1));
                $class = 'Mariopereira\\Fedex\\' . str_replace('/', '\\', $relative);

                if (class_exists($class) || interface_exists($class)) {
                    $classes[substr($class, strrpos($class, '\\') + 1)] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * The names of the public methods of the classes of the package
     * @return bool[] The names as keys
     */
    private static function methods(): array
    {
        static $methods = null;

        if ($methods === null) {
            $methods = [];

            foreach (self::classes() as $class) {
                foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    $methods[$method->getName()] = true;
                }
            }
        }

        return $methods;
    }

    /**
     * The names of the classes that the examples define
     * @return string[]
     */
    private static function definedHere(): array
    {
        if (self::$definedHere === null) {
            self::$definedHere = [];

            foreach (self::blocks() as [, $code]) {
                preg_match_all('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $code, $found);
                self::$definedHere = array_merge(self::$definedHere, $found[1]);
            }
        }

        return self::$definedHere;
    }

    /**
     * The blocks as the cases of a data provider
     * @return array
     */
    public function blocksProvider(): array
    {
        $cases = [];

        foreach (self::blocks() as [$line, $code]) {
            $cases['README line ' . $line] = [$line, $code];
        }

        return $cases;
    }

    /**
     * The calls of a block that have arguments to count: new Name(...) and Name::method(...) of the package
     * @param string $code The code of the block
     * @return array[] The kind (new or static), the class, the method (null for new), the number of arguments and the line
     */
    private static function calls(string $code): array
    {
        $tokens = token_get_all(self::asFile($code), TOKEN_PARSE);
        $count = count($tokens);
        $calls = [];

        for ($i = 0; $i < $count; $i++) {
            $isNew = is_array($tokens[$i]) && ($tokens[$i][0] === T_NEW);
            $name = null;
            $method = null;
            $open = null;

            if ($isNew && is_array($tokens[$i + 2] ?? null) && ($tokens[$i + 2][0] === T_STRING) && (($tokens[$i + 3] ?? null) === '(')) {
                $name = $tokens[$i + 2][1];
                $open = $i + 3;
            } elseif (is_array($tokens[$i]) && ($tokens[$i][0] === T_STRING) && is_array($tokens[$i + 1] ?? null) && ($tokens[$i + 1][0] === T_DOUBLE_COLON)
                && is_array($tokens[$i + 2] ?? null) && ($tokens[$i + 2][0] === T_STRING) && (($tokens[$i + 3] ?? null) === '(')
                && !(is_array($tokens[$i - 1] ?? null) && in_array($tokens[$i - 1][0], [T_NS_SEPARATOR, T_OBJECT_OPERATOR], true))) {
                $name = $tokens[$i][1];
                $method = $tokens[$i + 2][1];
                $open = $i + 3;
            }

            if ($name === null) {
                continue;
            }

            $depth = 0;
            $arguments = 0;
            $filled = false;

            for ($j = $open; $j < $count; $j++) {
                $token = $tokens[$j];
                $text = is_array($token) ? $token[1] : $token;

                if ($text === '(' || $text === '[' || $text === '{') {
                    $depth++;
                } elseif ($text === ')' || $text === ']' || $text === '}') {
                    $depth--;

                    if ($depth === 0) {
                        break;
                    }
                } elseif ($text === ',' && $depth === 1) {
                    $arguments++;
                    $filled = false;
                    continue;
                }

                if ($depth >= 1 && $j > $open && !(is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) {
                    $filled = true;
                }
            }

            $calls[] = [$isNew ? 'new' : 'static', $name, $method, $arguments + ($filled ? 1 : 0), is_array($tokens[$i]) ? $tokens[$i][2] : 0];
        }

        return $calls;
    }

    /**
     * There are examples to check
     * @return void
     */
    public function testThereAreExamplesToCheck(): void
    {
        $this->assertGreaterThanOrEqual(10, count(self::blocks()), 'the README has no php blocks, or the pattern that finds them is wrong');
        $this->assertGreaterThanOrEqual(15, count(self::classes()), 'the classes of the package were not found');
        $this->assertArrayHasKey('FedexClient', self::classes());
        $this->assertArrayHasKey('setRetryPolicy', self::methods());
    }

    /**
     * Every block parses with the PHP that runs the tests
     * @dataProvider blocksProvider
     * @param int $line The line of the README where the block starts
     * @param string $code The code of the block
     * @return void
     */
    public function testEveryBlockParsesWithThePhpThatRunsTheTests(int $line, string $code): void
    {
        try {
            token_get_all(self::asFile($code), TOKEN_PARSE);
        } catch (\ParseError $e) {
            $this->fail('README line ' . ($line + $e->getLine() - 1) . ' (PHP ' . PHP_VERSION . '): ' . $e->getMessage());
        }

        $this->addToAssertionCount(1);
    }

    /**
     * Every class of the package that a block imports exists
     * @dataProvider blocksProvider
     * @param int $line The line of the README where the block starts
     * @param string $code The code of the block
     * @return void
     */
    public function testEveryClassThatABlockImportsExists(int $line, string $code): void
    {
        preg_match_all('/^use\s+(Mariopereira\\\\[A-Za-z0-9_\\\\]+)\s*;/m', $code, $found);

        foreach ($found[1] as $name) {
            $this->assertTrue(class_exists($name) || interface_exists($name), "README line $line: use $name does not exist");
        }

        $this->addToAssertionCount(1);
    }

    /**
     * The constructors and the static methods that a block calls exist and take that number of arguments
     * @dataProvider blocksProvider
     * @param int $line The line of the README where the block starts
     * @param string $code The code of the block
     * @return void
     */
    public function testTheCallsOfABlockMatchTheSignaturesOfThePackage(int $line, string $code): void
    {
        $classes = self::classes();

        foreach (self::calls($code) as [$kind, $name, $method, $arguments, $at]) {
            $where = 'README line ' . ($line + $at - 1) . ": $name" . ($kind === 'new' ? '' : "::$method") . '()';

            if (!isset($classes[$name])) {
                $this->assertTrue(in_array($name, self::definedHere(), true) || class_exists($name), "$where: the class is not in the package, is not defined by an example and is not a class of PHP");
                continue;
            }

            $reflection = ($kind === 'new')
                ? (new \ReflectionClass($classes[$name]))->getConstructor()
                : ((new \ReflectionClass($classes[$name]))->hasMethod($method) ? new \ReflectionMethod($classes[$name], $method) : null);

            if ($reflection === null && $kind === 'new') {
                $this->assertSame(0, $arguments, "$where: the class has no constructor");
                continue;
            }

            $this->assertNotNull($reflection, "$where: the method does not exist");
            $this->assertGreaterThanOrEqual($reflection->getNumberOfRequiredParameters(), $arguments, "$where: too few arguments");
            $this->assertLessThanOrEqual($reflection->getNumberOfParameters(), $arguments, "$where: too many arguments");
        }

        $this->addToAssertionCount(1);
    }

    /**
     * Every constant and every method of a class of the package that a block uses exists
     * @dataProvider blocksProvider
     * @param int $line The line of the README where the block starts
     * @param string $code The code of the block
     * @return void
     */
    public function testEveryMemberOfAClassOfThePackageThatABlockUsesExists(int $line, string $code): void
    {
        $classes = self::classes();
        preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)(\s*\()?/', $code, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $checked = 0;

        foreach ($found as $match) {
            [$name, $member, $isCall] = [$match[1][0], $match[2][0], isset($match[3]) && $match[3][0] !== ''];

            if (!isset($classes[$name]) || $member === 'class') {
                continue;
            }

            $where = 'README line ' . ($line + substr_count(substr($code, 0, $match[0][1]), "\n")) . ": $name::$member" . ($isCall ? '()' : '');
            $exists = $isCall ? method_exists($classes[$name], $member) : defined($classes[$name] . '::' . $member);
            $checked++;

            $this->assertTrue($exists, "$where does not exist");
        }

        $this->addToAssertionCount($checked ?: 1);
    }

    /**
     * Every method that a block calls on an object is a method of the package
     * @dataProvider blocksProvider
     * @param int $line The line of the README where the block starts
     * @param string $code The code of the block
     * @return void
     */
    public function testEveryMethodThatABlockCallsOnAnObjectIsAMethodOfThePackage(int $line, string $code): void
    {
        preg_match_all('/->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $code, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $methods = self::methods();

        foreach ($found as $match) {
            $name = $match[1][0];
            $where = 'README line ' . ($line + substr_count(substr($code, 0, $match[0][1]), "\n"));

            $this->assertTrue(isset($methods[$name]) || in_array($name, self::FOREIGN_METHODS, true), "$where: ->$name() is not a public method of any class of the package");
        }

        $this->addToAssertionCount(1);
    }

    /**
     * Every path of the repository that the README mentions exists
     * @return void
     */
    public function testEveryPathOfTheRepositoryThatTheReadmeMentionsExists(): void
    {
        preg_match_all('/`((?:src|tests|schemas|docs)\/[A-Za-z0-9._\/-]*)`/', self::readme(), $found);

        $this->assertNotEmpty($found[1], 'the README mentions no path: the pattern that finds them is wrong');

        foreach (array_unique($found[1]) as $path) {
            $this->assertFileExists(dirname(__DIR__, 2) . '/' . $path, "the README mentions $path");
        }
    }
}
