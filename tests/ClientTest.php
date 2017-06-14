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

use Composer\CaBundle\CaBundle;
use Http\Client\Common\FlexibleHttpClient;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Raven\Breadcrumbs\ErrorHandler;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Configuration;
use Raven\Processor\SanitizeDataProcessor;
use Raven\Util\JSON;

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

    public function buildCurlCommand($url, $data, $headers)
    {
        return parent::buildCurlCommand($url, $data, $headers);
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
     * Expose the current url method to test it
     *
     * @return string
     */
    public function test_get_current_url()
    {
        return $this->get_current_url();
    }
}

class Dummy_Raven_Client_With_Overrided_Direct_Send extends \Raven\Client
{
    public $_send_http_asynchronous_curl_exec_called = false;
    public $_send_http_synchronous = false;
    public $_set_url;
    public $_set_data;
    public $_set_headers;
    public static $_close_curl_resource_called = false;

    public function send_http_asynchronous_curl_exec($url, $data, $headers)
    {
        $this->_send_http_asynchronous_curl_exec_called = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;
    }

    public function send_http_synchronous($url, $data, $headers)
    {
        $this->_send_http_synchronous = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;
    }

    public function get_curl_options()
    {
        $options = parent::get_curl_options();

        return $options;
    }

    public function get_curl_handler()
    {
        return $this->_curl_handler;
    }

    public function set_curl_handler(\Raven\CurlHandler $value)
    {
        $this->_curl_handler = $value;
    }

    public function close_curl_resource()
    {
        parent::close_curl_resource();
        self::$_close_curl_resource_called = true;
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
        if (is_null(self::$_test_data)) {
            self::$_test_data = '';
            for ($i = 0; $i < 128; $i++) {
                self::$_test_data .= chr(mt_rand(ord('a'), ord('z')));
            }
        }

        return self::$_test_data;
    }

    public static function test_filename()
    {
        return sys_get_temp_dir().'/clientraven.tmp';
    }

    protected function buildCurlCommand($url, $data, $headers)
    {
        return 'echo '.escapeshellarg(self::get_test_data()).' > '.self::test_filename();
    }
}

class Dummy_Raven_CurlHandler extends \Raven\CurlHandler
{
    public $_set_url;
    public $_set_data;
    public $_set_headers;
    public $_enqueue_called = false;
    public $_join_called = false;

    public function __construct($options = [], $join_timeout = 5)
    {
        parent::__construct($options, $join_timeout);
    }

    public function enqueue($url, $data = null, $headers = [])
    {
        $this->_enqueue_called = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;

        return 0;
    }

    public function join($timeout = null)
    {
        $this->_join_called = true;
    }
}

class ClientTest extends \PHPUnit_Framework_TestCase
{
    protected static $_folder = null;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$_folder = sys_get_temp_dir().'/sentry_server_'.microtime(true);
        mkdir(self::$_folder);

