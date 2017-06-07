<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven;

use Raven\CurlHandler;

/**
 * Raven PHP Client
 *
 * @package raven
 * @doc https://docs.sentry.io/clients/php/config/
 */

class Client
{
    const VERSION = '2.0.x-dev';

    const PROTOCOL = '6';

    const DEBUG = 'debug';
    const INFO = 'info';
    const WARN = 'warning';
    const WARNING = 'warning';
    const ERROR = 'error';
    const FATAL = 'fatal';

    /**
     * Default message limit
     */
    const MESSAGE_LIMIT = 1024;

    /**
     * @var Breadcrumbs The breadcrumbs
     */
    public $breadcrumbs;

    /**
     * @var Context The context
     */
    public $context;

    /**
     * @var TransactionStack The transaction stack
     */
    public $transaction;

    /**
     * @var string[]|null
     */
    public $severity_map;
    public $store_errors_for_bulk_send = false;

    /**
     * @var \Raven\ErrorHandler $error_handler
     */
    protected $error_handler;

    /**
     * @var \Raven\Serializer $serializer
     */
    protected $serializer;
    /**
     * @var \Raven\Serializer $serializer
     */
    protected $reprSerializer;

    /**
     * @var \Raven\Processor[] $processors An array of classes to use to process data before it is sent to Sentry
     */
    protected $processors = [];

    /**
     * @var string|int|null
     */
    public $_lasterror;
    /**
     * @var object|null
     */
    protected $_last_sentry_error;
    public $_last_event_id;
    public $_user;

    /**
     * @var array[] $_pending_events
     */
    public $_pending_events = [];

    /**
     * @var CurlHandler
     */
    protected $_curl_handler;
    /**
     * @var resource|null
     */
    protected $_curl_instance;
    /**
     * @var bool
     */
    protected $_shutdown_function_has_been_set = false;

    /**
     * @var Configuration The client configuration
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param Configuration $config The client configuration
     */
    public function __construct(Configuration $config)
    {
        $this->config = $config;
        $this->context = new Context();
        $this->breadcrumbs = new Breadcrumbs();
        $this->transaction = new TransactionStack();
        $this->serializer = new Serializer($this->config->getMbDetectOrder());
        $this->reprSerializer = new ReprSerializer($this->config->getMbDetectOrder());
        $this->processors = $this->createProcessors();

        if (static::is_http_request() && isset($_SERVER['PATH_INFO'])) {
            $this->transaction->push($_SERVER['PATH_INFO']);
        }

        if ($this->config->getSerializeAllObjects()) {
            $this->setAllObjectSerialize(true);
        }

        if ('async' === $this->config->getCurlMethod()) {
            $this->_curl_handler = new CurlHandler($this->get_curl_options());
        }

        if ($this->config->shouldInstallDefaultBreadcrumbHandlers()) {
            $this->registerDefaultBreadcrumbHandlers();
        }

        if ($this->config->shouldInstallShutdownHandler()) {
            $this->registerShutdownFunction();
        }
    }

    /**
     * Gets the client configuration.
     *
     * @return Configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function __destruct()
    {
        // Force close curl resource
        $this->close_curl_resource();
        $this->force_send_async_curl_events();
    }

    /**
     * Destruct all objects contain link to this object
     *
     * This method can not delete shutdown handler
     */
    public function close_all_children_link()
    {
        $this->processors = [];
    }

    /**
     * Gets the representation serialier.
     *
     * @return ReprSerializer
     */
    public function getReprSerializer()
    {
        return $this->reprSerializer;
    }

    /**
     * Gets the serializer.
     *
     * @return Serializer
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * Installs any available automated hooks (such as error_reporting).
     */
    public function install()
    {
        if ($this->error_handler) {
            throw new \Raven\Exception(sprintf('%s->install() must only be called once', get_class($this)));
        }
        $this->error_handler = new \Raven\ErrorHandler($this, false, $this->getConfig()->getErrorTypes());
        $this->error_handler->registerExceptionHandler();
        $this->error_handler->registerErrorHandler();
        $this->error_handler->registerShutdownFunction();
        return $this;
    }

