<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

/**
 * What the tests of the Basic Tracking API read from schemas/track.json, independently of the code that generated the
 * definitions of the class: the spec, the way its shapes are written, an instance of each of the twelve roots with every
 * field filled in, and the structure that holds only the required keys
 */
final class TrackSpec
{
    /**
     * The twelve roots of the spec that the class implements, by label
     */
    public const ROOTS = [
        'trackingnumbers.request' => 'Full_Schema_Tracking_Numbers', 'trackingnumbers.response' => 'TrkcResponseVO_TrackingNumber',
        'associated.request' => 'Full_Schema_Multiple_Piece_Shipment', 'associated.response' => 'TrkcResponseVO_Associated',
        'references.request' => 'Full_Schema_Tracking_References', 'references.response' => 'TrkcResponseVO_ReferenceNumber',
        'tcn.request' => 'Full_Schema_TCN', 'tcn.response' => 'TrkcResponseVO_TCN',
        'documents.request' => 'Full_Schema_SPOD', 'documents.response' => 'TrkcResponseVO_SPOD',
        'notification.request' => 'Full_Schema_Notification', 'notification.response' => 'TrkcResponseVO_Notifications',
    ];

    /**
     * The enums of the spec that the class checks as text, because they are defective in the spec: definition.property
     */
    public const TEXT_ENUMS = ['DelayDetail.subType', 'ServiceCommitMessage.type'];

    /**
     * What the generator answers when a definition refers to itself: the field is left out
     */
    private const SKIP = "\0skip";

    /**
     * The OpenAPI file, decoded
     * @var array|null
     */
    private static $spec;

    /**
     * The instances generated, by label: the instance and what can be broken in it
     * @var array|null
     */
    private static $instances;

    /**
     * The OpenAPI file, decoded
     * @return array
     */
    public static function spec(): array
    {
        if (self::$spec === null) {
            self::$spec = json_decode(file_get_contents(__DIR__ . '/../../schemas/track.json'), true);
        }

        return self::$spec;
    }

    /**
     * The components of the OpenAPI file
     * @return array
     */
    public static function components(): array
    {
        return self::spec()['components']['schemas'];
    }

    /**
     * The definition that a property refers to
     * @param array $property A property of a schema
     * @return string|null Null when the property does not refer to a definition
     */
    public static function refOf(array $property): ?string
    {
        if (isset($property['$ref'])) {
            return basename($property['$ref']);
        }

        if (isset($property['allOf'])) {
            // entries that only carry a description say nothing about the shape
            $parts = array_values(array_filter($property['allOf'], function ($entry) {
                return array_diff(array_keys($entry), ['description', 'example']) !== [];
            }));

            if (count($parts) === 1 && isset($parts[0]['$ref'])) {
                return basename($parts[0]['$ref']);
            }
        }

        return null;
    }

    /**
     * What the spec says about a property, in a canonical text: the class has to say the same
     * @param array $property A property of the spec
     * @param string $where Definition and property, to tell the enums that are checked as text
     * @return string
     */
    public static function canonSpec(array $property, string $where): string
    {
        if (($ref = self::refOf($property)) !== null) {
            return '@' . $ref;
        }

        $type = $property['type'] ?? '?';

        if ($type === 'array') {
            return '[' . self::canonSpec($property['items'], $where . '[]') . ']';
        }

        if ($type === 'object') {
            return 'object';
        }

        if ($type === '?') {
            return 'mixed';
        }

        $text = ['string' => 'string', 'integer' => 'int', 'number' => 'number', 'boolean' => 'bool'][$type] ?? "?$type";

        if (isset($property['enum']) && !in_array($where, self::TEXT_ENUMS, true)) {
            $text .= ' enum=' . implode(',', array_map('json_encode', $property['enum']));
        }

        return $text;
    }

    /**
     * What a definition of the class says about a property, in the same canonical text
     * @param mixed $node The node of the definition
     * @return string
     */
    public static function canonClass($node): string
    {
        if (is_string($node)) {
            return $node;
        }

        if (isset($node['@type'])) {
            $text = $node['@type'];

            if (isset($node['@enum'])) {
                $text .= ' enum=' . implode(',', array_map('json_encode', $node['@enum']));
            }

            return $text;
        }

        if (array_keys($node) === [0]) {
            return '[' . self::canonClass($node[0]) . ']';
        }

        return 'UNEXPECTED ' . json_encode($node);
    }

    /**
     * Every definition that a definition needs, itself included
     * @param string $name Name in the spec
     * @param array $seen Filled in with the names, as keys
     * @return void
     */
    public static function closure(string $name, array &$seen): void
    {
        if (isset($seen[$name])) {
            return;
        }

        $seen[$name] = true;
        $walk = function ($node) use (&$walk, &$seen) {
            if (!is_array($node)) {
                return;
            }

            foreach ($node as $key => $value) {
                if ($key === '$ref') {
                    self::closure(basename($value), $seen);
                } else {
                    $walk($value);
                }
            }
        };
        $walk(self::components()[$name] ?? []);
    }

    /**
     * The instance generated from a root, and what can be broken in it
     * @param string $label A key of ROOTS
     * @return array The instance and the meta ('leaves': path, type, typed (false when the spec gives no type), enum, required;
     * 'objects': path, keys, required)
     */
    public static function instance(string $label): array
    {
        if (self::$instances === null) {
            self::$instances = [];

            foreach (self::ROOTS as $key => $root) {
                $meta = ['objects' => [], 'leaves' => []];
                self::$instances[$key] = [self::named(self::components(), $root, $meta, [], []), $meta];
            }

            self::$instances['references.request'] = self::withShipDateEnd(self::$instances['references.request']);
        }

        return self::$instances[$label];
    }

