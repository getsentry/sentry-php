<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * Collects safely detached database query bindings without converting values.
 */
final class DatabaseDataCollector
{
    private const ATTRIBUTE_PREFIX = 'db.query.parameter.';

    /**
     * Keep traversal bounded for recursive and unexpectedly deep values.
     */
    private const MAX_DEPTH = 32;

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $bindings
     *
     * @return array<string, mixed>
     */
    public static function collectQueryData(DataCollectionPolicy $policy, array $bindings): array
    {
        if (!$policy->shouldCollectDatabaseQueryData() || $bindings === []) {
            return [];
        }

        $bindings = self::normalizeQueryBindings($bindings);
        $data = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($bindings as $key => $value) {
            $filtered = KeyValueDataFilter::filterKeyValueData(
                [(string) $key => $value],
                ['mode' => 'denyList', 'terms' => []]
            );
            if ($filtered === null) {
                continue;
            }

            $data[self::ATTRIBUTE_PREFIX . $key] = $filtered[(string) $key];
        }

        return $data;
    }

    /**
     * Creates a detached snapshot without invoking application code.
     *
     * This method is intended for integrations that must snapshot bindValue()
     * inputs before query execution.
     *
     * @param array<array-key, mixed> $bindings
     *
     * @return array<array-key, mixed>
     */
    public static function normalizeQueryBindings(array $bindings): array
    {
        return self::normalize($bindings, 0);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private static function normalize(array $values, int $depth): array
    {
        $normalized = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($values as $key => $value) {
            if (\is_array($value)) {
                $value = $depth >= self::MAX_DEPTH - 1
                    ? KeyValueDataFilter::FILTERED_VALUE
                    : self::normalize($value, $depth + 1);
            } elseif (($value !== null && !\is_scalar($value)) || (\is_float($value) && !is_finite($value))) {
                $value = KeyValueDataFilter::FILTERED_VALUE;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