    public static function getUserAgent()
    {
        return 'sentry-php/' . self::VERSION;
    }

    /**
     * Sets the \Raven\Processor sub-classes to be used when data is processed before being
     * sent to Sentry.
     *
     * @return \Raven\Processor[]
     */
    public function createProcessors()
    {
        $processors = [];
        $processorsOptions = $this->config->getProcessorsOptions();

        foreach ($this->config->getProcessors() as $processor) {
            /**  @var Processor $processorInstance */
            $processorInstance = new $processor($this);

            if (isset($processorsOptions[$processor])) {
                if (method_exists($processor, 'setProcessorOptions')) {
                    $processorInstance->setProcessorOptions($processorsOptions[$processor]);
                }
            }

            $processors[] = $processorInstance;
        }

        return $processors;
    }

    public function getLastError()
    {
        return $this->_lasterror;
    }

    /**
     * Given an identifier, returns a Sentry searchable string.
     *
     * @param mixed $ident
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getIdent($ident)
    {
        // XXX: We don't calculate checksums yet, so we only have the ident.
        return $ident;
    }

    /**
     * @param string     $message The message (primary description) for the event.
     * @param array      $params  params to use when formatting the message.
     * @param string     $level   Log level group
     * @param bool|array $stack
     * @param mixed      $vars
     * @return string|null
     * @deprecated
     * @codeCoverageIgnore
     */
    public function message($message, $params = [], $level = self::INFO,
                            $stack = false, $vars = null)
    {
        return $this->captureMessage($message, $params, $level, $stack, $vars);
    }

    /**
     * @param Exception $exception
     * @return string|null
     * @deprecated
     * @codeCoverageIgnore
     */
    public function exception($exception)
    {
        return $this->captureException($exception);
    }

    /**
     * Log a message to sentry
     *
     * @param string     $message The message (primary description) for the event.
     * @param array      $params  params to use when formatting the message.
     * @param array      $data    Additional attributes to pass with this event (see Sentry docs).
     * @param bool|array $stack
     * @param mixed      $vars
     * @return string|null
     */
    public function captureMessage($message, $params = [], $data = [],
                            $stack = false, $vars = null)
    {
        // Gracefully handle messages which contain formatting characters, but were not
        // intended to be used with formatting.
        if (!empty($params)) {
            $formatted_message = vsprintf($message, $params);
        } else {
            $formatted_message = $message;
        }

        if ($data === null) {
            $data = [];
        // support legacy method of passing in a level name as the third arg
        } elseif (!is_array($data)) {
            $data = [
                'level' => $data,
            ];
        }

        $data['message'] = $formatted_message;
        $data['sentry.interfaces.Message'] = [
            'message' => $message,
            'params' => $params,
            'formatted' => $formatted_message,
        ];

        return $this->capture($data, $stack, $vars);
    }

