<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Auth;

use Mariopereira\Fedex\Exception\FedexAuthException;
use Mariopereira\Fedex\Exception\FedexException;

/**
 * Token cache that keeps each entry in a JSON file of a directory. The directory is created when it does not exist.
 *
 * The files are written atomically (a temporary file that is renamed), so a reader never finds half a token. The file
 * is made readable by its owner only where the system allows it (not on Windows: protect the directory there). Put the
 * directory outside the web root: the files hold the access token.
 */
final class FileTokenCache implements FedexTokenCache
{
    /**
     * Largest file that is read, in bytes. A token entry has less than a few kilobytes
     * @var int
     */
    private const MAX_FILE_SIZE = 65536;

    /**
     * The directory of the files
     * @var string
     */
    private $directory;

    /**
     * Initialize the object, creating the directory when it does not exist
     * @param string $directory The directory of the files
     * @throws FedexAuthException When the directory is empty, cannot be created, or cannot be written to
     */
    public function __construct(string $directory)
    {
        $directory = rtrim(trim($directory), '/\\');

        if ('' === $directory) {
            throw new FedexAuthException('The directory of the token cache is empty', FedexException::EC_BAD_PARAMETER);
        }

        if (!is_dir($directory)) {
            self::quietly(function () use ($directory) {
                return mkdir($directory, 0700, true);
            });

            // Another process may have created it in the meantime
            if (!is_dir($directory)) {
                throw new FedexAuthException('The directory of the token cache cannot be created', FedexException::EC_BAD_PARAMETER);
            }
        }

        if (!is_writable($directory)) {
            throw new FedexAuthException('The directory of the token cache is not writable', FedexException::EC_BAD_PARAMETER);
        }

        $this->directory = $directory;
    }

    /**
     * Get the entry stored with a key
     * @param string $key The key: letters, digits, underscores and hyphens, up to 128
     * @return array|null The entry, null when there is no file for the key
     * @throws FedexAuthException When the key is not valid, or the file cannot be read or does not hold an entry
     */
    public function get(string $key) : ?array
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $content = self::quietly(function () use ($path) {
            return file_get_contents($path, false, null, 0, self::MAX_FILE_SIZE + 1);
        });

        if (false === $content) {
            throw new FedexAuthException('The token cache file cannot be read', FedexException::EC_BAD_RESULT_RETURNED);
        }

        if (strlen($content) > self::MAX_FILE_SIZE) {
            throw new FedexAuthException('The token cache file is too large', FedexException::EC_BAD_RESULT_RETURNED);
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new FedexAuthException('The token cache file does not hold an entry', FedexException::EC_BAD_RESULT_RETURNED);
        }

        return $data;
    }

    /**
     * Store an entry with a key, replacing the file that exists. The $ttl is not used: the entry carries its own
     * expiration
     * @param string $key The key: letters, digits, underscores and hyphens, up to 128
     * @param array $data The entry
     * @param int $ttl Not used
     * @return void
     * @throws FedexAuthException When the key is not valid, or the file cannot be written
     */
    public function set(string $key, array $data, int $ttl) : void
    {
        $path = $this->path($key);
        $json = json_encode($data);

        if (false === $json) {
            throw new FedexAuthException('The token cache entry cannot be encoded', FedexException::EC_BAD_PARAMETER);
        }

        // The temporary file is made in the directory itself (tempnam() would use the temporary directory of the system
        // when this one is gone, and the token would be written there). The x mode never uses a file that exists
        $temporary = $this->directory . DIRECTORY_SEPARATOR . 'tmp' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = self::quietly(function () use ($temporary) {
            return fopen($temporary, 'xb');
        });

        if (false === $handle) {
            throw new FedexAuthException('The token cache file cannot be created', FedexException::EC_BAD_PARAMETER);
        }

        // Only the owner has to read it. Not every system supports it, and then the directory is what protects it
        self::quietly(function () use ($temporary) {
            return chmod($temporary, 0600);
        });

        $written = self::quietly(function () use ($handle, $json) {
            return fwrite($handle, $json);
        });
        self::quietly(function () use ($handle) {
            return fclose($handle);
        });

        if ((strlen($json) !== $written) || !self::quietly(function () use ($temporary, $path) {
            return rename($temporary, $path);
        })) {
            self::quietly(function () use ($temporary) {
                return unlink($temporary);
            });

            throw new FedexAuthException('The token cache file cannot be written', FedexException::EC_BAD_PARAMETER);
        }
    }

    /**
     * Remove the file stored with a key. Nothing happens when there is none
     * @param string $key The key: letters, digits, underscores and hyphens, up to 128
     * @return void
     * @throws FedexAuthException When the key is not valid, or the file exists and cannot be removed
     */
    public function delete(string $key) : void
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return;
        }

        self::quietly(function () use ($path) {
            return unlink($path);
        });

        if (is_file($path)) {
            throw new FedexAuthException('The token cache file cannot be removed', FedexException::EC_BAD_PARAMETER);
        }
    }

    /**
     * The path of the file of a key
     * @param string $key The key
     * @return string
     * @throws FedexAuthException When the key is not valid
     */
    private function path(string $key) : string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $key)) {
            throw new FedexAuthException('The key of the token cache is not valid', FedexException::EC_BAD_PARAMETER);
        }

        return $this->directory . DIRECTORY_SEPARATOR . $key . '.json';
    }

    /**
     * Run a file function that reports a failure with a return value and also with a warning, keeping the return value
     * and discarding the warning (the callers turn the failure into an exception)
     * @param callable $fn The function to run
     * @return mixed What the function returned
     */
    private static function quietly(callable $fn)
    {
        set_error_handler(function () {
            return true;
        });

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }
}
