<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Mock\Client as MockClient;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Raven\Breadcrumbs\ErrorHandler;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Configuration;
use Raven\Processor\SanitizeDataProcessor;

function simple_function($a = null, $b = null, $c = null)
{
    throw new \RuntimeException('This simple function should fail before reaching this line!');
}

function invalid_encoding()
{
    $fp = fopen(__DIR__ . '/data/binary', 'r');
    simple_function(fread($fp, 64));
    fclose($fp);
}

// XXX: Is there a better way to stub the client?
class Dummy_Raven_Client extends \Raven\Client
{
    private $__sent_events = [];
    public $dummy_breadcrumbs_handlers_has_set = false;
    public $dummy_shutdown_handlers_has_set = false;

    public function getSentEvents()
    {
        return $this->__sent_events;
    }

    public function send(&$data)
    {
        if (!$this->config->shouldCapture($data)) {
            return;
        }

        $this->__sent_events[] = $data;
    }

    public static function is_http_request()
    {
        return true;
    }

    public function get_http_data()
    {
        return parent::get_http_data();
    }

    public function get_user_data()
    {
        return parent::get_user_data();
    }

    // short circuit breadcrumbs
    public function registerDefaultBreadcrumbHandlers()
    {
        $this->dummy_breadcrumbs_handlers_has_set = true;
    }

    public function registerShutdownFunction()
    {
        $this->dummy_shutdown_handlers_has_set = true;
    }

    /**
     * Expose the current url method to test it.
     *
     * @return string
     */
    public function test_get_current_url()
    {
        return $this->get_current_url();
    }
}

class Dummy_Raven_Client_No_Http extends Dummy_Raven_Client
{
    /**
     * @return bool
     */
    public static function is_http_request()
    {
        return false;
    }
}

class Dummy_Raven_Client_With_Sync_Override extends \Raven\Client
{
    private static $_test_data = null;

    public static function get_test_data()
    {
        if (null === self::$_test_data) {
            self::$_test_data = '';
            for ($i = 0; $i < 128; ++$i) {
                self::$_test_data .= chr(mt_rand(ord('a'), ord('z')));
            }
        }

        return self::$_test_data;
    }

    public static function test_filename()
    {
        return sys_get_temp_dir() . '/clientraven.tmp';
    }
}

