<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_CompatTest extends \PHPUnit\Framework\TestCase
{
    public function test_gethostname()
    {
        $this->assertEquals(Raven_Compat::gethostname(), Raven_Compat::_gethostname());
        $this->assertTrue(strlen(Raven_Compat::_gethostname()) > 0);
    }

    public function test_hash_hmac()
    {
        $result = Raven_Compat::hash_hmac('sha1', 'foo', 'bar');
        $this->assertEquals('85d155c55ed286a300bd1cf124de08d87e914f3a', $result);

        $result = Raven_Compat::_hash_hmac('sha1', 'foo', 'bar');
        $this->assertEquals('85d155c55ed286a300bd1cf124de08d87e914f3a', $result);

        $long_key = '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF';
        $result = Raven_Compat::_hash_hmac('md5', 'data', $long_key);
        $this->assertEquals('951038f9ab8a10c929ab6dbc5f927207', $result);

        $result = Raven_Compat::_hash_hmac('sha1', 'data', $long_key);
        $this->assertEquals('cbf0d1ca10d211da2bc15cb3b579ecfebf3056d2', $result);

        $result = Raven_Compat::_hash_hmac('md5', 'foobar', $long_key);
        $this->assertEquals('5490f3cddeb9665bce3239cbc4c15e2c', $result);

        $result = Raven_Compat::_hash_hmac('sha1', 'foobar', $long_key);
        $this->assertEquals('5729f50ff2fbb8f8bf81d7a86f69a89f7574697c', $result);


        $result = Raven_Compat::_hash_hmac('md5', 'foo', $long_key);
        $this->assertEquals('ab193328035cbd3a48dea9d64ba92736', $result);

        $result = Raven_Compat::_hash_hmac('sha1', 'foo', $long_key);
        $this->assertEquals('8f883d0755115314930968496573f27735eb0c41', $result);
    }

    public function test_json_encode()
    {
        $result = Raven_Compat::json_encode(array('foo' => array('bar' => 1)));
        $this->assertEquals('{"foo":{"bar":1}}', $result);

        $result = Raven_Compat::_json_encode(array('foo' => array('bar' => 1)));
        $this->assertEquals('{"foo":{"bar":1}}', $result);

        $result = Raven_Compat::_json_encode(array(1, 2, 3, 4, 'foo', 'bar'));
        $this->assertEquals('[1,2,3,4,"foo","bar"]', $result);

        $result = Raven_Compat::_json_encode(array(1, 'foo', 'foobar' => 'bar'));
        $this->assertEquals('{0:1,1:"foo","foobar":"bar"}', $result);

        $result = Raven_Compat::_json_encode(array(array()));
        $this->assertEquals('[[]]', $result);

        $result = Raven_Compat::_json_encode(array(null, false, true, 1.5));
        $this->assertEquals('[null,false,true,1.5]', $result);
    }

    /**
     * @covers Raven_Compat::_json_encode
     * @covers Raven_Compat::_json_encode_lowlevel
     *
     * I show you how deep the rabbit hole goes
     */
    public function test_json_encode_with_broken_data()
    {
        $data_broken_named = array();
        $data_broken_named_510 = null;
        $data_broken_named_511 = null;

        $data_broken = array();
        $data_broken_510 = null;
        $data_broken_511 = null;
        for ($i = 0; $i < 1024; $i++) {
            $data_broken = array($data_broken);
            $data_broken_named = array('a' => $data_broken_named);
            switch ($i) {
                case 510:
                    $data_broken_510 = $data_broken;
                    $data_broken_named_510 = $data_broken_named;
                    break;
                case 511:
                    $data_broken_511 = $data_broken;
                    $data_broken_named_511 = $data_broken_named;
                    break;
            }
        }
        $value_1024 = Raven_Compat::_json_encode($data_broken);
        $value_510 = Raven_Compat::_json_encode($data_broken_510);
        $value_511 = Raven_Compat::_json_encode($data_broken_511);
        $this->assertFalse($value_1024, 'Broken data encoded successfully with Raven_Compat::_json_encode');
        $this->assertNotFalse($value_510);
        $this->assertFalse($value_511);

        $value_1024 = Raven_Compat::_json_encode($data_broken_named);
        $value_510 = Raven_Compat::_json_encode($data_broken_named_510);
        $value_511 = Raven_Compat::_json_encode($data_broken_named_511);
        $this->assertFalse($value_1024, 'Broken data encoded successfully with Raven_Compat::_json_encode');
        $this->assertNotFalse($value_510);
        $this->assertFalse($value_511);
    }
}
