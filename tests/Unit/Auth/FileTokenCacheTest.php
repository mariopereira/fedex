<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Auth;

use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Auth\FedexTokenCache;
use Mariopereira\Fedex\Auth\FileTokenCache;
use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Tests\Support\FakeTransport;
use Mariopereira\Fedex\Tests\Support\Responses;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the token cache that keeps its entries in files
 */
final class FileTokenCacheTest extends TestCase
{
    /**
     * A folder of the temporary folder of the system that holds everything that a test makes, removed after each test
     * @var string|null
     */
    private $root;

    /**
     * Remove what the test made
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->root !== null) {
            self::removeTree($this->root);
        }

        $this->root = null;
    }

    /**
     * Remove a folder and everything in it
     * @param string $path The folder
     * @return void
     */
    private static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $name;
            is_dir($child) ? self::removeTree($child) : unlink($child);
        }

        rmdir($path);
    }

    /**
     * The folder that holds what the test makes, which is created on the first call
     * @return string
     */
    private function root(): string
    {
        if ($this->root === null) {
            $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fedex_tokens_' . bin2hex(random_bytes(6));
            mkdir($this->root);
        }

        return $this->root;
    }

    /**
     * A cache over a new folder
     * @return FileTokenCache
     */
    private function cache(): FileTokenCache
    {
        return new FileTokenCache($this->root() . DIRECTORY_SEPARATOR . 'cache');
    }

    /**
     * The files of the folder of the cache
     * @return string[]
     */
    private function files(): array
    {
        return array_values(array_diff(scandir($this->root() . DIRECTORY_SEPARATOR . 'cache'), ['.', '..']));
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

            return null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * The folder is created, with the folders above it, when it does not exist
     * @return void
     */
    public function testTheFolderIsCreatedWithTheFoldersAboveItWhenItDoesNotExist(): void
    {
        $dir = $this->root() . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b' . DIRECTORY_SEPARATOR . 'c';

        $cache = new FileTokenCache($dir);

        $this->assertDirectoryExists($dir);
        $this->assertInstanceOf(FedexTokenCache::class, $cache);

        $cache->set('k', ['a' => 1], 60);
        new FileTokenCache($dir . DIRECTORY_SEPARATOR);

        $this->assertSame(['a' => 1], (new FileTokenCache($dir))->get('k'), 'a folder that exists is used as it is, with or without a separator at the end');
    }

    /**
     * An empty folder, or one that cannot be created, is refused
     * @return void
     */
    public function testAnEmptyFolderOrOneThatCannotBeCreatedIsRefused(): void
    {
        foreach (['', '   ', '/', '\\'] as $dir) {
            $e = self::thrown(function () use ($dir) {
                new FileTokenCache($dir);
            });

            $this->assertInstanceOf(FedexAuthException::class, $e, "'$dir'");
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertSame('The directory of the token cache is empty', $e->getMessage());
        }

        $file = $this->root() . DIRECTORY_SEPARATOR . 'a file';
        file_put_contents($file, 'x');

        foreach ([$file, $file . DIRECTORY_SEPARATOR . 'below'] as $dir) {
            $e = self::thrown(function () use ($dir) {
                new FileTokenCache($dir);
            });

            $this->assertInstanceOf(FedexAuthException::class, $e, $dir);
            $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
            $this->assertStringStartsWith('The directory of the token cache', $e->getMessage());
        }

        $this->assertSame('x', file_get_contents($file));
    }

    /**
     * An entry is stored and read back as it is
     * @return void
     */
    public function testAnEntryIsStoredAndReadBackAsItIs(): void
    {
        $cache = $this->cache();
        $entry = ['v' => 1, 'token' => 'ç ã "quoted" \\ / ' . str_repeat('x', 3000), 'type' => 'bearer', 'scope' => null, 'expiration' => 1999999999];

        $this->assertNull($cache->get('abc'), 'there is nothing before it is stored');

        $cache->set('abc', $entry, 3600);

        $this->assertSame($entry, $cache->get('abc'));
        $this->assertSame(['abc.json'], $this->files(), 'one file, named after the key, and no temporary file left');
    }

    /**
     * An entry replaces the one that exists
     * @return void
     */
    public function testAnEntryReplacesTheOneThatExists(): void
    {
        $cache = $this->cache();

        $cache->set('k', ['token' => str_repeat('long', 100)], 60);
        $cache->set('k', ['token' => 'short'], 60);

        $this->assertSame(['token' => 'short'], $cache->get('k'));
        $this->assertSame(['k.json'], $this->files());
        $this->assertSame('{"token":"short"}', file_get_contents($this->root() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'k.json'));
    }

    /**
     * The entries of different keys are separate
     * @return void
     */
    public function testTheEntriesOfDifferentKeysAreSeparate(): void
    {
        $cache = $this->cache();

        $cache->set('a', ['n' => 1], 60);
        $cache->set('b', ['n' => 2], 60);

        $this->assertSame(1, $cache->get('a')['n']);
        $this->assertSame(2, $cache->get('b')['n']);
        $this->assertEqualsCanonicalizing(['a.json', 'b.json'], $this->files());
    }

    /**
     * An entry is removed, and removing one that is not there does nothing
     * @return void
     */
    public function testAnEntryIsRemovedAndRemovingOneThatIsNotThereDoesNothing(): void
    {
        $cache = $this->cache();
        $cache->set('a', ['n' => 1], 60);
        $cache->set('b', ['n' => 2], 60);

        $cache->delete('a');
        $cache->delete('a');
        $cache->delete('never-stored');

        $this->assertNull($cache->get('a'));
        $this->assertSame(2, $cache->get('b')['n']);
        $this->assertSame(['b.json'], $this->files());
    }

    /**
     * A key that could leave the folder or is not plain is refused by every operation
     * @return void
     */
    public function testAKeyThatCouldLeaveTheFolderOrIsNotPlainIsRefusedByEveryOperation(): void
    {
        $cache = $this->cache();

        foreach (['', '../x', '..', 'a/b', 'a\\b', 'a b', 'a.b', "a\n", "a\0b", str_repeat('k', 129), 'ç'] as $key) {
            foreach (['get', 'set', 'delete'] as $operation) {
                $e = self::thrown(function () use ($cache, $operation, $key) {
                    $operation === 'set' ? $cache->set($key, ['x' => 1], 60) : $cache->$operation($key);
                });

                $this->assertInstanceOf(FedexAuthException::class, $e, "$operation '" . addcslashes($key, "\0..\37") . "'");
                $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
                $this->assertSame('The key of the token cache is not valid', $e->getMessage());
            }
        }

        $this->assertSame([], $this->files());
        $this->assertSame([], array_diff(scandir($this->root()), ['.', '..', 'cache']), 'nothing was written outside the folder');

        foreach (['a', 'Z', '0', 'a-b_C9', str_repeat('k', 128), hash('sha256', 'x')] as $key) {
            $cache->set($key, ['x' => 1], 60);

            $this->assertSame(['x' => 1], $cache->get($key), $key);
        }
    }

    /**
     * A file that does not hold an entry is reported
     * @return void
     */
    public function testAFileThatDoesNotHoldAnEntryIsReported(): void
    {
        $cache = $this->cache();
        $path = $this->root() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'k.json';

        foreach (['', '{', 'not json', '"text"', '5', 'null', 'true', '{"a":1'] as $content) {
            file_put_contents($path, $content);

            $e = self::thrown(function () use ($cache) {
                $cache->get('k');
            });

            $this->assertInstanceOf(FedexAuthException::class, $e, "'$content'");
            $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
            $this->assertSame('The token cache file does not hold an entry', $e->getMessage());
        }

        file_put_contents($path, '[]');

        $this->assertSame([], $cache->get('k'), 'an empty entry is an entry: FedexAuth is the one that finds it not valid');
    }

    /**
     * A file that is too large is not read
     * @return void
     */
    public function testAFileThatIsTooLargeIsNotRead(): void
    {
        $cache = $this->cache();
        $path = $this->root() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'k.json';
        $prefix = '{"token":"';
        $suffix = '"}';

        file_put_contents($path, $prefix . str_repeat('x', 65536 - strlen($prefix . $suffix)) . $suffix);

        $this->assertCount(1, $cache->get('k'), 'a file of exactly 64 KB is read');

        file_put_contents($path, $prefix . str_repeat('x', 65537 - strlen($prefix . $suffix)) . $suffix);

        $e = self::thrown(function () use ($cache) {
            $cache->get('k');
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_BAD_RESULT_RETURNED, $e->getCode());
        $this->assertSame('The token cache file is too large', $e->getMessage());
    }

    /**
     * An entry cannot be stored where a folder is, and no temporary file is left
     * @return void
     */
    public function testAnEntryCannotBeStoredWhereAFolderIsAndNoTemporaryFileIsLeft(): void
    {
        $cache = $this->cache();
        mkdir($this->root() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'k.json');

        $e = self::thrown(function () use ($cache) {
            $cache->set('k', ['x' => 1], 60);
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame('The token cache file cannot be written', $e->getMessage());
        $this->assertSame(['k.json'], $this->files(), 'only the folder that was in the way');
    }

    /**
     * An entry that cannot be encoded is refused and nothing is written
     * @return void
     */
    public function testAnEntryThatCannotBeEncodedIsRefusedAndNothingIsWritten(): void
    {
        $cache = $this->cache();

        $e = self::thrown(function () use ($cache) {
            $cache->set('k', ['bad' => "\xff\xfe invalid utf-8"], 60);
        });

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame('The token cache entry cannot be encoded', $e->getMessage());
        $this->assertSame([], $this->files());
    }

    /**
     * The file can be read by its owner only, where the system has such permissions
     * @return void
     */
    public function testTheFileCanBeReadByItsOwnerOnlyWhereTheSystemHasSuchPermissions(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Windows has no file modes: the folder has to be protected with its permissions');
        }

        $cache = $this->cache();
        $cache->set('k', ['x' => 1], 60);

        clearstatcache();

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->root() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'k.json')), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->root() . DIRECTORY_SEPARATOR . 'cache')), -4), 'and the folder that was created');
    }

    /**
     * A folder that cannot be written to is refused
     * @return void
     */
    public function testAFolderThatCannotBeWrittenToIsRefused(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('Needs a system with file modes, and a user that they apply to');
        }

        $dir = $this->root() . DIRECTORY_SEPARATOR . 'readonly';
        mkdir($dir, 0500);

        $e = self::thrown(function () use ($dir) {
            new FileTokenCache($dir);
        });

        chmod($dir, 0700);

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame('The directory of the token cache is not writable', $e->getMessage());
    }

    /**
     * An entry that cannot be removed is reported
     * @return void
     */
    public function testAnEntryThatCannotBeRemovedIsReported(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('A file that is read-only cannot be removed on Windows only');
        }

        $cache = $this->cache();
        $cache->set('k', ['x' => 1], 60);
        $path = $this->root() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'k.json';
        chmod($path, 0444);

        try {
            $e = self::thrown(function () use ($cache) {
                $cache->delete('k');
            });
        } finally {
            chmod($path, 0666);
        }

        $this->assertInstanceOf(FedexAuthException::class, $e);
        $this->assertSame(FedexException::EC_BAD_PARAMETER, $e->getCode());
        $this->assertSame('The token cache file cannot be removed', $e->getMessage());
        $this->assertSame(['x' => 1], $cache->get('k'), 'the entry is still there');
    }

    /**
     * The authorization shares a token between two objects through the files
     * @return void
     */
    public function testTheAuthorizationSharesATokenBetweenTwoObjectsThroughTheFiles(): void
    {
        $cache = $this->cache();
        $first = new FakeTransport([Responses::token(['access_token' => 'T1'])]);
        $second = new FakeTransport([Responses::token(['access_token' => 'T2'])]);

        $one = (new FedexAuth(true, 'client_credentials', 'MY-ID', 'MY-SECRET', null, null, $first))->setTokenCache($cache);
        $two = (new FedexAuth(true, 'client_credentials', 'MY-ID', 'MY-SECRET', null, null, $second))->setTokenCache(new FileTokenCache($this->root() . DIRECTORY_SEPARATOR . 'cache'));

        $this->assertSame('T1', $one->getToken());
        $this->assertSame('T1', $two->getToken());
        $this->assertCount(0, $second->calls);
        $this->assertNull($one->getLastCacheError());
        $this->assertNull($two->getLastCacheError());

        $files = $this->files();
        $content = file_get_contents($this->root() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $files[0]);

        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}\.json$/', $files[0]);
        $this->assertStringNotContainsString('MY-ID', $files[0] . $content);
        $this->assertStringNotContainsString('MY-SECRET', $files[0] . $content);
        $this->assertStringContainsString('T1', $content, 'the token is what the file holds, which is why the folder has to be protected');
    }

    /**
     * The authorization goes on when the folder of the files is gone
     * @return void
     */
    public function testTheAuthorizationGoesOnWhenTheFolderOfTheFilesIsGone(): void
    {
        $cache = $this->cache();
        $transport = new FakeTransport([Responses::token(['access_token' => 'T1'])]);
        $auth = (new FedexAuth(true, 'client_credentials', 'id', 'secret', null, null, $transport))->setTokenCache($cache);

        self::removeTree($this->root() . DIRECTORY_SEPARATOR . 'cache');

        $this->assertSame('T1', $auth->getToken());
        $this->assertSame('The token could not be stored in the cache: ' . FedexAuthException::class . ': The token cache file cannot be created', $auth->getLastCacheError());
    }
}
