<?php

declare(strict_types=1);

/**
 * Validates data (stdClass from json_decode, or associative arrays) against a schema.
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Misc;

/**
 * Validates data against a schema.
 *
 * Schema syntax:
 *
 * ```
 * 1. Type spec (string)
 *      'string'            required string
 *      '?string'           value may be null
 *      'int|string'        union
 *      '@Address'          reference to a named definition (see define())
 *      '?@Address'         nullable reference
 *      'string|@Address'   references may be used inside unions too
 *    Types: string, int, float (strict), number (int|float), bool,
 *           array (any list), object (any map), null, mixed
 *
 * 2. Object schema (array whose keys are field names)
 *      'name'  => <node>   required key
 *      'name?' => <node>   optional key
 *
 * 3. List schema (array with a single element at index 0)
 *      ['string']          list of strings
 *      ['@Order']          list of Order objects
 *
 * 4. Rule node (array whose keys start with '@') – adds constraints
 *      '@type'    any node above (default 'mixed')
 *      '@min'     number: value >= min | string: length >= min | list/object: item count >= min
 *      '@max'     same, upper bound
 *      '@pattern' PCRE regex, strings only
 *      '@enum'    list of allowed values (strict comparison)
 *      '@check'   callable($value): true = valid, false = invalid,
 *                 string = invalid with that message
 *
 *    Constraints are skipped when the type check failed or the value is null
 *    (null is only possible if the type allows it, e.g. '?string').
 * ```
 */
class SchemaValidator
{
    /**
     * Keys recognised in a rule node
     * @var string[]
     */
    private const RULE_KEYS = ['@type', '@min', '@max', '@pattern', '@enum', '@check'];

    /**
     * List of errors
     * @var array
     */
    private $errors = [];

    /**
     * Allow properties not in the schema?
     * @var bool
     */
    private $allowExtra;

    /**
     * Schema definitions
     * @var array
     */
    private $definitions = [];

    /**
     * Initialize the instance
     * @param bool $allowExtra Allow properties not in the schema?
     * @param array $definitions Array of schemas (name => schema)
     */
    public function __construct(bool $allowExtra = true, array $definitions = [])
    {
        $this->allowExtra = $allowExtra;

        foreach ($definitions as $name => $schema) {
            $this->define((string) $name, $schema);
        }
    }

    /**
     * Register a named schema, usable anywhere as '@Name'.
     * Definitions may reference each other and themselves (recursive structures).
     * @param string $name Schema identifier
     * @param string|array $schema Schema definition
     * @return self
     * @throws \InvalidArgumentException
     */
    public function define(string $name, $schema): self
    {
        if (!preg_match('/^[A-Za-z_]\w*$/', $name)) {
            throw new \InvalidArgumentException("Invalid definition name '{$name}'");
        }

        $this->definitions[$name] = $schema;

        return $this;
    }

    /**
     * Validate data against a schema
     * @param mixed $data Data to validate
     * @param string|array $schema A schema node, or '@Name' to validate against a definition
     * @return bool
     */
    public function validate($data, $schema): bool
    {
        $this->errors = [];
        $this->check($data, $schema, '$');
        return $this->errors === [];
    }

    /**
     * Validate a JSON string against a schema
     * @param string $json JSON string
     * @param string|array $schema A schema node, or '@Name' to validate against a definition
     * @return bool
     */
    public function validateJson(string $json, $schema): bool
    {
        $data = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors = ['Invalid JSON: ' . json_last_error_msg()];
            return false;
        }

