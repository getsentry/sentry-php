<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function simple_function($a=null, $b=null, $c=null)
{
    assert(0);
}

function invalid_encoding()
{
    $fp = fopen(__DIR__ . '/../../data/binary', 'r');
    simple_function(fread($fp, 64));
    fclose($fp);
}


// XXX: Is there a better way to stub the client?
class Dummy_Raven_Client extends Raven_Client
{
    private $__sent_events = array();

    public function getSentEvents()
    {
        return $this->__sent_events;
    }
    public function send(&$data)
    {
        if (is_callable($this->send_callback) && call_user_func_array($this->send_callback, array(&$data)) === false) {
            // if send_callback returns falsely, end native send
            return;
        }
        $this->__sent_events[] = $data;
    }
    public function is_http_request()
    {
        return true;
    }
    public function get_auth_header($timestamp, $client, $api_key, $secret_key)
    {
        return parent::get_auth_header($timestamp, $client, $api_key, $secret_key);
    }
    public function get_http_data()
    {
        return parent::get_http_data();
    }
    public function get_user_data()
    {
        return parent::get_user_data();
    }

    /**
     * Expose the current url method to test it
     *
     * @return string
     */
    public function test_get_current_url()
    {
        return $this->get_current_url();
    }
}

class Raven_Tests_ClientTest extends PHPUnit_Framework_TestCase
{
    private function create_exception()
    {
        try {
            throw new Exception('Foo bar');
        } catch (Exception $ex) {
            return $ex;
        }
    }

    private function create_chained_exception()
    {
        try {
            throw new Exception('Foo bar');
        } catch (Exception $ex) {
            try {
                throw new Exception('Child exc', 0, $ex);
            } catch (Exception $ex2) {
                return $ex2;
            }
        }
    }

    public function testParseDSNHttp()
    {
        $result = Raven_Client::ParseDSN('http://public:secret@example.com/1');

        $this->assertEquals($result['project'], 1);
        $this->assertEquals($result['server'], 'http://example.com/api/1/store/');
        $this->assertEquals($result['public_key'], 'public');
        $this->assertEquals($result['secret_key'], 'secret');
    }

    public function testParseDSNHttps()
    {
        $result = Raven_Client::ParseDSN('https://public:secret@example.com/1');

        $this->assertEquals($result['project'], 1);
        $this->assertEquals($result['server'], 'https://example.com/api/1/store/');
        $this->assertEquals($result['public_key'], 'public');
        $this->assertEquals($result['secret_key'], 'secret');
    }

    public function testParseDSNPath()
    {
        $result = Raven_Client::ParseDSN('http://public:secret@example.com/app/1');

        $this->assertEquals($result['project'], 1);
        $this->assertEquals($result['server'], 'http://example.com/app/api/1/store/');
        $this->assertEquals($result['public_key'], 'public');
        $this->assertEquals($result['secret_key'], 'secret');
    }

    public function testParseDSNPort()
    {
        $result = Raven_Client::ParseDSN('http://public:secret@example.com:9000/app/1');

        $this->assertEquals($result['project'], 1);
        $this->assertEquals($result['server'], 'http://example.com:9000/app/api/1/store/');
        $this->assertEquals($result['public_key'], 'public');
        $this->assertEquals($result['secret_key'], 'secret');
    }

    public function testParseDSNInvalidScheme()
    {
        try {
            Raven_Client::ParseDSN('gopher://public:secret@/1');
            $this->fail();
        } catch (Exception $e) {
            return;
        }
    }

    public function testParseDSNMissingNetloc()
    {
        try {
            Raven_Client::ParseDSN('http://public:secret@/1');
            $this->fail();
        } catch (Exception $e) {
            return;
        }
    }

