<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

/**
 * Builds, from the schemas of a FedEx OpenAPI file, an instance of a component with every field filled in (using the
 * examples and the first value of each enum), and records what can be broken in it: the leaf fields and the objects.
 * It also has the helpers to read, change and remove a value at a path of such an instance
 */
final class SpecInstances
{
    /**
     * Name of the component that a property refers to
     * @param array $property A property of a schema
     * @return string|null Null when the property does not refer to a component
     */
    public static function refOf(array $property): ?string
    {
        if (isset($property['$ref'])) {
            return basename($property['$ref']);
        }

        return isset($property['allOf'][0]['$ref']) ? basename($property['allOf'][0]['$ref']) : null;
    }

    /**
     * Build an instance of a component
     * @param array $components The components of the file (components.schemas)
     * @param string $name Name of the component
     * @param array $meta Filled in with 'leaves' (path, type, enum, required, value, free) and 'objects' (path, keys,
     * required, name): what can be broken. A leaf is "free" when the file has no schema for it (any value is valid)
     * @return array The instance
     */
    public static function generate(array $components, string $name, array &$meta): array
    {
        $meta = ['leaves' => [], 'objects' => []];

        return self::object($components, $name, [], $meta, []);
    }

    /**
     * Build an object
     * @param array $components The components of the file
     * @param string|null $name Name of the component, or null when the schema is the $inline one
     * @param array $inline The schema, when it is written inline in a property
     * @param array $meta What can be broken
     * @param array $path Where the object is
     * @return array
     */
    private static function object(array $components, ?string $name, array $inline, array &$meta, array $path): array
    {
        while ($name !== null && isset($components[$name]['$ref'])) {
            $name = basename($components[$name]['$ref']);
        }

        $schema = ($name !== null) ? $components[$name] : $inline;
        $out = [];
        $keys = [];

        foreach ($schema['properties'] as $key => $property) {
            $keys[] = $key;
            $out[$key] = self::property($components, $property, $meta, array_merge($path, [$key]), in_array($key, $schema['required'] ?? [], true));
        }

        $meta['objects'][] = ['path' => $path, 'keys' => $keys, 'required' => $schema['required'] ?? [], 'name' => $name ?? '(inline)'];

        return $out;
    }

    /**
     * Build the value of a property
     * @param array $components The components of the file
     * @param array $property The property
     * @param array $meta What can be broken
     * @param array $path Where the property is
     * @param bool $required Is it required?
     * @return mixed
     */
    private static function property(array $components, array $property, array &$meta, array $path, bool $required)
    {
        $ref = self::refOf($property);

        if ($ref !== null) {
            if (!isset($components[$ref]['properties']) && !isset($components[$ref]['$ref'])) {
                // a component without a schema: any value is valid, here a text
                $meta['leaves'][] = ['path' => $path, 'type' => 'string', 'enum' => false, 'required' => $required, 'value' => 'INVALID.SUITE.NUMBER', 'free' => true];

                return 'INVALID.SUITE.NUMBER';
            }

            return self::object($components, $ref, [], $meta, $path);
        }

        $type = $property['type'] ?? 'string';

        if ($type === 'object' && isset($property['properties'])) {
            return self::object($components, null, $property, $meta, $path);
        }

        if ($type === 'array') {
            $itemRef = self::refOf($property['items'] ?? []);

            if ($itemRef !== null) {
                if (!isset($components[$itemRef]['properties']) && !isset($components[$itemRef]['$ref'])) {
                    $meta['leaves'][] = ['path' => array_merge($path, [0]), 'type' => 'string', 'enum' => false, 'required' => false, 'value' => 'INVALID.SUITE.NUMBER', 'free' => true];

                    return ['INVALID.SUITE.NUMBER'];
                }

                return [self::object($components, $itemRef, [], $meta, array_merge($path, [0]))];
            }

            return [self::scalar($property['items'], $meta, array_merge($path, [0]), false)];
        }

        return self::scalar($property, $meta, $path, $required);
    }

    /**
     * Build a scalar value
     * @param array $property The property
     * @param array $meta What can be broken
     * @param array $path Where the value is
     * @param bool $required Is it required?
     * @return mixed
     */
    private static function scalar(array $property, array &$meta, array $path, bool $required)
    {
        $type = $property['type'] ?? 'string';
        $example = $property['example'] ?? null;

        if ($type === 'string') {
            if (isset($property['enum'])) {
                $value = in_array($example, $property['enum'], true) ? $example : $property['enum'][0];
            } elseif (($property['format'] ?? '') === 'HH:MM:SS') {
                $value = '16:30:00';
            } else {
                $value = (string) ($example ?? 'x');
            }
        } elseif ($type === 'integer') {
            $value = (int) ($example ?? 1);
        } elseif ($type === 'number') {
            $value = is_numeric($example) ? $example + 0 : 1;
        } else {
            $value = is_bool($example) ? $example : (strtolower((string) $example) !== 'false');
        }

        $meta['leaves'][] = ['path' => $path, 'type' => $type, 'enum' => isset($property['enum']), 'required' => $required, 'value' => $value, 'free' => false];

        return $value;
    }

    /**
     * Read the value at a path
     * @param array $data The data
     * @param array $path The keys that lead to the value
     * @return mixed
     */
    public static function getAt(array $data, array $path)
    {
        foreach ($path as $key) {
            $data = $data[$key];
        }

        return $data;
    }

    /**
     * A copy of the data with another value at a path
     * @param mixed $data The data
     * @param array $path The keys that lead to the value
     * @param mixed $value The new value
     * @return mixed
     */
    public static function setAt($data, array $path, $value)
    {
        if ($path === []) {
            return $value;
        }

        $key = array_shift($path);
        $data[$key] = self::setAt($data[$key], $path, $value);

        return $data;
    }

    /**
     * A copy of the data without the value at a path
     * @param array $data The data
     * @param array $path The keys that lead to the value
     * @return array
     */
    public static function unsetAt(array $data, array $path): array
    {
        $key = array_shift($path);

        if ($path === []) {
            unset($data[$key]);

            return $data;
        }

        $data[$key] = self::unsetAt($data[$key], $path);

        return $data;
    }

    /**
     * A path written as the validator writes it
     * @param array $path The keys
     * @return string For example $.addressesToValidate[0].address
     */
    public static function pathName(array $path): string
    {
        return '$' . implode('', array_map(function ($key) {
            return is_int($key) ? "[$key]" : ".$key";
        }, $path));
    }

    /**
     * The data with the keys of every map sorted, to compare two requests without caring about the order of the keys
     * @param mixed $data The data
     * @return mixed
     */
    public static function normalize($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        ksort($data);

        return array_map([self::class, 'normalize'], $data);
    }
}
