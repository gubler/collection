<?php

namespace Gubler\Collection;

/**
 * Illuminate/Support helpers converted to class with static functions.
 *
 * Any functions specific to non-Arr or non-Collection classes were removed.
 * Any functions that just called static methods on Arr or Collection were removed.
 */
class Helper
{
    /**
     * Assign high numeric IDs to a config item to force appending.
     */
    public static function append_config(array $array): array
    {
        $start = 9999;

        foreach ($array as $key => $value) {
            if (is_numeric($key)) {
                $start++;

                $array[$start] = Arr::pull($array, $key);
            }
        }

        return $array;
    }

    /**
     * Determine if the given value is "blank".
     */
    public static function blank(mixed $value): bool
    {
        if (\is_null($value)) {
            return true;
        }

        if (\is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || \is_bool($value)) {
            return false;
        }

        if ($value instanceof \Countable) {
            return \count($value) === 0;
        }

        return empty($value);
    }

    /**
     * Get the class "basename" of the given object / class.
     */
    public static function class_basename(string|object $class): string
    {
        $class = \is_object($class) ? \get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }

    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     */
    public static function class_uses_recursive(object|string $class): array
    {
        if (\is_object($class)) {
            $class = \get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
            $results += self::trait_uses_recursive($class);
        }

        return array_unique($results);
    }

    /**
     * Fill in data where it's missing.
     */
    public static function data_fill(mixed &$target, array|string $key, mixed $value): mixed
    {
        return self::data_set($target, $key, $value, false);
    }

    /**
     * Get an item from an array or object using "dot" notation.
     */
    public static function data_get(mixed $target, null|array|string $key, mixed $default = null): mixed
    {
        if (\is_null($key)) {
            return $target;
        }

        $key = \is_array($key) ? $key : explode('.', $key);

        while (! \is_null($segment = array_shift($key))) {
            if ($segment === '*') {
                if ($target instanceof Collection) {
                    $target = $target->all();
                } elseif (! \is_array($target)) {
                    return self::value($default);
                }

                $result = Arr::pluck($target, $key);

                return \in_array('*', $key) ? Arr::collapse($result) : $result;
            }

            if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (\is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return self::value($default);
            }
        }

        return $target;
    }

    /**
     * Set an item on an array or object using dot notation.
     */
    public static function data_set(mixed &$target, array|string $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = \is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (! Arr::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    self::data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (Arr::accessible($target)) {
            if ($segments) {
                if (! Arr::exists($target, $segment)) {
                    $target[$segment] = [];
                }

                self::data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || ! Arr::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (\is_object($target)) {
            if ($segments) {
                if (! isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                self::data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || ! isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                self::data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }

    /**
     * Determine if a value is "filled".
     */
    public static function filled(mixed $value): bool
    {
        return ! self::blank($value);
    }

    /**
     * Get the first element of an array. Useful for method chaining.
     */
    public static function head(array $array): mixed
    {
        return reset($array);
    }

    /**
     * Get the last element from an array.
     */
    public static function last(array $array): mixed
    {
        return end($array);
    }

    /**
     * Get an item from an object using "dot" notation.
     */
    public static function object_get(object $object, ?string $key, mixed $default = null): mixed
    {
        if (\is_null($key) || trim($key) === '') {
            return $object;
        }

        foreach (explode('.', $key) as $segment) {
            if (! \is_object($object) || ! isset($object->{$segment})) {
                return self::value($default);
            }

            $object = $object->{$segment};
        }

        return $object;
    }

    /**
     * Replace a given pattern with each value in the array in sequentially.
     */
    public static function preg_replace_array(string $pattern, array $replacements, string $subject): string
    {
        return preg_replace_callback($pattern, static function() use (&$replacements) {
            foreach ($replacements as $key => $value) {
                return array_shift($replacements);
            }
        }, $subject);
    }

    /**
     * Retry an operation a given number of times.
     */
    public static function retry(int $times, callable $callback, int $sleep = 0): mixed
    {
        $times--;

        beginning:
        try {
            return $callback();
        } catch (\Exception $e) {
            if (! $times) {
                throw $e;
            }

            $times--;

            if ($sleep) {
                usleep($sleep * 1000);
            }

            goto beginning;
        }
    }

    /**
     * Throw the given exception if the given condition is true.
     */
    public static function throw_if(mixed $condition, \Throwable|string $exception, ...$parameters): mixed
    {
        if ($condition) {
            throw (\is_string($exception) ? new $exception(...$parameters) : $exception);
        }

        return $condition;
    }

    /**
     * Throw the given exception unless the given condition is true.
     */
    public static function throw_unless(mixed $condition, \Throwable|string $exception, ...$parameters): mixed
    {
        if (! $condition) {
            throw (\is_string($exception) ? new $exception(...$parameters) : $exception);
        }

        return $condition;
    }

    /**
     * Returns all traits used by a trait and its traits.
     */
    public static function trait_uses_recursive(string $trait): array
    {
        $traits = class_uses($trait);

        foreach ($traits as $trait) {
            $traits += self::trait_uses_recursive($trait);
        }

        return $traits;
    }

    /**
     * Transform the given value if it is present.
     */
    public static function transform(mixed $value, callable $callback, mixed $default = null): mixed
    {
        if (self::filled($value)) {
            return $callback($value);
        }

        if (\is_callable($default)) {
            return $default($value);
        }

        return $default;
    }

    /**
     * Return the default value of the given value.
     */
    public static function value(mixed $value): mixed
    {
        return $value instanceof \Closure ? $value() : $value;
    }

    /**
     * Return the given value, optionally passed through the given callback.
     */
    public static function with(mixed $value, ?callable $callback = null): mixed
    {
        return \is_null($callback) ? $value : $callback($value);
    }
}
