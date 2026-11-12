<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DatabaseDataCollector;
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
            'db.query.parameter.11' => 1.5,
            'db.query.parameter.:name' => 'Alice',
            'db.query.parameter.:code' => '00123',
        ], DatabaseDataCollector::collectQueryData($this->options(), [
            0 => null,
            2 => false,
            7 => 0,
            9 => '',
            11 => 1.5,
            ':name' => 'Alice',
            ':code' => '00123',
        ]));
    }

    public function testSensitiveBindingNamesAreFiltered(): void
    {
        $this->assertSame([
            'db.query.parameter.password' => '[Filtered]',
            'db.query.parameter.name' => 'Alice',
        ], DatabaseDataCollector::collectQueryData($this->options(), [
            'password' => 'secret',
            'name' => 'Alice',
        ]));
    }

    public function testDateTimeObjectsUseTheSdkSerializer(): void
    {
        $this->assertSame([
            'db.query.parameter.created_at' => 'DateTime(2026-01-02 03:04:05)',
            'db.query.parameter.updated_at' => 'DateTimeImmutable(2026-01-02 03:04:05.123456)',
        ], DatabaseDataCollector::collectQueryData($this->options(), [
            'created_at' => new \DateTime('2026-01-02 03:04:05', new \DateTimeZone('UTC')),
            'updated_at' => new \DateTimeImmutable('2026-01-02 03:04:05.123456', new \DateTimeZone('UTC')),
        ]));
    }

    public function testSensitiveFieldsIntroducedBySerializationAreFiltered(): void
    {
        $options = new Options([
            'class_serializers' => [
                \stdClass::class => static function (\stdClass $value): array {
                    return ['name' => $value->name, 'password' => 'secret'];
                },
            ],
        ]);

        $this->assertSame([
            'db.query.parameter.profile' => [
                'class' => \stdClass::class,
                'data' => ['name' => 'Alice', 'password' => '[Filtered]'],
            ],
        ], DatabaseDataCollector::collectQueryData($options, [
            'profile' => (object) ['name' => 'Alice'],
        ]));
    }

    public function testSensitiveBindingsDoNotInvokeObjectSerializers(): void
    {
        $calls = 0;
        $options = new Options([
            'class_serializers' => [
                \stdClass::class => static function (\stdClass $value) use (&$calls): array {
                    ++$calls;

                    return [];
                },
            ],
        ]);
        $object = new \stdClass();

        $this->assertSame([
            'db.query.parameter.password' => '[Filtered]',
        ], DatabaseDataCollector::collectQueryData($options, [
            'password' => $object,
        ]));
        $this->assertSame(0, $calls);
    }

    public function testNonFiniteFloatsAreFiltered(): void
    {
        $this->assertSame([
            'db.query.parameter.finite' => 1.5,
            'db.query.parameter.infinite' => '[Filtered]',
            'db.query.parameter.nan' => '[Filtered]',
        ], DatabaseDataCollector::collectQueryData($this->options(), [
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
        $data = DatabaseDataCollector::collectQueryData($this->options(), $bindings);

        $name = 'Bob';
        $date->modify('+1 day');

        $this->assertSame([
            'db.query.parameter.names' => ['Alice'],
            'db.query.parameter.date' => 'DateTime(2026-01-02 03:04:05)',
        ], $data);
    }

    public function testEmptyBindingsAreOmitted(): void
    {
        $this->assertSame([], DatabaseDataCollector::collectQueryData($this->options(), []));
    }

    public function testDisabledCollectionOmitsBindings(): void
    {
        $calls = 0;
        $options = new Options([
            'data_collection' => ['database_query_data' => false],
            'class_serializers' => [
                \stdClass::class => static function (\stdClass $value) use (&$calls): array {
                    ++$calls;

                    return [];
                },
            ],
        ]);

        $this->assertSame([], DatabaseDataCollector::collectQueryData($options, ['value' => new \stdClass()]));
        $this->assertSame(0, $calls);
    }

    private function options(): Options
    {
        return new Options();
    }
}
