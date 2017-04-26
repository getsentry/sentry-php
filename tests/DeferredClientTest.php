<?php

namespace Raven;

class Dummy_DeferredClient extends DeferredClient
{
    public $_send_remote_call_count = 0;
    public $_inner_limit_before_false = null;
    public static $_send_http_synchronous_answer = true;
    public static $event_ids = array();

    public static $store_pending_events_to_queue_called = false;

    public $_send_http_synchronous_last_url = null;
    public $_send_http_synchronous_last_data = null;
    public $_send_http_synchronous_last_headers = null;

    public function __construct($options_or_dsn = null, array $options = array())
    {
        $options['install_default_breadcrumb_handlers'] = Util::get(
            $options, 'install_default_breadcrumb_handlers', false
        );
        parent::__construct($options_or_dsn, $options);
    }

    protected function send_http_synchronous($url, $data, $headers = array())
    {
        $this->_send_remote_call_count++;
        $this->_send_http_synchronous_last_url = $url;
        $this->_send_http_synchronous_last_data = $data;
        $this->_send_http_synchronous_last_headers = $headers;
        if (!is_null($this->_inner_limit_before_false)) {
            if ($this->_inner_limit_before_false <= 0) {
                return false;
            }

            $this->_inner_limit_before_false--;
        }
        $decode = base64_decode($data);
        if (function_exists("gzcompress")) {
            $decode_b = gzuncompress($decode);
            if ($decode_b !== false) {
                $decode = $decode_b;
            }
        }
        $decode = json_decode($decode);
        if (isset($decode->event_id)) {
            self::$event_ids = $decode->event_id;
        }

        return self::$_send_http_synchronous_answer;
    }

    public function store_pending_events_to_queue()
    {
        self::$store_pending_events_to_queue_called = true;
        parent::store_pending_events_to_queue();
    }

    public function test_all_events_for_double()
    {
        if ($this->get_filenames_for_delete_count() == 0) {
            return true;
        }
        $reflection = new \ReflectionProperty('\\Raven\\DeferredClient', '_event_for_delete');
        $reflection->setAccessible(true);

        $ids = array();
        foreach ($reflection->getValue($this) as $data) {
            if (!is_null($data)) {
                foreach ($data as &$datum) {
                    if (in_array($datum['event_id'], $ids)) {
                        return false;
                    }
                    $ids[] = $datum['event_id'];
                }
            }
        }

        return true;
    }
}

