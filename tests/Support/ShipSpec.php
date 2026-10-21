<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

/**
 * What the tests of the Ship API read from schemas/ship.json, independently of the code that generated the definitions of
 * the class: the spec, the way its names and shapes are written, an instance of each of the twelve roots with every field
 * filled in, and the structure that holds only the required keys
 */
final class ShipSpec
{
    /**
     * Names of the spec that the validator cannot take (a hyphen): the class writes them with an underscore
     */
    public const HYPHENATED = ['FullSchema-VerifyShipment', 'FullSchema-CancelTag', 'FullSchema-getConfirmedShipmentAsyncResults'];

    /**
     * The twelve roots of the spec that the class implements, by label
     */
    public const ROOTS = [
        'create.request' => 'Full_Schema_Ship', 'create.response' => 'SHPCResponseVO_ShipShipment',
        'validate.request' => 'FullSchema-VerifyShipment', 'validate.response' => 'SHPCResponseVO_Validate',
        'cancel.request' => 'Full_Schema_Cancel_Shipment', 'cancel.response' => 'SHPCResponseVO_CancelShipment',
        'createtag.request' => 'Full_Schema_Create_Tag', 'createtag.response' => 'SHPCResponseVO_CreateTag',
        'canceltag.request' => 'FullSchema-CancelTag', 'canceltag.response' => 'SHPCResponseVO',
        'results.request' => 'FullSchema-getConfirmedShipmentAsyncResults', 'results.response' => 'SHPCResponseVO_GetOpenShipmentResults',
    ];

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
            self::$spec = json_decode(file_get_contents(__DIR__ . '/../../schemas/ship.json'), true);
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
     * The name that the class gives to a definition of the spec
     * @param string $name Name in the spec
     * @return string
     */
    public static function renamed(string $name): string
    {
        return str_replace(self::HYPHENATED, str_replace('-', '_', self::HYPHENATED), $name);
    }

    /**
     * The name in the spec of a definition of the class
     * @param string $name Name in the class
     * @return string
     */
    public static function original(string $name): string
    {
        return str_replace(str_replace('-', '_', self::HYPHENATED), self::HYPHENATED, $name);
    }

    /**
     * The definition that a property refers to (with the name of the class)
     * @param array $property A property of a schema
     * @return string|null Null when the property does not refer to a definition
     */
    public static function refOf(array $property): ?string
    {
        if (isset($property['$ref'])) {
            return self::renamed(basename($property['$ref']));
        }

        if (isset($property['allOf'])) {
            // entries that only carry a description say nothing about the shape
            $parts = array_values(array_filter($property['allOf'], function ($entry) {
                return array_diff(array_keys($entry), ['description', 'example']) !== [];
            }));

            if (count($parts) === 1 && isset($parts[0]['$ref'])) {
                return self::renamed(basename($parts[0]['$ref']));
            }
        }

        return null;
    }

    /**
     * Is the property "this definition, but these keys are required" (an allOf of a reference and an overlay)?
     * @param array $property A property of a schema
     * @return bool
     */
    public static function isOverlay(array $property): bool
    {
        return isset($property['allOf']) && count($property['allOf']) === 2 && isset($property['allOf'][0]['$ref']) && !isset($property['allOf'][1]['$ref']) && !isset($property['allOf'][1]['type']);
    }

