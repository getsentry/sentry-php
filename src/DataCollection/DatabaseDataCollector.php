<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\Options;
use Sentry\Serializer\Serializer;

final class DatabaseDataCollector
{
    private const ATTRIBUTE_PREFIX = 'db.query.parameter.';

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

        /** @var Options $options */
        $options = $policy->getOptions();
        $serializer = new Serializer($options);
        $data = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($bindings as $key => $value) {
            $key = (string) $key;
            if (KeyValueDataFilter::shouldFilterValue($key, KeyValueDataFilter::DEFAULT_BEHAVIOR)) {
                $data[self::ATTRIBUTE_PREFIX . $key] = KeyValueDataFilter::FILTERED_VALUE;

                continue;
            }

            $data[self::ATTRIBUTE_PREFIX . $key] = KeyValueDataFilter::filterKeyValue(
                $key,
                $serializer->serialize($value),
                KeyValueDataFilter::DEFAULT_BEHAVIOR
            );
        }

        return $data;
    }
}
