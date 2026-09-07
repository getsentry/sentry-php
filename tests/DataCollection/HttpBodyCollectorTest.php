<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\HttpBodyCollector;
use Sentry\Options;
use Sentry\Util\JSON;

final class HttpBodyCollectorTest extends TestCase
{
    public function testBodyDirectionsAndLimits(): void
    {
        foreach (DataCollectionOptions::HTTP_BODY_TYPES as $type) {
            $this->assertSame(0, HttpBodyCollector::getMaxBodyLength(new Options(), $type));
            $this->assertSame(0, HttpBodyCollector::getMaxBodyLength(new Options(['data_collection' => ['http_bodies' => []]]), $type));
            foreach (['never' => 0, 'none' => 0, 'small' => 1000, 'medium' => 10000, 'always' => 100000] as $size => $limit) {
                $options = new Options(['data_collection' => [], 'max_request_body_size' => $size]);
                $this->assertSame(substr($type, -7) === 'Request' ? $limit : 100000, HttpBodyCollector::getMaxBodyLength($options, $type));
            }
        }

        $options = new Options(['data_collection' => ['http_bodies' => ['outgoingResponse']]]);
        $this->assertSame(0, HttpBodyCollector::getMaxBodyLength($options, 'incomingResponse'));
        $this->assertSame(100000, HttpBodyCollector::getMaxBodyLength($options, 'outgoingResponse'));
    }

    public function testContentTypeSupport(): void
    {
        foreach ([
            'application/json' => true,
            ' Application/Problem+JSON ; charset=UTF-8' => true,
            ' APPLICATION/X-WWW-FORM-URLENCODED ; charset=UTF-8' => true,
            '' => false,
            'text/plain' => false,
            'application/jsonp' => false,
            'application/json+unknown' => false,
            'multipart/form-data; boundary=123' => false,
        ] as $contentType => $expected) {
            $this->assertSame($expected, HttpBodyCollector::isSupportedContentType($contentType));
        }
    }

    /**
     * @dataProvider parseProvider
     *
     * @param array<array-key, mixed>|null $expected
     */
    public function testParse(string $body, string $contentType, ?array $expected): void
    {
        $this->assertSame($expected, HttpBodyCollector::parse($body, $contentType));
    }

    public function parseProvider(): \Generator
    {
        yield 'JSON' => ['{"name":"Alice","password":"secret"}', 'application/json', ['name' => 'Alice', 'password' => 'secret']];
        yield 'JSON suffix' => ['{"token":"secret"}', ' Application/Problem+JSON ; charset=UTF-8', ['token' => 'secret']];
        yield 'form' => ['name=Alice&password=secret', 'application/x-www-form-urlencoded; charset=UTF-8', ['name' => 'Alice', 'password' => 'secret']];
        yield 'uppercase form' => ['password=secret', ' APPLICATION/X-WWW-FORM-URLENCODED ; charset=UTF-8', ['password' => 'secret']];
        yield 'repeated form keys' => ['name=Alice&name=Bob', 'application/x-www-form-urlencoded', ['name' => ['Alice', 'Bob']]];
        yield 'JSON list' => ['["secret",{"name":"Alice"}]', 'application/json', ['secret', ['name' => 'Alice']]];
        yield 'invalid JSON' => ['{invalid', 'application/json', null];
        yield 'scalar JSON' => ['42', 'application/json', null];
        yield 'boolean JSON' => ['false', 'application/json', null];
        yield 'string JSON' => ['"secret"', 'application/json', null];
        yield 'null JSON' => ['null', 'application/json', null];
        yield 'unsupported type' => ['raw secret', 'text/plain', null];
        yield 'multipart' => ['raw secret', 'multipart/form-data; boundary=123', null];
        yield 'invalid UTF-8' => ["{\"value\":\"\xff\"}", 'application/json', null];
        yield 'empty string' => ['', 'application/json', null];
        yield 'empty JSON object' => ['{}', 'application/json', []];
        yield 'empty JSON list' => ['[]', 'application/json', []];
    }

    /**
     * @dataProvider bodyProvider
     *
     * @param array<array-key, mixed> $body
     * @param array<array-key, mixed> $expected
     */
    public function testCollect(array $body, array $expected): void
    {
        $this->assertSame($expected, HttpBodyCollector::collect($body));
    }