    /**
     * Log an exception to sentry
     *
     * @param \Exception $exception The Exception object.
     * @param array      $data      Additional attributes to pass with this event (see Sentry docs).
     * @param mixed      $logger
     * @param mixed      $vars
     * @return string|null
     */
    public function captureException($exception, $data = null, $logger = null, $vars = null)
    {
        if (in_array(get_class($exception), $this->config->getExcludedExceptions())) {
            return null;
        }

        if ($data === null) {
            $data = [];
        }

        $exc = $exception;
        do {
            $exc_data = [
                'value' => $this->serializer->serialize($exc->getMessage()),
                'type' => get_class($exc),
            ];

            /**'exception'
             * Exception::getTrace doesn't store the point at where the exception
             * was thrown, so we have to stuff it in ourselves. Ugh.
             */
            $trace = $exc->getTrace();
            $frame_where_exception_thrown = [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
            ];

            array_unshift($trace, $frame_where_exception_thrown);

            // manually trigger autoloading, as it's not done in some edge cases due to PHP bugs (see #60149)
            if (!class_exists('\\Raven\\Stacktrace')) {
                // @codeCoverageIgnoreStart
                spl_autoload_call('\\Raven\\Stacktrace');
                // @codeCoverageIgnoreEnd
            }

            $exc_data['stacktrace'] = [
                'frames' => Stacktrace::fromBacktrace(
                    $this, $exception->getTrace(), $exception->getFile(), $exception->getLine()
                )->getFrames(),
            ];

            $exceptions[] = $exc_data;
        } while ($exc = $exc->getPrevious());

        $data['exception'] = [
            'values' => array_reverse($exceptions),
        ];
        if ($logger !== null) {
            $data['logger'] = $logger;
        }

        if (empty($data['level'])) {
            if (method_exists($exception, 'getSeverity')) {
                $data['level'] = $this->translateSeverity($exception->getSeverity());
            } else {
                $data['level'] = self::ERROR;
            }
        }

        return $this->capture($data, $trace, $vars);
    }


    /**
     * Capture the most recent error (obtained with ``error_get_last``).
     * @return string|null
     */
    public function captureLastError()
    {
        $error = error_get_last();

        if (null === $error || !isset($error['message'][0])) {
            return null;
        }

        $e = new \ErrorException(
            @$error['message'], 0, @$error['type'],
            @$error['file'], @$error['line']
        );

        return $this->captureException($e);
    }

    /**
     * Log an query to sentry
     *
     * @param string|null $query
     * @param string      $level
     * @param string      $engine
     */
    public function captureQuery($query, $level = self::INFO, $engine = '')
    {
        $data = [
            'message' => $query,
            'level' => $level,
            'sentry.interfaces.Query' => [
                'query' => $query
            ]
        ];

        if ($engine !== '') {
            $data['sentry.interfaces.Query']['engine'] = $engine;
        }
        return $this->capture($data, false);
    }

    /**
     * Return the last captured event's ID or null if none available.
     */
    public function getLastEventID()
    {
        return $this->_last_event_id;
    }

    protected function registerDefaultBreadcrumbHandlers()
    {
        $handler = new \Raven\Breadcrumbs\ErrorHandler($this);
        $handler->install();
    }

    protected function registerShutdownFunction()
    {
        if (!$this->_shutdown_function_has_been_set) {
            $this->_shutdown_function_has_been_set = true;
            register_shutdown_function([$this, 'onShutdown']);
        }
    }

    /**
     * @return bool
     * @codeCoverageIgnore
     */
    protected static function is_http_request()
    {
        return isset($_SERVER['REQUEST_METHOD']) && PHP_SAPI !== 'cli';
    }

