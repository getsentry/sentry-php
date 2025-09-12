<?php

declare(strict_types=1);

namespace Sentry\Tests\Attributes;

use PHPUnit\Framework\TestCase;
use Sentry\Attributes\Attribute;
use Sentry\Attributes\AttributeBag;

final class AttributeBagTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $bag = new AttributeBag();

        $this->assertCount(0, $bag->all());

        $bag->set('foo', 'bar');

        $this->assertCount(1, $bag->all());
        $this->assertInstanceOf(Attribute::class, $bag->get('foo'));

        $bag->set('will-be-removed', 'baz');

        $this->assertNotNull($bag->get('will-be-removed'));

        $bag->forget('will-be-removed');

        $this->assertNull($bag->get('will-be-removed'));
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
