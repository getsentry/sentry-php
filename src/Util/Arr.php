<?php

declare(strict_types=1);

namespace Sentry\Util;

/**
 * This class provides some utility methods to work with array's.
 *
 * @internal
 */
class Arr
{
    /**
     * Flatten a multi-dimensional associative array with dots except for keys that contain lists.
     *
     * This method is similar to Laravel's `Arr::dot()` method but does not flatten lists.
     * See: https://github.com/laravel/framework/blob/1bfad3020ec5d542ac7352c6fd0d388cbe29c46c/src/Illuminate/Collections/Arr.php#L163
     *
     * @param array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    public static function simpleDot(array $array): array
    {
        $results = [];

        $flatten = static function ($data, $prefix = '') use (&$results, &$flatten): void {
            foreach ($data as $key => $value) {
                $newKey = $prefix . $key;

                if (\is_array($value) && !empty($value) && !array_is_list($value)) {
                    $flatten($value, $newKey . '.');
                } else {
                    $results[$newKey] = $value;
                }
            }
        };

        $flatten($array);

        return $results;
    }
}
