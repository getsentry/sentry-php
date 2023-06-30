<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Frame;

final class FrameTest extends TestCase
{
    public function testConstructor(): void
    {
        $frame = new Frame('foo', 'bar', 10);

        $this->assertEquals('foo', $frame->getFunctionName());
        $this->assertEquals('bar', $frame->getFile());
        $this->assertEquals(10, $frame->getLine());
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters(string $getterMethod, string $setterMethod, $expectedData): void
    {
        $frame = new Frame('foo', 'bar', 10);
        $frame->$setterMethod($expectedData);

        $this->assertEquals($expectedData, $frame->$getterMethod());
    }

    public static function gettersAndSettersDataProvider(): array
    {
        return [
            ['getPreContext', 'setPreContext', ['foo' => 'bar', 'bar' => 'baz']],
            ['getContextLine', 'setContextLine', 'foo bar'],
            ['getPostContext', 'setPostContext', ['bar' => 'foo', 'baz' => 'bar']],
            ['isInApp', 'setIsInApp', true],
            ['getVars', 'setVars', ['foo' => 'bar']],
        ];
    }
}
