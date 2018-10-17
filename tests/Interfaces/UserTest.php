<?php

namespace Sentry\Tests\Interfaces;

use PHPUnit\Framework\TestCase;
use Sentry\Interfaces\User;

class UserTest extends TestCase
{
    public function testJsonSerialize()
    {
        $this->assertEquals(['id' => '1'], (new User('1'))->jsonSerialize());
        $this->assertEquals(['username' => 'username'], (new User(null, 'username'))->jsonSerialize());
        $this->assertEquals(['email' => 'email'], (new User(null, null, 'email'))->jsonSerialize());
        $this->assertEquals(['data' => ['a' => 'b']], (new User(null, null, null, ['a' => 'b']))->jsonSerialize());
    }
}
