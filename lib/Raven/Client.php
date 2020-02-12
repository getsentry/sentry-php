<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Raven PHP Client
 *
 * @package raven
 */

class Raven_Client
{
    const VERSION = '1.11.0';

    const PROTOCOL = '6';

    const DEBUG = 'debug';
    const INFO = 'info';
    const WARN = 'warning';
    const WARNING = 'warning';
    const ERROR = 'error';
    const FATAL = 'fatal';

    const MESSAGE_LIMIT = 1024;

    public $breadcrumbs;
    /**
     * @var Raven_Context
     */
    public $context;
    public $extra_data;
    /**
     * @var array|null
     */
    public $severity_map;
    public $store_errors_for_bulk_send = false;

    protected $error_handler;
    protected $error_types;

    /**
     * @var Raven_Serializer
     */
    protected $serializer;
    /**
     * @var Raven_ReprSerializer
     */
    protected $reprSerializer;

    /**
     * @var string
     */
    protected $app_path;
    /**
     * @var string[]
     */
    protected $prefixes;
    /**
     * @var string[]|null
     */
    protected $excluded_app_paths;
    /**
     * @var Callable
     */
    protected $transport;

    public $logger;
    /**
     * @var string Full URL to Sentry
     */
    public $server;
    public $secret_key;
    public $public_key;
    public $project;
    public $auto_log_stacks;
    public $name;
    public $site;
    public $tags;
    public $release;
    public $environment;
    public $sample_rate;
    public $trace;
    public $timeout;
    public $message_limit;
    public $exclude;
    public $excluded_exceptions;
    public $http_proxy;
    public $ignore_server_port;
    protected $send_callback;
    public $curl_method;
    public $curl_path;
    public $curl_ipv4;
    public $ca_cert;
    public $verify_ssl;
    public $curl_ssl_version;
    public $trust_x_forwarded_proto;
    public $mb_detect_order;
    /**
     * @var Raven_Processor[]
     */
    public $processors;
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
    public $_pending_events;
    public $sdk;
    /**
     * @var Raven_CurlHandler
     */
    protected $_curl_handler;
    /**
     * @var resource|null
     */
    protected $_curl_instance;
    /**
     * @var bool
     */
    protected $_shutdown_function_has_been_set;

    /**
     * @var bool
     */
    public $useCompression;

    /**
     * @var Raven_TransactionStack
     */
    public $transaction;

