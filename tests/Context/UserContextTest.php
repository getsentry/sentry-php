<?php

namespace Sentry\Tests\Interfaces;

use PHPUnit\Framework\TestCase;
use Sentry\Context\UserContext;

class UserContextTest extends TestCase
{
    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($getterMethod, $setterMethod, $value)
    {
        $context = new UserContext();
        $context->$setterMethod($value);

        $this->assertEquals($value, $context->$getterMethod());
    }

    public function gettersAndSettersDataProvider()
    {
        return [
            [
                'getId',
                'setId',
                'foo',
            ],
            [
                'getUsername',
                'setUsername',
                'bar',
            ],
            [
                'getEmail',
                'setEmail',
                'baz',
            ],
            [
                'getData',
                'setData',
                ['a' => 'b'],
            ],
        ];
    }
}
