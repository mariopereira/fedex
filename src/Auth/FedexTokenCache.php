<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Auth;

/**
 * Storage for the access token, so that it can be shared between PHP requests. Implement it on top of any store (a
 * file, APCu, Redis, a database) and give it to FedexAuth::setTokenCache().
 *
 * What is stored is a credential that FedEx accepts until it expires: keep it where only the application can read it,
 * and do not put it in a log. FedexAuth treats every failure of the cache as a cache miss, so the methods may throw
 * when the store fails.
 */
interface FedexTokenCache
{
    /**
     * Get the entry stored with a key
     * @param string $key The key (FedexAuth uses the 64 hexadecimal characters of a SHA-256 digest)
     * @return array|null The array that was given to set(), null when there is no entry
     * @throws \Throwable When the store fails or the entry cannot be read
     */
    public function get(string $key) : ?array;

    /**
     * Store an entry with a key, replacing the one that exists
     * @param string $key The key
     * @param array $data The entry: an array of text, numbers and null that has to be returned as it is by get()
     * @param int $ttl Seconds that the entry is useful for. A store that expires entries can use it, the entry
     * carries its own expiration anyway
     * @return void
     * @throws \Throwable When the store fails
     */
    public function set(string $key, array $data, int $ttl) : void;

    /**
     * Remove the entry stored with a key. Nothing happens when there is none
     * @param string $key The key
     * @return void
     * @throws \Throwable When the store fails
     */
    public function delete(string $key) : void;
}
