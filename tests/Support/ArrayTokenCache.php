<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Auth\FedexTokenCache;

/**
 * Token cache for the tests that keeps the entries in an array, records what is done to it and can be made to fail
 */
class ArrayTokenCache implements FedexTokenCache
{
    /**
     * The entries, by key
     * @var array
     */
    public $entries = [];

    /**
     * What was done, in order: the operation (get, set or delete) and the key
     * @var array
     */
    public $log = [];

    /**
     * The seconds that each set() was given, in order
     * @var int[]
     */
    public $ttls = [];

    /**
     * The operations that have to fail (get, set or delete), each one with the exception to throw
     * @var \Throwable[]
     */
    public $failOn = [];

    /**
     * Record the operation and fail if it has to
     * @param string $operation get, set or delete
     * @param string $key The key
     * @return void
     * @throws \Throwable The one in $failOn for the operation, if any
     */
    private function record(string $operation, string $key): void
    {
        $this->log[] = [$operation, $key];

        if (isset($this->failOn[$operation])) {
            throw $this->failOn[$operation];
        }
    }

    /**
     * Get an entry
     * @param string $key The key
     * @return array|null
     */
    public function get(string $key) : ?array
    {
        $this->record('get', $key);

        return $this->entries[$key] ?? null;
    }

    /**
     * Store an entry
     * @param string $key The key
     * @param array $data The entry
     * @param int $ttl The seconds that it is useful for
     * @return void
     */
    public function set(string $key, array $data, int $ttl) : void
    {
        $this->record('set', $key);
        $this->entries[$key] = $data;
        $this->ttls[] = $ttl;
    }

    /**
     * Remove an entry
     * @param string $key The key
     * @return void
     */
    public function delete(string $key) : void
    {
        $this->record('delete', $key);
        unset($this->entries[$key]);
    }

    /**
     * The operations that were done, without the keys
     * @return string[]
     */
    public function operations(): array
    {
        return array_column($this->log, 0);
    }
}