class Raven_Tests_DeferredClientTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        parent::tearDown();
        $this->clear_raven_test_folder();
    }

    public function setUp()
    {
        parent::setUp();
        $this->clear_raven_test_folder();
        Dummy_DeferredClient::$event_ids = array();
    }

    private function clear_raven_test_folder()
    {
        $client = new Dummy_DeferredClient(
            null, array(
                'queue_subfolder_name' => 'raven_test_',
            )
        );
        $folder = $client->get_folder_with_queue();
        if (file_exists($folder)) {
            foreach (scandir($folder) as $file) {
                if (!in_array($file, array('.', '..'))) {
                    unlink($folder.'/'.$file);
                }
            }
            rmdir($folder);
        }
    }

    public function test__construct()
    {
        $client = new Dummy_DeferredClient();
        $this->assertTrue($client->store_errors_for_bulk_send);
        $this->assertFalse($client->getShutdownFunctionHasBeenSet());
    }

    public function test__destruct()
    {
        Dummy_DeferredClient::$store_pending_events_to_queue_called = false;
        $client = new Dummy_DeferredClient('http://public:secret@example.com/1');
        $client->close_all_children_link();
        unset($client);
        $this->assertTrue(Dummy_DeferredClient::$store_pending_events_to_queue_called);
    }

    public function testGettersAndSetters()
    {
        $client = new Dummy_DeferredClient();
        $data = array(
            array('_request_limit', null, 100,),
            array('_request_limit', null, '100', 100),
            array('_request_limit', null, null,),
            array('_request_limit', null, 100.0, 100),
            array('_queue_subfolder_name', null, 'tmp',),
            array('_queue_folder_name', null, sys_get_temp_dir(),),
            array('_deleted_queue_files_count', null, 100,),
            array('_deleted_queue_files_count', null, 0,),
            array('_non_processed_queue_files_count', null, 100,),
            array('_non_processed_queue_files_count', null, 0,),
            array('_sent_request_to_server_count', null, 100,),
            array('_sent_request_to_server_count', null, 0,),
            array('_sent_success_request_to_server_count', null, 100,),
            array('_sent_success_request_to_server_count', null, 0,),
        );
        foreach ($data as &$datum) {
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
        foreach ($data as &$datum) {
            $client = new Dummy_DeferredClient();
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
    }

    private function subTestGettersAndSettersDatum(DeferredClient $client, $datum)
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
        $property = new \ReflectionProperty('\\Raven\\DeferredClient', $property_name);
        $property->setAccessible(true);

        if (method_exists($client, $method_set_name)) {
            $setter_output = $client->$method_set_name($value_in);
            if (!is_null($setter_output) and is_object($setter_output)) {
                // chaining call test
                $this->assertEquals(spl_object_hash($client), spl_object_hash($setter_output));
            }
            $actual_value = $property->getValue($client);
            $this->assertEquals($value_out, $actual_value);
        }

        if (method_exists($client, $method_get_name)) {
            $property->setValue($client, $value_out);
            $reflection = new \ReflectionMethod('\\Raven\\DeferredClient', $method_get_name);
            if ($reflection->isPublic()) {
                $actual_value = $client->$method_get_name();
                $this->assertEquals($value_out, $actual_value);
            }
        }
    }

    private static function generate_hash_trivial($key_length)
    {
        $hashes = array_merge(range(0, 9), range('a', 'z'), array('_', '-'));
        $hashes_count_minus = count($hashes) - 1;
        $hash = '';
        for ($i = 0; $i < $key_length; $i++) {
            $hash .= $hashes[mt_rand(0, $hashes_count_minus)];
        }

        return $hash;
    }

    /**
     * @covers DeferredClient::get_folder_with_queue
     * @backupGlobals
     */
    public function testGet_folder_with_queue()
    {
        $client = new Dummy_DeferredClient();

        $client->setQueueFolderName(sys_get_temp_dir());
        $client->setQueueSubfolderName('raven');
        $this->assertEquals(sys_get_temp_dir().'/raven', $client->get_folder_with_queue());

        $client->setQueueFolderName(sys_get_temp_dir());
        $client->setQueueSubfolderName(null);
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertEquals(sys_get_temp_dir().'/example.com', $client->get_folder_with_queue());

        $client->setQueueFolderName(sys_get_temp_dir());
        $client->setQueueSubfolderName(null);
        unset($_SERVER['HTTP_HOST']);
        $this->assertEquals(sys_get_temp_dir().'/cli', $client->get_folder_with_queue());
    }

    /**
     * @covers DeferredClient::create_queue_folder_if_need
     * @backupGlobals
     */
    public function testCreate_queue_folder_if_need()
    {
        $this->create_queue_folder_if_need_subtest();
        $_SERVER['HOME'] = sys_get_temp_dir();
        $this->create_queue_folder_if_need_subtest();

        $filename = tempnam(sys_get_temp_dir(), 'sentry_test_');
        $this->assertFileExists($filename);
        $client = new Dummy_DeferredClient();
        $client->setQueueFolderName($filename);
        $client->setQueueSubfolderName('piece');
        $u = false;
        try {
            $reflection = new \ReflectionMethod('\\Raven\\DeferredClient', 'create_queue_folder_if_need');
            $reflection->setAccessible(true);
            $reflection->invoke($client);
        } catch (Exception $e) {
            $u = true;
        }
        $this->assertFileExists($filename);
        $this->assertTrue($u, '\\Raven\\DeferredClient::create_queue_folder_if_need didn\'t throw exception');
        unlink($filename);
    }

    /**
     * @covers DeferredClient::create_queue_folder_if_need
     */
    private function create_queue_folder_if_need_subtest()
    {
        $reflection = new \ReflectionMethod('\\Raven\\DeferredClient', 'create_queue_folder_if_need');
        $reflection->setAccessible(true);

        foreach (range(1, 3) as $length) {
            $client = new Dummy_DeferredClient();
            $pieces = array();
            $folders = array();
            $folder = sys_get_temp_dir();
            for ($i = 0; $i < $length; $i++) {
                $piece = self::generate_hash_trivial(mt_rand(3, 20));
                $pieces[] = $piece;
                $folder .= '/'.$piece;
                $folders[] = $folder;
            }
            $last_piece = self::generate_hash_trivial(mt_rand(3, 20));
            $folder .= '/'.$last_piece;
            $folders[] = $folder;
            unset($folder, $piece);
            $full_folder_name = sys_get_temp_dir().'/'.implode('/', $pieces);
            $client->setQueueFolderName($full_folder_name);
            $client->setQueueSubfolderName($last_piece);
            $reflection->invoke($client);

            $this->assertFileExists($client->get_folder_with_queue());

            $folders = array_reverse($folders);
            foreach ($folders as $folder) {
                rmdir($folder);
            }
            $this->assertFileNotExists($folders[count($folders) - 1]);
        }
    }

    public function testStore_pending_events_to_queue()
    {
        $client = new Dummy_DeferredClient(
            'http://public:secret@example.com/1', array(
                'queue_subfolder_name' => 'raven_test_',
            )
        );
        $folder = $client->get_folder_with_queue();
        if (file_exists($folder)) {
            foreach (scandir($folder) as $file) {
                if (!in_array($file, array('.', '..')) and (substr($file, -4) == '.rvn')) {
                    unlink($folder.'/'.$file);
                }
            }
        }
        $client->captureMessage('foobar');
        $ts1 = microtime(true);
        $client->store_pending_events_to_queue();
        $ts2 = microtime(true);
        $stored_file = null;
        foreach (scandir($folder) as $file) {
            if (preg_match('_^([0-9.]+)\\.rvn$_', $file, $a)) {
                $this->assertGreaterThanOrEqual($ts1, (double)$a[1]);
                $this->assertLessThanOrEqual($ts2, (double)$a[1]);
                $stored_file = $folder.'/'.$file;
                break;
            }
        }
        $this->assertNotNull($stored_file);

        $data = unserialize(file_get_contents($stored_file, LOCK_EX));
        $this->assertInternalType('object', $data);
        $this->assertObjectHasAttribute('data', $data);
        foreach ($data->data as $datum) {
            $this->assertInternalType('array', $datum);
            foreach (array('level', 'message', 'event_id', 'timestamp') as $key) {
                $this->assertArrayHasKey($key, $datum);
            }
        }
    }

    /**
     * @covers ::direct_send_to_server
     */
    public function testDirect_send_to_server_with_no_server()
    {
        $reflection = new \ReflectionMethod('\\Raven\\DeferredClient', 'direct_send_to_server');
        $reflection->setAccessible(true);
        $client = new Dummy_DeferredClient(
            null, array(
                'queue_subfolder_name' => 'raven_test_',
            )
        );
        $data = array();
        $client->_send_remote_call_count = 0;
        $this->assertFalse($reflection->invoke($client, $data));
        $this->assertEquals(0, $client->_send_remote_call_count);
    }

    /**
     * @covers DeferredClient::direct_send_to_server
     */
    public function testDirect_send_to_server_with_server()
    {
        $reflection = new \ReflectionMethod('\\Raven\\DeferredClient', 'direct_send_to_server');
        $reflection->setAccessible(true);
        $client = new Dummy_DeferredClient(
            'http://public:secret@example.com/1', array(
                'queue_subfolder_name' => 'raven_test_',
            )
        );
        $data = array();
        $client->_send_remote_call_count = 0;
        Dummy_DeferredClient::$_send_http_synchronous_answer = true;
        $this->assertTrue($reflection->invoke($client, $data));
        $this->assertEquals(1, $client->_send_remote_call_count);
    }

    /**
     * @covers DeferredClient::get_events_count
     * @covers DeferredClient::get_filenames_for_delete_count
     */
    public function testGet_events_count()
    {
        $reflection = new \ReflectionProperty('\\Raven\\DeferredClient', '_event_for_delete');
        $reflection->setAccessible(true);
        $client = new Dummy_DeferredClient('http://public:secret@example.com/1');

        $reflection->setValue($client, array());
        $this->assertEquals(0, $client->get_filenames_for_delete_count());
        $this->assertEquals(0, $client->get_events_count());

        $reflection->setValue($client, array(array(null), array(null)));
        $this->assertEquals(2, $client->get_filenames_for_delete_count());
        $this->assertEquals(2, $client->get_events_count());

        $reflection->setValue($client, array(array(null, null), array(null)));
        $this->assertEquals(2, $client->get_filenames_for_delete_count());
        $this->assertEquals(3, $client->get_events_count());

        $reflection->setValue($client, array(null, array(null)));
        $this->assertEquals(2, $client->get_filenames_for_delete_count());
        $this->assertEquals(1, $client->get_events_count());

        $reflection->setValue($client, array(null, array(null), array(null)));
        $this->assertEquals(3, $client->get_filenames_for_delete_count());
        $this->assertEquals(2, $client->get_events_count());
    }

    public function testRead_all_queue_non_existed_folder()
    {
        $reflection = new \ReflectionProperty('\\Raven\\DeferredClient', '_event_for_delete');
        $reflection->setAccessible(true);
        $client = new Dummy_DeferredClient();
        $client->setQueueSubfolderName('long_non_existed_folder_'.self::generate_hash_trivial(20));
        $client->read_all_queue();
        $this->assertEquals(0, $client->get_filenames_for_delete_count());
        $this->assertEquals(0, $client->get_events_count());
    }

    public function testRead_all_queue_broken_data()
    {
        $reflection = new \ReflectionProperty('\\Raven\\DeferredClient', '_event_for_delete');
        $reflection->setAccessible(true);
        $client = new Dummy_DeferredClient();
        $client->setQueueFolderName(sys_get_temp_dir());
        $client->setQueueSubfolderName('raven_test_');
        $client->captureMessage('foobar');
        $client->store_pending_events_to_queue();
        file_put_contents($client->get_folder_with_queue().'/12346798.123.rvn', serialize(null), LOCK_EX);

        $client = new Dummy_DeferredClient();
        $client->setQueueFolderName(sys_get_temp_dir());
        $client->setQueueSubfolderName('raven_test_');
        $client->read_all_queue();
        $this->assertEquals(1, $client->get_filenames_for_delete_count());
        $this->assertEquals(1, $client->get_events_count());
    }

    /**
     * @covers DeferredClient::read_all_queue
     * @covers DeferredClient::send_all_loaded_events_from_queue
     */
    public function testRead_all_queue_store()
    {
        $reflection = new \ReflectionProperty('\\Raven\\DeferredClient', '_event_for_delete');
        $reflection->setAccessible(true);
        $client2 = new Dummy_DeferredClient(
            null, array(
                'queue_subfolder_name' => 'raven_test_',
                'queue_folder_name'    => sys_get_temp_dir(),
            )
        );
        for ($i = 0; $i < 3; $i++) {
            $client = new Dummy_DeferredClient(
                null, array(
                    'queue_subfolder_name' => 'raven_test_',
                    'queue_folder_name'    => sys_get_temp_dir(),
                )
            );
            $client->captureMessage(
                'Client #1', array(), array(
                    'extra' => array(
                        'i' => $i,
                    ),
                )
            );
            $client2->captureMessage(
                'Client #2', array(), array(
                    'extra' => array(
                        'i' => $i,
                    ),
                )
            );
            $client->store_pending_events_to_queue();
            unset($client);
        }
        $client2->store_pending_events_to_queue();
        unset($client2);

        $client = new Dummy_DeferredClient(
            'http://public:secret@example.com/1', array(
                'queue_subfolder_name' => 'raven_test_',
            )
        );
        $client->setQueueFolderName(sys_get_temp_dir());
        $client->setQueueSubfolderName('raven_test_');
        $client->read_all_queue();
        $this->assertEquals(4, $client->get_filenames_for_delete_count());
        $this->assertEquals(6, $client->get_events_count());

        //
        $filenames = array_keys($reflection->getValue($client));
        $client->send_all_loaded_events_from_queue();
        foreach ($filenames as &$filename) {
            $this->assertFileNotExists($client->get_folder_with_queue().'/'.$filename);
        }
        $this->assertEquals(4, $client->getDeletedQueueFilesCount());
        $this->assertEquals(6, $client->getSentRequestToServerCount());
    }

    /**
     * @covers DeferredClient::read_all_queue
     * @covers DeferredClient::send_all_loaded_events_from_queue
     */
    public function testSend_too_much_events()
    {
        Dummy_DeferredClient::$_send_http_synchronous_answer = true;

        // case 1
        $this->produce_folder(20);
        $client = new Dummy_DeferredClient(
            null, array(
                'queue_subfolder_name' => 'raven_test_',
                'queue_folder_name'    => sys_get_temp_dir(),
            )
        );
        $client->read_all_queue();
        $this->assertEquals(20, $client->get_filenames_for_delete_count());
        $this->assertEquals(20, $client->get_events_count());
        $client->setRequestLimit(15);
        $client->send_all_loaded_events_from_queue();
        $this->assertEquals(15, $client->getSentRequestToServerCount());
        $this->assertEquals(0, $client->getSentSuccessRequestToServerCount());
        $this->assertEquals(20, $client->get_filenames_for_delete_count());
        $this->assertEquals(20, $client->get_events_count());

        $client->server = 'http://public:secret@example.com/1';
        $client->send_all_loaded_events_from_queue();
        $this->assertEquals(15 * 2, $client->getSentRequestToServerCount());
        $this->assertEquals(15, $client->getSentSuccessRequestToServerCount());
        $this->assertEquals(5, $client->get_events_count());

        $client->server = 'http://public:secret@example.com/1';
        $client->send_all_loaded_events_from_queue();
        $this->assertEquals(15 + 20, $client->getSentRequestToServerCount());
        $this->assertEquals(20, $client->getSentSuccessRequestToServerCount());
        $this->assertEquals(0, $client->get_events_count());
    }


    /**
     * @covers DeferredClient::read_all_queue
     * @covers DeferredClient::send_all_loaded_events_from_queue
     */
    public function testSend_too_much_file()
    {
        Dummy_DeferredClient::$_send_http_synchronous_answer = true;
        foreach (array(0, 11, 19, 20) as $limit) {
            foreach (array(false, true) as $u) {
                $this->clear_raven_test_folder();
                // case 1
                $this->produce_folder(20, 1);
                $client = new Dummy_DeferredClient(
                    null, array(
                        'queue_subfolder_name' => 'raven_test_',
                        'queue_folder_name'    => sys_get_temp_dir(),
                    )
                );
                $client->read_all_queue();
                $this->assertEquals(1, $client->get_filenames_for_delete_count());
                $this->assertEquals(20, $client->get_events_count());
                $client->setRequestLimit(15);
                $client->send_all_loaded_events_from_queue();
                $this->assertEquals(15, $client->getSentRequestToServerCount());
                $this->assertEquals(0, $client->getSentSuccessRequestToServerCount());

                if ($u) {
                    $client = new Dummy_DeferredClient(
                        'http://public:secret@example.com/1', array(
                            'queue_subfolder_name' => 'raven_test_',
                            'queue_folder_name'    => sys_get_temp_dir(),
                        )
                    );
                    $client->setRequestLimit(15);
                } else {
                    $client->server = 'http://public:secret@example.com/1';
                }
                $client->read_all_queue();
                $this->assertEquals(1, $client->get_filenames_for_delete_count());
                $this->assertEquals(20, $client->get_events_count());
                $client->_inner_limit_before_false = $limit;
                $client->send_all_loaded_events_from_queue();
                $this->assertEquals($u ? 15 : 30, $client->getSentRequestToServerCount());
                $this->assertEquals(min($limit, 15), $client->getSentSuccessRequestToServerCount());
                $this->assertEquals(1, $client->get_filenames_for_delete_count());
                $this->assertEquals(20 - min($limit, 15), $client->get_events_count());

                $client2 = new Dummy_DeferredClient(
                    null, array(
                        'queue_subfolder_name' => 'raven_test_',
                        'queue_folder_name'    => sys_get_temp_dir(),
                    )
                );
                $client2->read_all_queue();
                $this->assertEquals(1, $client2->get_filenames_for_delete_count());
                $this->assertEquals(20 - min($limit, 15), $client2->get_events_count());
            }
        }
    }

    private function produce_folder($events_count, $files_count = null, $sub_folder = 'raven_test_')
    {
        if (is_null($files_count)) {
            $files_count = $events_count;
        } elseif ($events_count < $files_count) {
            throw new Exception('Malformed call');
        }
        for ($i = 0; $i < $files_count - 1; $i++) {
            $client = new Dummy_DeferredClient(
                null, array(
                    'queue_folder_name'    => sys_get_temp_dir(),
                    'queue_subfolder_name' => $sub_folder,
                )
            );
            $client->captureMessage(
                'Test Message', array(), array(
                    'extra' => array(
                        'i'            => $i,
                        'files_count'  => $files_count,
                        'events_count' => $events_count,
                        'sub_folder'   => $sub_folder,
                    ),
                    'tags'  => array(
                        'type' => 1,
                    ),
                )
            );
            $client->store_pending_events_to_queue();
        }
        $client = new Dummy_DeferredClient(
            null, array(
                'queue_folder_name'    => sys_get_temp_dir(),
                'queue_subfolder_name' => $sub_folder,
            )
        );
        for ($i = 0; $i <= $events_count - $files_count; $i++) {
            $client->captureMessage(
                'Test Message', array(), array(
                    'extra' => array(
                        'i'            => $i,
                        'files_count'  => $files_count,
                        'events_count' => $events_count,
                        'sub_folder'   => $sub_folder,
                    ),
                    'tags'  => array(
                        'type' => 2,
                    ),
                )
            );
        }
        $client->store_pending_events_to_queue();

        //
        $reflection = new \ReflectionProperty('\\Raven\\DeferredClient', '_event_for_delete');
        $reflection->setAccessible(true);
        $client = new Dummy_DeferredClient(
            null, array(
                'queue_folder_name'    => sys_get_temp_dir(),
                'queue_subfolder_name' => $sub_folder,
            )
        );
        $client->store_pending_events_to_queue();
        foreach ($reflection->getValue($client) as $datum) {
            $this->assertInternalType('array', $datum);
            foreach (array('level', 'message', 'event_id', 'timestamp', 'tags') as $key) {
                $this->assertArrayHasKey($key, $datum);
            }
        }
        $this->assertTrue($client->test_all_events_for_double(), 'File database is not consistent');
    }
}
