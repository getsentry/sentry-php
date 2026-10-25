<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

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
        $options = $policy->getOptions();
        if ($options === null || $bindings === [] || !$policy->shouldCollectDatabaseQueryData()) {
            return [];
        }

        $filter = new KeyValueDataFilter(KeyValueCollectionBehavior::denyList());
        $data = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($filter->filterKeyValueData($bindings, [new Serializer($options), 'serialize']) ?? [] as $key => $value) {
            $data[self::ATTRIBUTE_PREFIX . $key] = $value;
        }

        return $data;
    }
}