    protected function get_http_data()
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $header_key =
                    str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$header_key] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH']) && $value !== '') {
                $header_key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$header_key] = $value;
            }
        }

        $result = [
            'method' => self::_server_variable('REQUEST_METHOD'),
            'url' => $this->get_current_url(),
            'query_string' => self::_server_variable('QUERY_STRING'),
        ];

        // dont set this as an empty array as PHP will treat it as a numeric array
        // instead of a mapping which goes against the defined Sentry spec
        if (!empty($_POST)) {
            $result['data'] = $_POST;
        }
        if (!empty($_COOKIE)) {
            $result['cookies'] = $_COOKIE;
        }
        if (!empty($headers)) {
            $result['headers'] = $headers;
        }

        return [
            'request' => $result,
        ];
    }

    protected function get_user_data()
    {
        $user = $this->context->user;
        if ($user === null) {
            if (!function_exists('session_id') || !session_id()) {
                return [];
            }
            $user = [
                'id' => session_id(),
            ];
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $user['ip_address'] = $_SERVER['REMOTE_ADDR'];
            }
            if (!empty($_SESSION)) {
                $user['data'] = $_SESSION;
            }
        }
        return [
            'user' => $user,
        ];
    }

    public function get_default_data()
    {
        return [
            'server_name' => $this->config->getServerName(),
            'project' => $this->config->getProjectId(),
            'logger' => $this->config->getLogger(),
            'tags' => $this->config->getTags(),
            'platform' => 'php',
            'culprit' => $this->transaction->peek(),
            'sdk' => [
                'name' => 'sentry-php',
                'version' => self::VERSION,
            ],
        ];
    }

    public function capture($data, $stack = null, $vars = null)
    {
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        }
        if (!isset($data['level'])) {
            $data['level'] = self::ERROR;
        }
        if (!isset($data['tags'])) {
            $data['tags'] = [];
        }
        if (!isset($data['extra'])) {
            $data['extra'] = [];
        }
        if (!isset($data['event_id'])) {
            $data['event_id'] = static::uuid4();
        }

        if (isset($data['message'])) {
            $data['message'] = substr($data['message'], 0, self::MESSAGE_LIMIT);
        }

        $data = array_merge($this->get_default_data(), $data);

        if (static::is_http_request()) {
            $data = array_merge($this->get_http_data(), $data);
        }

        $data = array_merge($this->get_user_data(), $data);

        if (!empty($this->config->getRelease())) {
            $data['release'] = $this->config->getRelease();
        }

        if (!empty($this->config->getCurrentEnvironment())) {
            $data['environment'] = $this->config->getCurrentEnvironment();
        }

        $data['tags'] = array_merge(
            $this->config->getTags(),
            $this->context->tags,
            $data['tags']);

        $data['extra'] = array_merge(
            $this->context->extra,
            $data['extra']);

        if (empty($data['extra'])) {
            unset($data['extra']);
        }
        if (empty($data['tags'])) {
            unset($data['tags']);
        }
        if (empty($data['user'])) {
            unset($data['user']);
        }
        if (empty($data['request'])) {
            unset($data['request']);
        }

        if (!$this->breadcrumbs->is_empty()) {
            $data['breadcrumbs'] = $this->breadcrumbs->fetch();
        }

        if ((!$stack && $this->config->getAutoLogStacks()) || $stack === true) {
            $stack = debug_backtrace();

            // Drop last stack
            array_shift($stack);
        }

        if (!empty($stack)) {
            // manually trigger autoloading, as it's not done in some edge cases due to PHP bugs (see #60149)
            if (!class_exists('\\Raven\\Stacktrace')) {
                // @codeCoverageIgnoreStart
                spl_autoload_call('\\Raven\\Stacktrace');
                // @codeCoverageIgnoreEnd
            }

            if (!isset($data['stacktrace']) && !isset($data['exception'])) {
                $data['stacktrace'] = [
                    'frames' => Stacktrace::fromBacktrace(
                        $this, $stack, isset($stack['file']) ? $stack['file'] : __FILE__,
                        isset($stack['line']) ? $stack['line'] : __LINE__ - 2
                    )->getFrames(),
                ];
            }
        }

        $this->sanitize($data);
        $this->process($data);

        if (!$this->store_errors_for_bulk_send) {
            $this->send($data);
        } else {
            $this->_pending_events[] = $data;
        }

        $this->_last_event_id = $data['event_id'];

        return $data['event_id'];
    }

    public function sanitize(&$data)
    {
        // attempt to sanitize any user provided data
        if (!empty($data['request'])) {
            $data['request'] = $this->serializer->serialize($data['request']);
        }
        if (!empty($data['user'])) {
            $data['user'] = $this->serializer->serialize($data['user'], 3);
        }
        if (!empty($data['extra'])) {
            $data['extra'] = $this->serializer->serialize($data['extra']);
        }
        if (!empty($data['tags'])) {
            foreach ($data['tags'] as $key => $value) {
                $data['tags'][$key] = @(string)$value;
            }
        }
        if (!empty($data['contexts'])) {
            $data['contexts'] = $this->serializer->serialize($data['contexts'], 5);
        }
    }

    /**
     * Process data through all defined \Raven\Processor sub-classes
     *
     * @param array $data Associative array of data to log
     */
    public function process(&$data)
    {
        foreach ($this->processors as $processor) {
            $processor->process($data);
        }
    }

    public function sendUnsentErrors()
    {
        foreach ($this->_pending_events as $data) {
            $this->send($data);
        }
        $this->_pending_events = [];
        if ($this->store_errors_for_bulk_send) {
            //in case an error occurs after this is called, on shutdown, send any new errors.
            $this->store_errors_for_bulk_send = !defined('RAVEN_CLIENT_END_REACHED');
        }
    }

    /**
     * @param array $data
     * @return string|bool
     */
    public function encode(&$data)
    {
        $message = json_encode($data);
        if ($message === false) {
            if (function_exists('json_last_error_msg')) {
                $this->_lasterror = json_last_error_msg();
            } else {
                // @codeCoverageIgnoreStart
                $this->_lasterror = json_last_error();
                // @codeCoverageIgnoreEnd
            }
            return false;
        }

        if (function_exists("gzcompress")) {
            $message = gzcompress($message);
        }

        // PHP's builtin curl_* function are happy without this, but the exec method requires it
        $message = base64_encode($message);

        return $message;
    }

    /**
     * Wrapper to handle encoding and sending data to the Sentry API server.
     *
     * @param array     $data       Associative array of data to log
     */
    public function send(&$data)
    {
        if (false === $this->config->shouldCapture($data) || !$this->config->getServer()) {
            return;
        }

        if ($this->getConfig()->getTransport()) {
            call_user_func($this->getConfig()->getTransport(), $this, $data);
            return;
        }

        // should this event be sampled?
        if (mt_rand(1, 100) / 100.0 > $this->config->getSampleRate()) {
            return;
        }

        $message = $this->encode($data);

        $headers = [
            'User-Agent' => static::getUserAgent(),
            'X-Sentry-Auth' => $this->getAuthHeader(),
            'Content-Type' => 'application/octet-stream'
        ];

        $config = $this->getConfig();
        $server = sprintf('%s/api/%d/store/', $config->getServer(), $config->getProjectId());

        $this->send_remote($server, $message, $headers);
    }

    /**
     * Send data to Sentry
     *
     * @param string       $url     Full URL to Sentry
     * @param array|string $data    Associative array of data to log
     * @param array        $headers Associative array of headers
     */
    protected function send_remote($url, $data, $headers = [])
    {
        $parts = parse_url($url);
        $parts['netloc'] = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : null);
        $this->send_http($url, $data, $headers);
    }

    /**
     * @return array
     * @doc http://stackoverflow.com/questions/9062798/php-curl-timeout-is-not-working/9063006#9063006
     * @doc https://3v4l.org/4I7F5
     */
    protected function get_curl_options()
    {
        $options = [
            CURLOPT_VERBOSE => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => $this->config->isSslVerificationEnabled(),
            CURLOPT_CAINFO => $this->config->getSslCaFile(),
            CURLOPT_USERAGENT => 'sentry-php/' . self::VERSION,
        ];
        if (!empty($this->config->getProxy())) {
            $options[CURLOPT_PROXY] = $this->config->getProxy();
        }
        if (null !== $this->config->getCurlSslVersion()) {
            $options[CURLOPT_SSLVERSION] = $this->config->getCurlSslVersion();
        }
        if ($this->config->getCurlIpv4()) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        if (defined('CURLOPT_TIMEOUT_MS')) {
            // MS is available in curl >= 7.16.2
            $timeout = max(1, ceil(1000 * $this->config->getTimeout()));

            // None of the versions of PHP contains this constant
            if (!defined('CURLOPT_CONNECTTIMEOUT_MS')) {
                //see stackoverflow link in the phpdoc
                define('CURLOPT_CONNECTTIMEOUT_MS', 156);
            }

            $options[CURLOPT_CONNECTTIMEOUT_MS] = $timeout;
            $options[CURLOPT_TIMEOUT_MS] = $timeout;
        } else {
            // fall back to the lower-precision timeout.
            $timeout = max(1, ceil($this->config->getTimeout()));
            $options[CURLOPT_CONNECTTIMEOUT] = $timeout;
            $options[CURLOPT_TIMEOUT] = $timeout;
        }
        return $options;
    }

    /**
     * Send the message over http to the sentry url given
     *
     * @param string       $url     URL of the Sentry instance to log to
     * @param array|string $data    Associative array of data to log
     * @param array        $headers Associative array of headers
     */
    protected function send_http($url, $data, $headers = [])
    {
        if ($this->config->getCurlMethod() == 'async') {
            $this->_curl_handler->enqueue($url, $data, $headers);
        } elseif ($this->config->getCurlMethod() == 'exec') {
            $this->send_http_asynchronous_curl_exec($url, $data, $headers);
        } else {
            $this->send_http_synchronous($url, $data, $headers);
        }
    }

    /**
     * @param string $url
     * @param string $data
     * @param array  $headers
     * @return string
     *
     * This command line ensures exec returns immediately while curl runs in the background
     */
    protected function buildCurlCommand($url, $data, $headers)
    {
        $post_fields = '';
        foreach ($headers as $key => $value) {
            $post_fields .= ' -H '.escapeshellarg($key.': '.$value);
        }
        $cmd = sprintf(
            '%s -X POST%s -d %s %s -m %d %s%s> /dev/null 2>&1 &',
            escapeshellcmd($this->config->getCurlPath()), $post_fields,
            escapeshellarg($data), escapeshellarg($url), $this->config->getTimeout(),
            !$this->config->isSslVerificationEnabled() ? '-k ' : '',
            !empty($this->config->getSslCaFile()) ? '--cacert '.escapeshellarg($this->config->getSslCaFile()).' ' : ''
        );

        return $cmd;
    }

    /**
     * Send the cURL to Sentry asynchronously. No errors will be returned from cURL
     *
     * @param string       $url     URL of the Sentry instance to log to
     * @param array|string $data    Associative array of data to log
     * @param array        $headers Associative array of headers
     * @return bool
     */
    protected function send_http_asynchronous_curl_exec($url, $data, $headers)
    {
        exec($this->buildCurlCommand($url, $data, $headers));
        return true; // The exec method is just fire and forget, so just assume it always works
    }

    /**
     * Send a blocking cURL to Sentry and check for errors from cURL
     *
     * @param string       $url     URL of the Sentry instance to log to
     * @param array|string $data    Associative array of data to log
     * @param array        $headers Associative array of headers
     * @return bool
     */
    protected function send_http_synchronous($url, $data, $headers)
    {
        $new_headers = [];
        foreach ($headers as $key => $value) {
            array_push($new_headers, $key .': '. $value);
        }
        // XXX(dcramer): Prevent 100-continue response form server (Fixes GH-216)
        $new_headers[] = 'Expect:';

        if (is_null($this->_curl_instance)) {
            $this->_curl_instance = curl_init($url);
        }
        curl_setopt($this->_curl_instance, CURLOPT_POST, 1);
        curl_setopt($this->_curl_instance, CURLOPT_HTTPHEADER, $new_headers);
        curl_setopt($this->_curl_instance, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->_curl_instance, CURLOPT_RETURNTRANSFER, true);

        $options = $this->get_curl_options();
        if (isset($options[CURLOPT_CAINFO])) {
            $ca_cert = $options[CURLOPT_CAINFO];
            unset($options[CURLOPT_CAINFO]);
        } else {
            $ca_cert = null;
        }
        curl_setopt_array($this->_curl_instance, $options);

        $buffer = curl_exec($this->_curl_instance);

        $errno = curl_errno($this->_curl_instance);
        // CURLE_SSL_CACERT || CURLE_SSL_CACERT_BADFILE
        if (in_array($errno, [CURLE_SSL_CACERT, 77]) && !is_null($ca_cert)) {
            curl_setopt($this->_curl_instance, CURLOPT_CAINFO, $ca_cert);
            $buffer = curl_exec($this->_curl_instance);
            $errno = curl_errno($this->_curl_instance);
        }
        if ($errno != 0) {
            $this->_lasterror = curl_error($this->_curl_instance);
            $this->_last_sentry_error = null;
            return false;
        }

        $code = curl_getinfo($this->_curl_instance, CURLINFO_HTTP_CODE);
        $success = ($code == 200);
        if ($success) {
            $this->_lasterror = null;
            $this->_last_sentry_error = null;
        } else {
            // It'd be nice just to raise an exception here, but it's not very PHP-like
            $this->_lasterror = curl_error($this->_curl_instance);
            $this->_last_sentry_error = @json_decode($buffer);
        }

        return $success;
    }

    /**
     * Generate a Sentry authorization header string
     *
     * @param string $timestamp  Timestamp when the event occurred
     * @param string $client     HTTP client name (not \Raven\Client object)
     * @param string $api_key    Sentry API key
     * @param string $secret_key Sentry API key
     * @return string
     */
    protected static function get_auth_header($timestamp, $client, $api_key, $secret_key)
    {
        $header = [
            sprintf('sentry_timestamp=%F', $timestamp),
            "sentry_client={$client}",
            sprintf('sentry_version=%s', self::PROTOCOL),
        ];

        if ($api_key) {
            $header[] = "sentry_key={$api_key}";
        }

        if ($secret_key) {
            $header[] = "sentry_secret={$secret_key}";
        }


        return sprintf('Sentry %s', implode(', ', $header));
    }

    public function getAuthHeader()
    {
        $timestamp = microtime(true);
        return $this->get_auth_header(
            $timestamp, static::getUserAgent(), $this->config->getPublicKey(), $this->config->getSecretKey()
        );
    }

    /**
     * Generate an uuid4 value
     *
     * @return string
     */
    protected static function uuid4()
    {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        return str_replace('-', '', $uuid);
    }

    /**
     * Return the URL for the current request
     *
     * @return string|null
     */
    protected function get_current_url()
    {
        // When running from commandline the REQUEST_URI is missing.
        if (!isset($_SERVER['REQUEST_URI'])) {
            return null;
        }

        // HTTP_HOST is a client-supplied header that is optional in HTTP 1.0
        $host = (!empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST']
            : (!empty($_SERVER['LOCAL_ADDR'])  ? $_SERVER['LOCAL_ADDR']
            : (!empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '')));

        $httpS = $this->isHttps() ? 's' : '';
        return "http{$httpS}://{$host}{$_SERVER['REQUEST_URI']}";
    }

    /**
     * Was the current request made over https?
     *
     * @return bool
     */
    protected function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }

        if (!empty($this->config->isTrustXForwardedProto()) &&
            !empty($_SERVER['X-FORWARDED-PROTO']) &&
            $_SERVER['X-FORWARDED-PROTO'] === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Get the value of a key from $_SERVER
     *
     * @param string $key Key whose value you wish to obtain
     * @return string     Key's value
     */
    private static function _server_variable($key)
    {
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return '';
    }

    /**
     * Translate a PHP Error constant into a Sentry log level group
     *
     * @param string $severity PHP E_$x error constant
     * @return string          Sentry log level group
     */
    public function translateSeverity($severity)
    {
        if (is_array($this->severity_map) && isset($this->severity_map[$severity])) {
            return $this->severity_map[$severity];
        }
        switch ($severity) {
            case E_ERROR:              return \Raven\Client::ERROR;
            case E_WARNING:            return \Raven\Client::WARN;
            case E_PARSE:              return \Raven\Client::ERROR;
            case E_NOTICE:             return \Raven\Client::INFO;
            case E_CORE_ERROR:         return \Raven\Client::ERROR;
            case E_CORE_WARNING:       return \Raven\Client::WARN;
            case E_COMPILE_ERROR:      return \Raven\Client::ERROR;
            case E_COMPILE_WARNING:    return \Raven\Client::WARN;
            case E_USER_ERROR:         return \Raven\Client::ERROR;
            case E_USER_WARNING:       return \Raven\Client::WARN;
            case E_USER_NOTICE:        return \Raven\Client::INFO;
            case E_STRICT:             return \Raven\Client::INFO;
            case E_RECOVERABLE_ERROR:  return \Raven\Client::ERROR;
            case E_DEPRECATED:         return \Raven\Client::WARN;
            case E_USER_DEPRECATED:    return \Raven\Client::WARN;
        }
        return \Raven\Client::ERROR;
    }

    /**
     * Provide a map of PHP Error constants to Sentry logging groups to use instead
     * of the defaults in translateSeverity()
     *
     * @param string[] $map
     */
    public function registerSeverityMap($map)
    {
        $this->severity_map = $map;
    }

    /**
     * Convenience function for setting a user's ID and Email
     *
     * @deprecated
     * @param string      $id    User's ID
     * @param string|null $email User's email
     * @param array       $data  Additional user data
     * @codeCoverageIgnore
     */
    public function set_user_data($id, $email = null, $data = [])
    {
        $user = ['id' => $id];
        if (isset($email)) {
            $user['email'] = $email;
        }
        $this->user_context(array_merge($user, $data));
    }

    public function force_send_async_curl_events()
    {
        if (!is_null($this->_curl_handler)) {
            $this->_curl_handler->join();
        }
    }

    public function onShutdown()
    {
        if (!defined('RAVEN_CLIENT_END_REACHED')) {
            define('RAVEN_CLIENT_END_REACHED', true);
        }
        $this->sendUnsentErrors();
        $this->force_send_async_curl_events();
    }

    /**
     * Sets user context.
     *
     * @param array $data  Associative array of user data
     * @param bool  $merge Merge existing context with new context
     */
    public function user_context($data, $merge = true)
    {
        if ($merge && $this->context->user !== null) {
            // bail if data is null
            if (!$data) {
                return;
            }
            $this->context->user = array_merge($this->context->user, $data);
        } else {
            $this->context->user = $data;
        }
    }

    /**
     * Appends tags context.
     *
     * @param array $data Associative array of tags
     */
    public function tags_context($data)
    {
        $this->context->tags = array_merge($this->context->tags, $data);
    }

    /**
     * Appends additional context.
     *
     * @param array $data Associative array of extra data
     */
    public function extra_context($data)
    {
        $this->context->extra = array_merge($this->context->extra, $data);
    }

    /**
     * @param array $processors
     */
    public function setProcessors(array $processors)
    {
        $this->processors = $processors;
    }

    /**
     * @return object|null
     */
    public function getLastSentryError()
    {
        return $this->_last_sentry_error;
    }

    /**
     * @return bool
     */
    public function getShutdownFunctionHasBeenSet()
    {
        return $this->_shutdown_function_has_been_set;
    }

    public function close_curl_resource()
    {
        if (!is_null($this->_curl_instance)) {
            curl_close($this->_curl_instance);
            $this->_curl_instance = null;
        }
    }

    public function setAllObjectSerialize($value)
    {
        $this->serializer->setAllObjectSerialize($value);
        $this->reprSerializer->setAllObjectSerialize($value);
    }
}