        return $this->validate($data, $schema);
    }

    /**
     * Get errors found
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check a value against a schema
     * @param mixed $value Value to check
     * @param string|array $schema A schema node, or '@Name' or an array of those to validate against a definition
     * @param string $path Path in data
     * @return void
     * @throws \InvalidArgumentException
     */
    private function check($value, $schema, string $path): void
    {
        if (is_string($schema)) {
            $this->checkSpec($value, $schema, $path);
            return;
        }

        if (!is_array($schema)) {
            throw new \InvalidArgumentException("Invalid schema node at {$path}");
        }

        if ($this->isRuleNode($schema)) {
            $this->checkRules($value, $schema, $path);
            return;
        }

        // List schema
        if ((count($schema) === 1) && array_key_exists(0, $schema)) {
            if (!is_array($value) || !$this->isList($value)) {
                $this->errors[] = sprintf('%s: expected list, got %s', $path, $this->typeOf($value));
                return;
            }

            foreach ($value as $i => $item) {
                $this->check($item, $schema[0], "{$path}[{$i}]");
            }

            return;
        }

        // Object schema
        $this->checkObject($value, $schema, $path);
    }

    /**
     * Check a value against a specification
     * @param mixed $value Value to check
     * @param string $spec Specification
     * @param string $path Path in data
     * @return void
     */
    private function checkSpec($value, string $spec, string $path): void
    {
        $body = $spec;

        if ($body !== '' && $body[0] === '?') {
            if ($value === null) {
                return;
            }
            $body = substr($body, 1);
        }

        // A lone reference: report the nested errors directly
        if (preg_match('/^@([A-Za-z_]\w*)$/', $body, $m)) {
            $this->check($value, $this->definition($m[1]), $path);
            return;
        }

        if (!$this->matchesType($value, $body, $path)) {
            $this->errors[] = sprintf('%s: expected %s, got %s', $path, $spec, $this->typeOf($value));
        }
    }

    /**
     * Check if a value is an object and matches a schema
     * @param mixed $value Value to check
     * @param array $schema Schema to validate against
     * @param string $path Path in data
     * @return void
     */
    private function checkObject($value, array $schema, string $path): void
    {
        if (!$this->isMap($value)) {
            $this->errors[] = sprintf('%s: expected object, got %s', $path, $this->typeOf($value));
            return;
        }

        $data = is_object($value) ? get_object_vars($value) : $value;
        $known = [];

        foreach ($schema as $key => $sub) {
            $key = (string) $key;
            $optional = substr($key, -1) === '?';
            $name = $optional ? substr($key, 0, -1) : $key;
            $known[] = $name;

            if (!array_key_exists($name, $data)) {
                if (!$optional) {
                    $this->errors[] = "{$path}.{$name}: required key is missing";
                }
                continue;
            }
            $this->check($data[$name], $sub, "{$path}.{$name}");
        }

        if (!$this->allowExtra) {
            foreach (array_diff(array_map('strval', array_keys($data)), $known) as $extra) {
                $this->errors[] = "{$path}.{$extra}: unexpected key";
            }
        }
    }

    /**
     * Check a value against the rules
     * @param mixed $value Value to check
     * @param array $node List of rules
     * @param string $path Path in data
     * @return void
     * @throws \InvalidArgumentException
     */
    private function checkRules($value, array $node, string $path): void
    {
        $unknown = array_diff(array_keys($node), self::RULE_KEYS);

        if ($unknown) {
            throw new \InvalidArgumentException(
                "Unknown rule(s) at {$path}: " . implode(', ', $unknown)
            );
        }

        // 1. type
        $before = count($this->errors);
        $this->check($value, $node['@type'] ?? 'mixed', $path);

        if (count($this->errors) > $before || $value === null) {
            return;
        }

        // 2. enum
        if (array_key_exists('@enum', $node) && !in_array($value, (array) $node['@enum'], true)) {
            $this->errors[] = sprintf(
                '%s: must be one of %s',
                $path,
                json_encode(array_values((array)$node['@enum']), JSON_UNESCAPED_UNICODE)
            );
        }

        // 3. min / max
        if (array_key_exists('@min', $node) || array_key_exists('@max', $node)) {
            $this->checkBounds($value, $node, $path);
        }

        // 4. pattern
        if (array_key_exists('@pattern', $node)) {
            if (!is_string($value)) {
                $this->errors[] = sprintf('%s: @pattern requires a string, got %s', $path, $this->typeOf($value));
            } else {
                $r = @preg_match($node['@pattern'], $value);

                if (false === $r) {
                    throw new \InvalidArgumentException("Invalid regex at {$path}: {$node['@pattern']}");
                }

                if (0 === $r) {
                    $this->errors[] = sprintf('%s: does not match pattern %s', $path, $node['@pattern']);
                }
            }
        }

        // 5. custom callback
        if (array_key_exists('@check', $node)) {
            if (!is_callable($node['@check'])) {
                throw new \InvalidArgumentException("@check at {$path} is not callable");
            }

            $r = call_user_func($node['@check'], $value);

            if (true !== $r) {
                $this->errors[] = sprintf('%s: %s', $path, is_string($r) ? $r : 'custom check failed');
            }
        }
    }

    /**
     * Check value limits
     * @param mixed $value Value to check
     * @param array $node List of rules
     * @param string $path Path in data
     * @return void
     */
    private function checkBounds($value, array $node, string $path): void
    {
        if (is_int($value) || is_float($value)) {
            $size = $value;
            $label = 'value';
        } elseif (is_string($value)) {
            $size = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            $label = 'length';
        } elseif (is_array($value)) {
            $size = count($value);
            $label = 'item count';
        } elseif (is_object($value)) {
            $size = count(get_object_vars($value));
            $label = 'item count';
        } else {
            $this->errors[] = sprintf('%s: @min/@max cannot be applied to %s', $path, $this->typeOf($value));
            return;
        }

        if (array_key_exists('@min', $node) && $size < $node['@min']) {
            $this->errors[] = sprintf('%s: %s must be >= %s (got %s)', $path, $label, $node['@min'], $size);
        }
        if (array_key_exists('@max', $node) && $size > $node['@max']) {
            $this->errors[] = sprintf('%s: %s must be <= %s (got %s)', $path, $label, $node['@max'], $size);
        }
    }

    /**
     * Check if the value has the specified type
     * @param mixed $value Value to check
     * @param string $typeSpec Type specification (see top of this file for more info)
     * @param string $path Path in data
     * @return bool
     * @throws \InvalidArgumentException
     */
    private function matchesType($value, string $typeSpec, string $path): bool
    {
        foreach (explode('|', $typeSpec) as $type) {
            $type = trim($type);

            // Reference inside a union: matches if it validates without errors
            if (('' !== $type) && ('@' === $type[0])) {
                $saved = $this->errors;
                $this->errors = [];
                $this->check($value, $this->definition(substr($type, 1)), $path);
                $ok = $this->errors === [];
                $this->errors = $saved;

                if ($ok) {
                    return true;
                }

                continue;
            }

            switch ($type) {
                case 'mixed':
                    return true;

                case 'string':
                    if (is_string($value)) {
                        return true;
                    }

                    break;

                case 'int':
                    if (is_int($value)) {
                        return true;
                    }

                    break;

                case 'float':
                    if (is_float($value)) {
                        return true;
                    }

                    break;

                case 'number':
                    if (is_int($value) || is_float($value)) {
                        return true;
                    }

                    break;
                case 'bool':
                    if (is_bool($value)) {
                        return true;
                    }

                    break;

                case 'null':
                    if ($value === null) {
                        return true;
                    }

                    break;

                case 'array':
                    if (is_array($value) && $this->isList($value)) {
                        return true;
                    }

                    break;

                case 'object':
                    if ($this->isMap($value)) {
                        return true;
                    }

                    break;

                default:
                    throw new \InvalidArgumentException("Unknown type '{$type}' in schema");
            }
        }

        return false;
    }

    /**
     * Return a schema definition by name
     * @param string $name Name of the schema
     * @return array
     * @throws \InvalidArgumentException
     */
    private function definition(string $name)
    {
        if (!array_key_exists($name, $this->definitions)) {
            throw new \InvalidArgumentException("Unknown definition '@{$name}'");
        }

        return $this->definitions[$name];
    }

    /**
     * Check if we have a rule node
     * @param array $schema
     * @return bool
     */
    private function isRuleNode(array $schema): bool
    {
        foreach (array_keys($schema) as $k) {
            if (is_string($k) && ('' !== $k) && ('@' === $k[0])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value is a map
     * @param mixed $value Value to check
     * @return bool
     */
    private function isMap($value): bool
    {
        return is_object($value)
            || (is_array($value) && (([] === $value) || !$this->isList($value)));
    }

    /**
     * Check if an array is a list
     * @param array $a Array to check
     * @return bool
     */
    private function isList(array $a): bool
    {
        return ([] === $a) || (array_keys($a) === range(0, count($a) - 1));
    }

    /**
     * Get type of value
     * @param mixed $value Value to check
     * @return string Value type
     */
    private function typeOf($value): string
    {
        return is_object($value) ? 'object' : gettype($value);
    }
}

/**************************************************************************
 * EXAMPLE

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $v = new SchemaValidator(false);

    // Named, reusable definitions
    $v->define('Address', [
        'street' => 'string',
        'city'   => 'string',
        'zip?'   => ['@type' => 'string', '@pattern' => '/^\d{4}-\d{3}$/'],
    ]);
    $v->define('Order', [
        'sku' => ['@type' => 'string', '@pattern' => '/^[A-Z]{2}\d+$/'],
        'qty' => ['@type' => 'int', '@min' => 1, '@max' => 100],
    ]);
    $v->define('Category', [          // recursive: a tree
        'name'      => 'string',
        'children?' => ['@Category'],
    ]);

    $schema = [
        'id'        => ['@type' => 'int', '@min' => 1],
        'name'      => ['@type' => 'string', '@min' => 2, '@max' => 50],
        'email?'    => ['@type' => 'string', '@check' => function ($s) {
            return filter_var($s, FILTER_VALIDATE_EMAIL) !== false ? true : 'not a valid e-mail';
        }],
        'role'      => ['@type' => 'string', '@enum' => ['admin', 'user', 'guest']],
        'nickname'  => '?string',
        'tags'      => ['@type' => ['string'], '@max' => 5],   // list of strings, at most 5
        'address?'  => '@Address',
        'orders'    => ['@Order'],                             // list of Order
        'category?' => '@Category',
    ];

    $json = <<<'JSON'
    {
      "id": 0,
      "name": "Ana",
      "email": "not-an-email",
      "role": "root",
      "nickname": null,
      "tags": ["a", "b"],
      "address": {"street": "Rua X", "city": "Porto", "zip": "4000"},
      "orders": [{"sku": "AB12", "qty": 2}, {"sku": "ab12", "qty": 500}],
      "category": {"name": "root", "children": [{"name": "kid", "children": [{"name": 5}]}]}
    }
    JSON;

    var_dump($v->validateJson($json, $schema));
    print_r($v->getErrors());
    // Expected to include, e.g.:
    //   $.id: value must be >= 1 (got 0)
    //   $.email: not a valid e-mail
    //   $.role: must be one of ["admin","user","guest"]
    //   $.address.zip: does not match pattern /^\d{4}-\d{3}$/
    //   $.orders[1].sku: does not match pattern /^[A-Z]{2}\d+$/
    //   $.orders[1].qty: value must be <= 100 (got 500)
    //   $.category.children[0].children[0].name: expected string, got integer
}

*/
