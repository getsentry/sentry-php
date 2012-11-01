<?php

use Raven\Compat;

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_CompatTest extends PHPUnit_Framework_TestCase
{
    public function test_gethostname()
    {
        $this->assertEquals(Compat::gethostname(), Compat::_gethostname());
        $this->assertTrue(strlen(Compat::_gethostname()) > 0);
    }

    public function test_hash_hmac()
    {
        $result = Compat::hash_hmac('sha1', 'foo', 'bar');
        $this->assertEquals($result, '85d155c55ed286a300bd1cf124de08d87e914f3a');

        $result = Compat::_hash_hmac('sha1', 'foo', 'bar');
        $this->assertEquals($result, '85d155c55ed286a300bd1cf124de08d87e914f3a');
    }

    public function test_json_encode()
    {
        $result = Compat::json_encode(array('foo' => array('bar' => 1)));
        $this->assertEquals($result, '{"foo":{"bar":1}}');

        $result = Compat::_json_encode(array('foo' => array('bar' => 1)));
        $this->assertEquals($result, '{"foo":{"bar":1}}');
    }
}

