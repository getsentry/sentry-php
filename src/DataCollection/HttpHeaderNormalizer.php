<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\Util\Http;

/**
 * Prepares header lines and maps for collection without invoking application
 * callbacks. Already normalized PSR-7 headers do not require this step.
 *
 * @internal
 */
final class HttpHeaderNormalizer
{
    private function __construct()
    {
    }

    /**
     * Integer-keyed strings are raw header lines. Numeric header names in maps
     * must use array values to distinguish them from raw lines.
     *
     * @param array<array-key, mixed> $headers
     *
     * @return array<array-key, string[]>
     */
    public static function normalize(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            // Numeric keys with array values can be valid header names in a
            // header map; only scalar entries are interpreted as raw lines.
            if (\is_int($name) && !\is_array($values)) {
                if (!\is_string($values)) {
                    continue;
                }

                $parsedHeaders = [];
                Http::parseResponseHeaders($values, $parsedHeaders);
                foreach ($parsedHeaders as $parsedName => $parsedValues) {
                    self::appendHeader($normalized, (string) $parsedName, $parsedValues);
                }

                continue;
            }

            self::appendHeader($normalized, (string) $name, \is_array($values) ? $values : [$values]);
        }

        return $normalized;
    }

    /**
     * @param array<array-key, string[]> $normalized
     * @param array<array-key, mixed>    $values
     */
    private static function appendHeader(array &$normalized, string $name, array $values): void
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return;
        }

        foreach ($values as $value) {
            // Header bags may contain nulls or objects. Do not call
            // __toString or retain objects for later serialization.
            $normalized[$name][] = \is_scalar($value) ? (string) $value : KeyValueDataFilter::FILTERED_VALUE;
        }
    }
}
