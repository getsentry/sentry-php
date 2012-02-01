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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testParseDsnInvalidScheme()
    {
        $result = Raven_Client::parseDsn('gopher://public:secret@/1');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testParseDsnMissingNetloc()
    {
        $result = Raven_Client::parseDsn('http://public:secret@/1');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testParseDsnMissingProject()
    {
        $result = Raven_Client::parseDsn('http://public:secret@example.com');
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
}