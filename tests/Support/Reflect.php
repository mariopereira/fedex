<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

/**
 * Reads the state that the classes keep private or protected, for the tests that need to know what was stored
 */
final class Reflect
{
    /**
     * Read a property
     * @param object $object The object
     * @param string $class The class that declares the property
     * @param string $name Name of the property
     * @return mixed
     */
    public static function prop($object, string $class, string $name)
    {
        $property = new \ReflectionProperty($class, $name);

        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true); // needed before PHP 8.1, and deprecated since PHP 8.5
        }

        return $property->getValue($object);
    }
}
