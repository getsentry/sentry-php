<?php

namespace Raven;

/**
 * Raven PHP Client
 *
 * @package raven
 */
class DeferredClient extends Client
{
    const INITIAL_REQUEST_LIMIT = 1000;
    protected $_queue_subfolder_name;
    protected $_queue_folder_name;
    /**
     * @var array[][]
     */
    private $_event_for_delete = array();
    protected $_deleted_queue_files_count = 0;
    protected $_non_processed_queue_files_count = 0;
    protected $_sent_request_to_server_count = 0;
    protected $_sent_success_request_to_server_count = 0;
    protected $_request_limit = self::INITIAL_REQUEST_LIMIT;

    public function __construct($options_or_dsn = null, $options = array())
    {
        $options['install_shutdown_handler'] = Util::get($options, 'install_shutdown_handler', false);
        $this->_queue_folder_name = Util::get($options, 'queue_folder_name', sys_get_temp_dir());
        $this->_queue_subfolder_name = Util::get($options, 'queue_subfolder_name', null);
        parent::__construct($options_or_dsn, $options);
        $this->_request_limit = Util::get($options, 'request_limit', self::INITIAL_REQUEST_LIMIT);
        $this->store_errors_for_bulk_send = true;
    }

    public function __destruct()
    {
        $this->store_pending_events_to_queue();
        parent::__destruct();
    }

    public function store_pending_events_to_queue()
    {
        if (!empty($this->_pending_events)) {
            $this->create_queue_folder_if_need();
            file_put_contents(
                $this->get_folder_with_queue().'/'.microtime(true).'.rvn',
                serialize(
                    (object)array(
                        'data'   => $this->_pending_events,
                        'domain' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null,
                    )
                ), LOCK_EX
            );
        }
        $this->_pending_events = array();
    }

    /**
     * @return string
     */
    public function get_folder_with_queue()
    {
        return sprintf(
            '%s/%s', $this->_queue_folder_name,
            !is_null($this->_queue_subfolder_name) ? $this->_queue_subfolder_name
                : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'cli')
        );
    }

    /**
     * @throws Exception
     */
    protected function create_queue_folder_if_need()
    {
        $folder = $this->get_folder_with_queue();
        if (isset($_SERVER['HOME']) and (substr($folder, 0, strlen($_SERVER['HOME'])) == $_SERVER['HOME'])) {
            $full_folder = $_SERVER['HOME'];
            $add = ltrim(substr($folder, strlen($_SERVER['HOME']) + 1), '/');
        } else {
            $full_folder = '';
            $add = $folder;
        }

        foreach (explode('/', $add) as $chunk) {
            $full_folder = '/'.ltrim($full_folder.'/'.$chunk, '/');
            if (!file_exists($full_folder)) {
                mkdir($full_folder);
                chmod($full_folder, 7 << 6);
            } elseif (!is_dir($full_folder)) {
                throw new Exception($full_folder.' is not a folder');
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function read_all_queue_and_send_all_events()
    {
        $this->read_all_queue();
        $this->send_all_loaded_events_from_queue();
    }

    public function read_all_queue()
    {
        if (!file_exists($this->get_folder_with_queue())) {
            return;
        }

        foreach (scandir($this->get_folder_with_queue()) as $d) {
            if (in_array($d, array('.', '..')) or (substr($d, -4) !== '.rvn')) {
                continue;
            }

            $filename = $this->get_folder_with_queue().'/'.$d;
            $data = @unserialize(file_get_contents($filename, LOCK_EX));
            if (!is_object($data)) {
                continue;
            }
            $this->_event_for_delete[$filename] = $data->data;
        }
    }

    public function send_all_loaded_events_from_queue()
    {
        $non_empty = false;
        $this_session_sent_request_count = 0;
        foreach ($this->_event_for_delete as $filename => &$data) {
            if (!is_null($this->_request_limit) and
                ($this_session_sent_request_count >= $this->_request_limit)
            ) {
                $non_empty = true;
                break;
            }
            if (is_null($data) or empty($data)) {
                continue;
            }
            $non_processed = array();
            $file_content_changes = false;
            foreach ($data as &$datum) {
                if (!is_null($this->_request_limit) and
                    ($this_session_sent_request_count >= $this->_request_limit)
                ) {
                    $non_processed[] = $datum;
                    continue;
                }
                $data_has_been_sent = $this->direct_send_to_server($datum);
                $this->_sent_request_to_server_count++;
                $this_session_sent_request_count++;
                if (!$data_has_been_sent) {
                    $non_processed[] = $datum;
                } else {
                    $file_content_changes = true;
                    $this->_sent_success_request_to_server_count++;
                }
            }
            if (!$file_content_changes) {
                // Not a single piece has been sent
                $non_empty = true;
            } elseif (count($non_processed) > 0) {
                $this->_non_processed_queue_files_count++;
                $current_file_data = unserialize(file_get_contents($filename, LOCK_EX));
                $current_file_data->data = $non_processed;
                $data = $non_processed;
                file_put_contents($filename, serialize($current_file_data), LOCK_EX);
                $non_empty = true;
            } else {
                $this->_deleted_queue_files_count++;
                @unlink($filename);
                $data = null;
            }
        }
        if (!$non_empty) {
            $this->_event_for_delete = array();
        }
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    protected function direct_send_to_server($data)
    {
        if (!$this->server) {
            return false;
        }

        $message = $this->encode($data);
        $headers = array(
            'User-Agent'    => static::getUserAgent(),
            'X-Sentry-Auth' => $this->getAuthHeader(),
            'Content-Type'  => 'application/octet-stream'
        );

        return $this->send_http_synchronous($this->server, $message, $headers);
    }

    /**
     * @return int
     * @codeCoverageIgnore
     */
    public function get_data_error_count()
    {
        return count($this->_pending_events);
    }

    /**
     * @return int
     */
    public function get_filenames_for_delete_count()
    {
        return count($this->_event_for_delete);
    }

    /**
     * @return int
     */
    public function get_events_count()
    {
        $count = 0;
        foreach ($this->_event_for_delete as &$data) {
            if (!is_null($data)) {
                $count += count($data);
            }
        }

        return $count;
    }

    public function getRequestLimit()
    {
        return $this->_request_limit;
    }

    /**
     * @param int|null $value
     * @return DeferredClient
     */
    public function setRequestLimit($value)
    {
        $this->_request_limit = is_null($value) ? null : (integer)$value;
        return $this;
    }

    public function getQueueSubfolderName()
    {
        return $this->_queue_subfolder_name;
    }

    /**
     * @param string|null $value
     * @return DeferredClient
     */
    public function setQueueSubfolderName($value)
    {
        $this->_queue_subfolder_name = $value;
        return $this;
    }

    public function getQueueFolderName()
    {
        return $this->_queue_folder_name;
    }

    /**
     * @param string $value
     * @return DeferredClient
     */
    public function setQueueFolderName($value)
    {
        $this->_queue_folder_name = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getDeletedQueueFilesCount()
    {
        return $this->_deleted_queue_files_count;
    }

    /**
     * @return int
     */
    public function getNonProcessedQueueFilesCount()
    {
        return $this->_non_processed_queue_files_count;
    }

    /**
     * @return int
     */
    public function getSentRequestToServerCount()
    {
        return $this->_sent_request_to_server_count;
    }

    /**
     * @return int
     */
    public function getSentSuccessRequestToServerCount()
    {
        return $this->_sent_success_request_to_server_count;
    }
}