    /**
     * The instance of the references request as the class takes it: the spec requires shipDateEndDate, and the class requires
     * shipDateEnd (the example, the other schemas and the documentation write it) and accepts shipDateEndDate as an optional field
     * @param array $built The instance and the meta that the spec gives
     * @return array The instance and the meta, with shipDateEnd added and required, and shipDateEndDate optional
     */
    private static function withShipDateEnd(array $built): array
    {
        [$instance, $meta] = $built;
        $path = ['referencesInformation'];
        $instance['referencesInformation']['shipDateEnd'] = $instance['referencesInformation']['shipDateEndDate'];

        foreach ($meta['objects'] as $i => $object) {
            if ($object['path'] === $path) {
                $meta['objects'][$i]['keys'][] = 'shipDateEnd';
                $meta['objects'][$i]['required'] = array_values(array_merge(array_diff($object['required'], ['shipDateEndDate']), ['shipDateEnd']));
            }
        }

        foreach ($meta['leaves'] as $i => $leaf) {
            if ($leaf['path'] === array_merge($path, ['shipDateEndDate'])) {
                $meta['leaves'][$i]['required'] = false;
                $meta['leaves'][] = ['path' => array_merge($path, ['shipDateEnd']), 'type' => $leaf['type'], 'typed' => true, 'enum' => null, 'required' => true];
            }
        }

        return [$instance, $meta];
    }

    /**
     * Build an object, with every optional field filled in
     * @param array $components The components of the spec
     * @param array $schema The schema of the object
     * @param array $meta What can be broken
     * @param array $path Where the object is
     * @param array $stack The definitions being built, to stop at a reference to itself
     * @return array
     */
    private static function object(array $components, array $schema, array &$meta, array $path, array $stack): array
    {
        $required = $schema['required'] ?? [];
        $out = [];
        $keys = [];

        foreach ($schema['properties'] as $key => $property) {
            $value = self::property($components, $property, $meta, array_merge($path, [$key]), in_array($key, $required, true), $stack);

            if ($value === self::SKIP) {
                continue;
            }

            $keys[] = $key;
            $out[$key] = $value;
        }

        $meta['objects'][] = ['path' => $path, 'keys' => $keys, 'required' => array_values(array_intersect($required, $keys))];

        return $out;
    }

    /**
     * Build the value of a definition
     * @param array $components The components of the spec
     * @param string $name Name in the spec
     * @param array $meta What can be broken
     * @param array $path Where the value is
     * @param array $stack The definitions being built
     * @return mixed
     */
    private static function named(array $components, string $name, array &$meta, array $path, array $stack)
    {
        if (in_array($name, $stack, true)) {
            return self::SKIP;
        }

        $stack[] = $name;
        $schema = $components[$name];

        if (isset($schema['$ref'])) {
            return self::named($components, basename($schema['$ref']), $meta, $path, $stack);
        }

        if (isset($schema['properties'])) {
            return self::object($components, $schema, $meta, $path, $stack);
        }

        if (($schema['type'] ?? '') === 'array') {
            return self::property($components, $schema, $meta, $path, false, $stack);
        }

        if (isset($schema['allOf']) && self::refOf($schema) !== null) {
            return self::named($components, self::refOf($schema), $meta, $path, $stack);
        }

        return self::scalar($schema, $meta, $path, false);
    }

    /**
     * Build the value of a property
     * @param array $components The components of the spec
     * @param array $property The property
     * @param array $meta What can be broken
     * @param array $path Where the property is
     * @param bool $required Is it required?
     * @param array $stack The definitions being built
     * @return mixed
     */
    private static function property(array $components, array $property, array &$meta, array $path, bool $required, array $stack)
    {
        if (($ref = self::refOf($property)) !== null) {
            return self::named($components, $ref, $meta, $path, $stack);
        }

        $type = $property['type'] ?? (isset($property['properties']) ? 'object' : 'string');

        if ($type === 'object') {
            return isset($property['properties']) ? self::object($components, $property, $meta, $path, $stack) : ['k' => 'v'];
        }

        if ($type === 'array') {
            $item = self::property($components, $property['items'], $meta, array_merge($path, [0]), false, $stack);

            return $item === self::SKIP ? [] : [$item];
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

        if ($type === 'string') {
            $value = isset($property['enum']) ? $property['enum'][0] : 'x';
        } elseif ($type === 'integer') {
            $value = 1;
        } elseif ($type === 'number') {
            $value = 1.5;
        } else {
            $value = true;
        }

        $meta['leaves'][] = ['path' => $path, 'type' => $type, 'typed' => isset($property['type']), 'enum' => $property['enum'] ?? null, 'required' => $required];

        return $value;
    }

    /**
     * The structure that holds only the required keys of a schema, recursively: what the new...Request() methods return
     * @param array $schema The schema (or a property)
     * @param array $stack The definitions being built
     * @return mixed
     */
    public static function requiredOnly(array $schema, array $stack = [])
    {
        $components = self::components();

        if (($ref = self::refOf($schema)) !== null) {
            if (in_array($ref, $stack, true)) {
                return null;
            }

            return self::requiredOnly($components[$ref], array_merge($stack, [$ref]));
        }

        if (($schema['type'] ?? '') === 'array') {
            return [];
        }

        if (!isset($schema['properties'])) {
            return null;
        }

        $required = $schema['required'] ?? [];
        $out = [];

        foreach ($schema['properties'] as $key => $property) {
            if (in_array($key, $required, true)) {
                $out[$key] = self::requiredOnly($property, $stack);
            }
        }

        return $out;
    }
}