    public function __construct($options_or_dsn = null, $options = array())
    {
        if (is_array($options_or_dsn)) {
            $options = array_merge($options_or_dsn, $options);
        }

        if (!is_array($options_or_dsn) && !empty($options_or_dsn)) {
            $dsn = $options_or_dsn;
        } elseif (!empty($_SERVER['SENTRY_DSN'])) {
            $dsn = @$_SERVER['SENTRY_DSN'];
        } elseif (!empty($options['dsn'])) {
            $dsn = $options['dsn'];
        } else {
            $dsn = null;
        }

        if (!empty($dsn)) {
            $options = array_merge($options, self::parseDSN($dsn));
        }

        $this->logger = Raven_Util::get($options, 'logger', 'php');
        $this->server = Raven_Util::get($options, 'server');
        $this->secret_key = Raven_Util::get($options, 'secret_key');
        $this->public_key = Raven_Util::get($options, 'public_key');
        $this->project = Raven_Util::get($options, 'project', 1);
        $this->auto_log_stacks = (bool) Raven_Util::get($options, 'auto_log_stacks', false);
        $this->name = Raven_Util::get($options, 'name', Raven_Compat::gethostname());
        $this->site = Raven_Util::get($options, 'site', self::_server_variable('SERVER_NAME'));
        $this->tags = Raven_Util::get($options, 'tags', array());
        $this->release = Raven_Util::get($options, 'release', null);
        $this->environment = Raven_Util::get($options, 'environment', null);
        $this->sample_rate = Raven_Util::get($options, 'sample_rate', 1);
        $this->trace = (bool) Raven_Util::get($options, 'trace', true);
        $this->timeout = Raven_Util::get($options, 'timeout', 2);
        $this->message_limit = Raven_Util::get($options, 'message_limit', self::MESSAGE_LIMIT);
        $this->exclude = Raven_Util::get($options, 'exclude', array());
        $this->excluded_exceptions = Raven_Util::get($options, 'excluded_exceptions', array());
        $this->severity_map = null;
        $this->http_proxy = Raven_Util::get($options, 'http_proxy');
        $this->ignore_server_port = Raven_Util::get($options, 'ignore_server_port', false);
        $this->extra_data = Raven_Util::get($options, 'extra', array());
        $this->send_callback = Raven_Util::get($options, 'send_callback', null);
        $this->curl_method = Raven_Util::get($options, 'curl_method', 'sync');
        $this->curl_path = Raven_Util::get($options, 'curl_path', 'curl');
        $this->curl_ipv4 = Raven_Util::get($options, 'curl_ipv4', false);
        $this->ca_cert = Raven_Util::get($options, 'ca_cert', static::get_default_ca_cert());
        $this->verify_ssl = Raven_Util::get($options, 'verify_ssl', true);
        $this->curl_ssl_version = Raven_Util::get($options, 'curl_ssl_version');
        $this->trust_x_forwarded_proto = Raven_Util::get($options, 'trust_x_forwarded_proto');
        $this->transport = Raven_Util::get($options, 'transport', null);
        $this->mb_detect_order = Raven_Util::get($options, 'mb_detect_order', null);
        $this->error_types = Raven_Util::get($options, 'error_types', null);

        // app path is used to determine if code is part of your application
        $this->setAppPath(Raven_Util::get($options, 'app_path', null));
        $this->setExcludedAppPaths(Raven_Util::get($options, 'excluded_app_paths', null));
        // a list of prefixes used to coerce absolute paths into relative
        $this->setPrefixes(Raven_Util::get($options, 'prefixes', static::getDefaultPrefixes()));
        $this->processors = $this->setProcessorsFromOptions($options);

        $this->_lasterror = null;
        $this->_last_sentry_error = null;
        $this->_curl_instance = null;
        $this->_last_event_id = null;
        $this->_user = null;
        $this->_pending_events = array();
        $this->context = new Raven_Context();
        $this->breadcrumbs = new Raven_Breadcrumbs();
        $this->_shutdown_function_has_been_set = false;
        $this->useCompression = function_exists('gzcompress');

        $this->sdk = Raven_Util::get($options, 'sdk', array(
            'name' => 'sentry-php',
            'version' => self::VERSION,
        ));
        $this->serializer = new Raven_Serializer($this->mb_detect_order, $this->message_limit);
        $this->reprSerializer = new Raven_ReprSerializer($this->mb_detect_order, $this->message_limit);

        if ($this->curl_method == 'async') {
            $this->_curl_handler = new Raven_CurlHandler($this->get_curl_options());
        }

        $this->transaction = new Raven_TransactionStack();
        if (static::is_http_request() && isset($_SERVER['PATH_INFO'])) {
            // @codeCoverageIgnoreStart
            $this->transaction->push($_SERVER['PATH_INFO']);
            // @codeCoverageIgnoreEnd
        }

        if (Raven_Util::get($options, 'install_default_breadcrumb_handlers', true)) {
            $this->registerDefaultBreadcrumbHandlers();
        }

        if (Raven_Util::get($options, 'install_shutdown_handler', true)) {
            $this->registerShutdownFunction();
        }

        $this->triggerAutoload();
    }

    public function __destruct()
    {
        // Force close curl resource
        $this->close_curl_resource();
    }

    /**
     * Destruct all objects contain link to this object
     *
     * This method can not delete shutdown handler
     */
    public function close_all_children_link()
    {
        $this->processors = array();
    }

    /**
     * Installs any available automated hooks (such as error_reporting).
     */
    public function install()
    {
        if ($this->error_handler) {
            throw new Raven_Exception(sprintf('%s->install() must only be called once', get_class($this)));
        }
        $this->error_handler = new Raven_ErrorHandler($this, false, $this->error_types);
        $this->error_handler->registerExceptionHandler();
        $this->error_handler->registerErrorHandler();
        $this->error_handler->registerShutdownFunction();

        if ($this->_curl_handler) {
            $this->_curl_handler->registerShutdownFunction();
        }

        return $this;
    }

    public function getRelease()
    {
        return $this->release;
    }

