<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_ClientTest extends PHPUnit_Framework_TestCase
{
    public function testParseDsnHttp()
    {
        $result = Raven_Client::parseDsn('http://public:secret@example.com/1');

        $this->assertEquals($result['project'], 1);
        $this->assertEquals($result['servers'], array('http://example.com/api/store/'));
        $this->assertEquals($result['public_key'], 'public');
        $this->assertEquals($result['secret_key'], 'secret');
    }

    public function testParseDsnHttps()
    {
        $result = Raven_Client::parseDsn('https://public:secret@example.com/1');

        $this->assertEquals($result['project'], 1);
        $this->assertEquals($result['servers'], array('https://example.com/api/store/'));
        $this->assertEquals($result['public_key'], 'public');
        $this->assertEquals($result['secret_key'], 'secret');
    }

    public function testParseDsnPath()
    {
        $result = Raven_Client::parseDsn('http://public:secret@example.com/app/1');

        $this->assertEquals($result['project'], 1);
        $this->assertEquals($result['servers'], array('http://example.com/app/api/store/'));
        $this->assertEquals($result['public_key'], 'public');
        $this->assertEquals($result['secret_key'], 'secret');
    }

    public function testParseDsnPort()
    {
        $result = Raven_Client::parseDsn('http://public:secret@example.com:9000/app/1');

        $this->assertEquals($result['project'], 1);
        $this->assertEquals($result['servers'], array('http://example.com:9000/app/api/store/'));
        $this->assertEquals($result['public_key'], 'public');
        $this->assertEquals($result['secret_key'], 'secret');
    }

    public function testParseDsnInvalidScheme()
    {
        try {
            $result = Raven_Client::parseDsn('gopher://public:secret@/1');
            $this->fail();
        } catch (Exception $e) {
            return;
        }
    }

    public function testParseDsnMissingNetloc()
    {
        try {
            $result = Raven_Client::parseDsn('http://public:secret@/1');
            $this->fail();
        } catch (Exception $e) {
            return;
        }
    }

    public function testParseDsnMissingProject()
    {
        try {
            $result = Raven_Client::parseDsn('http://public:secret@example.com');
            $this->fail();
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testParseDsnMissingPublicKey()
    {
        $result = Raven_Client::parseDsn('http://:secret@example.com/1');
    }
    /**
     * @expectedException InvalidArgumentException
     */
    public function testParseDsnMissingSecretKey()
    {
        $result = Raven_Client::parseDsn('http://public@example.com/1');
    }

    public function testDsnFirstArgument()
    {
        $client = new Raven_Client('http://public:secret@example.com/1');

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->servers, array('http://example.com/api/store/'));
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
    }

    public function testDsnFirstArgumentWithOptions()
    {
        $client = new Raven_Client('http://public:secret@example.com/1', array(
            'site' => 'foo',
        ));

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->servers, array('http://example.com/api/store/'));
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
        $this->assertEquals($client->site, 'foo');
    }

    public function testOptionsFirstArgument()
    {
        $client = new Raven_Client(array(
            'servers' => array('http://example.com/api/store/'),
        ));

        $this->assertEquals($client->servers, array('http://example.com/api/store/'));
    }

    public function testOptionsFirstArgumentWithOptions()
    {
        $client = new Raven_Client(array(
            'servers' => array('http://example.com/api/store/'),
        ), array(
            'site' => 'foo',
        ));

        $this->assertEquals($client->servers, array('http://example.com/api/store/'));
        $this->assertEquals($client->site, 'foo');
    }
}