    public function testParseDSNMissingProject()
    {
        try {
            Raven_Client::ParseDSN('http://public:secret@example.com');
            $this->fail();
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testParseDSNMissingPublicKey()
    {
        Raven_Client::ParseDSN('http://:secret@example.com/1');
    }
    /**
     * @expectedException InvalidArgumentException
     */
    public function testParseDSNMissingSecretKey()
    {
        Raven_Client::ParseDSN('http://public@example.com/1');
    }

    public function testDsnFirstArgument()
    {
        $client = new Raven_Client('http://public:secret@example.com/1');

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
    }

    public function testDsnFirstArgumentWithOptions()
    {
        $client = new Raven_Client('http://public:secret@example.com/1', array(
            'site' => 'foo',
        ));

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
        $this->assertEquals($client->site, 'foo');
    }

    public function testOptionsFirstArgument()
    {
        $client = new Raven_Client(array(
            'server' => 'http://example.com/api/1/store/',
            'project' => 1,
        ));

        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
    }


    public function testDsnInOptionsFirstArg()
    {
        $client = new Raven_Client(array(
            'dsn' => 'http://public:secret@example.com/1',
        ));

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
    }

    public function testDsnInOptionsSecondArg()
    {
        $client = new Raven_Client(null, array(
            'dsn' => 'http://public:secret@example.com/1',
        ));

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
    }

    public function testOptionsFirstArgumentWithOptions()
    {
        $client = new Raven_Client(array(
            'server' => 'http://example.com/api/1/store/',
            'project' => 1,
        ), array(
            'site' => 'foo',
        ));

        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->site, 'foo');
    }

    public function testOptionsExtraData()
    {
        $client = new Dummy_Raven_Client(array('extra' => array('foo' => 'bar')));

        $client->captureMessage('Test Message %s', array('foo'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['extra']['foo'], 'bar');
    }

    public function testEmptyExtraData()
    {
        $client = new Dummy_Raven_Client(array('extra' => array()));

        $client->captureMessage('Test Message %s', array('foo'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals(array_key_exists('extra', $event), false);
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
            'formatted' => 'Test Message foo',
        ));
    }

    public function testCaptureMessageHandlesOptionsAsThirdArg()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', array('foo'), array(
            'level' => Dummy_Raven_Client::WARNING,
            'extra' => array('foo' => 'bar')
        ));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['level'], Dummy_Raven_Client::WARNING);
        $this->assertEquals($event['extra']['foo'], 'bar');
    }

    public function testCaptureMessageHandlesLevelAsThirdArg()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', array('foo'), Dummy_Raven_Client::WARNING);
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['level'], Dummy_Raven_Client::WARNING);
    }

    public function testCaptureExceptionSetsInterfaces()
    {
        # TODO: it'd be nice if we could mock the stacktrace extraction function here
        $client = new Dummy_Raven_Client();
        $ex = $this->create_exception();
        $client->captureException($ex);

        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);

        $exc = $event['exception'];
        $this->assertEquals(count($exc['values']), 1);
        $this->assertEquals($exc['values'][0]['value'], 'Foo bar');
        $this->assertEquals($exc['values'][0]['type'], 'Exception');
        $this->assertFalse(empty($exc['values'][0]['module']));

        $this->assertFalse(empty($exc['values'][0]['stacktrace']['frames']));
        $frames = $exc['values'][0]['stacktrace']['frames'];
        $frame = $frames[count($frames) - 1];
        $this->assertTrue($frame['lineno'] > 0);
        $this->assertEquals($frame['module'], 'ClientTest.php:Raven_Tests_ClientTest');
        $this->assertEquals($frame['function'], 'create_exception');
        $this->assertFalse(isset($frame['vars']));
        $this->assertEquals($frame['context_line'], '            throw new Exception(\'Foo bar\');');
        $this->assertFalse(empty($frame['pre_context']));
        $this->assertFalse(empty($frame['post_context']));
    }

    public function testCaptureExceptionChainedException()
    {
        if (version_compare(PHP_VERSION, '5.3.0', '<')) {
            $this->markTestSkipped('PHP 5.3 required for chained exceptions.');
        }

        # TODO: it'd be nice if we could mock the stacktrace extraction function here
        $client = new Dummy_Raven_Client();
        $ex = $this->create_chained_exception();
        $client->captureException($ex);

        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);

        $exc = $event['exception'];
        $this->assertEquals(count($exc['values']), 2);
        $this->assertEquals($exc['values'][0]['value'], 'Foo bar');
        $this->assertEquals($exc['values'][1]['value'], 'Child exc');
    }

    public function testCaptureExceptionDifferentLevelsInChainedExceptionsBug()
    {
        if (version_compare(PHP_VERSION, '5.3.0', '<')) {
            $this->markTestSkipped('PHP 5.3 required for chained exceptions.');
        }

        $client = new Dummy_Raven_Client();
        $e1 = new ErrorException('First', 0, E_DEPRECATED);
        $e2 = new ErrorException('Second', 0, E_NOTICE, __FILE__, __LINE__, $e1);
        $e3 = new ErrorException('Third', 0, E_ERROR, __FILE__, __LINE__, $e2);

        $client->captureException($e1);
        $client->captureException($e2);
        $client->captureException($e3);
        $events = $client->getSentEvents();

        $event = array_pop($events);
        $this->assertEquals($event['level'], Dummy_Raven_Client::ERROR);

        $event = array_pop($events);
        $this->assertEquals($event['level'], Dummy_Raven_Client::INFO);

        $event = array_pop($events);
        $this->assertEquals($event['level'], Dummy_Raven_Client::WARNING);
    }

    public function testCaptureExceptionHandlesOptionsAsSecondArg()
    {
        $client = new Dummy_Raven_Client();
        $ex = $this->create_exception();
        $client->captureException($ex, array('culprit' => 'test'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['culprit'], 'test');
    }

    public function testCaptureExceptionHandlesCulpritAsSecondArg()
    {
        $client = new Dummy_Raven_Client();
        $ex = $this->create_exception();
        $client->captureException($ex, 'test');
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['culprit'], 'test');
    }

    public function testCaptureExceptionHandlesExcludeOption()
    {
        $client = new Dummy_Raven_Client(array(
            'exclude' => array('Exception'),
        ));
        $ex = $this->create_exception();
        $client->captureException($ex, 'test');
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 0);
    }

    public function testCaptureExceptionInvalidUTF8()
    {
        $client = new Dummy_Raven_Client();
        try {
            invalid_encoding();
        } catch (Exception $ex) {
            $client->captureException($ex);
        }
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);

        // if this fails to encode it returns false
        $message = $client->encode($events[0]);
        $this->assertNotEquals($message, false, $client->getLastError());
    }

    public function testDoesRegisterProcessors()
    {
        $client = new Dummy_Raven_Client(array(
            'processors' => array('Raven_SanitizeDataProcessor'),
        ));
        $this->assertEquals(count($client->processors), 1);
        $this->assertTrue($client->processors[0] instanceof Raven_SanitizeDataProcessor);
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
        $defaults = Dummy_Raven_Client::getDefaultProcessors();

        $this->assertEquals(count($client->processors), count($defaults));
    }

    public function testDefaultProcessorsContainSanitizeDataProcessor()
    {
        $defaults = Dummy_Raven_Client::getDefaultProcessors();

        $this->assertTrue(in_array('Raven_SanitizeDataProcessor', $defaults));
    }

    public function testGetDefaultData()
    {
        $client = new Dummy_Raven_Client();
        $expected = array(
            'platform' => 'php',
            'project' => $client->project,
            'server_name' => $client->name,
            'site' => $client->site,
            'logger' => $client->logger,
            'tags' => $client->tags,
            'sdk' => array(
                'name' => 'sentry-php',
                'version' => $client::VERSION,
            ),
        );
        $this->assertEquals($expected, $client->get_default_data());
    }

    /**
     * @backupGlobals
     */
    public function testGetHttpData()
    {
        $_SERVER = array(
            'REDIRECT_STATUS'     => '200',
            'CONTENT_TYPE'        => 'text/xml',
            'CONTENT_LENGTH'      => '99',
            'HTTP_HOST'           => 'getsentry.com',
            'HTTP_ACCEPT'         => 'text/html',
            'HTTP_ACCEPT_CHARSET' => 'utf-8',
            'HTTP_COOKIE'         => 'cupcake: strawberry',
            'SERVER_PORT'         => '443',
            'SERVER_PROTOCOL'     => 'HTTP/1.1',
            'REQUEST_METHOD'      => 'PATCH',
            'QUERY_STRING'        => 'q=bitch&l=en',
            'REQUEST_URI'         => '/welcome/',
            'SCRIPT_NAME'         => '/index.php',
        );
        $_POST = array(
            'stamp' => '1c',
        );
        $_COOKIE = array(
            'donut' => 'chocolat',
        );

        $expected = array(
            'request' => array(
                'method' => 'PATCH',
                'url' => 'https://getsentry.com/welcome/',
                'query_string' => 'q=bitch&l=en',
                'data' => array(
                    'stamp'           => '1c',
                ),
                'cookies' => array(
                    'donut'           => 'chocolat',
                ),
                'headers' => array(
                    'Host'            => 'getsentry.com',
                    'Accept'          => 'text/html',
                    'Accept-Charset'  => 'utf-8',
                    'Cookie'          => 'cupcake: strawberry',
                    'Content-Type'    => 'text/xml',
                    'Content-Length'  => '99',
                ),
            )
        );

        $client = new Dummy_Raven_Client();
        $this->assertEquals($expected, $client->get_http_data());
    }

    public function testGetUserDataWithSetUser()
    {
        $client = new Dummy_Raven_Client();

        $id = 'unique_id';
        $email = 'foo@example.com';

        $user = array(
            'username' => 'my_user',
        );

        $client->set_user_data($id, $email, $user);

        $expected = array(
            'user' => array(
                'id' => 'unique_id',
                'username' => 'my_user',
                'email' => 'foo@example.com',
            )
        );

        $this->assertEquals($expected, $client->get_user_data());
    }

    public function testGetUserDataWithNoUser()
    {
        $client = new Dummy_Raven_Client();

        $expected = array(
            'user' => array(
                'id' => session_id(),
            )
        );
        $this->assertEquals($expected, $client->get_user_data());
    }

    public function testGetAuthHeader()
    {
        $client = new Dummy_Raven_Client();

        $clientstring = 'sentry-php/test';
        $timestamp = '1234341324.340000';

        $expected = "Sentry sentry_timestamp={$timestamp}, sentry_client={$clientstring}, " .
                    "sentry_version=" . Dummy_Raven_Client::PROTOCOL . ", " .
                    "sentry_key=publickey, sentry_secret=secretkey";

        $this->assertEquals($expected, $client->get_auth_header($timestamp, 'sentry-php/test', 'publickey', 'secretkey'));
    }

    public function testCaptureMessageWithUserContext()
    {
        $client = new Dummy_Raven_Client();

        $client->user_context(array('email' => 'foo@example.com'));

        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals(array(
            'email' => 'foo@example.com',
        ), $event['user']);
    }

    public function testCaptureMessageWithUnserializableUserData()
    {
        $client = new Dummy_Raven_Client();

        $client->user_context(array(
            'email' => 'foo@example.com',
            'data' => array(
                'error' => new Exception('test'),
            )
        ));

        $client->captureMessage('test');
        $events = $client->getSentEvents();
        // we're just asserting that this goes off without a hitch
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
    }

    public function testCaptureMessageWithTagsContext()
    {
        $client = new Dummy_Raven_Client();

        $client->tags_context(array('foo' => 'bar'));
        $client->tags_context(array('biz' => 'boz'));
        $client->tags_context(array('biz' => 'baz'));

        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals(array(
            'foo' => 'bar',
            'biz' => 'baz',
        ), $event['tags']);
    }

    public function testCaptureMessageWithExtraContext()
    {
        $client = new Dummy_Raven_Client();

        $client->extra_context(array('foo' => 'bar'));
        $client->extra_context(array('biz' => 'boz'));
        $client->extra_context(array('biz' => 'baz'));

        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals(array(
            'foo' => 'bar',
            'biz' => 'baz',
        ), $event['extra']);
    }

    public function testGetLastEventID()
    {
        $client = new Dummy_Raven_Client();
        $client->capture(array('message' => 'test', 'event_id' => 'abc'));
        $this->assertEquals($client->getLastEventID(), 'abc');
    }

    public function testCustomTransport()
    {
        $events = array();

        $client = new Raven_Client('https://public:secret@sentry.example.com/1');
        $client->setTransport(function ($client, $data) use (&$events) {
            $events[] = $data;
        });
        $client->capture(array('message' => 'test', 'event_id' => 'abc'));
        $this->assertEquals(count($events), 1);
    }

    public function cb1($data)
    {
        $this->assertEquals('test', $data['message']);
        return false;
    }

    public function cb2($data)
    {
        $this->assertEquals('test', $data['message']);
        return true;
    }

    public function cb3(&$data)
    {
        unset($data['message']);
        return true;
    }

    public function testSendCallback()
    {
        $client = new Dummy_Raven_Client(array('send_callback' => array($this, 'cb1')));
        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(0, count($events));

        $client = new Dummy_Raven_Client(array('send_callback' => array($this, 'cb2')));
        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));

        $client = new Dummy_Raven_Client(array('send_callback' => array($this, 'cb3')));
        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $this->assertEquals(empty($events[0]['message']), true);
    }

    /**
     * Set the server array to the test values, check the current url
     *
     * @dataProvider currentUrlProvider
     * @param array $serverData
     * @param array $options
     * @param string $expected - the url expected
     * @param string $message - fail message
     */
    public function testCurrentUrl($serverVars, $options, $expected, $message)
    {
        $_SERVER = $serverVars;

        $client = new Dummy_Raven_Client($options);
        $result = $client->test_get_current_url();

        $this->assertSame($expected, $result, $message);
    }

    /**
     * Arrays of:
     *  $_SERVER data
     *  config
     *  expected url
     *  Fail message
     *
     * @return array
     */
    public function currentUrlProvider()
    {
        return array(
            array(
                array(),
                array(),
                null,
                'No url expected for empty REQUEST_URI'
            ),
            array(
                array(
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                ),
                array(),
                'http://example.com/',
                'The url is expected to be http with the request uri'
            ),
            array(
                array(
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'HTTPS' => 'on'
                ),
                array(),
                'https://example.com/',
                'The url is expected to be https because of HTTPS on'
            ),
            array(
                array(
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'SERVER_PORT' => '443'
                ),
                array(),
                'https://example.com/',
                'The url is expected to be https because of the server port'
            ),
            array(
                array(
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'X-FORWARDED-PROTO' => 'https'
                ),
                array(),
                'http://example.com/',
                'The url is expected to be http because the X-Forwarded header is ignored'
            ),
            array(
                array(
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'X-FORWARDED-PROTO' => 'https'
                ),
                array('trust_x_forwarded_proto' => true),
                'https://example.com/',
                'The url is expected to be https because the X-Forwarded header is trusted'
            )
        );
    }
}