    public function setRelease($value)
    {
        $this->release = $value;
        return $this;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function setEnvironment($value)
    {
        $this->environment = $value;
        return $this;
    }

    /**
     * Note: Prior to PHP 5.6, a stream opened with php://input can
     * only be read once;
     *
     * @see http://php.net/manual/en/wrappers.php.php
     */
    protected static function getInputStream()
    {
        if (PHP_VERSION_ID < 50600) {
            return null;
        }

        return file_get_contents('php://input');
    }

    private static function getDefaultPrefixes()
    {
        $value = get_include_path();
        return explode(PATH_SEPARATOR, $value);
    }

    private static function _convertPath($value)
    {
        $path = @realpath($value);
        if ($path === false) {
            $path = $value;
        }
        // we need app_path to have a trailing slash otherwise
        // base path detection becomes complex if the same
        // prefix is matched
        if (substr($path, 0, 1) === DIRECTORY_SEPARATOR && substr($path, -1) !== DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }
        return $path;
    }

    public function getAppPath()
    {
        return $this->app_path;
    }

    public function setAppPath($value)
    {
        if ($value) {
            $this->app_path = static::_convertPath($value);
        } else {
            $this->app_path = null;
        }
        return $this;
    }

    public function getExcludedAppPaths()
    {
        return $this->excluded_app_paths;
    }

    public function setExcludedAppPaths($value)
    {
        if ($value) {
            $excluded_app_paths = array();

            // We should be able to exclude a php files
            foreach ((array) $value as $path) {
                $excluded_app_paths[] = substr($path, -4) !== '.php' ? self::_convertPath($path) : $path;
            }
        } else {
            $excluded_app_paths = null;
        }

        $this->excluded_app_paths = $excluded_app_paths;

        return $this;
    }

    public function getPrefixes()
    {
        return $this->prefixes;
    }

    /**
     * @param array $value
     * @return Raven_Client
     */
    public function setPrefixes($value)
    {
        $this->prefixes = $value ? array_map(array($this, '_convertPath'), $value) : $value;
        return $this;
    }

    public function getSendCallback()
    {
        return $this->send_callback;
    }

    public function setSendCallback($value)
    {
        $this->send_callback = $value;
        return $this;
    }

    public function getTransport()
    {
        return $this->transport;
    }

    public function getServerEndpoint($value = '')
    {
        return $this->server;
    }

    public static function getUserAgent()
    {
        return 'sentry-php/' . self::VERSION;
    }

    /**
     * Set a custom transport to override how Sentry events are sent upstream.
     *
     * The bound function will be called with ``$client`` and ``$data`` arguments
     * and is responsible for encoding the data, authenticating, and sending
     * the data to the upstream Sentry server.
     *
     * @param Callable $value Function to be called
     * @return Raven_Client
     */
    public function setTransport($value)
    {
        $this->transport = $value;
        return $this;
    }

    /**
     * @return string[]|Raven_Processor[]
     */
    public static function getDefaultProcessors()
    {
        return array(
            'Raven_Processor_SanitizeDataProcessor',
        );
    }

    /**
     * Sets the Raven_Processor sub-classes to be used when data is processed before being
     * sent to Sentry.
     *
     * @param $options
     * @return Raven_Processor[]
     */
    public function setProcessorsFromOptions($options)
    {
        $processors = array();
        foreach (Raven_Util::get($options, 'processors', static::getDefaultProcessors()) as $processor) {
            /**
             * @var Raven_Processor        $new_processor
             * @var Raven_Processor|string $processor
             */
            $new_processor = new $processor($this);

            if (isset($options['processorOptions']) && is_array($options['processorOptions'])) {
                if (isset($options['processorOptions'][$processor])
                    && method_exists($processor, 'setProcessorOptions')
                ) {
                    $new_processor->setProcessorOptions($options['processorOptions'][$processor]);
                }
            }
            $processors[] = $new_processor;
        }
        return $processors;
    }

    /**
     * Parses a Raven-compatible DSN and returns an array of its values.
     *
     * @param string $dsn Raven compatible DSN
     * @return array      parsed DSN
     *
     * @see http://raven.readthedocs.org/en/latest/config/#the-sentry-dsn
     */
    public static function parseDSN($dsn)
    {
        switch (strtolower($dsn)) {
            case '':
            case 'false':
            case '(false)':
            case 'empty':
            case '(empty)':
            case 'null':
            case '(null)':
                return array();
        }

        $url = parse_url($dsn);
        $scheme = (isset($url['scheme']) ? $url['scheme'] : '');
        if (!in_array($scheme, array('http', 'https'))) {
            throw new InvalidArgumentException(
                'Unsupported Sentry DSN scheme: '.
                (!empty($scheme) ? $scheme : '<not set>')
            );
        }
        $netloc = (isset($url['host']) ? $url['host'] : null);
        $netloc .= (isset($url['port']) ? ':'.$url['port'] : null);
        $rawpath = (isset($url['path']) ? $url['path'] : null);
        if ($rawpath) {
            $pos = strrpos($rawpath, '/', 1);
            if ($pos !== false) {
                $path = substr($rawpath, 0, $pos);
                $project = substr($rawpath, $pos + 1);
            } else {
                $path = '';
                $project = substr($rawpath, 1);
            }
        } else {
            $project = null;
            $path = '';
        }
        $username = (isset($url['user']) ? $url['user'] : null);
        $password = (isset($url['pass']) ? $url['pass'] : null);
        if (empty($netloc) || empty($project) || empty($username)) {
            throw new InvalidArgumentException('Invalid Sentry DSN: ' . $dsn);
        }

        return array(
            'server'     => sprintf('%s://%s%s/api/%s/store/', $scheme, $netloc, $path, $project),
            'project'    => $project,
            'public_key' => $username,
            'secret_key' => $password,
        );
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
    public function message($message, $params = array(), $level = self::INFO,
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
    public function captureMessage($message, $params = array(), $data = array(),
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
            $data = array();
        // support legacy method of passing in a level name as the third arg
        } elseif (!is_array($data)) {
            $data = array(
                'level' => $data,
            );
        }

        $data['message'] = $formatted_message;
        $data['sentry.interfaces.Message'] = array(
            'message' => $message,
            'params' => $params,
            'formatted' => $formatted_message,
        );

        return $this->capture($data, $stack, $vars);
    }

    /**
     * Log an exception to sentry
     *
     * @param \Throwable|\Exception $exception The Throwable/Exception object.
     * @param array                 $data      Additional attributes to pass with this event (see Sentry docs).
     * @param mixed                 $logger
     * @param mixed                 $vars
     * @return string|null
     */
    public function captureException($exception, $data = null, $logger = null, $vars = null)
    {
        $has_chained_exceptions = PHP_VERSION_ID >= 50300;

        if (in_array(get_class($exception), $this->exclude)) {
            return null;
        }

        foreach ($this->excluded_exceptions as $exclude) {
            if ($exception instanceof $exclude) {
                return null;
            }
        }

        if ($data === null) {
            $data = array();
        }

        $exc = $exception;
        do {
            $exc_data = array(
                'value' => $this->serializer->serialize($exc->getMessage()),
                'type' => get_class($exc),
            );

            /**'exception'
             * Exception::getTrace doesn't store the point at where the exception
             * was thrown, so we have to stuff it in ourselves. Ugh.
             */
            $trace = $exc->getTrace();
            $frame_where_exception_thrown = array(
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
            );

            array_unshift($trace, $frame_where_exception_thrown);

            $exc_data['stacktrace'] = array(
                'frames' => Raven_Stacktrace::get_stack_info(
                    $trace, $this->trace, $vars, $this->message_limit, $this->prefixes,
                    $this->app_path, $this->excluded_app_paths, $this->serializer, $this->reprSerializer
                ),
            );

            $exceptions[] = $exc_data;
        } while ($has_chained_exceptions && $exc = $exc->getPrevious());

        $data['exception'] = array(
            'values' => array_reverse($exceptions),
        );
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
        if (null === $error = error_get_last()) {
            return null;
        }

        $e = new ErrorException(
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
        $data = array(
            'message' => $query,
            'level' => $level,
            'sentry.interfaces.Query' => array(
                'query' => $query
            )
        );

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
        $handler = new Raven_Breadcrumbs_ErrorHandler($this);
        $handler->install();
    }

    protected function registerShutdownFunction()
    {
        if (!$this->_shutdown_function_has_been_set) {
            $this->_shutdown_function_has_been_set = true;
            register_shutdown_function(array($this, 'onShutdown'));
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
        $headers = array();

        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $header_key =
                    str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$header_key] = $value;
            } elseif (in_array($key, array('CONTENT_TYPE', 'CONTENT_LENGTH')) && $value !== '') {
                $header_key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$header_key] = $value;
            }
        }

        $result = array(
            'method' => self::_server_variable('REQUEST_METHOD'),
            'url' => $this->get_current_url(),
            'query_string' => self::_server_variable('QUERY_STRING'),
        );

        // dont set this as an empty array as PHP will treat it as a numeric array
        // instead of a mapping which goes against the defined Sentry spec
        if (!empty($_POST)) {
            $result['data'] = $_POST;
        } elseif (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
            $raw_data = $this->getInputStream() ?: false;
            if ($raw_data !== false) {
                $result['data'] = (array) json_decode($raw_data, true) ?: null;
            }
        }
        if (!empty($_COOKIE)) {
            $result['cookies'] = $_COOKIE;
        }
        if (!empty($headers)) {
            $result['headers'] = $headers;
        }

        return array(
            'request' => $result,
        );
    }