    public function bodyProvider(): \Generator
    {
        yield 'parsed data' => [['profile' => ['name' => 'Alice', 'password' => 'secret']], ['profile' => ['name' => 'Alice', 'password' => '[Filtered]']]];
        yield 'nested object' => [['profile' => (object) ['password' => 'secret']], ['profile' => '[Filtered]']];
        yield 'unkeyed data' => [['secret', ['name' => 'Alice']], ['secret', ['name' => 'Alice']]];
        yield 'scalar list' => [['secret', 'foo', false, null], ['secret', 'foo', false, null]];
        yield 'sensitive parent' => [['token' => ['foo']], ['token' => '[Filtered]']];
        yield 'empty array' => [[], []];
    }

    public function testParsedBodiesAreCollected(): void
    {
        $body = HttpBodyCollector::parse('["secret",{"password":"value","name":"Alice"}]', 'application/json');
        $this->assertNotNull($body);
        $this->assertSame(['secret', ['password' => '[Filtered]', 'name' => 'Alice']], HttpBodyCollector::collect($body));
    }

    public function testNestedObjectsAreFilteredWithoutCallbacksOrInputChanges(): void
    {
        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                throw new \LogicException('Must not serialize application objects');
            }

            public function __toString(): string
            {
                throw new \LogicException('Must not stringify application objects');
            }
        };
        $plain = (object) ['password' => 'secret'];
        $body = ['object' => $object, 'profile' => $plain];
        $this->assertSame(['object' => '[Filtered]', 'profile' => '[Filtered]'], HttpBodyCollector::collect($body));
        $this->assertSame(['object' => $object, 'profile' => $plain], $body);
        $this->assertSame('secret', $plain->password);
        $subclass = new class extends \stdClass implements \IteratorAggregate {
            public function getIterator(): \Traversable
            {
                throw new \LogicException('Must not iterate application objects');
            }
        };
        $this->assertSame(['object' => '[Filtered]'], HttpBodyCollector::collect(['object' => $subclass]));
    }

    public function testResourcesAreNotReadOrClosed(): void
    {
        $resource = fopen('php://temp', 'r+');
        $this->assertIsResource($resource);
        try {
            fwrite($resource, 'prefix:secret');
            fseek($resource, 7);
            $this->assertSame(['file' => '[Filtered]'], HttpBodyCollector::collect(['file' => $resource]));
            $this->assertSame(7, ftell($resource));
            $this->assertSame('secret', stream_get_contents($resource));
        } finally {
            fclose($resource);
        }
        $this->assertSame(['file' => '[Filtered]'], HttpBodyCollector::collect(['file' => $resource]));
    }

    public function testNormalizationDepthIsBounded(): void
    {
        $body = ['value' => null];
        for ($i = 0; $i < 31; ++$i) {
            $body = ['child' => $body];
        }

        $this->assertSame($body, HttpBodyCollector::collect($body));
        $this->assertNull(HttpBodyCollector::collect(['child' => $body]));

        $parsed = HttpBodyCollector::parse(JSON::encode($body), 'application/json');
        $this->assertNotNull($parsed);
        $this->assertSame($body, HttpBodyCollector::collect($parsed));
        $parsed = HttpBodyCollector::parse(JSON::encode(['child' => $body]), 'application/json');
        $this->assertNotNull($parsed);
        $this->assertNull(HttpBodyCollector::collect($parsed));
    }

    public function testNormalizationPreservesReferencedInput(): void
    {
        $object = (object) ['value' => 'original'];
        $nested = ['value' => &$object];
        $body = ['nested' => &$nested];
        $this->assertSame(['nested' => ['value' => '[Filtered]']], HttpBodyCollector::collect($body));
        $this->assertSame($object, $nested['value']);
        $this->assertSame($object, $body['nested']['value']);
        $this->assertSame('original', $object->value);
    }

    public function testRecursiveArraysAreOmitted(): void
    {
        $array = [];
        $array['self'] = &$array;
        $this->assertNull(HttpBodyCollector::collect($array));
    }
}
