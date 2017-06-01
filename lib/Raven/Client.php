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
     * @var \Raven\Breadcrumbs
     */
    protected $breadcrumbs;
    /**
     * @var \Raven\Context
     */
    protected $context;
    /**
     * @var \Raven\TransactionStack
     */
    protected $transaction;
    /**
     * @var array $extra_data
     */
    protected $extra_data;
    /**
     * @var string[]|null
     */
    protected $severity_map;
    public $store_errors_for_bulk_send = false;

    /**
     * @var \Raven\ErrorHandler $error_handler
     */
    protected $error_handler;
    /**
     * @var integer|null bit mask for error_reporting used in ErrorHandler::handleError
     */
    protected $error_types;

    /**
     * @var \Raven\Serializer $serializer
     */
    protected $serializer;
    /**
     * @var \Raven\Serializer $serializer
     */
    protected $reprSerializer;

    /**
     * @var string $app_path The root path to your application code
     */
    protected $app_path;
    /**
     * @var string[] $prefixes Prefixes which should be stripped from filenames to create relative paths
     */
    protected $prefixes;
    /**
     * @var string[]|null Paths to exclude from app_path detection
     */
    protected $excluded_app_paths;
    /**
     * @var Callable Set a custom transport to override how Sentry events are sent upstream
     */
    protected $transport;

    /**
     * @var string $logger Adjust the default logger name for messages
     */
    protected $logger;
    /**
     * @var string Full URL to Sentry (not a DSN)
     * @doc https://docs.sentry.io/quickstart/
     */
    protected $server;
    /**
     * @var string $secret_key Password in Sentry Server
     */
    protected $secret_key;
    /**
     * @var string $public_key Password in Sentry Server
     */
    protected $public_key;
    /**
     * @var integer $project This project ID in Sentry Server
     */
    protected $project;
    /**
     * @var boolean $auto_log_stacks Fill stacktrace by debug_backtrace()
     */
    protected $auto_log_stacks;
    /**
     * @var string $name Override the default value for the server’s hostname
     */
    protected $name;
    /**
     * @var string $site SERVER_NAME (not a HTTP_HOST)
     */
    protected $site;
    /**
     * @var array $tags An array of tags to apply to events in this context
     */
    protected $tags;
    /**
     * @var mixed $release The version of your application (e.g. git SHA)
     */
    protected $release;
    /**
     * @var string $environment The environment your application is running in
     */
    protected $environment;
    /**
     * @var double The sampling factor to apply to events. A value of 0.00 will deny sending
     * any events, and a value of 1.00 will send 100% of events
     */
    protected $sample_rate;
    /**
     * @var boolean $trace Set this to false to disable reflection tracing
     * (function calling arguments) in stacktraces
     * @todo Проверить используется ли это
     */
    protected $trace;
    /**
     * @var double $timeout Timeout for sending data
     */
    protected $timeout;
    /**
     * @var string $message_limit This value is used to truncate message and frame variables.
     * However it is not guarantee that length of whole message will be restricted by this value
     */
    protected $message_limit;
    /**
     * @var string[] $exclude Excluded exceptions classes
     */
    protected $exclude;
    protected $http_proxy;
    /**
     * @var Callable $send_callback A function which will be called whenever data is ready to be sent.
     * Within the function you can mutate the data, or alternatively return false to instruct the SDK
     * to not send the event
     */
    protected $send_callback;
    /**
     * @var string $curl_method
     * sync (default): send requests immediately when they’re made
     * async: uses a curl_multi handler for best-effort asynchronous submissions
     * exec: asynchronously send events by forking a curl process for each item
     */
    protected $curl_method;
    /**
     * @var string $curl_path Specify the path to the curl binary to be used with the ‘exec’ curl method
     */
    protected $curl_path;
    /**
     * @var boolean $curl_ipv4 Resolve domain only with IPv4
     * @todo change to $curl_ipresolve, http://php.net/manual/ru/function.curl-setopt.php
     */
    protected $curl_ipv4;
    /**
     * @var string $ca_cert The path to the CA certificate bundle
     */
    protected $ca_cert;
    /**
     * @var boolean $verify_ssl
     */
    protected $verify_ssl;
    /**
     * @var mixed The SSL version (2 or 3) to use. By default PHP will try to determine this itself,
     * although in some cases this must be set manually
     */
    protected $curl_ssl_version;
    protected $trust_x_forwarded_proto;
    protected $mb_detect_order;
    /**
     * @var \Raven\Processor[] $processors An array of classes to use to process data before it is sent to Sentry
     */
    protected $processors;
    /**
     * @var string|int|null
     */
    protected $_lasterror;
    /**
     * @var object|null
     */
    protected $_last_sentry_error;
    protected $_last_event_id;
    protected $_user;
    /**
     * @var array[] $_pending_events
     */
    protected $_pending_events;
    /**
     * @var array User Agent showed in Sentry
     */
    protected $sdk;
    /**
     * @var \Raven\CurlHandler
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

    public function __construct($options_or_dsn = null, $options = [])
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
        unset($dsn);
        $this->init_with_options($options);

        if (\Raven\Util::get($options, 'install_default_breadcrumb_handlers', true)) {
            $this->registerDefaultBreadcrumbHandlers();
        }

        if (\Raven\Util::get($options, 'install_shutdown_handler', true)) {
            $this->registerShutdownFunction();
        }
    }

    /**
     * @param array $options
     */
    protected function init_with_options($options)
    {
        foreach (
            [
                ['logger', 'php',],
                ['server',],
                ['secret_key',],
                ['public_key',],
                ['project',1,],
                ['auto_log_stacks', false,],
                ['name', gethostname()],
                ['site', self::_server_variable('SERVER_NAME')],
                ['tags', []],
                ['release', []],
                ['environment'],
                ['sample_rate', 1],
                ['trace', true],
                ['timeout', 2],
                ['message_limit', self::MESSAGE_LIMIT],
                ['exclude', []],
                ['http_proxy'],
                ['extra_data', [], 'extra'],
                ['send_callback'],

                ['curl_method', 'sync'],
                ['curl_path', 'curl'],
                ['curl_ipv4', true],
                ['ca_cert', static::get_default_ca_cert()],
                ['verify_ssl', true],
                ['curl_ssl_version'],
                ['trust_x_forwarded_proto'],
                ['transport'],
                ['mb_detect_order'],
                ['error_types'],
            ] as &$set
        ) {
            if (count($set) == 1) {
                $set = [$set[0], null, null];
            } elseif (count($set) == 2) {
                $set[] = null;
            }

            list($object_key, $default_value, $array_key) = $set;
            if (is_null($array_key)) {
                $array_key = $object_key;
            }

            // @todo It should be isset or array_key_exists?
            $this->{$object_key} = isset($options[$array_key]) ? $options[$array_key] : $default_value;
        }
        $this->auto_log_stacks = (boolean)$this->auto_log_stacks;
        $this->severity_map = null;

        // app path is used to determine if code is part of your application
        $this->setAppPath(\Raven\Util::get($options, 'app_path', null));
        $this->setExcludedAppPaths(\Raven\Util::get($options, 'excluded_app_paths', null));
        // a list of prefixes used to coerce absolute paths into relative
        $this->setPrefixes(\Raven\Util::get($options, 'prefixes', static::getDefaultPrefixes()));
        $this->processors = $this->setProcessorsFromOptions($options);

        $this->_lasterror = null;
        $this->_last_sentry_error = null;
        $this->_curl_instance = null;
        $this->_last_event_id = null;
        $this->_user = null;
        $this->_pending_events = [];
        $this->context = new \Raven\Context();
        $this->breadcrumbs = new \Raven\Breadcrumbs();
        $this->_shutdown_function_has_been_set = false;

        $this->sdk = \Raven\Util::get($options, 'sdk', [
            'name' => 'sentry-php',
            'version' => self::VERSION,
        ]);
        $this->serializer = new \Raven\Serializer($this->mb_detect_order);
        $this->reprSerializer = new \Raven\ReprSerializer($this->mb_detect_order);
        if (\Raven\Util::get($options, 'serialize_all_object', false)) {
            $this->setAllObjectSerialize(true);
        }

        if ($this->curl_method == 'async') {
            $this->_curl_handler = new \Raven\CurlHandler($this->get_curl_options());
        }

        $this->transaction = new \Raven\TransactionStack();
        if (static::is_http_request() && isset($_SERVER['PATH_INFO'])) {
            // @codeCoverageIgnoreStart
            $this->transaction->push($_SERVER['PATH_INFO']);
            // @codeCoverageIgnoreEnd
        }
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
        $this->error_handler = new \Raven\ErrorHandler($this, false, $this->error_types);
        $this->error_handler->registerExceptionHandler();
        $this->error_handler->registerErrorHandler();
        $this->error_handler->registerShutdownFunction();
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
            $path = $path.DIRECTORY_SEPARATOR;
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
        $this->excluded_app_paths = $value ? array_map([$this, '_convertPath'], $value) : null;
        return $this;
    }

    public function getPrefixes()
    {
        return $this->prefixes;
    }

    /**
     * @param array $value
     * @return \Raven\Client
     */
    public function setPrefixes($value)
    {
        $this->prefixes = $value ? array_map([$this, '_convertPath'], $value) : $value;
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

    public function getServerEndpoint()
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
     * @return \Raven\Client
     */
    public function setTransport($value)
    {
        $this->transport = $value;
        return $this;
    }

    /**
     * @return string[]|\Raven\Processor[]
     */
    public static function getDefaultProcessors()
    {
        return [
            '\\Raven\\Processor\\SanitizeDataProcessor',
        ];
    }

    /**
     * Sets the \Raven\Processor sub-classes to be used when data is processed before being
     * sent to Sentry.
     *
     * @param $options
     * @return \Raven\Processor[]
     */
    public function setProcessorsFromOptions($options)
    {
        $processors = [];
        foreach (\Raven\util::get($options, 'processors', static::getDefaultProcessors()) as $processor) {
            /**
             * @var \Raven\Processor        $new_processor
             * @var \Raven\Processor|string $processor
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
     * @doc http://raven.readthedocs.org/en/latest/config/#the-sentry-dsn
     */
    public static function parseDSN($dsn)
    {
        $url = parse_url($dsn);
        $scheme = (isset($url['scheme']) ? $url['scheme'] : '');
        if (!in_array($scheme, ['http', 'https'])) {
            throw new \InvalidArgumentException(
                'Unsupported Sentry DSN scheme: '.
                (!empty($scheme) ? $scheme : /** @lang text */'<not set>')
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
        if (empty($netloc) || empty($project) || empty($username) || empty($password)) {
            throw new \InvalidArgumentException('Invalid Sentry DSN: ' . $dsn);
        }

        return [
            'server'     => sprintf('%s://%s%s/api/%s/store/', $scheme, $netloc, $path, $project),
            'project'    => $project,
            'public_key' => $username,
            'secret_key' => $password,
        ];
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
        if (in_array(get_class($exception), $this->exclude)) {
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

    protected function getExtra_data()
    {
        return $this->extra_data;
    }

    public function get_default_data()
    {
        return [
            'server_name' => $this->name,
            'project' => $this->project,
            'site' => $this->site,
            'logger' => $this->logger,
            'tags' => $this->tags,
            'platform' => 'php',
            'sdk' => $this->sdk,
            'culprit' => $this->transaction->peek(),
        ];
    }

    public function capture($data, $stack = null)
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
            $data['message'] = substr($data['message'], 0, $this->message_limit);
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
            $this->getExtra_data(),
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

        if ((!$stack && $this->auto_log_stacks) || $stack === true) {
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
    protected function process(&$data)
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
    protected function encode(&$data)
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
        if (is_callable($this->send_callback)
            && call_user_func_array($this->send_callback, [&$data]) === false
        ) {
            // if send_callback returns false, end native send
            return;
        }

        if (!$this->server) {
            return;
        }

        if ($this->transport) {
            call_user_func($this->transport, $this, $data);
            return;
        }

        // should this event be sampled?
        if (mt_rand(1, 100) / 100.0 > $this->sample_rate) {
            return;
        }

        $message = $this->encode($data);

        $headers = [
            'User-Agent' => static::getUserAgent(),
            'X-Sentry-Auth' => $this->getAuthHeader(),
            'Content-Type' => 'application/octet-stream'
        ];

        $this->send_remote($this->server, $message, $headers);
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

    protected static function get_default_ca_cert()
    {
        return dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cacert.pem';
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
            CURLOPT_SSL_VERIFYPEER => $this->verify_ssl,
            CURLOPT_CAINFO => $this->ca_cert,
            CURLOPT_USERAGENT => 'sentry-php/' . self::VERSION,
        ];
        if ($this->http_proxy) {
            $options[CURLOPT_PROXY] = $this->http_proxy;
        }
        if ($this->curl_ssl_version) {
            $options[CURLOPT_SSLVERSION] = $this->curl_ssl_version;
        }
        if ($this->curl_ipv4) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        if (defined('CURLOPT_TIMEOUT_MS')) {
            // MS is available in curl >= 7.16.2
            $timeout = max(1, ceil(1000 * $this->timeout));

            // None of the versions of PHP contains this constant
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
    protected function send_http($url, $data, $headers = [])
    {
        if ($this->curl_method == 'async') {
            $this->_curl_handler->enqueue($url, $data, $headers);
        } elseif ($this->curl_method == 'exec') {
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
            escapeshellcmd($this->curl_path), $post_fields,
            escapeshellarg($data), escapeshellarg($url), $this->timeout,
            !$this->verify_ssl ? '-k ' : '',
            !empty($this->ca_cert) ? '--cacert '.escapeshellarg($this->ca_cert).' ' : ''
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

    protected function getAuthHeader()
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

    /**
     * @return Breadcrumbs
     */
    public function getBreadcrumbs()
    {
        return $this->breadcrumbs;
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