class ClientTest extends TestCase
{
    protected static $_folder = null;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$_folder = sys_get_temp_dir() . '/sentry_server_' . microtime(true);
        mkdir(self::$_folder);
    }

    public static function tearDownAfterClass()
    {
        exec(sprintf('rm -rf %s', escapeshellarg(self::$_folder)));
    }

    public function tearDown()
    {
        parent::tearDown();
        if (file_exists(Dummy_Raven_Client_With_Sync_Override::test_filename())) {
            unlink(Dummy_Raven_Client_With_Sync_Override::test_filename());
        }
    }

    private function create_exception()
    {
        try {
            throw new \Exception('Foo bar');
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    private function create_chained_exception()
    {
        try {
            throw new \Exception('Foo bar');
        } catch (\Exception $ex) {
            try {
                throw new \Exception('Child exc', 0, $ex);
            } catch (\Exception $ex2) {
                return $ex2;
            }
        }
    }

    public function testDestructor()
    {
        $waitCalled = false;

        /** @var ResponseInterface|\PHPUnit_Framework_MockObject_MockObject $response */
        $response = $this->getMockBuilder(ResponseInterface::class)
            ->getMock();

        $promise = new PromiseMock($response);
        $promise->then(function (ResponseInterface $response) use (&$waitCalled) {
            $waitCalled = true;

            return $response;
        });

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $client = ClientBuilder::create(['server' => 'http://public:secret@example.com/1'])
            ->setHttpClient($httpClient)
            ->getClient();

        $data = ['foo'];

        $this->assertAttributeEmpty('pendingRequests', $client);

        $client->send($data);

        $this->assertAttributeNotEmpty('pendingRequests', $client);
        $this->assertFalse($waitCalled);

        // The destructor should never be called explicitly because it simply
        // does nothing in PHP, but it's the only way to assert that the
        // $waitCalled variable is true because PHPUnit maintains references
        // to all mocks instances
        $client->__destruct();

        $this->assertTrue($waitCalled);
    }

    public function testOptionsExtraData()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->extra_context(['foo' => 'bar']);
        $client->captureMessage('Test Message %s', ['foo']);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('bar', $client->_pending_events[0]['extra']['foo']);
    }

    public function testOptionsExtraDataWithNull()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->extra_context(['foo' => 'bar']);
        $client->captureMessage('Test Message %s', ['foo'], null);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('bar', $client->_pending_events[0]['extra']['foo']);
    }

    public function testEmptyExtraData()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureMessage('Test Message %s', ['foo']);

        $this->assertCount(1, $client->_pending_events);
        $this->assertArrayNotHasKey('extra', $client->_pending_events[0]);
    }

    public function testCaptureMessageDoesHandleUninterpolatedMessage()
    {
        $client = ClientBuilder::create()->getClient();

        $client->store_errors_for_bulk_send = true;

        $client->captureMessage('foo %s');

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('foo %s', $client->_pending_events[0]['message']);
    }

    public function testCaptureMessageDoesHandleInterpolatedMessage()
    {
        $client = ClientBuilder::create()->getClient();

        $client->store_errors_for_bulk_send = true;

        $client->captureMessage('foo %s', ['bar']);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('foo bar', $client->_pending_events[0]['message']);
    }

    public function testCaptureMessageDoesHandleInterpolatedMessageWithRelease()
    {
        $client = ClientBuilder::create(['release' => '1.2.3'])->getClient();
        $client->store_errors_for_bulk_send = true;

        $this->assertEquals('1.2.3', $client->getConfig()->getRelease());

        $client->captureMessage('foo %s', ['bar']);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('foo bar', $client->_pending_events[0]['message']);
        $this->assertEquals('1.2.3', $client->_pending_events[0]['release']);
    }

    public function testCaptureMessageSetsInterface()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureMessage('foo %s', ['bar']);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('foo bar', $client->_pending_events[0]['message']);
        $this->assertArrayHasKey('sentry.interfaces.Message', $client->_pending_events[0]);
        $this->assertEquals('foo bar', $client->_pending_events[0]['sentry.interfaces.Message']['formatted']);
        $this->assertEquals('foo %s', $client->_pending_events[0]['sentry.interfaces.Message']['message']);
        $this->assertEquals(['bar'], $client->_pending_events[0]['sentry.interfaces.Message']['params']);
    }

    public function testCaptureMessageHandlesOptionsAsThirdArg()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureMessage('Test Message %s', ['foo'], [
            'level' => Dummy_Raven_Client::LEVEL_WARNING,
            'extra' => ['foo' => 'bar'],
        ]);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals(Dummy_Raven_Client::LEVEL_WARNING, $client->_pending_events[0]['level']);
        $this->assertEquals('bar', $client->_pending_events[0]['extra']['foo']);
        $this->assertEquals('Test Message foo', $client->_pending_events[0]['message']);
    }

    public function testCaptureMessageHandlesLevelAsThirdArg()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureMessage('Test Message %s', ['foo'], Dummy_Raven_Client::LEVEL_WARNING);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals(Dummy_Raven_Client::LEVEL_WARNING, $client->_pending_events[0]['level']);
        $this->assertEquals('Test Message foo', $client->_pending_events[0]['message']);
    }

    /**
     * @covers \Raven\Client::captureException
     */
    public function testCaptureExceptionSetsInterfaces()
    {
        // TODO: it'd be nice if we could mock the stacktrace extraction function here
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureException($this->create_exception());

        $this->assertCount(1, $client->_pending_events);

        $this->assertCount(1, $client->_pending_events[0]['exception']['values']);
        $this->assertEquals('Foo bar', $client->_pending_events[0]['exception']['values'][0]['value']);
        $this->assertEquals('Exception', $client->_pending_events[0]['exception']['values'][0]['type']);
        $this->assertNotEmpty($client->_pending_events[0]['exception']['values'][0]['stacktrace']['frames']);

        $frames = $client->_pending_events[0]['exception']['values'][0]['stacktrace']['frames'];
        $frame = $frames[count($frames) - 1];

        $this->assertTrue($frame['lineno'] > 0);
        $this->assertEquals('Raven\Tests\ClientTest::create_exception', $frame['function']);
        $this->assertFalse(isset($frame['vars']));
        $this->assertEquals('            throw new \Exception(\'Foo bar\');', $frame['context_line']);
        $this->assertNotEmpty($frame['pre_context']);
        $this->assertNotEmpty($frame['post_context']);
    }

    public function testCaptureExceptionChainedException()
    {
        // TODO: it'd be nice if we could mock the stacktrace extraction function here
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureException($this->create_chained_exception());

        $this->assertCount(1, $client->_pending_events);
        $this->assertCount(2, $client->_pending_events[0]['exception']['values']);
        $this->assertEquals('Foo bar', $client->_pending_events[0]['exception']['values'][0]['value']);
        $this->assertEquals('Child exc', $client->_pending_events[0]['exception']['values'][1]['value']);
    }

    public function testCaptureExceptionDifferentLevelsInChainedExceptionsBug()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $e1 = new \ErrorException('First', 0, E_DEPRECATED);
        $e2 = new \ErrorException('Second', 0, E_NOTICE, __FILE__, __LINE__, $e1);
        $e3 = new \ErrorException('Third', 0, E_ERROR, __FILE__, __LINE__, $e2);

        $client->captureException($e1);
        $client->captureException($e2);
        $client->captureException($e3);

        $this->assertCount(3, $client->_pending_events);
        $this->assertEquals(Client::LEVEL_WARNING, $client->_pending_events[0]['level']);
        $this->assertEquals(Client::LEVEL_INFO, $client->_pending_events[1]['level']);
        $this->assertEquals(Client::LEVEL_ERROR, $client->_pending_events[2]['level']);
    }

    public function testCaptureExceptionHandlesOptionsAsSecondArg()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureException($this->create_exception(), ['culprit' => 'test']);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('test', $client->_pending_events[0]['culprit']);
    }

    public function testCaptureExceptionHandlesExcludeOption()
    {
        $client = ClientBuilder::create(['excluded_exceptions' => ['Exception']])->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureException($this->create_exception(), 'test');

        $this->assertEmpty($client->_pending_events);
    }

    public function testCaptureExceptionInvalidUTF8()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        try {
            invalid_encoding();
        } catch (\Exception $ex) {
            $client->captureException($ex);
        }

        $this->assertCount(1, $client->_pending_events);

        try {
            $client->send($client->_pending_events[0]);
        } catch (\Exception $ex) {
            $this->fail();
        }
    }

    public function testDoesRegisterProcessors()
    {
        $client = ClientBuilder::create(['processors' => [SanitizeDataProcessor::class]])->getClient();

        $this->assertInstanceOf(SanitizeDataProcessor::class, $this->getObjectAttribute($client, 'processors')[0]);
    }

    public function testProcessDoesCallProcessors()
    {
        $data = ['key' => 'value'];

        $processor = $this->getMockBuilder('Processor')
            ->setMethods(['process'])
            ->getMock();

        $processor->expects($this->once())
            ->method('process')
            ->with($data);

        $client = ClientBuilder::create()->getClient();

        $client->setProcessors([$processor]);
        $client->process($data);
    }

    public function testGetDefaultData()
    {
        $client = ClientBuilder::create()->getClient();
        $config = $client->getConfig();

        $client->transaction->push('test');

        $expected = [
            'platform' => 'php',
            'project' => $config->getProjectId(),
            'server_name' => $config->getServerName(),
            'logger' => $config->getLogger(),
            'tags' => $config->getTags(),
            'culprit' => 'test',
            'sdk' => [
                'name' => 'sentry-php',
                'version' => $client::VERSION,
            ],
        ];

        $this->assertEquals($expected, $client->get_default_data());
    }

    /**
     * @backupGlobals
     */
    public function testGetHttpData()
    {
        $_SERVER = [
            'REDIRECT_STATUS' => '200',
            'CONTENT_TYPE' => 'text/xml',
            'CONTENT_LENGTH' => '99',
            'HTTP_HOST' => 'getsentry.com',
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_ACCEPT_CHARSET' => 'utf-8',
            'HTTP_COOKIE' => 'cupcake: strawberry',
            'SERVER_PORT' => '443',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD' => 'PATCH',
            'QUERY_STRING' => 'q=bitch&l=en',
            'REQUEST_URI' => '/welcome/',
            'SCRIPT_NAME' => '/index.php',
        ];
        $_POST = [
            'stamp' => '1c',
        ];
        $_COOKIE = [
            'donut' => 'chocolat',
        ];

        $expected = [
            'request' => [
                'method' => 'PATCH',
                'url' => 'https://getsentry.com/welcome/',
                'query_string' => 'q=bitch&l=en',
                'data' => [
                    'stamp' => '1c',
                ],
                'cookies' => [
                    'donut' => 'chocolat',
                ],
                'headers' => [
                    'Host' => 'getsentry.com',
                    'Accept' => 'text/html',
                    'Accept-Charset' => 'utf-8',
                    'Cookie' => 'cupcake: strawberry',
                    'Content-Type' => 'text/xml',
                    'Content-Length' => '99',
                ],
            ],
        ];

        $config = new Configuration([
            'install_default_breadcrumb_handlers' => false,
            'install_shutdown_handler' => false,
        ]);

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client($config, $httpClient, $requestFactory);

        $this->assertEquals($expected, $client->get_http_data());
    }

    public function testCaptureMessageWithUserData()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->user_context([
            'id' => 'unique_id',
            'email' => 'foo@example.com',
            'username' => 'my_user',
        ]);

        $client->captureMessage('foo');

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('unique_id', $client->_pending_events[0]['user']['id']);
        $this->assertEquals('my_user', $client->_pending_events[0]['user']['username']);
        $this->assertEquals('foo@example.com', $client->_pending_events[0]['user']['email']);
    }

    public function testCaptureMessageWithNoUserData()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureMessage('foo');

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals(session_id(), $client->_pending_events[0]['user']['id']);
    }

    public function testCaptureMessageWithUnserializableUserData()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->user_context([
            'email' => 'foo@example.com',
            'data' => [
                'error' => new \Exception('test'),
            ],
        ]);

        $client->captureMessage('test');

        $this->assertCount(1, $client->_pending_events);
    }

    public function testCaptureMessageWithTagsContext()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->tags_context(['foo' => 'bar']);
        $client->tags_context(['biz' => 'boz']);
        $client->tags_context(['biz' => 'baz']);

        $client->captureMessage('test');

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals(['foo' => 'bar', 'biz' => 'baz'], $client->_pending_events[0]['tags']);
    }

    public function testCaptureMessageWithExtraContext()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->extra_context(['foo' => 'bar']);
        $client->extra_context(['biz' => 'boz']);
        $client->extra_context(['biz' => 'baz']);

        $client->captureMessage('test');

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals(['foo' => 'bar', 'biz' => 'baz'], $client->_pending_events[0]['extra']);
    }

    public function testCaptureExceptionContainingLatin1()
    {
        $client = ClientBuilder::create(['mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8']])->getClient();
        $client->store_errors_for_bulk_send = true;

        // we need a non-utf8 string here.
        // nobody writes non-utf8 in exceptions, but it is the easiest way to test.
        // in real live non-utf8 may be somewhere in the exception's stacktrace
        $utf8String = 'äöü';
        $latin1String = utf8_decode($utf8String);
        $client->captureException(new \Exception($latin1String));

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals($utf8String, $client->_pending_events[0]['exception']['values'][0]['value']);
    }

    public function testCaptureExceptionInLatin1File()
    {
        $client = ClientBuilder::create(['mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8']])->getClient();
        $client->store_errors_for_bulk_send = true;

        require_once __DIR__ . '/Fixtures/code/Latin1File.php';

        $frames = $client->_pending_events[0]['exception']['values'][0]['stacktrace']['frames'];

        $utf8String = '// äöü';
        $found = false;

        foreach ($frames as $frame) {
            if (!isset($frame['pre_context'])) {
                continue;
            }

            if (in_array($utf8String, $frame['pre_context'])) {
                $found = true;

                break;
            }
        }

        $this->assertTrue($found);
    }

    public function testCaptureLastError()
    {
        if (function_exists('error_clear_last')) {
            error_clear_last();
        }

        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $this->assertNull($client->captureLastError());
        $this->assertEmpty($client->_pending_events);

        /* @var $undefined */
        /* @noinspection PhpExpressionResultUnusedInspection */
        @$undefined;

        $client->captureLastError();

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('Undefined variable: undefined', $client->_pending_events[0]['exception']['values'][0]['value']);
    }

    public function testGetLastEventID()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->capture([
            'message' => 'test',
            'event_id' => 'abc',
        ]);

        $this->assertEquals('abc', $client->getLastEventID());
    }

    public function testCustomTransport()
    {
        $events = [];

        $client = ClientBuilder::create([
            'server' => 'https://public:secret@sentry.example.com/1',
            'install_default_breadcrumb_handlers' => false,
        ])->getClient();

        $client->getConfig()->setTransport(function ($client, $data) use (&$events) {
            $events[] = $data;
        });

        $client->capture(['message' => 'test', 'event_id' => 'abc']);

        $this->assertCount(1, $events);
    }

    public function testAppPathLinux()
    {
        $client = ClientBuilder::create(['project_root' => '/foo/bar'])->getClient();

        $this->assertEquals('/foo/bar/', $client->getConfig()->getProjectRoot());

        $client->getConfig()->setProjectRoot('/foo/baz/');

        $this->assertEquals('/foo/baz/', $client->getConfig()->getProjectRoot());
    }

    public function testAppPathWindows()
    {
        $client = ClientBuilder::create(['project_root' => 'C:\\foo\\bar\\'])->getClient();

        $this->assertEquals('C:\\foo\\bar\\', $client->getConfig()->getProjectRoot());
    }

    /**
     * @expectedException \Raven\Exception
     * @expectedExceptionMessage Raven\Client->install() must only be called once
     */
    public function testCannotInstallTwice()
    {
        $client = ClientBuilder::create()->getClient();

        $client->install();
        $client->install();
    }

    /**
     * @dataProvider sendWithEncodingDataProvider
     */
    public function testSendWithEncoding($options, $expectedRequest)
    {
        $httpClient = new MockClient();

        $client = ClientBuilder::create($options)
            ->setHttpClient($httpClient)
            ->getClient();

        $data = ['foo bar'];

        $client->send($data);

        $requests = $httpClient->getRequests();

        $this->assertCount(1, $requests);

        $stream = $requests[0]->getBody();
        $stream->rewind();

        $this->assertEquals($expectedRequest['body'], (string) $stream);
        $this->assertArraySubset($expectedRequest['headers'], $requests[0]->getHeaders());
    }

    public function sendWithEncodingDataProvider()
    {
        return [
            [
                [
                    'server' => 'http://public:secret@example.com/1',
                    'encoding' => 'json',
                ],
                [
                    'body' => '["foo bar"]',
                    'headers' => [
                        'Content-Type' => ['application/json'],
                    ],
                ],
            ],
            [
                [
                    'server' => 'http://public:secret@example.com/1',
                    'encoding' => 'gzip',
                ],
                [
                    'body' => 'eJyLVkrLz1dISixSigUAFYQDlg==',
                    'headers' => [
                        'Content-Type' => ['application/octet-stream'],
                    ],
                ],
            ],
        ];
    }

    public function testSendCallback()
    {
        $config = new Configuration([
            'should_capture' => function ($data) {
                $this->assertEquals('test', $data['message']);

                return false;
            },
        ]);

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client($config, $httpClient, $requestFactory);

        $client->captureMessage('test');

        $this->assertEmpty($client->getSentEvents());

        $config->setShouldCapture(function ($data) {
            $this->assertEquals('test', $data['message']);

            return true;
        });

        $client->captureMessage('test');

        $this->assertCount(1, $client->getSentEvents());

        $config->setShouldCapture(function (&$data) {
            unset($data['message']);

            return true;
        });

        $client->captureMessage('test');

        $this->assertCount(2, $client->getSentEvents());
        $this->assertArrayNotHasKey('message', $client->getSentEvents()[1]);
    }

    public function testSanitizeExtra()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['extra' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [2], 3,
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['extra' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3,
                ],
            ],
        ]], $data);
    }

    public function testSanitizeObjects()
    {
        $client = ClientBuilder::create(['serialize_all_object' => true])->getClient();
        $clone = ClientBuilder::create()->getClient();
        $data = [
            'extra' => [
                'object' => $clone,
            ],
        ];

        $reflection = new \ReflectionClass($clone);
        $expected = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($clone);
            if (is_array($value)) {
                $property->setValue($clone, []);
                $expected[$property->getName()] = [];
                continue;
            }
            if (!is_object($value)) {
                $expected[$property->getName()] = $value;
                continue;
            }

            $new_value = [];
            $reflection2 = new \ReflectionClass($value);
            foreach ($reflection2->getProperties(\ReflectionProperty::IS_PUBLIC) as $property2) {
                $sub_value = $property2->getValue($value);
                if (is_array($sub_value)) {
                    $new_value[$property2->getName()] = 'Array of length ' . count($sub_value);
                    continue;
                }
                if (is_object($sub_value)) {
                    $sub_value = null;
                    $property2->setValue($value, null);
                }
                $new_value[$property2->getName()] = $sub_value;
            }

            ksort($new_value);
            $expected[$property->getName()] = $new_value;
            unset($reflection2, $property2, $sub_value, $new_value);
        }
        unset($reflection, $property, $value, $reflection, $clone);
        ksort($expected);

        $client->sanitize($data);
        ksort($data['extra']['object']);
        foreach ($data['extra']['object'] as $key => &$value) {
            if (is_array($value)) {
                ksort($value);
            }
        }

        $this->assertEquals(['extra' => ['object' => $expected]], $data);
    }

    public function testSanitizeTags()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['tags' => [
            'foo' => 'bar',
            'baz' => ['biz'],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['tags' => [
            'foo' => 'bar',
            'baz' => 'Array',
        ]], $data);
    }

    public function testSanitizeUser()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['user' => [
            'email' => 'foo@example.com',
        ]];
        $client->sanitize($data);

        $this->assertEquals(['user' => [
            'email' => 'foo@example.com',
        ]], $data);
    }

    public function testSanitizeRequest()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['request' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [2], 3,
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['request' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3,
                ],
            ],
        ]], $data);
    }

    public function testSanitizeContexts()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['contexts' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [
                        'foo' => 'bar',
                        'level4' => [['level5', 'level5 a'], 2],
                    ], 3,
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['contexts' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [
                        'foo' => 'bar',
                        'level4' => ['Array of length 2', 2],
                    ], 3,
                ],
            ],
        ]], $data);
    }

    public function testUserContextWithoutMerge()
    {
        $client = ClientBuilder::create()->getClient();

        $client->user_context(['foo' => 'bar'], false);
        $client->user_context(['baz' => 'bar'], false);

        $this->assertEquals(['baz' => 'bar'], $client->context->user);
    }

    public function testUserContextWithMerge()
    {
        $client = ClientBuilder::create()->getClient();

        $client->user_context(['foo' => 'bar'], true);
        $client->user_context(['baz' => 'bar'], true);

        $this->assertEquals(['foo' => 'bar', 'baz' => 'bar'], $client->context->user);
    }

    public function testUserContextWithMergeAndNull()
    {
        $client = ClientBuilder::create()->getClient();

        $client->user_context(['foo' => 'bar'], true);
        $client->user_context(null, true);

        $this->assertEquals(['foo' => 'bar'], $client->context->user);
    }

    /**
     * Set the server array to the test values, check the current url.
     *
     * @dataProvider currentUrlProvider
     *
     * @param array  $serverVars
     * @param array  $options
     * @param string $expected   - the url expected
     * @param string $message    - fail message
     * @covers \Raven\Client::get_current_url
     * @covers \Raven\Client::isHttps
     */
    public function testCurrentUrl($serverVars, $options, $expected, $message)
    {
        $_SERVER = $serverVars;

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client(new Configuration($options), $httpClient, $requestFactory);
        $result = $client->test_get_current_url();

        $this->assertSame($expected, $result, $message);
    }

    /**
     * Arrays of:
     *  $_SERVER data
     *  config
     *  expected url
     *  Fail message.
     *
     * @return array
     */
    public function currentUrlProvider()
    {
        return [
            [
                [],
                [],
                null,
                'No url expected for empty REQUEST_URI',
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                ],
                [],
                'http://example.com/',
                'The url is expected to be http with the request uri',
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'HTTPS' => 'on',
                ],
                [],
                'https://example.com/',
                'The url is expected to be https because of HTTPS on',
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'SERVER_PORT' => '443',
                ],
                [],
                'https://example.com/',
                'The url is expected to be https because of the server port',
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'X-FORWARDED-PROTO' => 'https',
                ],
                [],
                'http://example.com/',
                'The url is expected to be http because the X-Forwarded header is ignored',
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'X-FORWARDED-PROTO' => 'https',
                ],
                ['trust_x_forwarded_proto' => true],
                'https://example.com/',
                'The url is expected to be https because the X-Forwarded header is trusted',
            ],
        ];
    }

    /**
     * @covers \Raven\Client::getLastError
     * @covers \Raven\Client::getLastEventID
     * @covers \Raven\Client::getLastSentryError
     * @covers \Raven\Client::getShutdownFunctionHasBeenSet
     */
    public function testGettersAndSetters()
    {
        $client = ClientBuilder::create()->getClient();
        $callable = [$this, 'stabClosureVoid'];

        $data = [
            ['_lasterror', null, null],
            ['_lasterror', null, 'value'],
            ['_lasterror', null, mt_rand(100, 999)],
            ['_last_sentry_error', null, (object) ['error' => 'test']],
            ['_last_event_id', null, mt_rand(100, 999)],
            ['_last_event_id', null, 'value'],
            ['_shutdown_function_has_been_set', null, true],
            ['_shutdown_function_has_been_set', null, false],
        ];
        foreach ($data as &$datum) {
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
        foreach ($data as &$datum) {
            $client = ClientBuilder::create()->getClient();
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
    }

    private function subTestGettersAndSettersDatum(\Raven\Client $client, $datum)
    {
        if (count($datum) == 3) {
            list($property_name, $function_name, $value_in) = $datum;
            $value_out = $value_in;
        } else {
            list($property_name, $function_name, $value_in, $value_out) = $datum;
        }
        if (null === $function_name) {
            $function_name = str_replace('_', '', $property_name);
        }

        $method_get_name = 'get' . $function_name;
        $method_set_name = 'set' . $function_name;
        $property = new \ReflectionProperty('\\Raven\\Client', $property_name);
        $property->setAccessible(true);

        if (method_exists($client, $method_set_name)) {
            $setter_output = $client->$method_set_name($value_in);
            if (null !== $setter_output and is_object($setter_output)) {
                // chaining call test
                $this->assertEquals(spl_object_hash($client), spl_object_hash($setter_output));
            }
            $actual_value = $property->getValue($client);
            $this->assertMixedValueAndArray($value_out, $actual_value);
        }

        if (method_exists($client, $method_get_name)) {
            $property->setValue($client, $value_out);
            $reflection = new \ReflectionMethod('\\Raven\Client', $method_get_name);
            if ($reflection->isPublic()) {
                $actual_value = $client->$method_get_name();
                $this->assertMixedValueAndArray($value_out, $actual_value);
            }
        }
    }

    private function assertMixedValueAndArray($expected_value, $actual_value)
    {
        if (null === $expected_value) {
            $this->assertNull($actual_value);
        } elseif ($expected_value === true) {
            $this->assertTrue($actual_value);
        } elseif ($expected_value === false) {
            $this->assertFalse($actual_value);
        } elseif (is_string($expected_value) or is_int($expected_value) or is_float($expected_value)) {
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
     * @covers \Raven\Client::translateSeverity
     * @covers \Raven\Client::registerSeverityMap
     */
    public function testTranslateSeverity()
    {
        $reflection = new \ReflectionProperty('\\Raven\Client', 'severity_map');
        $reflection->setAccessible(true);
        $client = ClientBuilder::create()->getClient();

        $predefined = [E_ERROR, E_WARNING, E_PARSE, E_NOTICE, E_CORE_ERROR, E_CORE_WARNING,
                       E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR, E_USER_WARNING,
                       E_USER_NOTICE, E_STRICT, E_RECOVERABLE_ERROR, ];
        $predefined[] = E_DEPRECATED;
        $predefined[] = E_USER_DEPRECATED;
        $predefined_values = ['debug', 'info', 'warning', 'warning', 'error', 'fatal'];

        // step 1
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('error', $client->translateSeverity(123456));
        // step 2
        $client->registerSeverityMap([]);
        $this->assertMixedValueAndArray([], $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123456));
        // step 3
        $client->registerSeverityMap([123456 => 'foo']);
        $this->assertMixedValueAndArray([123456 => 'foo'], $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 4
        $client->registerSeverityMap([E_USER_ERROR => 'bar']);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 5
        $client->registerSeverityMap([E_USER_ERROR => 'bar', 123456 => 'foo']);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
    }

    public function testCaptureExceptionWithLogger()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureException(new \Exception(), null, 'foobar');

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('foobar', $client->_pending_events[0]['logger']);
    }

    /**
     * @backupGlobals
     * @covers \Raven\Client::_server_variable
     */
    public function test_server_variable()
    {
        $method = new \ReflectionMethod('\\Raven\Client', '_server_variable');
        $method->setAccessible(true);
        foreach ($_SERVER as $key => $value) {
            $actual = $method->invoke(null, $key);
            $this->assertNotNull($actual);
            $this->assertEquals($value, $actual);
        }
        foreach (['foo', 'bar', 'foobar', '123456', 'SomeLongNonExistedKey'] as $key => $value) {
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

    public function testRegisterDefaultBreadcrumbHandlers()
    {
        if (isset($_ENV['HHVM']) and ($_ENV['HHVM'] == 1)) {
            $this->markTestSkipped('HHVM stacktrace behaviour');

            return;
        }

        $previous = set_error_handler([$this, 'stabClosureErrorHandler'], E_USER_NOTICE);

        ClientBuilder::create()->getClient();

        $this->_closure_called = false;

        trigger_error('foobar', E_USER_NOTICE);
        set_error_handler($previous, E_ALL);

        $this->assertTrue($this->_closure_called);

        if (isset($this->_debug_backtrace[1]['function']) && ($this->_debug_backtrace[1]['function'] == 'call_user_func') && version_compare(PHP_VERSION, '7.0', '>=')) {
            $offset = 2;
        } elseif (version_compare(PHP_VERSION, '7.0', '>=')) {
            $offset = 1;
        } else {
            $offset = 2;
        }

        $this->assertEquals(ErrorHandler::class, $this->_debug_backtrace[$offset]['class']);
    }

    private $_closure_called = false;

    public function stabClosureVoid()
    {
        $this->_closure_called = true;
    }

    public function stabClosureNull()
    {
        $this->_closure_called = true;

        return null;
    }

    public function stabClosureFalse()
    {
        $this->_closure_called = true;

        return false;
    }

    private $_debug_backtrace = [];

    public function stabClosureErrorHandler($code, $message, $file = '', $line = 0, $context = [])
    {
        $this->_closure_called = true;
        $this->_debug_backtrace = debug_backtrace();

        return true;
    }

    public function testOnShutdown()
    {
        $httpClient = new MockClient();

        $client = ClientBuilder::create(['server' => 'http://public:secret@example.com/1'])
            ->setHttpClient($httpClient)
            ->getClient();

        $this->assertEquals(0, count($client->_pending_events));
        $client->_pending_events[] = ['foo' => 'bar'];
        $client->sendUnsentErrors();
        $this->assertCount(1, $httpClient->getRequests());
        $this->assertEquals(0, count($client->_pending_events));

        $client->store_errors_for_bulk_send = true;
        $client->captureMessage('foobar');
        $this->assertEquals(1, count($client->_pending_events));

        // step 3
        $client->onShutdown();
        $this->assertCount(2, $httpClient->getRequests());
        $this->assertEquals(0, count($client->_pending_events));
    }

    public function testSendChecksShouldCaptureOption()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $httpClient->expects($this->never())
            ->method('sendAsyncRequest');

        $config = new Configuration([
            'server' => 'http://public:secret@example.com/1',
            'install_default_breadcrumb_handlers' => false,
            'should_capture' => function () use (&$shouldCaptureCalled) {
                $shouldCaptureCalled = true;

                return false;
            },
        ]);

        $client = new Client($config, $httpClient, $requestFactory);

        $data = ['foo' => 'bar'];

        $client->send($data);

        $this->assertTrue($shouldCaptureCalled);
    }

    public function testSendFailsWhenNoServerIsConfigured()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        $httpClient->expects($this->never())
            ->method('sendAsyncRequest');

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $client = new Client(new Configuration(), $httpClient, $requestFactory);
        $data = ['foo' => 'bar'];

        $client->send($data);
    }

    public function test__construct_handlers()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        foreach ([true, false] as $u1) {
            foreach ([true, false] as $u2) {
                $client = new Dummy_Raven_Client(
                    new Configuration([
                        'install_default_breadcrumb_handlers' => $u1,
                        'install_shutdown_handler' => $u2,
                    ]),
                    $httpClient,
                    $requestFactory
                );

                $this->assertEquals($u1, $client->dummy_breadcrumbs_handlers_has_set);
                $this->assertEquals($u2, $client->dummy_shutdown_handlers_has_set);
            }
        }
    }

    public function testGet_user_data()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        // step 1
        $client = new Dummy_Raven_Client(new Configuration(), $httpClient, $requestFactory);
        $output = $client->get_user_data();
        $this->assertInternalType('array', $output);
        $this->assertArrayHasKey('user', $output);
        $this->assertArrayHasKey('id', $output['user']);
        $session_old = $_SESSION;

        // step 2
        $session_id = session_id();
        session_write_close();
        session_id('');
        $output = $client->get_user_data();
        $this->assertInternalType('array', $output);
        $this->assertEquals(0, count($output));

        // step 3
        session_id($session_id);
        @session_start(['use_cookies' => false]);
        $_SESSION = ['foo' => 'bar'];
        $output = $client->get_user_data();
        $this->assertInternalType('array', $output);
        $this->assertArrayHasKey('user', $output);
        $this->assertArrayHasKey('id', $output['user']);
        $this->assertArrayHasKey('data', $output['user']);
        $this->assertArrayHasKey('foo', $output['user']['data']);
        $this->assertEquals('bar', $output['user']['data']['foo']);
        $_SESSION = $session_old;
    }

    public function testCaptureLevel()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        foreach ([Client::MESSAGE_LIMIT * 3, 100] as $length) {
            $message = '';

            for ($i = 0; $i < $length; ++$i) {
                $message .= chr($i % 256);
            }

            $client = ClientBuilder::create()->getClient();
            $client->store_errors_for_bulk_send = true;

            $client->capture(['message' => $message]);

            $this->assertCount(1, $client->_pending_events);
            $this->assertEquals('error', $client->_pending_events[0]['level']);
            $this->assertEquals(substr($message, 0, min(\Raven\Client::MESSAGE_LIMIT, $length)), $client->_pending_events[0]['message']);
            $this->assertArrayNotHasKey('release', $client->_pending_events[0]);
        }

        $client = new Dummy_Raven_Client(new Configuration(), $httpClient, $requestFactory);
        $client->store_errors_for_bulk_send = true;

        $client->capture(['message' => 'foobar']);

        $input = $client->get_http_data();

        $this->assertEquals($input['request'], $client->_pending_events[0]['request']);
        $this->assertArrayNotHasKey('release', $client->_pending_events[0]);

        $client = new Dummy_Raven_Client(new Configuration(), $httpClient, $requestFactory);
        $client->store_errors_for_bulk_send = true;

        $client->capture(['message' => 'foobar', 'request' => ['foo' => 'bar']]);

        $this->assertEquals(['foo' => 'bar'], $client->_pending_events[0]['request']);
        $this->assertArrayNotHasKey('release', $client->_pending_events[0]);

        foreach ([false, true] as $u1) {
            foreach ([false, true] as $u2) {
                $options = [];

                if ($u1) {
                    $options['release'] = 'foo';
                }

                if ($u2) {
                    $options['current_environment'] = 'bar';
                }

                $client = new Client(new Configuration($options), $httpClient, $requestFactory);
                $client->store_errors_for_bulk_send = true;

                $client->capture(['message' => 'foobar']);

                if ($u1) {
                    $this->assertEquals('foo', $client->_pending_events[0]['release']);
                } else {
                    $this->assertArrayNotHasKey('release', $client->_pending_events[0]);
                }

                if ($u2) {
                    $this->assertEquals('bar', $client->_pending_events[0]['environment']);
                }
            }
        }
    }

    public function testCaptureNoUserAndRequest()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client_No_Http(new Configuration(['install_default_breadcrumb_handlers' => false]), $httpClient, $requestFactory);
        $client->store_errors_for_bulk_send = true;

        $session_id = session_id();

        session_write_close();
        session_id('');

        $client->capture(['user' => '', 'request' => '']);

        $this->assertCount(1, $client->_pending_events);
        $this->assertArrayNotHasKey('user', $client->_pending_events[0]);
        $this->assertArrayNotHasKey('request', $client->_pending_events[0]);

        session_id($session_id);
        @session_start(['use_cookies' => false]);
    }

    public function testCaptureAutoLogStacks()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->capture(['auto_log_stacks' => true], true);

        $this->assertCount(1, $client->_pending_events);
        $this->assertArrayHasKey('stacktrace', $client->_pending_events[0]);
        $this->assertInternalType('array', $client->_pending_events[0]['stacktrace']['frames']);
    }

    /**
     * @dataProvider sampleRateAbsoluteDataProvider
     */
    public function testSampleRateAbsolute($options)
    {
        $httpClient = new MockClient();

        $client = ClientBuilder::create($options)
            ->setHttpClient($httpClient)
            ->getClient();

        for ($i = 0; $i < 10; ++$i) {
            $client->captureMessage('foobar');
        }

        switch ($options['sample_rate']) {
            case 0:
                $this->assertEmpty($httpClient->getRequests());
                break;
            case 1:
                $this->assertNotEmpty($httpClient->getRequests());
                break;
        }
    }

    public function sampleRateAbsoluteDataProvider()
    {
        return [
            [
                [
                    'server' => 'http://public:secret@example.com/1',
                    'sample_rate' => 0,
                ],
            ],
            [
                [
                    'server' => 'http://public:secret@example.com/1',
                    'sample_rate' => 1,
                ],
            ],
        ];
    }

    public function testSetAllObjectSerialize()
    {
        $client = ClientBuilder::create()->getClient();

        $client->setAllObjectSerialize(true);

        $this->assertTrue($client->getSerializer()->getAllObjectSerialize());
        $this->assertTrue($client->getReprSerializer()->getAllObjectSerialize());

        $client->setAllObjectSerialize(false);

        $this->assertFalse($client->getSerializer()->getAllObjectSerialize());
        $this->assertFalse($client->getReprSerializer()->getAllObjectSerialize());
    }
}

class PromiseMock implements Promise
{
    private $result;

    private $state;

    private $onFullfilledCallbacks = [];

    private $onRejectedCallbacks = [];

    public function __construct($result, $state = self::FULFILLED)
    {
        $this->result = $result;
        $this->state = $state;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onFulfilled) {
            $this->onFullfilledCallbacks[] = $onFulfilled;
        }

        if (null !== $onRejected) {
            $this->onRejectedCallbacks[] = $onRejected;
        }

        return $this;
    }

    public function getState()
    {
        return $this->state;
    }

    public function wait($unwrap = true)
    {
        switch ($this->state) {
            case self::FULFILLED: {
                foreach ($this->onFullfilledCallbacks as $onFullfilledCallback) {
                    $onFullfilledCallback($this->result);
                }

                break;
            }
            case self::REJECTED: {
                foreach ($this->onRejectedCallbacks as $onRejectedCallback) {
                    $onRejectedCallback($this->result);
                }

                break;
            }
        }

        if ($unwrap) {
            return $this->result;
        }

        return null;
    }
}