    protected function get_user_data()
    {
        $user = $this->context->user;
        if ($user === null) {
            if (!function_exists('session_id') || !session_id()) {
                return array();
            }
            $user = array(
                'id' => session_id(),
            );
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $user['ip_address'] = $_SERVER['REMOTE_ADDR'];
            }
            if (!empty($_SESSION)) {
                $user['data'] = $_SESSION;
            }
        }
        return array(
            'user' => $user,
        );
    }

    protected function get_extra_data()
    {
        return $this->extra_data;
    }

    public function get_default_data()
    {
        return array(
            'server_name' => $this->name,
            'project' => $this->project,
            'site' => $this->site,
            'logger' => $this->logger,
            'tags' => $this->tags,
            'platform' => 'php',
            'sdk' => $this->sdk,
            'transaction' => $this->transaction->peek(),
        );
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
            $data['tags'] = array();
        }
        if (!isset($data['extra'])) {
            $data['extra'] = array();
        }
        if (!isset($data['event_id'])) {
            $data['event_id'] = static::uuid4();
        }

        if (isset($data['message'])) {
            $data['message'] = Raven_Compat::substr($data['message'], 0, $this->message_limit);
        }

        $data = array_merge($this->get_default_data(), $data);

        if (static::is_http_request()) {
            $data = array_merge($this->get_http_data(), $data);
        }

        $data = array_merge($this->get_user_data(), $data);

        if ($this->release) {
            $data['release'] = $this->release;
        }
        if ($this->environment) {
            $data['environment'] = $this->environment;
        }

        $data['tags'] = array_merge(
            $this->tags,
            $this->context->tags,
            $data['tags']);

        $data['extra'] = array_merge(
            $this->get_extra_data(),
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
        if (empty($data['site'])) {
            unset($data['site']);
        }

        $existing_runtime_context = isset($data['contexts']['runtime']) ? $data['contexts']['runtime'] : array();
        $runtime_context = array('version' => self::cleanup_php_version(), 'name' => 'php');
        $data['contexts']['runtime'] =  array_merge($runtime_context, $existing_runtime_context);

        if (!$this->breadcrumbs->is_empty()) {
            $data['breadcrumbs'] = $this->breadcrumbs->fetch();
        }

        if ((!$stack && $this->auto_log_stacks) || $stack === true) {
            $stack = debug_backtrace();

            // Drop last stack
            array_shift($stack);
        }

        if (! empty($stack) && ! isset($data['stacktrace']) && ! isset($data['exception'])) {
            $data['stacktrace'] = array(
                'frames' => Raven_Stacktrace::get_stack_info(
                    $stack, $this->trace, $vars, $this->message_limit, $this->prefixes,
                    $this->app_path, $this->excluded_app_paths, $this->serializer, $this->reprSerializer
                ),
            );
        }

        $this->sanitize($data);
        $this->process($data);

        if (!$this->store_errors_for_bulk_send) {
            if ($this->send($data) === false) {
                $this->_last_event_id = null;

                return null;
            }
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
            $data['request'] = $this->serializer->serialize($data['request'], 5);
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
        if (!empty($data['breadcrumbs'])) {
            $data['breadcrumbs'] = $this->serializer->serialize($data['breadcrumbs'], 5);
        }
    }

    /**
     * Process data through all defined Raven_Processor sub-classes
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
        $this->_pending_events = array();
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
        $message = Raven_Compat::json_encode($data);
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

        if ($this->useCompression) {
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
        if (is_callable($this->send_callback)
            && call_user_func_array($this->send_callback, array(&$data)) === false
        ) {
            // if send_callback returns false, end native send
            return false;
        }

        if (!$this->server) {
            return false;
        }

        if ($this->transport) {
            return call_user_func($this->transport, $this, $data);
        }

        // should this event be sampled?
        if (rand(1, 100) / 100.0 > $this->sample_rate) {
            return false;
        }

        $message = $this->encode($data);

        $headers = array(
            'User-Agent' => static::getUserAgent(),
            'X-Sentry-Auth' => $this->getAuthHeader(),
            'Content-Type' => 'application/octet-stream'
        );

        $this->send_remote($this->server, $message, $headers);
    }

    /**
     * Send data to Sentry
     *
     * @param string       $url     Full URL to Sentry
     * @param array|string $data    Associative array of data to log
     * @param array        $headers Associative array of headers
     */
    protected function send_remote($url, $data, $headers = array())
    {
        $parts = parse_url($url);
        $parts['netloc'] = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : null);
        $this->send_http($url, $data, $headers);
    }

    protected static function get_default_ca_cert()
    {
        return dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cacert.pem';
    }

    /**
     * @return array
     * @see http://stackoverflow.com/questions/9062798/php-curl-timeout-is-not-working/9063006#9063006
     */
    protected function get_curl_options()
    {
        $options = array(
            CURLOPT_VERBOSE => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => $this->verify_ssl,
            CURLOPT_CAINFO => $this->ca_cert,
            CURLOPT_USERAGENT => 'sentry-php/' . self::VERSION,
        );
        if ($this->http_proxy) {
            $options[CURLOPT_PROXY] = $this->http_proxy;
        }
        if ($this->curl_ssl_version) {
            $options[CURLOPT_SSLVERSION] = $this->curl_ssl_version;
        }
        if ($this->verify_ssl === false) {
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        if ($this->curl_ipv4) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        if (defined('CURLOPT_TIMEOUT_MS')) {
            // MS is available in curl >= 7.16.2
            $timeout = max(1, ceil(1000 * $this->timeout));

            // some versions of PHP 5.3 don't have this defined correctly
            if (!defined('CURLOPT_CONNECTTIMEOUT_MS')) {
                //see stackoverflow link in the phpdoc
                define('CURLOPT_CONNECTTIMEOUT_MS', 156);
            }

            $options[CURLOPT_CONNECTTIMEOUT_MS] = $timeout;
            $options[CURLOPT_TIMEOUT_MS] = $timeout;
        } else {
            // fall back to the lower-precision timeout.
            $timeout = max(1, ceil($this->timeout));
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
    protected function send_http($url, $data, $headers = array())
    {
        if ($this->curl_method == 'async') {
            $this->_curl_handler->enqueue($url, $data, $headers);
        } elseif ($this->curl_method == 'exec') {
            $this->send_http_asynchronous_curl_exec($url, $data, $headers);
        } else {
            $this->send_http_synchronous($url, $data, $headers);
        }
    }

    protected function buildCurlCommand($url, $data, $headers)
    {
        // TODO(dcramer): support ca_cert
        $cmd = $this->curl_path.' -X POST ';
        foreach ($headers as $key => $value) {
            $cmd .= '-H ' . escapeshellarg($key.': '.$value). ' ';
        }
        $cmd .= '-d ' . escapeshellarg($data) . ' ';
        $cmd .= escapeshellarg($url) . ' ';
        $cmd .= '-m 5 ';  // 5 second timeout for the whole process (connect + send)
        if (!$this->verify_ssl) {
            $cmd .= '-k ';
        }
        $cmd .= '> /dev/null 2>&1 &'; // ensure exec returns immediately while curl runs in the background

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
        $new_headers = array();
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
        if ((($errno == 60) || ($errno == 77)) && !is_null($ca_cert)) {
            curl_setopt($this->_curl_instance, CURLOPT_CAINFO, $ca_cert);
            $buffer = curl_exec($this->_curl_instance);
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
     * @param string $client     HTTP client name (not Raven_Client object)
     * @param string $api_key    Sentry API key
     * @param string $secret_key Sentry API key
     * @return string
     */
    protected static function get_auth_header($timestamp, $client, $api_key, $secret_key)
    {
        $header = array(
            sprintf('sentry_timestamp=%F', $timestamp),
            "sentry_client={$client}",
            sprintf('sentry_version=%s', self::PROTOCOL),
        );

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
            $timestamp, static::getUserAgent(), $this->public_key, $this->secret_key
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

        if (!$this->ignore_server_port) {
            $hasNonDefaultPort = !empty($_SERVER['SERVER_PORT']) && !in_array((int)$_SERVER['SERVER_PORT'], array(80, 443));
            if ($hasNonDefaultPort && !preg_match('#:[0-9]*$#', $host)) {
                $host .= ':' . $_SERVER['SERVER_PORT'];
            }
        }

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

        if (!empty($this->trust_x_forwarded_proto) &&
            !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
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
            case E_ERROR:              return Raven_Client::ERROR;
            case E_WARNING:            return Raven_Client::WARN;
            case E_PARSE:              return Raven_Client::ERROR;
            case E_NOTICE:             return Raven_Client::INFO;
            case E_CORE_ERROR:         return Raven_Client::ERROR;
            case E_CORE_WARNING:       return Raven_Client::WARN;
            case E_COMPILE_ERROR:      return Raven_Client::ERROR;
            case E_COMPILE_WARNING:    return Raven_Client::WARN;
            case E_USER_ERROR:         return Raven_Client::ERROR;
            case E_USER_WARNING:       return Raven_Client::WARN;
            case E_USER_NOTICE:        return Raven_Client::INFO;
            case E_STRICT:             return Raven_Client::INFO;
            case E_RECOVERABLE_ERROR:  return Raven_Client::ERROR;
        }
        if (PHP_VERSION_ID >= 50300) {
            switch ($severity) {
            case E_DEPRECATED:         return Raven_Client::WARN;
            case E_USER_DEPRECATED:    return Raven_Client::WARN;
          }
        }
        return Raven_Client::ERROR;
    }

    /**
     * Provide a map of PHP Error constants to Sentry logging groups to use instead
     * of the defaults in translateSeverity()
     *
     * @param array $map
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
    public function set_user_data($id, $email = null, $data = array())
    {
        $user = array('id' => $id);
        if (isset($email)) {
            $user['email'] = $email;
        }
        $this->user_context(array_merge($user, $data));
    }

    public function onShutdown()
    {
        if (!defined('RAVEN_CLIENT_END_REACHED')) {
            define('RAVEN_CLIENT_END_REACHED', true);
        }
        $this->sendUnsentErrors();
        if ($this->curl_method == 'async') {
            $this->_curl_handler->join();
        }
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

    /**
     * @param Raven_Serializer $serializer
     */
    public function setSerializer(Raven_Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param Raven_ReprSerializer $reprSerializer
     */
    public function setReprSerializer(Raven_ReprSerializer $reprSerializer)
    {
        $this->reprSerializer = $reprSerializer;
    }

    /**
     * Cleans up the PHP version string by extracting junk from the extra part of the version.
     *
     * @param string $extra
     *
     * @return string
     */
    public static function cleanup_php_version($extra = PHP_EXTRA_VERSION)
    {
        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;

        if (!empty($extra) && preg_match('{^-(?<extra>(beta|rc)-?([0-9]+)?(-dev)?)}i', $extra, $matches) === 1) {
            $version .= '-' . $matches['extra'];
        }

        return $version;
    }

    private function triggerAutoload()
    {
        // manually trigger autoloading, as it cannot be done during error handling in some edge cases due to PHP (see #60149)

        if (! class_exists('Raven_Stacktrace')) {
            spl_autoload_call('Raven_Stacktrace');
        }

        if (function_exists('mb_detect_encoding')) {
            mb_detect_encoding('string');
        }

        if (function_exists('mb_convert_encoding')) {
            mb_convert_encoding('string', 'UTF8');
        }
    }
}
