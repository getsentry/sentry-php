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
    public static function is_http_request()
    {
        return true;
    }
    public static function get_auth_header($timestamp, $client, $api_key, $secret_key)
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
    public function buildCurlCommand($url, $data, $headers)
    {
        return parent::buildCurlCommand($url, $data, $headers);
    }
    // short circuit breadcrumbs
    public function registerDefaultBreadcrumbHandlers()
    {
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

class Dummy_Raven_Client_With_Overrided_Direct_Send extends Raven_Client
{
    var $_send_http_asynchronous_curl_exec_called = false;
    var $_send_http_synchronous = false;
    var $_set_url;
    var $_set_data;
    var $_set_headers;

    function send_http_asynchronous_curl_exec($url, $data, $headers)
    {
        $this->_send_http_asynchronous_curl_exec_called = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;
    }

    function send_http_synchronous($url, $data, $headers)
    {
        $this->_send_http_synchronous = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;
    }

    function get_curl_options()
    {
        $options = parent::get_curl_options();

        return $options;
    }

    function get_curl_handler()
    {
        return $this->_curl_handler;
    }

    function set_curl_handler(Raven_CurlHandler $value)
    {
        $this->_curl_handler = $value;
    }
}

class Dummy_Raven_CurlHandler extends Raven_CurlHandler
{
    var $_set_url;
    var $_set_data;
    var $_set_headers;
    var $_enqueue_called = false;
    var $_join_called = false;

    function __construct($options = array(), $join_timeout = 5)
    {
        parent::__construct($options, $join_timeout);
    }

    function enqueue($url, $data = null, $headers = array())
    {
        $this->_enqueue_called = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;

        return 0;
    }

    function join($timeout = null)
    {
        $this->_join_called = true;
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

    /**
     * @covers Raven_Client::__construct
     */
    public function testDsnFirstArgument()
    {
        $client = new Dummy_Raven_Client('http://public:secret@example.com/1');

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
    }

    /**
     * @covers Raven_Client::__construct
     */
    public function testDsnFirstArgumentWithOptions()
    {
        $client = new Dummy_Raven_Client('http://public:secret@example.com/1', array(
            'site' => 'foo',
        ));

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
        $this->assertEquals($client->site, 'foo');
    }

    /**
     * @covers Raven_Client::__construct
     */
    public function testOptionsFirstArgument()
    {
        $client = new Dummy_Raven_Client(array(
            'server' => 'http://example.com/api/1/store/',
            'project' => 1,
        ));

        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
    }


    /**
     * @covers Raven_Client::__construct
     */
    public function testDsnInOptionsFirstArg()
    {
        $client = new Dummy_Raven_Client(array(
            'dsn' => 'http://public:secret@example.com/1',
        ));

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
    }

    /**
     * @covers Raven_Client::__construct
     */
    public function testDsnInOptionsSecondArg()
    {
        $client = new Dummy_Raven_Client(null, array(
            'dsn' => 'http://public:secret@example.com/1',
        ));

        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
    }

    /**
     * @covers Raven_Client::__construct
     */
    public function testOptionsFirstArgumentWithOptions()
    {
        $client = new Dummy_Raven_Client(array(
            'server' => 'http://example.com/api/1/store/',
            'project' => 1,
        ), array(
            'site' => 'foo',
        ));

        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->site, 'foo');
    }

    /**
     * @covers Raven_Client::captureMessage
     */
    public function testOptionsExtraData()
    {
        $client = new Dummy_Raven_Client(array('extra' => array('foo' => 'bar')));

        $client->captureMessage('Test Message %s', array('foo'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['extra']['foo'], 'bar');
    }

    /**
     * @covers Raven_Client::captureMessage
     */
    public function testOptionsExtraDataWithNull()
    {
        $client = new Dummy_Raven_Client(array('extra' => array('foo' => 'bar')));

        $client->captureMessage('Test Message %s', array('foo'), null);
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['extra']['foo'], 'bar');
    }

    /**
     * @covers Raven_Client::captureMessage
     */
    public function testEmptyExtraData()
    {
        $client = new Dummy_Raven_Client(array('extra' => array()));

        $client->captureMessage('Test Message %s', array('foo'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals(array_key_exists('extra', $event), false);
    }

    /**
     * @covers Raven_Client::captureMessage
     */
    public function testCaptureMessageDoesHandleUninterpolatedMessage()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s');
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['message'], 'Test Message %s');
    }

    /**
     * @covers Raven_Client::captureMessage
     */
    public function testCaptureMessageDoesHandleInterpolatedMessage()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', array('foo'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['message'], 'Test Message foo');
    }

    /**
     * @covers Raven_Client::captureMessage
     */
    public function testCaptureMessageDoesHandleInterpolatedMessageWithRelease()
    {
        $client = new Dummy_Raven_Client();
        $client->setRelease(20160909144742);

        $this->assertEquals(20160909144742, $client->getRelease());

        $client->captureMessage('Test Message %s', array('foo'));
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['release'], 20160909144742);
        $this->assertEquals($event['message'], 'Test Message foo');
    }

    /**
     * @covers Raven_Client::captureMessage
     */
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
        $this->assertEquals('Test Message foo', $event['message']);
    }

    /**
     * @covers Raven_Client::captureMessage
     */
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
        $this->assertEquals('Test Message foo', $event['message']);
    }

    /**
     * @covers Raven_Client::captureMessage
     */
    public function testCaptureMessageHandlesLevelAsThirdArg()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', array('foo'), Dummy_Raven_Client::WARNING);
        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals($event['level'], Dummy_Raven_Client::WARNING);
        $this->assertEquals('Test Message foo', $event['message']);
    }

    /**
     * @covers Raven_Client::captureException
     */
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

        $this->assertFalse(empty($exc['values'][0]['stacktrace']['frames']));
        $frames = $exc['values'][0]['stacktrace']['frames'];
        $frame = $frames[count($frames) - 1];
        $this->assertTrue($frame['lineno'] > 0);
        $this->assertEquals($frame['function'], 'create_exception');
        $this->assertFalse(isset($frame['vars']));
        $this->assertEquals($frame['context_line'], '            throw new Exception(\'Foo bar\');');
        $this->assertFalse(empty($frame['pre_context']));
        $this->assertFalse(empty($frame['post_context']));
    }

    /**
     * @covers Raven_Client::captureException
     */
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

    /**
     * @covers Raven_Client::captureException
     */
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

    /**
     * @covers Raven_Client::captureException
     */
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

    /**
     * @covers Raven_Client::captureException
     */
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

    /**
     * @covers Raven_Client::captureException
     */
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

    /**
     * @covers Raven_Client::__construct
     */
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

        $processor = $this->getMockBuilder('Processor')
                          ->setMethods(array('process'))
                          ->getMock();
        $processor->expects($this->once())
               ->method('process')
               ->with($data);

        $client = new Dummy_Raven_Client();
        $client->processors[] = $processor;
        $client->process($data);
    }

    /**
     * @covers Raven_Client::__construct
     * @covers Raven_Client::getDefaultProcessors
     */
    public function testDefaultProcessorsAreUsed()
    {
        $client = new Dummy_Raven_Client();
        $defaults = Dummy_Raven_Client::getDefaultProcessors();

        $this->assertEquals(count($client->processors), count($defaults));
    }

    /**
     * @covers Raven_Client::getDefaultProcessors
     */
    public function testDefaultProcessorsContainSanitizeDataProcessor()
    {
        $defaults = Dummy_Raven_Client::getDefaultProcessors();

        $this->assertTrue(in_array('Raven_SanitizeDataProcessor', $defaults));
    }

    /**
     * @covers Raven_Client::__construct
     * @covers Raven_Client::get_default_data
     */
    public function testGetDefaultData()
    {
        $client = new Dummy_Raven_Client();
        $client->transaction->push('test');
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
            'culprit' => 'test',
        );
        $this->assertEquals($expected, $client->get_default_data());
    }

    /**
     * @backupGlobals
     * @covers Raven_Client::get_http_data
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

    /**
     * @covers Raven_Client::user_context
     * @covers Raven_Client::get_user_data
     */
    public function testGetUserDataWithSetUser()
    {
        $client = new Dummy_Raven_Client();

        $id = 'unique_id';
        $email = 'foo@example.com';

        $user = array(
            'username' => 'my_user',
        );

        // @todo переписать
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

    /**
     * @covers Raven_Client::get_user_data
     */
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

    /**
     * @covers Raven_Client::get_auth_header
     */
    public function testGet_Auth_Header()
    {
        $client = new Dummy_Raven_Client();

        $clientstring = 'sentry-php/test';
        $timestamp = '1234341324.340000';

        $expected = "Sentry sentry_timestamp={$timestamp}, sentry_client={$clientstring}, " .
                    "sentry_version=" . Dummy_Raven_Client::PROTOCOL . ", " .
                    "sentry_key=publickey, sentry_secret=secretkey";

        $this->assertEquals($expected, $client->get_auth_header($timestamp, 'sentry-php/test', 'publickey', 'secretkey'));
    }

    /**
     * @covers Raven_Client::getAuthHeader
     */
    public function testGetAuthHeader()
    {
        $client = new Dummy_Raven_Client();
        $ts1 = microtime(true);
        $header = $client->getAuthHeader();
        $ts2 = microtime(true);
        $this->assertEquals(1, preg_match('/sentry_timestamp=([0-9.]+)/', $header, $a));
        $this->assertRegExp('/^[0-9]+(\\.[0-9]+)?$/', $a[1]);
        $this->assertGreaterThanOrEqual($ts1, (double)$a[1]);
        $this->assertLessThanOrEqual($ts2, (double)$a[1]);
    }

    /**
     * @covers Raven_Client::captureMessage
     */
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

    /**
     * @covers Raven_Client::captureMessage
     */
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
        array_pop($events);
    }

    /**
     * @covers Raven_Client::captureMessage
     * @covers Raven_Client::tags_context
     */
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

    /**
     * @covers Raven_Client::captureMessage
     * @covers Raven_Client::extra_context
     */
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

    /**
     * @covers Raven_Client::captureException
     */
    public function testCaptureExceptionContainingLatin1()
    {
        // If somebody has a non-utf8 codebase, she/he should add the encoding to the detection order
        $options = array(
            'mb_detect_order' => array(
                'ISO-8859-1', 'ASCII', 'UTF-8'
            )
        );

        $client = new Dummy_Raven_Client($options);

        // we need a non-utf8 string here.
        // nobody writes non-utf8 in exceptions, but it is the easiest way to test.
        // in real live non-utf8 may be somewhere in the exception's stacktrace
        $utf8String = 'äöü';
        $latin1String = utf8_decode($utf8String);
        $client->captureException(new \Exception($latin1String));

        $events = $client->getSentEvents();
        $event = array_pop($events);

        $this->assertEquals($event['exception']['values'][0]['value'], $utf8String);
    }


    public function testCaptureExceptionInLatin1File()
    {
        // If somebody has a non-utf8 codebase, she/he should add the encoding to the detection order
        $options = array(
            'mb_detect_order' => array(
                'ISO-8859-1', 'ASCII', 'UTF-8'
            )
        );

        $client = new Dummy_Raven_Client($options);

        require_once(__DIR__.'/resources/captureExceptionInLatin1File.php');

        $events = $client->getSentEvents();
        $event = array_pop($events);

        $stackTrace = array_pop($event['exception']['values'][0]['stacktrace']['frames']);

        $utf8String = "// äöü";
        $found = false;
        foreach ($stackTrace['pre_context'] as $line) {
            if ($line == $utf8String) {
                $found = true;
                break;
            }
        }

        $this->assertEquals($found, true);
    }

    /**
     * @covers Raven_Client::captureLastError
     */
    public function testCaptureLastError()
    {
        $client = new Dummy_Raven_Client();
        $this->assertNull($client->captureLastError());
        $this->assertEquals(0, count($client->getSentEvents()));

        @$undefined;

        $client->captureLastError();
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals($event['exception']['values'][0]['value'], 'Undefined variable: undefined');
    }

    /**
     * @covers Raven_Client::getLastEventID
     */
    public function testGetLastEventID()
    {
        $client = new Dummy_Raven_Client();
        $client->capture(array('message' => 'test', 'event_id' => 'abc'));
        $this->assertEquals($client->getLastEventID(), 'abc');
    }

    /**
     * @covers Raven_Client::setTransport
     */
    public function testCustomTransport()
    {
        $events = array();

        // transport test requires default client
        $client = new Raven_Client('https://public:secret@sentry.example.com/1', array(
            'install_default_breadcrumb_handlers' => false,
        ));
        $client->setTransport(function ($client, $data) use (&$events) {
            $events[] = $data;
        });
        $client->capture(array('message' => 'test', 'event_id' => 'abc'));
        $this->assertEquals(count($events), 1);
    }

    /**
     * @covers Raven_Client::setAppPath
     */
    public function testAppPathLinux()
    {
        $client = new Dummy_Raven_Client();
        $client->setAppPath('/foo/bar');

        $this->assertEquals($client->getAppPath(), '/foo/bar/');

        $client->setAppPath('/foo/baz/');

        $this->assertEquals($client->getAppPath(), '/foo/baz/');
    }

    /**
     * @covers Raven_Client::setAppPath
     */
    public function testAppPathWindows()
    {
        $client = new Dummy_Raven_Client();
        $client->setAppPath('C:\\foo\\bar\\');

        $this->assertEquals($client->getAppPath(), 'C:\\foo\\bar\\');
    }

    /**
     * @expectedException Raven_Exception
     * @expectedExceptionMessage Raven_Client->install() must only be called once
     */
    public function testCannotInstallTwice()
    {
        $client = new Dummy_Raven_Client('https://public:secret@sentry.example.com/1');
        $client->install();
        $client->install();
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

    /**
     * @covers Raven_Client::send
     */
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
     * @covers Raven_Client::sanitize
     */
    public function testSanitizeExtra()
    {
        $client = new Dummy_Raven_Client();
        $data = array('extra' => array(
            'context' => array(
                'line' => 1216,
                'stack' => array(
                    1, array(2), 3
                ),
            ),
        ));
        $client->sanitize($data);

        $this->assertEquals($data, array('extra' => array(
            'context' => array(
                'line' => 1216,
                'stack' => array(
                    1, 'Array of length 1', 3
                ),
            ),
        )));
    }

    /**
     * @covers Raven_Client::sanitize
     */
    public function testSanitizeTags()
    {
        $client = new Dummy_Raven_Client();
        $data = array('tags' => array(
            'foo' => 'bar',
            'baz' => array('biz'),
        ));
        $client->sanitize($data);

        $this->assertEquals($data, array('tags' => array(
            'foo' => 'bar',
            'baz' => 'Array',
        )));
    }

    /**
     * @covers Raven_Client::sanitize
     */
    public function testSanitizeUser()
    {
        $client = new Dummy_Raven_Client();
        $data = array('user' => array(
            'email' => 'foo@example.com',
        ));
        $client->sanitize($data);

        $this->assertEquals($data, array('user' => array(
            'email' => 'foo@example.com',
        )));
    }

    /**
     * @covers Raven_Client::buildCurlCommand
     */
    public function testBuildCurlCommandEscapesInput()
    {
        $data = '{"foo": "\'; ls;"}';
        $client = new Dummy_Raven_Client();
        $result = $client->buildCurlCommand('http://foo.com', $data, array());
        $this->assertEquals($result, 'curl -X POST -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 > /dev/null 2>&1 &');

        $result = $client->buildCurlCommand('http://foo.com', $data, array('key' => 'value'));
        $this->assertEquals($result, 'curl -X POST -H \'key: value\' -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 > /dev/null 2>&1 &');

        $client->verify_ssl = false;
        $result = $client->buildCurlCommand('http://foo.com', $data, array());
        $this->assertEquals($result, 'curl -X POST -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 -k > /dev/null 2>&1 &');

        $result = $client->buildCurlCommand('http://foo.com', $data, array('key' => 'value'));
        $this->assertEquals($result, 'curl -X POST -H \'key: value\' -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 -k > /dev/null 2>&1 &');
    }

    /**
     * @covers Raven_Client::user_context
     */
    public function testUserContextWithoutMerge()
    {
        $client = new Dummy_Raven_Client();
        $client->user_context(array('foo' => 'bar'), false);
        $client->user_context(array('baz' => 'bar'), false);
        $this->assertEquals($client->context->user, array('baz' => 'bar'));
    }

    /**
     * @covers Raven_Client::user_context
     */
    public function testUserContextWithMerge()
    {
        $client = new Dummy_Raven_Client();
        $client->user_context(array('foo' => 'bar'), true);
        $client->user_context(array('baz' => 'bar'), true);
        $this->assertEquals($client->context->user, array('foo' => 'bar', 'baz' => 'bar'));
    }

    /**
     * @covers Raven_Client::user_context
     */
    public function testUserContextWithMergeAndNull()
    {
        $client = new Dummy_Raven_Client();
        $client->user_context(array('foo' => 'bar'), true);
        $client->user_context(null, true);
        $this->assertEquals($client->context->user, array('foo' => 'bar'));
    }

    /**
     * Set the server array to the test values, check the current url
     *
     * @dataProvider currentUrlProvider
     * @param array $serverVars
     * @param array $options
     * @param string $expected - the url expected
     * @param string $message - fail message
     * @covers Raven_Client::get_current_url
     * @covers Raven_Client::isHttps
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

    /**
     * @covers Raven_Client::uuid4()
     */
    public function testUuid4()
    {
        $method = new ReflectionMethod('Raven_Client', 'uuid4');
        $method->setAccessible(true);
        for ($i = 0; $i < 1000; $i++) {
            $this->assertRegExp('/^[0-9a-z-]+$/', $method->invoke(null));
        }
    }

    /**
     * @covers Raven_Client::getEnvironment
     * @covers Raven_Client::setEnvironment
     * @covers Raven_Client::getRelease
     * @covers Raven_Client::setRelease
     * @covers Raven_Client::getAppPath
     * @covers Raven_Client::setAppPath
     * @covers Raven_Client::getExcludedAppPaths
     * @covers Raven_Client::setExcludedAppPaths
     * @covers Raven_Client::getPrefixes
     * @covers Raven_Client::setPrefixes
     * @covers Raven_Client::getSendCallback
     * @covers Raven_Client::setSendCallback
     * @covers Raven_Client::getTransport
     * @covers Raven_Client::setTransport
     * @covers Raven_Client::getServerEndpoint
     * @covers Raven_Client::getLastError
     * @covers Raven_Client::getLastEventID
     * @covers Raven_Client::get_extra_data
     * @covers Raven_Client::setProcessors
     */
    public function testGettersAndSetters()
    {
        $client = new Dummy_Raven_Client();
        $property_method__convert_path = new ReflectionMethod('Raven_Client', '_convertPath');
        $property_method__convert_path->setAccessible(true);
        // @todo зависит от версии php. Вставить сюда closure
        $callable = array($this, 'stabClosureVoid');

        $data = array(
            array('environment', null, 'value',),
            array('environment', null, null,),
            array('release', null, 'value',),
            array('release', null, null,),
            array('app_path', null, 'value', $property_method__convert_path->invoke($client, 'value')),
            array('app_path', null, null,),
            array('app_path', null, false, null,),
            array('excluded_app_paths', null, array('value'),
                  array($property_method__convert_path->invoke($client, 'value'))),
            array('excluded_app_paths', null, array(), null),
            array('excluded_app_paths', null, null),
            array('prefixes', null, array('value'), array($property_method__convert_path->invoke($client, 'value'))),
            array('prefixes', null, array()),
            array('send_callback', null, $callable),
            array('send_callback', null, null),
            array('transport', null, $callable),
            array('transport', null, null),
            array('server', 'ServerEndpoint', 'http://example.com/'),
            array('server', 'ServerEndpoint', 'http://example.org/'),
            array('_lasterror', null, null,),
            array('_lasterror', null, 'value',),
            array('_lasterror', null, mt_rand(100, 999),),
            array('_last_event_id', null, mt_rand(100, 999),),
            array('_last_event_id', null, 'value',),
            array('extra_data', '_extra_data', array('key' => 'value'),),
            array('processors', 'processors', array(),),
            array('processors', 'processors', array('key' => 'value'),),
        );
        foreach ($data as &$datum) {
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
        foreach ($data as &$datum) {
            $client = new Dummy_Raven_Client();
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
    }

    private function subTestGettersAndSettersDatum(Raven_Client $client, $datum)
    {
        if (count($datum) == 3) {
            list($property_name, $function_name, $value_in) = $datum;
            $value_out = $value_in;
        } else {
            list($property_name, $function_name, $value_in, $value_out) = $datum;
        }
        if (is_null($function_name)) {
            $function_name = str_replace('_', '', $property_name);
        }

        $method_get_name = 'get'.$function_name;
        $method_set_name = 'set'.$function_name;
        $property = new ReflectionProperty('Raven_Client', $property_name);
        $property->setAccessible(true);

        if (method_exists($client, $method_set_name)) {
            $setter_output = $client->$method_set_name($value_in);
            if (!is_null($setter_output) and is_object($setter_output)) {
                // chaining call test
                $this->assertEquals(spl_object_hash($client), spl_object_hash($setter_output));
            }
            $actual_value = $property->getValue($client);
            $this->assertMixedValueAndArray($value_out, $actual_value);
        }

        if (method_exists($client, $method_get_name)) {
            $property->setValue($client, $value_out);
            $reflection = new ReflectionMethod('Raven_Client', $method_get_name);
            if ($reflection->isPublic()) {
                $actual_value = $client->$method_get_name();
                $this->assertMixedValueAndArray($value_out, $actual_value);
            }
        }
    }

    private function assertMixedValueAndArray($expected_value, $actual_value)
    {
        if (is_null($expected_value)) {
            $this->assertNull($actual_value);
        } elseif ($expected_value === true) {
            $this->assertTrue($actual_value);
        } elseif ($expected_value === false) {
            $this->assertFalse($actual_value);
        } elseif (is_string($expected_value) or is_integer($expected_value) or is_double($expected_value)) {
            $this->assertEquals($expected_value, $actual_value);
        } elseif (is_array($expected_value)) {
            $this->assertInternalType('array', $actual_value);
            $this->assertEquals(count($expected_value), count($actual_value));
            foreach ($expected_value as $key => $value) {
                $this->assertArrayHasKey($key, $actual_value);
                $this->assertMixedValueAndArray($value, $actual_value[$key]);
            }
        } elseif (is_callable($expected_value) or is_object($expected_value)) {
            $this->assertEquals(spl_object_hash($expected_value), spl_object_hash($actual_value));
        }
    }

    /**
     * @covers Raven_Client::_convertPath
     */
    function test_convertPath()
    {
        $property = new ReflectionMethod('Raven_Client', '_convertPath');
        $property->setAccessible(true);
        // @todo
    }

    /**
     * @covers Raven_Client::getDefaultProcessors
     */
    function testGetDefaultProcessors()
    {
        foreach (Raven_Client::getDefaultProcessors() as $class_name) {
            $this->assertInternalType('string', $class_name);
            $this->assertTrue(class_exists($class_name));
            $reflection = new ReflectionClass($class_name);
            $this->assertTrue($reflection->isSubclassOf('Raven_Processor'));
            $this->assertFalse($reflection->isAbstract());
        }
    }

    /**
     * @covers Raven_Client::get_default_ca_cert
     */
    function testGet_default_ca_cert()
    {
        $reflection = new ReflectionMethod('Raven_Client', 'get_default_ca_cert');
        $reflection->setAccessible(true);
        $this->assertFileExists($reflection->invoke(null));
    }

    /**
     * @covers Raven_Client::translateSeverity
     * @covers Raven_Client::registerSeverityMap
     */
    function testTranslateSeverity()
    {
        $reflection = new ReflectionProperty('Raven_Client', 'severity_map');
        $reflection->setAccessible(true);
        $client = new Dummy_Raven_Client();

        $predefined = array(E_ERROR, E_WARNING, E_PARSE, E_NOTICE, E_CORE_ERROR, E_CORE_WARNING,
                       E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR, E_USER_WARNING,
                       E_USER_NOTICE, E_STRICT, E_RECOVERABLE_ERROR,);
        if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
            $predefined[] = E_DEPRECATED;
            $predefined[] = E_USER_DEPRECATED;
        }
        $predefined_values = array('debug', 'info', 'warning', 'warning', 'error', 'fatal',);

        // step 1
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('error', $client->translateSeverity(123456));
        // step 2
        $client->registerSeverityMap(array());
        $this->assertMixedValueAndArray(array(), $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123456));
        // step 3
        $client->registerSeverityMap(array(123456 => 'foo',));
        $this->assertMixedValueAndArray(array(123456 => 'foo'), $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 4
        $client->registerSeverityMap(array(E_USER_ERROR => 'bar',));
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 5
        $client->registerSeverityMap(array(E_USER_ERROR => 'bar', 123456 => 'foo',));
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
    }

    /**
     * @covers Raven_Client::getUserAgent
     */
    function testGetUserAgent()
    {
        $this->assertRegExp('|^[0-9a-z./_-]+$|i', Raven_Client::getUserAgent());
    }

    function testCaptureExceptionWithLogger()
    {
        $client = new Dummy_Raven_Client();
        $client->captureException(new Exception(), null, 'foobar');

        $events = $client->getSentEvents();
        $this->assertEquals(count($events), 1);
        $event = array_pop($events);
        $this->assertEquals('foobar', $event['logger']);
    }

    /**
     * @covers Raven_Client::__construct
     * @covers Raven_Client::send
     * @covers Raven_Client::send_remote
     * @covers Raven_Client::send_http
     */
    function testCurl_method()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', array(
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            )
        );
        $client->captureMessage('foobar');
        $this->assertTrue($client->_send_http_synchronous);
        $this->assertFalse($client->_send_http_asynchronous_curl_exec_called);

        // step 2
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', array(
                'curl_method' => 'exec',
                'install_default_breadcrumb_handlers' => false,
            )
        );
        $client->captureMessage('foobar');
        $this->assertFalse($client->_send_http_synchronous);
        $this->assertTrue($client->_send_http_asynchronous_curl_exec_called);
    }

    /**
     * @covers Raven_Client::__construct
     * @covers Raven_Client::send
     * @covers Raven_Client::send_remote
     * @covers Raven_Client::send_http
     */
    function testCurl_method_async()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', array(
                'curl_method' => 'async',
                'install_default_breadcrumb_handlers' => false,
            )
        );
        $object = $client->get_curl_handler();
        $this->assertInternalType('object', $object);
        $this->assertEquals('Raven_CurlHandler', get_class($object));

        $reflection = new ReflectionProperty('Raven_CurlHandler', 'options');
        $reflection->setAccessible(true);
        $this->assertEquals($client->get_curl_options(), $reflection->getValue($object));

        // step 2
        $ch = new Dummy_Raven_CurlHandler();
        $client->set_curl_handler($ch);
        $client->captureMessage('foobar');
        $this->assertFalse($client->_send_http_synchronous);
        $this->assertFalse($client->_send_http_asynchronous_curl_exec_called);
        $this->assertTrue($ch->_enqueue_called);
    }

    /**
     * @backupGlobals
     * @covers Raven_Client::__construct
     */
    function testConstructWithServerDSN()
    {
        $_SERVER['SENTRY_DSN'] = 'http://public:secret@example.com/1';
        $client = new Dummy_Raven_Client();
        $this->assertEquals($client->project, 1);
        $this->assertEquals($client->server, 'http://example.com/api/1/store/');
        $this->assertEquals($client->public_key, 'public');
        $this->assertEquals($client->secret_key, 'secret');
    }

    /**
     * @backupGlobals
     * @covers Raven_Client::_server_variable
     */
    function test_server_variable()
    {
        $method = new ReflectionMethod('Raven_Client', '_server_variable');
        $method->setAccessible(true);
        foreach ($_SERVER as $key => $value) {
            $actual = $method->invoke(null, $key);
            $this->assertNotNull($actual);
            $this->assertEquals($value, $actual);
        }
        foreach (array('foo', 'bar', 'foobar', '123456', 'SomeLongNonExistedKey') as $key => $value) {
            if (!isset($_SERVER[$key])) {
                $actual = $method->invoke(null, $key);
                $this->assertNotNull($actual);
                $this->assertEquals('', $actual);
            }
            unset($_SERVER[$key]);
            $actual = $method->invoke(null, $key);
            $this->assertNotNull($actual);
            $this->assertEquals('', $actual);
        }
    }

    function testEncode()
    {
        $client = new Dummy_Raven_Client();
        $data_broken = array();
        for ($i = 0; $i < 1024; $i++) {
            $data_broken = array($data_broken);
        }
        $value = $client->encode($data_broken);
        $this->assertFalse($value);
        unset($data_broken);

        $data = array('some' => (object)array('value' => 'data'), 'foo' => array('bar', null, 123), false);
        $json_stringify = Raven_Compat::json_encode($data);
        $value = $client->encode($data);
        $this->assertRegExp('_^[a-zA-Z0-9/=]+$_', $value);
        $decoded = base64_decode($value);
        if (function_exists("gzcompress")) {
            $decoded = gzuncompress($decoded);
        }

        $this->assertEquals($json_stringify, $decoded);
    }

    /**
     * @covers Raven_Client::__construct
     * @covers Raven_Client::registerDefaultBreadcrumbHandlers
     */
    function testRegisterDefaultBreadcrumbHandlers()
    {
        $previous = set_error_handler(array($this, 'stabClosureErrorHandler'), E_USER_NOTICE);
        new Raven_Client(null, array());
        $this->_closure_called = false;
        trigger_error('foobar', E_USER_NOTICE);
        $u = $this->_closure_called;
        $debug_backtrace = $this->_debug_backtrace;
        set_error_handler($previous, E_ALL);
        $this->assertTrue($u);
        $this->assertEquals('Raven_Breadcrumbs_ErrorHandler', $debug_backtrace[2]['class']);
    }

    private $_closure_called = false;

    function stabClosureVoid()
    {
        $this->_closure_called = true;
    }

    function stabClosureNull()
    {
        $this->_closure_called = true;

        return null;
    }

    function stabClosureFalse()
    {
        $this->_closure_called = true;

        return false;
    }

    private $_debug_backtrace = array();

    function stabClosureErrorHandler($code, $message, $file = '', $line = 0, $context = array())
    {
        $this->_closure_called = true;
        $this->_debug_backtrace = debug_backtrace();

        return true;
    }

    /**
     * @covers Raven_Client::onShutdown
     * @covers Raven_Client::sendUnsentErrors
     */
    function testOnShutdown()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', array(
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            )
        );
        $this->assertEquals(0, count($client->_pending_events));
        $client->_pending_events[] = array('foo' => 'bar');
        $client->sendUnsentErrors();
        $this->assertTrue($client->_send_http_synchronous);
        $this->assertFalse($client->_send_http_asynchronous_curl_exec_called);
        $this->assertEquals(0, count($client->_pending_events));

        // step 2
        $client->_send_http_synchronous = false;
        $client->_send_http_asynchronous_curl_exec_called = false;

        $client->store_errors_for_bulk_send = true;
        $client->captureMessage('foobar');
        $this->assertEquals(1, count($client->_pending_events));
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
        $client->_send_http_synchronous = false;
        $client->_send_http_asynchronous_curl_exec_called = false;

        // step 3
        $client->onShutdown();
        $this->assertTrue($client->_send_http_synchronous);
        $this->assertFalse($client->_send_http_asynchronous_curl_exec_called);
        $this->assertEquals(0, count($client->_pending_events));

        // step 1
        $client = null;
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', array(
                'curl_method' => 'async',
                'install_default_breadcrumb_handlers' => false,
            )
        );
        $ch = new Dummy_Raven_CurlHandler();
        $client->set_curl_handler($ch);
        $client->captureMessage('foobar');
        $client->onShutdown();
        $client = null;
        $this->assertTrue($ch->_join_called);
    }

    /**
     * @covers Raven_Client::send
     */
    function testNonWorkingSendSendCallback()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', array(
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            )
        );
        $this->_closure_called = false;
        $client->setSendCallback(array($this, 'stabClosureNull'));
        $this->assertFalse($this->_closure_called);
        $data = array('foo' => 'bar');
        $client->send($data);
        $this->assertTrue($this->_closure_called);
        $this->assertTrue($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
        // step 2
        $this->_closure_called = false;
        $client->_send_http_synchronous = false;
        $client->_send_http_asynchronous_curl_exec_called = false;
        $client->setSendCallback(array($this, 'stabClosureFalse'));
        $this->assertFalse($this->_closure_called);
        $data = array('foo' => 'bar');
        $client->send($data);
        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
    }

    /**
     * @covers Raven_Client::send
     */
    function testNonWorkingSendDSNEmpty()
    {
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', array(
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            )
        );
        $client->server = null;
        $data = array('foo' => 'bar');
        $client->send($data);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
    }

    /**
     * @covers Raven_Client::send
     */
    function testNonWorkingSendSetTransport()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', array(
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            )
        );
        $this->_closure_called = false;
        $client->setTransport(array($this, 'stabClosureNull'));
        $this->assertFalse($this->_closure_called);
        $data = array('foo' => 'bar');
        $client->send($data);
        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
        // step 2
        $this->_closure_called = false;
        $client->setSendCallback(array($this, 'stabClosureFalse'));
        $this->assertFalse($this->_closure_called);
        $data = array('foo' => 'bar');
        $client->send($data);
        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
    }
}