    /**
     * What the spec says about a property, in a canonical text: the class has to say the same
     * @param array $property A property of the spec
     * @param string $where Definition and property, for the name of a derived definition
     * @return string
     */
    public static function canonSpec(array $property, string $where): string
    {
        if (($ref = self::refOf($property)) !== null) {
            return '@' . $ref;
        }

        if (self::isOverlay($property)) {
            return '@' . str_replace('.', '_', $where);
        }

        if (isset($property['anyOf'])) {
            return implode('|', array_map(function ($alternative) {
                return '@' . self::refOf($alternative);
            }, $property['anyOf']));
        }

        $type = $property['type'] ?? '?';

        if ($type === 'array') {
            return '[' . self::canonSpec($property['items'], $where . '[]') . ']';
        }

        if ($type === 'object') {
            return isset($property['properties']) ? '@' . str_replace(['.', '[]'], ['_', '_item'], $where) : 'object';
        }

        $text = ['string' => 'string', 'integer' => 'int', 'number' => 'number', 'boolean' => 'bool'][$type] ?? "?$type";

        if (isset($property['enum'])) {
            $text .= ' enum=' . implode(',', $property['enum']);
        }

        if (isset($property['maxLength'])) {
            $text .= ' max=' . $property['maxLength'];
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
            $text = is_array($node['@type']) ? '[' . self::canonClass($node['@type'][0]) . ']' : $node['@type'];

            if (isset($node['@enum'])) {
                $text .= ' enum=' . implode(',', $node['@enum']);
            }

            if (isset($node['@max'])) {
                $text .= ' max=' . $node['@max'];
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
        $components = self::components();
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
        $walk($components[$name] ?? []);
    }

    /**
     * The instance generated from a root, and what can be broken in it
     * @param string $label A key of ROOTS
     * @return array The instance and the meta ('leaves': path, type, enum, max, required; 'objects': path, keys, required)
     */
    public static function instance(string $label): array
    {
        if (self::$instances === null) {
            self::$instances = [];

            foreach (self::ROOTS as $key => $root) {
                $meta = ['objects' => [], 'leaves' => []];
                self::$instances[$key] = [self::named(self::components(), $root, [], $meta, [], []), $meta];
            }
        }

        return self::$instances[$label];
    }

    /**
     * Build an object, with every optional field filled in
     * @param array $components The components of the spec
     * @param array $schema The schema of the object
     * @param array $overlay The overlay that adds required keys to it
     * @param array $meta What can be broken
     * @param array $path Where the object is
     * @param array $stack The definitions being built, to stop at a reference to itself
     * @return array
     */
    private static function object(array $components, array $schema, array $overlay, array &$meta, array $path, array $stack): array
    {
        $required = array_values(array_unique(array_merge($schema['required'] ?? [], $overlay['required'] ?? [])));
        $out = [];
        $keys = [];

        foreach ($schema['properties'] as $key => $property) {
            $sub = $overlay['properties'][$key] ?? [];
            $value = self::property($components, $property, $meta, array_merge($path, [$key]), in_array($key, $required, true), $stack, (is_array($sub) && $sub !== []) ? $sub : []);

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
     * @param array $overlay The overlay that adds required keys to it
     * @param array $meta What can be broken
     * @param array $path Where the value is
     * @param array $stack The definitions being built
     * @return mixed
     */
    private static function named(array $components, string $name, array $overlay, array &$meta, array $path, array $stack)
    {
        if (in_array($name, $stack, true)) {
            return self::SKIP;
        }

        $stack[] = $name;
        $schema = $components[$name];

        if (isset($schema['$ref'])) {
            return self::named($components, basename($schema['$ref']), $overlay, $meta, $path, $stack);
        }

        if (isset($schema['properties'])) {
            return self::object($components, $schema, $overlay, $meta, $path, $stack);
        }

        if ($name === 'CreateTagRequestedShipment') {
            $base = $components['RequestedShipment_tag'];
            $merged = ['properties' => $base['properties'] + ['pickupDetail' => $schema['allOf'][1]['properties']['pickupDetail']], 'required' => array_merge($base['required'], ['pickupDetail'])];
            $value = self::object($components, $merged, [], $meta, $path, $stack);
            $undefined = ['shipTimestamp' => '2020-07-03T09:00:00Z', 'shippingLabelType' => 'x', 'specialServicesRequested' => ['a']];
            $last = count($meta['objects']) - 1;
            $meta['objects'][$last]['keys'] = array_merge($meta['objects'][$last]['keys'], array_keys($undefined));

            return array_merge($value, $undefined);
        }

        if (isset($schema['allOf']) && self::refOf($schema) !== null) {
            return self::named($components, self::original(self::refOf($schema)), $overlay, $meta, $path, $stack);
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
     * @param array $overlay The overlay that adds required keys to it
     * @return mixed
     */
    private static function property(array $components, array $property, array &$meta, array $path, bool $required, array $stack, array $overlay)
    {
        if (self::isOverlay($property)) {
            return self::named($components, basename($property['allOf'][0]['$ref']), $property['allOf'][1], $meta, $path, $stack);
        }

        if (($ref = self::refOf($property)) !== null) {
            return self::named($components, self::original($ref), $overlay, $meta, $path, $stack);
        }

        if (isset($property['anyOf'])) {
            return self::named($components, basename($property['anyOf'][0]['$ref']), [], $meta, $path, $stack);
        }

        $type = $property['type'] ?? (isset($property['properties']) ? 'object' : 'string');

        if ($type === 'object') {
            return isset($property['properties']) ? self::object($components, $property, [], $meta, $path, $stack) : ['k' => 'v'];
        }

        if ($type === 'array') {
            $item = self::property($components, $property['items'], $meta, array_merge($path, [0]), false, $stack, []);

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

        $meta['leaves'][] = ['path' => $path, 'type' => $type, 'enum' => $property['enum'] ?? null, 'max' => $property['maxLength'] ?? null, 'required' => $required];

        return $value;
    }

    /**
     * The structure that holds only the required keys of a schema, recursively: what newCreateRequest() and the others return.
     * A required object whose keys are all optional lists its keys (an empty object would be left out of the request)
     * @param array $schema The schema (or a property)
     * @param array $overlay The overlay that adds required keys to it
     * @param array $stack The definitions being built
     * @return mixed
     */
    public static function requiredOnly(array $schema, array $overlay = [], array $stack = [])
    {
        $components = self::components();

        if (self::isOverlay($schema)) {
            return self::requiredOnly($components[basename($schema['allOf'][0]['$ref'])], $schema['allOf'][1], $stack);
        }

        if (isset($schema['allOf']) && count($schema['allOf']) === 1) {
            return self::requiredOnly($schema['allOf'][0], $overlay, $stack);
        }

        if (isset($schema['$ref'])) {
            $name = basename($schema['$ref']);

            if (in_array($name, $stack, true)) {
                return null;
            }

            if ($name === 'CreateTagRequestedShipment') {
                // RequestedShipment_tag and the pickupDetail of the overlay; the three keys that are not defined are optional
                $base = $components['RequestedShipment_tag'];
                $merged = ['properties' => $base['properties'] + ['pickupDetail' => $components['CreateTagRequestedShipment']['allOf'][1]['properties']['pickupDetail']], 'required' => array_merge($base['required'], ['pickupDetail'])];

                return self::requiredOnly($merged, [], array_merge($stack, [$name]));
            }

            return self::requiredOnly($components[$name], $overlay, array_merge($stack, [$name]));
        }

        if (($schema['type'] ?? '') === 'array') {
            return [];
        }

        if (!isset($schema['properties'])) {
            return null;
        }

        $required = array_values(array_unique(array_merge($schema['required'] ?? [], $overlay['required'] ?? [])));
        $out = [];

        foreach ($schema['properties'] as $key => $property) {
            if (in_array($key, $required, true)) {
                $sub = $overlay['properties'][$key] ?? [];
                $out[$key] = self::requiredOnly($property, (is_array($sub) && $sub !== []) ? $sub : [], $stack);
            }
        }

        if ($out === []) {
            foreach ($schema['properties'] as $key => $property) {
                $out[$key] = (($property['type'] ?? '') === 'array') ? [] : null;
            }
        }

        return $out;
    }
}