        // Root CA #A1
        // Сертификат CA #A4, signed by CA #A1 (end-user certificate)
        $temporary_openssl_file_conf_alt = self::$_folder.'/openssl-config-alt.tmp';
        file_put_contents($temporary_openssl_file_conf_alt, sprintf('HOME = .
RANDFILE = $ENV::HOME/.rnd
[ req ]
distinguished_name = req_distinguished_name
req_extensions = v3_req

[ req_distinguished_name ]

[ v3_req ]
subjectAltName         = DNS:*.org, DNS:127.0.0.1, DNS:%s
keyUsage               = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment, keyAgreement, keyCertSign
basicConstraints       = CA:FALSE
subjectKeyIdentifier   = hash
authorityKeyIdentifier = keyid,issuer
extendedKeyUsage       = serverAuth,clientAuth
certificatePolicies    = @polsect

[polsect]
policyIdentifier       = 1.2.3.4.5.6.7
userNotice.1           = @notice

[notice]
explicitText           = "UTF8:Please add this certificate to black list"
organization           = "Sentry"
', strtolower(gethostname())));
        $certificate_offset = mt_rand(0, 10000) << 16;

        // CA #A1
        $csr_a1 = openssl_csr_new([
            "countryName" => "US",
            "localityName" => "Nowhere",
            "organizationName" => "Sentry",
            "organizationalUnitName" => "Development Center. CA #A1",
            "commonName" => "Sentry: Test HTTP Server: CA #A1",
            "emailAddress" => "noreply@sentry.example.com",
        ], $ca_a1_pair, [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA, // currently only RSA works
            'encrypt_key' => true,
        ]);
        if ($csr_a1 === false) {
            throw new \Exception("Can not create CSR pair A1");
        }
        $crt_a1 = openssl_csr_sign($csr_a1, null, $ca_a1_pair, 1, [
            "digest_alg" => "sha256",
        ], hexdec("0A1") + $certificate_offset);
        if ($crt_a1 === false) {
            throw new \Exception("Can not create certificate A1");
        }

        /** @noinspection PhpUndefinedVariableInspection */
        openssl_x509_export($crt_a1, $certout);
        openssl_pkey_export($ca_a1_pair, $pkeyout);
        file_put_contents(self::$_folder.'/crt_a1.crt', $certout, LOCK_EX);
        file_put_contents(self::$_folder.'/crt_a1.pem', $pkeyout, LOCK_EX);
        openssl_pkey_export($ca_a1_pair, $pkeyout, 'password');
        file_put_contents(self::$_folder.'/crt_a1.p.pem', $pkeyout, LOCK_EX);
        unset($csr_a1, $certout, $pkeyout);

        // CA #A4
        $csr_a4 = openssl_csr_new([
            "countryName" => "US",
            "localityName" => "Nowhere",
            "organizationName" => "Sentry",
            "organizationalUnitName" => "Development Center",
            "commonName" => "127.0.0.1",
            "emailAddress" => "noreply@sentry.example.com",
        ], $ca_a4_pair, [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA, // currently only RSA works
            'encrypt_key' => true,
        ]);
        if ($csr_a4 === false) {
            throw new \Exception("Can not create CSR pair A4");
        }
        $crt_a4 = openssl_csr_sign($csr_a4, file_get_contents(self::$_folder.'/crt_a1.crt'),
            $ca_a1_pair, 1, [
                "digest_alg" => "sha256",
                'config' => $temporary_openssl_file_conf_alt,
                'x509_extensions' => 'v3_req',
            ], hexdec("0A4") + $certificate_offset);
        if ($crt_a4 === false) {
            throw new \Exception("Can not create certificate A4");
        }

        /** @noinspection PhpUndefinedVariableInspection */
        openssl_x509_export($crt_a4, $certout);
        openssl_pkey_export($ca_a4_pair, $pkeyout);
        file_put_contents(self::$_folder.'/crt_a4.crt', $certout, LOCK_EX);
        file_put_contents(self::$_folder.'/crt_a4.c.crt',
            $certout."\n".file_get_contents(self::$_folder.'/crt_a1.crt'), LOCK_EX);
        file_put_contents(self::$_folder.'/crt_a4.pem', $pkeyout, LOCK_EX);
        openssl_pkey_export($ca_a4_pair, $pkeyout, 'password');
        file_put_contents(self::$_folder.'/crt_a4.p.pem', $pkeyout, LOCK_EX);
        unset($csr_a4, $certout, $pkeyout);
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

        $this->assertEquals('1.2.3', $client->getConfig()->getRelease());

        $client->store_errors_for_bulk_send = true;

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
            'level' => Dummy_Raven_Client::WARNING,
            'extra' => ['foo' => 'bar']
        ]);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals(Dummy_Raven_Client::WARNING, $client->_pending_events[0]['level']);
        $this->assertEquals('bar', $client->_pending_events[0]['extra']['foo']);
        $this->assertEquals('Test Message foo', $client->_pending_events[0]['message']);
    }

    public function testCaptureMessageHandlesLevelAsThirdArg()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->captureMessage('Test Message %s', ['foo'], Dummy_Raven_Client::WARNING);

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals(Dummy_Raven_Client::WARNING, $client->_pending_events[0]['level']);
        $this->assertEquals('Test Message foo', $client->_pending_events[0]['message']);
    }

    /**
     * @covers \Raven\Client::captureException
     */
    public function testCaptureExceptionSetsInterfaces()
    {
        # TODO: it'd be nice if we could mock the stacktrace extraction function here
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
        $this->assertFalse(empty($frame['pre_context']));
        $this->assertFalse(empty($frame['post_context']));
    }

    public function testCaptureExceptionChainedException()
    {
        # TODO: it'd be nice if we could mock the stacktrace extraction function here
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
        $this->assertEquals(Client::WARNING, $client->_pending_events[0]['level']);
        $this->assertEquals(Client::INFO, $client->_pending_events[1]['level']);
        $this->assertEquals(Client::ERROR, $client->_pending_events[2]['level']);
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
                    'stamp'           => '1c',
                ],
                'cookies' => [
                    'donut'           => 'chocolat',
                ],
                'headers' => [
                    'Host'            => 'getsentry.com',
                    'Accept'          => 'text/html',
                    'Accept-Charset'  => 'utf-8',
                    'Cookie'          => 'cupcake: strawberry',
                    'Content-Type'    => 'text/xml',
                    'Content-Length'  => '99',
                ],
            ]
        ];

        $config = new Configuration([
            'install_default_breadcrumb_handlers' => false,
            'install_shutdown_handler' => false,
        ]);

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client($config, new FlexibleHttpClient($httpClient), $messageFactory);

        $this->assertEquals($expected, $client->get_http_data());
    }

    public function testCaptureMessageWithUserData()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $client->user_context([
            'id' => 'unique_id',
            'email' => 'foo@example.com',
            'username' => 'my_user'
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

        require_once(__DIR__ . '/Fixtures/code/Latin1File.php');

        $frames = $client->_pending_events[0]['exception']['values'][0]['stacktrace']['frames'];

        $utf8String = "// äöü";
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

        /** @var $undefined */
        /** @noinspection PhpExpressionResultUnusedInspection */
        @$undefined;

        $client->captureLastError();

        $this->assertCount(1, $client->_pending_events);
        $this->assertEquals('Undefined variable: undefined', $client->_pending_events[0]['exception']['values'][0]['value']);
    }

    public function testGetLastEventID()
    {
        $client = ClientBuilder::create()->getClient();
        $client->capture([
            'message' => 'test',
            'event_id' => 'abc'
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

    public function testSendCallback()
    {
        $config = new Configuration([
            'should_capture' => function ($data) {
                $this->assertEquals('test', $data['message']);

                return false;
            }
        ]);

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client($config, new FlexibleHttpClient($httpClient), $messageFactory);

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
                    1, [2], 3
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['extra' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3
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
                    $new_value[$property2->getName()] = 'Array of length '.count($sub_value);
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
                    1, [2], 3
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['request' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3
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
                    ], 3
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
                    ], 3
                ],
            ],
        ]], $data);
    }

    public function testBuildCurlCommandEscapesInput()
    {
        $data = '{"foo": "\'; ls;"}';
        $client = ClientBuilder::create(['timeout' => 5])->getClient();
        $result = $client->buildCurlCommand('http://foo.com', $data, []);
        $folder = CaBundle::getSystemCaRootBundlePath();
        $this->assertEquals('curl -X POST -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 --cacert \''.$folder.'\' > /dev/null 2>&1 &', $result);

        $client->getConfig()->setSslCaFile(null);

        $result = $client->buildCurlCommand('http://foo.com', $data, []);

        $this->assertEquals('curl -X POST -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 > /dev/null 2>&1 &', $result);

        $result = $client->buildCurlCommand('http://foo.com', $data, ['key' => 'value']);

        $this->assertEquals('curl -X POST -H \'key: value\' -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 > /dev/null 2>&1 &', $result);

        $client->getConfig()->setSslVerificationEnabled(false);

        $result = $client->buildCurlCommand('http://foo.com', $data, []);

        $this->assertEquals('curl -X POST -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 -k > /dev/null 2>&1 &', $result);

        $result = $client->buildCurlCommand('http://foo.com', $data, ['key' => 'value']);

        $this->assertEquals('curl -X POST -H \'key: value\' -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 -k > /dev/null 2>&1 &', $result);
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
     * Set the server array to the test values, check the current url
     *
     * @dataProvider currentUrlProvider
     * @param array $serverVars
     * @param array $options
     * @param string $expected - the url expected
     * @param string $message - fail message
     * @covers \Raven\Client::get_current_url
     * @covers \Raven\Client::isHttps
     */
    public function testCurrentUrl($serverVars, $options, $expected, $message)
    {
        $_SERVER = $serverVars;

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client(new Configuration($options), new FlexibleHttpClient($httpClient), $messageFactory);
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
        return [
            [
                [],
                [],
                null,
                'No url expected for empty REQUEST_URI'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                ],
                [],
                'http://example.com/',
                'The url is expected to be http with the request uri'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'HTTPS' => 'on'
                ],
                [],
                'https://example.com/',
                'The url is expected to be https because of HTTPS on'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'SERVER_PORT' => '443'
                ],
                [],
                'https://example.com/',
                'The url is expected to be https because of the server port'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'X-FORWARDED-PROTO' => 'https'
                ],
                [],
                'http://example.com/',
                'The url is expected to be http because the X-Forwarded header is ignored'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'X-FORWARDED-PROTO' => 'https'
                ],
                ['trust_x_forwarded_proto' => true],
                'https://example.com/',
                'The url is expected to be https because the X-Forwarded header is trusted'
            ]
        ];
    }

    /**
     * @covers \Raven\Client::uuid4()
     */
    public function testUuid4()
    {
        $method = new \ReflectionMethod('\\Raven\\Client', 'uuid4');
        $method->setAccessible(true);
        for ($i = 0; $i < 1000; $i++) {
            $this->assertRegExp('/^[0-9a-z-]+$/', $method->invoke(null));
        }
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
            ['_lasterror', null, null,],
            ['_lasterror', null, 'value',],
            ['_lasterror', null, mt_rand(100, 999),],
            ['_last_sentry_error', null, (object)['error' => 'test',],],
            ['_last_event_id', null, mt_rand(100, 999),],
            ['_last_event_id', null, 'value',],
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
        if (is_null($function_name)) {
            $function_name = str_replace('_', '', $property_name);
        }

        $method_get_name = 'get'.$function_name;
        $method_set_name = 'set'.$function_name;
        $property = new \ReflectionProperty('\\Raven\\Client', $property_name);
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
            $reflection = new \ReflectionMethod('\\Raven\Client', $method_get_name);
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
        $predefined_values = ['debug', 'info', 'warning', 'warning', 'error', 'fatal', ];

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
        $client->registerSeverityMap([123456 => 'foo', ]);
        $this->assertMixedValueAndArray([123456 => 'foo'], $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 4
        $client->registerSeverityMap([E_USER_ERROR => 'bar', ]);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 5
        $client->registerSeverityMap([E_USER_ERROR => 'bar', 123456 => 'foo', ]);
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
        $httpClient = new FlexibleHttpClient(new \Http\Mock\Client());

        /** @var RequestInterface|\PHPUnit_Framework_MockObject_MockObject $request */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $messageFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            new Configuration([
                'server' => 'http://public:secret@example.com/1',
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            ]),
            $httpClient,
            $messageFactory
        );
        $this->assertEquals(0, count($client->_pending_events));
        $client->_pending_events[] = ['foo' => 'bar'];
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
            new Configuration([
                'server' => 'http://public:secret@example.com/1',
                'curl_method' => 'async',
                'install_default_breadcrumb_handlers' => false,
            ]),
            $httpClient,
            $messageFactory
        );
        $ch = new Dummy_Raven_CurlHandler();
        $client->set_curl_handler($ch);
        $client->captureMessage('foobar');
        $client->onShutdown();
        $client = null;
        $this->assertTrue($ch->_join_called);
    }

    public function testSendChecksShouldCaptureOption()
    {
        $this->_closure_called = false;

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            new Configuration([
                'server' => 'http://public:secret@example.com/1',
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
                'should_capture' => function () {
                    $this->_closure_called = true;

                    return false;
                },
            ]),
            new FlexibleHttpClient($httpClient),
            $messageFactory
        );

        $data = ['foo' => 'bar'];

        $client->send($data);

        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous || $client->_send_http_asynchronous_curl_exec_called);
    }

    public function testSendFailsWhenNoServerIsConfigured()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        $httpClient->expects($this->never())
            ->method('sendAsyncRequest');

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Client(new Configuration(), new FlexibleHttpClient($httpClient), $messageFactory);
        $data = ['foo' => 'bar'];

        $client->send($data);
    }

    public function testNonWorkingSendSetTransport()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            new Configuration([
                'server' => 'http://public:secret@example.com/1',
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            ]),
            new FlexibleHttpClient($httpClient),
            $messageFactory
        );

        $this->_closure_called = false;

        $client->getConfig()->setTransport([$this, 'stabClosureNull']);

        $this->assertFalse($this->_closure_called);

        $data = ['foo' => 'bar'];

        $client->send($data);

        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous || $client->_send_http_asynchronous_curl_exec_called);

        $this->_closure_called = false;

        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            new Configuration([
                'server' => 'http://public:secret@example.com/1',
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
                'should_capture' => function () {
                    $this->_closure_called = true;

                    return false;
                }
            ]),
            new FlexibleHttpClient($httpClient),
            $messageFactory
        );

        $this->assertFalse($this->_closure_called);

        $data = ['foo' => 'bar'];

        $client->send($data);

        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous || $client->_send_http_asynchronous_curl_exec_called);
    }

    public function test__construct_handlers()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        foreach ([true, false] as $u1) {
            foreach ([true, false] as $u2) {
                $client = new Dummy_Raven_Client(
                    new Configuration([
                        'install_default_breadcrumb_handlers' => $u1,
                        'install_shutdown_handler' => $u2,
                    ]),
                    new FlexibleHttpClient($httpClient),
                    $messageFactory
                );

                $this->assertEquals($u1, $client->dummy_breadcrumbs_handlers_has_set);
                $this->assertEquals($u2, $client->dummy_shutdown_handlers_has_set);
            }
        }
    }

    public function test__destruct_calls_close_functions()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            new Configuration([
                'server' => 'http://public:secret@example.com/1',
                'install_default_breadcrumb_handlers' => false,
                'install_shutdown_handler' => false,
            ]),
            new FlexibleHttpClient($httpClient),
            $messageFactory
        );

        $client::$_close_curl_resource_called = false;

        $client->close_all_children_link();

        unset($client);

        $this->assertTrue(Dummy_Raven_Client_With_Overrided_Direct_Send::$_close_curl_resource_called);
    }

    public function testGet_user_data()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        // step 1
        $client = new Dummy_Raven_Client(new Configuration(), new FlexibleHttpClient($httpClient), $messageFactory);
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
        @session_start(['use_cookies' => false, ]);
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

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        foreach ([Client::MESSAGE_LIMIT * 3, 100] as $length) {
            $message = '';

            for ($i = 0; $i < $length; $i++) {
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

        $client = new Dummy_Raven_Client(new Configuration(), new FlexibleHttpClient($httpClient), $messageFactory);
        $client->store_errors_for_bulk_send = true;

        $client->capture(['message' => 'foobar']);

        $input = $client->get_http_data();

        $this->assertEquals($input['request'], $client->_pending_events[0]['request']);
        $this->assertArrayNotHasKey('release', $client->_pending_events[0]);

        $client = new Dummy_Raven_Client(new Configuration(), new FlexibleHttpClient($httpClient), $messageFactory);
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

                $client = new Client(new Configuration($options), new FlexibleHttpClient($httpClient), $messageFactory);
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

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client_No_Http(new Configuration(['install_default_breadcrumb_handlers' => false]), new FlexibleHttpClient($httpClient), $messageFactory);
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

    public function testCaptureNonEmptyBreadcrumb()
    {
        $client = ClientBuilder::create()->getClient();
        $client->store_errors_for_bulk_send = true;

        $ts1 = microtime(true);

        $client->breadcrumbs->record(['foo' => 'bar']);
        $client->breadcrumbs->record(['honey' => 'clover']);

        $client->capture([]);

        foreach ($client->_pending_events[0]['breadcrumbs'] as &$crumb) {
            $this->assertGreaterThanOrEqual($ts1, $crumb['timestamp']);

            unset($crumb['timestamp']);
        }

        $this->assertEquals([
            ['foo' => 'bar'],
            ['honey' => 'clover'],
        ], $client->_pending_events[0]['breadcrumbs']);
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

    public function testSend_http_asynchronous_curl_exec()
    {
        /** @var RequestInterface|\PHPUnit_Framework_MockObject_MockObject $request */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $messageFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $client = new Dummy_Raven_Client_With_Sync_Override(
            new Configuration([
                'server' => 'http://public:secret@example.com/1',
                'curl_method' => 'exec',
                'install_default_breadcrumb_handlers' => false,
            ]),
            new FlexibleHttpClient(new \Http\Mock\Client()),
            $messageFactory
        );

        if (file_exists(Dummy_Raven_Client_With_Sync_Override::test_filename())) {
            unlink(Dummy_Raven_Client_With_Sync_Override::test_filename());
        }

        $client->captureMessage('foobar');

        $test_data = Dummy_Raven_Client_With_Sync_Override::get_test_data();

        $this->assertStringEqualsFile(Dummy_Raven_Client_With_Sync_Override::test_filename(), $test_data . "\n");
    }

    public function testClose_curl_resource()
    {
        $client = ClientBuilder::create()->getClient();

        $reflection = new \ReflectionProperty(Client::class, '_curl_instance');
        $reflection->setAccessible(true);

        $ch = curl_init();

        $reflection->setValue($client, $ch);

        unset($ch);

        $this->assertInternalType('resource', $reflection->getValue($client));

        $client->close_curl_resource();

        $this->assertNull($reflection->getValue($client));
    }

    public function testSampleRateAbsolute()
    {
        /** @var RequestInterface|\PHPUnit_Framework_MockObject_MockObject $request */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $messageFactory->expects($this->any())
            ->method('createRequest')
            ->willReturn($request);

        $config = new Configuration([
            'server' => 'http://public:secret@example.com/1',
            'curl_method' => 'foobar',
            'install_default_breadcrumb_handlers' => false,
            'sample_rate' => 0,
        ]);

        $httpClient = new \Http\Mock\Client();
        $client = new Client($config, new FlexibleHttpClient($httpClient), $messageFactory);

        for ($i = 0; $i < 1000; $i++) {
            $client->captureMessage('foobar');

            $this->assertEmpty($httpClient->getRequests());
        }

        $config->setSampleRate(1);

        for ($i = 0; $i < 1000; $i++) {
            $client->captureMessage('foobar');

            $this->assertNotEmpty($httpClient->getRequests());
        }
    }

    public function testSampleRatePrc()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            new Configuration([
                'sample_rate' => 0.5,
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            ]),
            new FlexibleHttpClient($httpClient),
            $messageFactory
        );

        $u_true = false;
        $u_false = false;

        for ($i = 0; $i < 1000; $i++) {
            $client->captureMessage('foobar');

            if ($client->_send_http_synchronous || $client->_send_http_asynchronous_curl_exec_called) {
                $u_true = true;
            } else {
                $u_false = true;
            }

            if ($u_true || $u_false) {
                return;
            }
        }

        $this->fail('sample_rate=0.5 can not produce fails and successes at the same time');
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

    /**
     * @return int
     */
    protected static function get_port()
    {
        exec('netstat -n -t', $buf);
        $current_used_ports = [];
        foreach ($buf as $line) {
            list(, , , $local_addr) = preg_split('_\\s+_', $line, 7);
            if (preg_match('_:([0-9]+)$_', $local_addr, $a)) {
                $current_used_ports[] = (int)$a[1];
            }
        }
        $current_used_ports = array_unique($current_used_ports);
        sort($current_used_ports);

        do {
            $port = mt_rand(55000, 60000);
        } while (in_array($port, $current_used_ports));

        return $port;
    }

    public function dataDirectSend()
    {
        $data = [];

        $block1 = [];
        $block1[] = [
            'options'        => [
                'server' => 'http://login:password@127.0.0.1:{port}/5',
            ],
            'server_options' => [],
            'timeout'        => 0,
            'is_failed'      => false,
        ];
        $block1[] = [
            'options'        => [
                'server'         => 'http://login:password@127.0.0.1:{port}/5',
                'curl_method' => 'async',
            ],
            'server_options' => [],
            'timeout'        => 0,
            'is_failed'      => false,
        ];
        $block1[] = [
            'options'        => [
                'server'         => 'http://login:password@127.0.0.1:{port}/5',
                'curl_method' => 'exec',
            ],
            'server_options' => [],
            'timeout'        => 5,
            'is_failed'      => false,
        ];

        $j = count($block1);
        for ($i = 0; $i < $j; $i++) {
            $datum = $block1[$i];
            $datum['server_options']['http_code'] = 403;
            $datum['is_failed'] = true;
            $block1[] = $datum;
        }

        $block_ssl = [['options' => [], 'server_options' => [], 'timeout' => 0, 'is_failed' => false]];
        $block_ssl[] = [
            'options'        => [
                'server'     => 'http://login:password@127.0.0.1:{port}/5',
                'ssl_ca_file' => '{folder}/crt_a1.crt',
            ],
            'server_options' => [
                'ssl_server_certificate_file' => '{folder}/crt_a4.c.crt',
                'ssl_server_key_file'         => '{folder}/crt_a4.pem',
                'is_ssl'                      => true,
            ],
            'timeout'        => 5,
            'is_failed'      => false,
        ];

        foreach ($block1 as $b1) {
            foreach ($block_ssl as $b2) {
                $datum = [
                    'options'        => array_merge(
                        isset($b1['options']) ? $b1['options'] : [],
                        isset($b2['options']) ? $b2['options'] : []
                    ),
                    'server_options' => array_merge(
                        isset($b1['server_options']) ? $b1['server_options'] : [],
                        isset($b2['server_options']) ? $b2['server_options'] : []
                    ),
                    'timeout'        => max($b1['timeout'], $b2['timeout']),
                    'is_failed'      => ($b1['is_failed'] or $b2['is_failed']),
                ];
                if (isset($datum['options']['ssl_ca_file'])) {
                    $datum['options']['server'] = str_replace('http://', 'https://', $datum['options']['server']);
                }

                $data[] = $datum;
            }
        }

        return $data;
    }

    /**
     * @param array   $sentry_options
     * @param array   $server_options
     * @param integer $timeout
     * @param boolean $is_failed
     *
     * @dataProvider dataDirectSend
     */
    public function testDirectSend($sentry_options, $server_options, $timeout, $is_failed)
    {
        foreach ($sentry_options as &$value) {
            if (is_string($value)) {
                $value = str_replace('{folder}', self::$_folder, $value);
            }
        }
        foreach ($server_options as &$value) {
            if (is_string($value)) {
                $value = str_replace('{folder}', self::$_folder, $value);
            }
            $value = str_replace('{folder}', self::$_folder, $value);
        }
        unset($value);

        $port = self::get_port();
        $sentry_options['server'] = str_replace('{port}', $port, $sentry_options['server']);
        $sentry_options['timeout'] = 10;

        $client = ClientBuilder::create($sentry_options)->getClient();
        $output_filename = tempnam(self::$_folder, 'output_http_');
        foreach (
            [
                'port'            => $port,
                'output_filename' => $output_filename,
            ] as $key => $value
        ) {
            $server_options[$key] = $value;
        }

        $filename = tempnam(self::$_folder, 'sentry_http_');
        file_put_contents($filename, serialize((object)$server_options), LOCK_EX);

        $cli_output_filename = tempnam(sys_get_temp_dir(), 'output_http_');
        exec(
            sprintf(
                'php '.__DIR__.'/bin/httpserver.php --config=%s >%s 2>&1 &',
                escapeshellarg($filename),
                escapeshellarg($cli_output_filename)
            )
        );
        $ts = microtime(true);
        $u = false;
        do {
            if (preg_match('_listen_i', file_get_contents($cli_output_filename))) {
                $u = true;
                break;
            }
        } while ($ts + 10 > microtime(true));
        $this->assertTrue($u, 'Can not start Test HTTP Server');
        unset($u, $ts);

        $extra = ['foo'.mt_rand(0, 10000) => microtime(true).':'.mt_rand(20, 100)];
        $event = $client->captureMessage(
            'Test Message', [], [
                'level' => Client::INFO,
                'extra' => $extra,
            ]
        );
        $client->sendUnsentErrors();
        $client->force_send_async_curl_events();
        if ($is_failed) {
            if (!isset($sentry_options['curl_method']) or
                !in_array($sentry_options['curl_method'], ['async', 'exec'])
            ) {
                if (isset($server_options['http_code'])) {
                    $this->assertNotNull($client->getLastError());
                    $this->assertNotNull($client->getLastSentryError());
                }
            }
        } else {
            $this->assertNotNull($event);
        }
        if ($timeout > 0) {
            usleep($timeout * 1000000);
        }
        $this->assertFileExists($output_filename);
        $buf = file_get_contents($output_filename);
        $server_input = unserialize($buf);
        $this->assertNotFalse($server_input);
        /** @var \NokitaKaze\TestHTTPServer\ClientDatum $connection */
        $connection = $server_input['connection'];
        $this->assertNotNull($connection);
        $this->assertEquals('/api/5/store/', $connection->request_url);

        $body = base64_decode($connection->blob_body);
        if (function_exists('gzuncompress')) {
            $new_body = gzuncompress($body);
            if ($new_body !== false) {
                $body = $new_body;
            }
            unset($new_body);
        }
        $body = json_decode($body);
        $this->assertEquals(5, $body->project);
        $this->assertEquals('Test Message', $body->message);
        $this->assertEquals($event, $body->event_id);
        $this->assertEquals($extra, (array)$body->extra);
        $this->assertEquals('info', $body->level);

        $this->assertRegExp(
            '|^Sentry sentry_timestamp=[0-9.]+,\\s+sentry_client=sentry\\-php/[a-z0-9.-]+,\\s+sentry_version=[0-9]+,'.
            '\\s+sentry_key=login,\\s+sentry_secret=password|',
            $connection->request_head_params['X-Sentry-Auth']
        );
    }
}
