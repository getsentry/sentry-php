<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DatabaseDataCollector;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\Options;

final class DatabaseDataCollectorTest extends TestCase
{
    public function testScalarBindingsAndKeysArePreserved(): void
    {
        $this->assertSame([
            'db.query.parameter.0' => null,
            'db.query.parameter.2' => false,
            'db.query.parameter.7' => 0,
            'db.query.parameter.9' => '',
            'db.query.parameter.:name' => 'Alice',
        ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), [
            0 => null,
            2 => false,
            7 => 0,
            9 => '',
            ':name' => 'Alice',
        ]));
    }

    public function testSensitiveBindingNamesAreFiltered(): void
    {
        $this->assertSame([
            'db.query.parameter.password' => '[Filtered]',
            'db.query.parameter.name' => 'Alice',
        ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), [
            'password' => 'secret',
            'name' => 'Alice',
        ]));
    }

    public function testDateTimeObjectsUseTheSdkSerializer(): void
    {
        $this->assertSame([
            'db.query.parameter.created_at' => 'DateTime(2026-01-02 03:04:05)',
            'db.query.parameter.updated_at' => 'DateTimeImmutable(2026-01-02 03:04:05.123456)',
        ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), [
            'created_at' => new \DateTime('2026-01-02 03:04:05', new \DateTimeZone('UTC')),
            'updated_at' => new \DateTimeImmutable('2026-01-02 03:04:05.123456', new \DateTimeZone('UTC')),
        ]));
    }

    public function testConfiguredObjectSerializationIsFiltered(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options([
            'data_collection' => [],
            'class_serializers' => [
                \stdClass::class => static function (\stdClass $value): array {
                    return ['name' => $value->name, 'password' => $value->password];
                },
            ],
        ]));

        $this->assertSame([
            'db.query.parameter.profile' => [
                'class' => \stdClass::class,
                'data' => ['name' => 'Alice', 'password' => '[Filtered]'],
            ],
        ], DatabaseDataCollector::collectQueryData($policy, [
            'profile' => (object) ['name' => 'Alice', 'password' => 'secret'],
        ]));
    }

    public function testFlatListsPreserveValuesAndSerializeObjects(): void
    {
        $date = new \DateTimeImmutable('2026-01-02 03:04:05', new \DateTimeZone('UTC'));

        $this->assertSame([
            'db.query.parameter.values' => [null, false, 0, 1.5, '', 'Alice'],
            'db.query.parameter.empty' => [],
            'db.query.parameter.dates' => ['DateTimeImmutable(2026-01-02 03:04:05)'],
            'db.query.parameter.callable' => ['DateTimeImmutable(2026-01-02 03:04:05)', 'format'],
        ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), [
            'values' => [null, false, 0, 1.5, '', 'Alice'],
            'empty' => [],
            'dates' => [$date],
            'callable' => [$date, 'format'],
        ]));
    }

    /**
     * @dataProvider unsupportedArrayProvider
     *
     * @param array<array-key, mixed> $value
     */
    public function testAssociativeAndNestedArrayValuesAreFiltered(array $value): void
    {
        $this->assertSame([
            'db.query.parameter.value' => '[Filtered]',
        ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), ['value' => $value]));
    }

    public function unsupportedArrayProvider(): \Generator
    {
        yield 'associative' => [['name' => 'Alice']];
        yield 'sparse numeric' => [[0 => 'Alice', 2 => 'Bob']];
        yield 'one based' => [[1 => 'Alice']];
        yield 'nested list' => [[[1, 2]]];
        yield 'nested associative' => [[['name' => 'Alice']]];

        $recursive = [];
        $recursive[] = &$recursive;

        yield 'recursive list' => [$recursive];
    }

    public function testRejectedBindingsDoNotInvokeObjectSerializers(): void
    {
        $calls = 0;
        $policy = DataCollectionPolicy::fromOptions(new Options([
            'data_collection' => [],
            'class_serializers' => [
                \stdClass::class => static function (\stdClass $value) use (&$calls): array {
                    ++$calls;

                    return [];
                },
            ],
        ]));
        $object = new \stdClass();

        $this->assertSame([
            'db.query.parameter.password' => '[Filtered]',
            'db.query.parameter.associative' => '[Filtered]',
            'db.query.parameter.nested' => '[Filtered]',
        ], DatabaseDataCollector::collectQueryData($policy, [
            'password' => $object,
            'associative' => ['value' => $object],
            'nested' => [$object, []],
        ]));
        $this->assertSame(0, $calls);
    }

    public function testResourcesAreFiltered(): void
    {
        $resource = fopen('php://temp', 'w+');

        try {
            $this->assertSame([
                'db.query.parameter.resource' => '[Filtered]',
                'db.query.parameter.values' => ['[Filtered]'],
            ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), [
                'resource' => $resource,
                'values' => [$resource],
            ]));
        } finally {
            fclose($resource);
        }
    }

    public function testNonFiniteFloatsAreFiltered(): void
    {
        $this->assertSame([
            'db.query.parameter.finite' => 1.5,
            'db.query.parameter.infinite' => '[Filtered]',
            'db.query.parameter.nan' => '[Filtered]',
        ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), [
            'finite' => 1.5,
            'infinite' => \INF,
            'nan' => \NAN,
        ]));
    }

    public function testCollectedValuesAreDetached(): void
    {
        $name = 'Alice';
        $date = new \DateTime('2026-01-02 03:04:05', new \DateTimeZone('UTC'));
        $bindings = ['names' => [&$name], 'date' => $date];
        $data = DatabaseDataCollector::collectQueryData($this->enabledPolicy(), $bindings);

        $name = 'Bob';
        $date->modify('+1 day');

        $this->assertSame([
            'db.query.parameter.names' => ['Alice'],
            'db.query.parameter.date' => 'DateTime(2026-01-02 03:04:05)',
        ], $data);
    }

    public function testEmptyBindingsAreOmitted(): void
    {
        $this->assertSame([], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), []));
    }

    /**
     * @dataProvider disabledPolicyProvider
     *
     * @param array<string, mixed>|null $dataCollection
     */
    public function testDisabledPoliciesOmitBindings(?array $dataCollection, bool $sendDefaultPii): void
    {
        $calls = 0;
        $options = [
            'send_default_pii' => $sendDefaultPii,
            'class_serializers' => [
                \stdClass::class => static function (\stdClass $value) use (&$calls): array {
                    ++$calls;

                    return [];
                },
            ],
        ];
        if ($dataCollection !== null) {
            $options['data_collection'] = $dataCollection;
        }

        $this->assertSame([], DatabaseDataCollector::collectQueryData(
            DataCollectionPolicy::fromOptions(new Options($options)),
            ['value' => new \stdClass()]
        ));
        $this->assertSame(0, $calls);
    }

    public function disabledPolicyProvider(): \Generator
    {
        yield 'legacy without PII' => [null, false];
        yield 'legacy with PII' => [null, true];
        yield 'configured disabled without PII' => [['database_query_data' => false], false];
        yield 'configured disabled with PII' => [['database_query_data' => false], true];
    }

    private function enabledPolicy(): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['data_collection' => []]));
    }
}
