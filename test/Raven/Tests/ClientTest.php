<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// XXX: Is there a better way to stub the client?
class Dummy_Raven_Client extends Raven_Client
{
    private $__sent_events = array();

    public function getSentEvents()
    {
        return $this->__sent_events;
    }
    public function send($data)
    {
        $this->__sent_events[] = $data;
    }
}

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

    public function testCaptureMessageDoesHandleUninterpolatedMessage()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s');
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['message'], 'Test Message %s');
    }

    public function testCaptureMessageDoesHandleInterpolatedMessage()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', array('foo'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['message'], 'Test Message foo');
    }

    public function testCaptureMessageSetsInterface()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', array('foo'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['sentry.interfaces.Message'], array(
            'message' => 'Test Message %s',
            'params' => array('foo'),
        ));
    }

    public function testCaptureExceptionSetsInterfaces()
    {
        # TODO: it'd be nice if we could mock the stacktrace extraction function here
        $client = new Dummy_Raven_Client();
        try {
            throw new Exception('Foo bar');
        } catch (Exception $ex) {
            $client->captureException($ex);
        }

        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);

        $exc = $event['sentry.interfaces.Exception'];
        $this->assertEquals($exc['value'], 'Foo bar');
        $this->assertEquals($exc['type'], 'Exception');
        $this->assertFalse(empty($exc['module']));

        $this->assertFalse(empty($event['sentry.interfaces.Stacktrace']['frames']));
        $frames = $event['sentry.interfaces.Stacktrace']['frames'];
        $frame = $frames[count($frames) - 1];
        $this->assertEquals($frame['lineno'], 199);
        $this->assertEquals($frame['module'], 'ClientTest.php:Raven_Tests_ClientTest');
        $this->assertEquals($frame['function'], 'testCaptureExceptionSetsInterfaces');
        $this->assertEquals($frame['vars'], array());
        $this->assertEquals($frame['context_line'], '            throw new Exception(\'Foo bar\');');
        $this->assertFalse(empty($frame['pre_context']));
        $this->assertFalse(empty($frame['post_context']));
    }

    public function testDoesRegisterProcessors()
    {
        $client = new Dummy_Raven_Client(array(
            'processors' => array('Raven_Processor'),
        ));
        $this->assertEquals(count($client->processors), 1);
        $this->assertTrue($client->processors[0] instanceof Raven_Processor);
    }

    public function testProcessDoesCallProcessors()
    {
        $data = array("key"=>"value");

        $processor = $this->getMock('Processor', array('process'));
        $processor->expects($this->once())
               ->method('process')
               ->with($data);

        $client = new Dummy_Raven_Client();
        $client->processors[] = $processor;
        $client->process($data);
    }

    public function testDefaultProcessorsAreUsed()
    {
        $client = new Dummy_Raven_Client();
        $this->assertEquals(count($client->processors), count($client->getDefaultProcessors()));
    }

    public function testDefaultProcessorsContainSanitizeDataProcessor()
    {
        $client = new Dummy_Raven_Client();
        $defaults = $client->getDefaultProcessors();
        $this->assertTrue(in_array('Raven_SanitizeDataProcessor', $defaults));
    }
}
