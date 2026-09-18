<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\Options;
use Sentry\Serializer\Serializer;
use Sentry\Util\Arr;

final class DatabaseDataCollector
{
    private const ATTRIBUTE_PREFIX = 'db.query.parameter.';

    private function __construct()
    {
    }

    /**
     * Collects DB bindings that are not nested arrays. It will use the SDK serializer to
     * create displayable values for objects such as \DateTime.
     *
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
                self::serializeBinding($value, $serializer),
                KeyValueDataFilter::DEFAULT_BEHAVIOR
            );
        }

        return $data;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private static function serializeBinding($value, Serializer $serializer)
    {
        if (!\is_array($value)) {
            return \is_object($value) ? $serializer->serialize($value) : $value;
        }

        if (!Arr::isList($value)) {
            return KeyValueDataFilter::FILTERED_VALUE;
        }

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($value as $item) {
            if (\is_array($item)) {
                return KeyValueDataFilter::FILTERED_VALUE;
            }
        }

        $serialized = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($value as $item) {
            $serialized[] = \is_object($item) ? $serializer->serialize($item) : $item;
        }

        return $serialized;
    }
}
