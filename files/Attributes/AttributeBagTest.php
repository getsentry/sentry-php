<?php

declare(strict_types=1);

namespace Sentry\Tests\Attributes;

use PHPUnit\Framework\TestCase;
use Sentry\Attributes\Attribute;
use Sentry\Attributes\AttributeBag;

/**
 * @phpstan-import-type AttributeValue from Attribute
 * @phpstan-import-type AttributeSerialized from Attribute
 */
final class AttributeBagTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $bag = new AttributeBag();

        $this->assertCount(0, $bag->all());

        $bag->set('foo', 'bar');

        $this->assertCount(1, $bag->all());
        $this->assertInstanceOf(Attribute::class, $bag->get('foo'));

        $this->assertNull($bag->get('non-existing'));
    }

    public function testSerializeAsJson(): void
    {
        $bag = new AttributeBag();
        $bag->set('foo', 'bar');

        $this->assertEquals(
            ['foo' => ['type' => 'string', 'value' => 'bar']],
            $bag->jsonSerialize()
        );

        $this->assertEquals(
            '{"foo":{"type":"string","value":"bar"}}',
            json_encode($bag)
        );
    }

    public function testSerializeAsArray(): void
    {
        $bag = new AttributeBag();
        $bag->set('foo', 'bar');

        $this->assertEquals(
            ['foo' => ['type' => 'string', 'value' => 'bar']],
            $bag->toArray()
        );
    }

    public function testSerializeAsSimpleArray(): void
    {
        $bag = new AttributeBag();
        $bag->set('foo', 'bar');

        $this->assertEquals(
            ['foo' => 'bar'],
            $bag->toSimpleArray()
        );
    }
}
