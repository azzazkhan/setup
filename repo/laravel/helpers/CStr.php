<?php

declare(strict_types=1);

namespace Helpers;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class CStr
{
    /**
     * Checks if the referenced value does exists, is of string type
     * and is not an empty string.
     *
     * @param  mixed  $string  The referenced value to check
     * @return bool
     */
    public static function isValidString(&$string): bool
    {
        return isset($string) && is_string($string) && ! empty($string) && strlen($string) > 0;
    }

    /**
     * Checks if the referenced value does exists, is of boolean type.
     *
     * @param  mixed  $boolean
     * @param  mixed  $string  The referenced value to check
     * @return bool
     */
    public static function isValidBoolean(&$boolean): bool
    {
        return isset($boolean) && is_bool($boolean);
    }

    /**
     * Checks if the referenced value does exists, is of array type
     * and is not an empty array.
     *
     * @param  mixed  $array  The referenced value to check
     * @return bool
     */
    public static function isValidArray(&$array): bool
    {
        return isset($array) && is_array($array) && ! empty($array) && count($array) > 0;
    }

    /**
     * Applies logic to generate a computed CSS class names string based on
     * provided data.
     *
     * @param  array  $class_data  Associative array with keys as class name
     *                             and value as a boolean expression
     * @return string A computed CSS classes text based on passed
     *                conditions
     */
    public static function classes(array $class_data): string
    {
        $__classes = [];

        foreach ($class_data as $class => $condition) {
            if (is_int($class) && is_string($condition)) {
                $__classes[] = $condition;

                continue;
            }

            if (! $condition || ! is_string($class)) {
                continue;
            }
            array_push($__classes, $class);
        }

        return implode(' ', $__classes);
    }

    /**
     * Processed the provided styles array into attribute value string.
     *
     * @param  array  $styles
     * @return string
     */
    public static function styles(array $styles): string
    {
        $__styles = [];

        foreach ($styles as $property => $value) {
            if (is_string($property) && strlen($property) && (is_string($value) || is_int($value))) {
                $__styles[] = "{$property}: {$value}";
            }
        }

        return implode('; ', $__styles);
    }

    /**
     * Coverts array of props passed to an element to html attribute/value
     * string.
     *
     * @param  array  $attrs  Attributes being passed to an element
     * @return string An HTML string that can be embedded into a
     *                tag
     */
    public static function attributes(array $attrs): string
    {
        $attributes = [];

        foreach ($attrs as $attr => $val) {
            if (! is_string($attr) || strlen($attr) == 0) {
                $val = Str::squish($val);
                if (! is_string($val) || strlen($val) == 0) {
                    continue;
                }

                $attributes[] = $val;
            } else {
                $attributes[] = sprintf('%s="%s"', $attr, (string) $val);
            }
        }

        return implode(' ', $attributes);
    }

    /**
     * Generates a unique ID for an HTML element.
     *
     * @param  string  $field  The snake_cased name of field.
     * @return string
     */
    public static function id(string $field = 'unlabeled_element'): string
    {
        return sprintf(
            '%s__%s_%s',
            $field,
            hash('crc32b', microtime() . random_int(0, 1_000)),
            hash('crc32b', microtime() . random_int(0, 10_000)),
        );
    }

    /**
     * Generates HTML markup for provided element tag and attributes.
     *
     * @param  string  $tag
     * @param  string|null  $children
     * @param  array  $attrs
     * @param  string  $id
     * @param  bool  $inline
     * @return string
     *
     * @throws  InvalidArgumentException
     */
    public static function element(
        string $tag,
        ?string $children = null,
        array $attrs = [],
        ?string $id = null,
        bool $inline = false,
    ): string {
        if (! Str::squish($tag)) {
            throw new InvalidArgumentException('The [tag] must be a valid HTML tag name!');
        }

        $tag = Str::kebab($tag);
        $attrs['id'] = $id ?: (array_key_exists('id', $attrs) ? $attrs['id'] : self::id(Str::snake($tag)));
        $attributes = self::attributes($attrs);

        if ($inline) {
            return <<<PHP
            <{$tag} {$attributes} />
            PHP;
        }

        return <<<PHP
        <{$tag} {$attributes}>{$children}</{$tag}>
        PHP;
    }
}
