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

    public function testSensitiveBindingNamesAreFilteredRecursively(): void
    {
        $this->assertSame([
            'db.query.parameter.password' => '[Filtered]',
            'db.query.parameter.profile' => [
                'api_token' => '[Filtered]',
                'name' => 'Alice',
            ],
        ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), [
            'password' => 'secret',
            'profile' => ['api_token' => 'secret', 'name' => 'Alice'],
        ]));
    }

    public function testUnsupportedValuesAreFilteredWithoutInvokingApplicationCode(): void
    {
        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                throw new \LogicException('Must not serialize');
            }

            public function __toString(): string
            {
                throw new \LogicException('Must not cast');
            }
        };
        $callback = static function (): void {
            throw new \LogicException('Must not invoke');
        };
        $resource = fopen('php://temp', 'w+');

        try {
            $this->assertSame([
                'db.query.parameter.object' => '[Filtered]',
                'db.query.parameter.callback' => '[Filtered]',
                'db.query.parameter.resource' => '[Filtered]',
            ], DatabaseDataCollector::collectQueryData($this->enabledPolicy(), [
                'object' => $object,
                'callback' => $callback,
                'resource' => $resource,
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

    public function testNormalizedBindingsAreDetached(): void
    {
        $bindings = ['profile' => ['name' => 'Alice']];
        $normalized = DatabaseDataCollector::normalizeQueryBindings($bindings);

        $bindings['profile']['name'] = 'Bob';

        $this->assertSame(['profile' => ['name' => 'Alice']], $normalized);
    }

    public function testRecursiveBindingsAreBounded(): void
    {
        $recursive = [];
        $recursive['child'] = &$recursive;
        $result = DatabaseDataCollector::collectQueryData($this->enabledPolicy(), ['root' => $recursive]);
        $value = $result['db.query.parameter.root'];

        for ($depth = 0; $depth < 32 && \is_array($value); ++$depth) {
            $value = $value['child'];
        }

        $this->assertSame('[Filtered]', $value);
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
        $options = ['send_default_pii' => $sendDefaultPii];
        if ($dataCollection !== null) {
            $options['data_collection'] = $dataCollection;
        }

        $this->assertSame([], DatabaseDataCollector::collectQueryData(
            DataCollectionPolicy::fromOptions(new Options($options)),
            ['name' => 'Alice']
        ));
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
