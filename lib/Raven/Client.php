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

use Http\Client\HttpAsyncClient;
use Http\Message\Encoding\CompressStream;
use Http\Message\RequestFactory;
use Http\Promise\Promise;
use Psr\Http\Message\ResponseInterface;
use Raven\Breadcrumbs\Breadcrumb;
use Raven\Breadcrumbs\Recorder;
use Raven\HttpClient\Encoding\Base64EncodingStream;
use Raven\Util\JSON;

/**
 * Raven PHP Client.
 *
 * @doc https://docs.sentry.io/clients/php/config/
 */
class Client
{
    const VERSION = '2.0.x-dev';

    const PROTOCOL = '6';

    /**
     * Debug log levels.
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_FATAL = 'fatal';

    /**
     * Default message limit.
     */
    const MESSAGE_LIMIT = 1024;

    /**
     * @var Recorder The bredcrumbs recorder
     */
    protected $recorder;

    /**
     * This constant defines the client's user-agent string.
     */
    const USER_AGENT = 'sentry-php/' . self::VERSION;

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
    public $severityMap;
    public $storeErrorsForBulkSend = false;

    /**
     * @var ErrorHandler
     */
    protected $errorHandler;

    /**
     * @var \Raven\Serializer
     */
    protected $serializer;
    /**
     * @var \Raven\Serializer
     */
    protected $reprSerializer;

    /**
     * @var \Raven\Processor[] An array of classes to use to process data before it is sent to Sentry
     */
    protected $processors = [];

    /**
     * @var string|int|null
     */
    private $lastError;

    private $lastEventId;

    /**
     * @var array[]
     */
    public $pendingEvents = [];

    /**
     * @var bool
     */
    protected $shutdownFunctionHasBeenSet = false;

    /**
     * @var Configuration The client configuration
     */
    protected $config;

    /**
     * @var HttpAsyncClient The HTTP client
     */
    private $httpClient;

    /**
     * @var RequestFactory The PSR-7 request factory
     */
    private $requestFactory;

    /**
     * @var Promise[] The list of pending requests
     */
    private $pendingRequests = [];

    /**
     * Constructor.
     *
     * @param Configuration   $config         The client configuration
     * @param HttpAsyncClient $httpClient     The HTTP client
     * @param RequestFactory  $requestFactory The PSR-7 request factory
     */
    public function __construct(Configuration $config, HttpAsyncClient $httpClient, RequestFactory $requestFactory)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->context = new Context();
        $this->recorder = new Recorder();
        $this->transaction = new TransactionStack();
        $this->serializer = new Serializer($this->config->getMbDetectOrder());
        $this->reprSerializer = new ReprSerializer($this->config->getMbDetectOrder());
        $this->processors = $this->createProcessors();

        if (static::isHttpRequest() && isset($_SERVER['PATH_INFO'])) {
            $this->transaction->push($_SERVER['PATH_INFO']);
        }

        if ($this->config->getSerializeAllObjects()) {
            $this->setAllObjectSerialize(true);
        }

        if ($this->config->shouldInstallDefaultBreadcrumbHandlers()) {
            $this->registerDefaultBreadcrumbHandlers();
        }

        if ($this->config->shouldInstallShutdownHandler()) {
            $this->registerShutdownFunction();
        }
    }

    /**
     * Destruct all objects contain link to this object.
     *
     * This method can not delete shutdown handler
     */
    public function __destruct()
    {
        $this->sendUnsentErrors();
    }

    /**
     * Records the given breadcrumb.
     *
     * @param Breadcrumb $breadcrumb The breadcrumb instance
     */
    public function leaveBreadcrumb(Breadcrumb $breadcrumb)
    {
        $this->recorder->record($breadcrumb);
    }

    /**
     * Clears all recorded breadcrumbs.
     */
    public function clearBreadcrumbs()
    {
        $this->recorder->clear();
    }

    public function getConfig()
    {
        return $this->config;
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
     *
     * @throws \Raven\Exception
     */
    public function install()
    {
        if ($this->errorHandler) {
            throw new \Raven\Exception(__CLASS__ . '->install() must only be called once');
        }

        $this->errorHandler = new ErrorHandler($this, false, $this->getConfig()->getErrorTypes());
        $this->errorHandler->registerExceptionHandler();
        $this->errorHandler->registerErrorHandler();
        $this->errorHandler->registerShutdownFunction();

        return $this;
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
            /** @var Processor $processorInstance */
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
        return $this->lastError;
    }

    /**
     * Given an identifier, returns a Sentry searchable string.
     *
     * @param mixed $ident
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getIdent($ident)
    {
        // XXX: We don't calculate checksums yet, so we only have the ident.
        return $ident;
    }

    /**
     * @param string     $message the message (primary description) for the event
     * @param array      $params  params to use when formatting the message
     * @param string     $level   Log level group
     * @param bool|array $stack
     * @param mixed      $vars
     *
     * @return string|null
     *
     * @deprecated
     * @codeCoverageIgnore
     */
    public function message(
        $message,
        $params = [],
        $level = self::LEVEL_INFO,
        $stack = false,
        $vars = null
    ) {
        return $this->captureMessage($message, $params, $level, $stack, $vars);
    }

    /**
     * Log a message to sentry.
     *
     * @param string     $message the message (primary description) for the event
     * @param array      $params  params to use when formatting the message
     * @param array      $data    additional attributes to pass with this event (see Sentry docs)
     * @param bool|array $stack
     * @param mixed      $vars
     *
     * @return string|null
     */
    public function captureMessage(
        $message,
        $params = [],
        $data = [],
                            $stack = false,
        $vars = null
    ) {
        if ($data === null) {
            $data = [];
            // support legacy method of passing in a level name as the third arg
        } elseif (!is_array($data)) {
            $data = [
                'level' => $data,
            ];
        }

        $data['sentry.interfaces.Message'] = [
            'message' => $message,
            'params' => $params,
        ];

        return $this->capture($data, $stack, $vars);
    }

    /**
     * Log an exception to sentry.
     *
     * @param \Exception $exception the Exception object
     * @param array      $data      additional attributes to pass with this event (see Sentry docs)
     * @param mixed      $logger
     * @param mixed      $vars
     *
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

        $currentException = $exception;
        do {
            $exceptionData = [
                'value' => $this->serializer->serialize($currentException->getMessage()),
                'type' => get_class($currentException),
            ];

            /**
             * Exception::getTrace doesn't store the point at where the exception
             * was thrown, so we have to stuff it in ourselves. Ugh.
             */
            $trace = $currentException->getTrace();
            $frameWhereExceptionWasThrown = [
                'file' => $currentException->getFile(),
                'line' => $currentException->getLine(),
            ];

            array_unshift($trace, $frameWhereExceptionWasThrown);

            $this->autoloadRavenStacktrace();

            $exc_data['stacktrace'] = [
                'frames' => Stacktrace::createFromBacktrace(
                    $this,
                    $exception->getTrace(),
                    $exception->getFile(),
                    $exception->getLine()
                )->getFrames(),
            ];

            $exceptions[] = $exceptionData;
        } while ($currentException = $currentException->getPrevious());

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
                $data['level'] = self::LEVEL_ERROR;
            }
        }

        return $this->capture($data, $trace, $vars);
    }

    /**
     * Capture the most recent error (obtained with ``error_get_last``).
     *
     * @return string|null
     */
    public function captureLastError()
    {
        $error = error_get_last();

        if (null === $error || !isset($error['message'][0])) {
            return null;
        }

        $e = new \ErrorException(
            @$error['message'],
            0,
            @$error['type'],
            @$error['file'],
            @$error['line']
        );

        return $this->captureException($e);
    }

    /**
     * Log an query to sentry.
     *
     * @param string|null $query
     * @param string      $level
     * @param string      $engine
     */
    public function captureQuery($query, $level = self::LEVEL_INFO, $engine = '')
    {
        $data = [
            'message' => $query,
            'level' => $level,
            'sentry.interfaces.Query' => [
                'query' => $query,
            ],
        ];

        if ($engine !== '') {
            $data['sentry.interfaces.Query']['engine'] = $engine;
        }

        return $this->capture($data, false);
    }

    /**
     * Return the last captured event's ID or null if none available.
     */
    public function getLastEventId()
    {
        return $this->lastEventId;
    }

    protected function registerDefaultBreadcrumbHandlers()
    {
        $handler = new Breadcrumbs\ErrorHandler($this);
        $handler->install();
    }

    protected function registerShutdownFunction()
    {
        if (!$this->shutdownFunctionHasBeenSet) {
            $this->shutdownFunctionHasBeenSet = true;
            register_shutdown_function([$this, 'onShutdown']);
        }
    }

    /**
     * @return bool
     * @codeCoverageIgnore
     */
    protected static function isHttpRequest()
    {
        return isset($_SERVER['REQUEST_METHOD']) && PHP_SAPI !== 'cli';
    }

    protected function getHttpData()
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
            'url' => $this->getCurrentUrl(),
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

        return $result;
    }

    protected function getUserData()
    {
        $user = $this->context->getUserData();
        if (empty($user)) {
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

        return $user;
    }

    public function getDefaultData()
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
        $event = new Event($this->config);
        $event->setTagsContext(array_merge($this->context->tags, isset($data['tags']) ? $data['tags'] : []));
        $event->setUserContext(array_merge($this->get_user_data(), isset($data['user']) ? $data['user'] : []));
        $event->setExtraContext(array_merge($this->context->extra, isset($data['extra']) ? $data['extra'] : []));

        if (static::is_http_request()) {
            $event->setRequest(isset($data['request']) ? $data['request'] : $this->get_http_data());
        }

        if (isset($data['culprit'])) {
            $event->setCulprit($data['culprit']);
        } else {
            $event->setCulprit($this->transaction->peek());
        }

        if (isset($data['level'])) {
            $event->setLevel($data['level']);
        }

        if (isset($data['logger'])) {
            $event->setLogger($data['logger']);
        }

        if (isset($data['message'])) {
            $event->setMessage(substr($data['message'], 0, static::MESSAGE_LIMIT));
        }

        if (isset($data['sentry.interfaces.Message'])) {
            $event->setMessage(substr($data['sentry.interfaces.Message']['message'], 0, static::MESSAGE_LIMIT), $data['sentry.interfaces.Message']['params']);
        }

        if (isset($data['exception'])) {
            $event->setException($data['exception']);
        }

        foreach ($this->recorder as $breadcrumb) {
            $event->addBreadcrumb($breadcrumb);
        }

        if ((!$stack && $this->config->getAutoLogStacks()) || $stack === true) {
            $stack = debug_backtrace();

            // Drop last stack
            array_shift($stack);
        }

        if (!empty($stack)) {
            $this->autoloadRavenStacktrace();

            if (!isset($data['stacktrace']) && !isset($data['exception'])) {
                $data['stacktrace'] = Stacktrace::createFromBacktrace($this, $stack, isset($stack['file']) ? $stack['file'] : __FILE__, isset($stack['line']) ? $stack['line'] : __LINE__);
            }
        }

        if (isset($data['stacktrace'])) {
            $event->setStacktrace($data['stacktrace']);
        }

        $data = $event->toArray();

        $this->sanitize($data);
        $this->process($data);

        if (!$this->storeErrorsForBulkSend) {
            $this->send($data);
        } else {
            $this->pendingEvents[] = $data;
        }

        $this->lastEventId = $data['event_id'];

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
                $data['tags'][$key] = @(string) $value;
            }
        }
        if (!empty($data['contexts'])) {
            $data['contexts'] = $this->serializer->serialize($data['contexts'], 5);
        }
    }

    /**
     * Process data through all defined \Raven\Processor sub-classes.
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
        foreach ($this->pendingEvents as $data) {
            $this->send($data);
        }

        $this->pendingEvents = [];

        if ($this->storeErrorsForBulkSend) {
            //in case an error occurs after this is called, on shutdown, send any new errors.
            $this->storeErrorsForBulkSend = !defined('RAVEN_CLIENT_END_REACHED');
        }

        foreach ($this->pendingRequests as $pendingRequest) {
            $pendingRequest->wait();
        }
    }

    /**
     * Sends the given event to the Sentry server.
     *
     * @param array $data Associative array of data to log
     */
    public function send(&$data)
    {
        if (!$this->config->shouldCapture($data) || !$this->config->getServer()) {
            return;
        }

        if ($this->config->getTransport()) {
            call_user_func($this->getConfig()->getTransport(), $this, $data);

            return;
        }

        // should this event be sampled?
        if (mt_rand(1, 100) / 100.0 > $this->config->getSampleRate()) {
            return;
        }

        $request = $this->requestFactory->createRequest(
            'POST',
            sprintf('api/%d/store/', $this->getConfig()->getProjectId()),
            ['Content-Type' => $this->isEncodingCompressed() ? 'application/octet-stream' : 'application/json'],
            JSON::encode($data)
        );

        if ($this->isEncodingCompressed()) {
            $request = $request->withBody(
                new Base64EncodingStream(
                    new CompressStream($request->getBody())
                )
            );
        }

        $promise = $this->httpClient->sendAsyncRequest($request);

        // This function is defined in-line so it doesn't show up for
        // type-hinting on classes that implement this trait.
        $cleanupPromiseCallback = function (ResponseInterface $response) use ($promise) {
            $index = array_search($promise, $this->pendingRequests, true);

            if (false === $index) {
                return $response;
            }

            unset($this->pendingRequests[$index]);

            return $response;
        };

        $promise->then($cleanupPromiseCallback, $cleanupPromiseCallback);

        $this->pendingRequests[] = $promise;
    }

    /**
     * Return the URL for the current request
     *
     * @return string|null
     */
    protected function getCurrentUrl()
    {
        // When running from commandline the REQUEST_URI is missing.
        if (!isset($_SERVER['REQUEST_URI'])) {
            return null;
        }

        // HTTP_HOST is a client-supplied header that is optional in HTTP 1.0
        $host = (!empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST']
            : (!empty($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR']
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
     * Get the value of a key from $_SERVER.
     *
     * @param string $key Key whose value you wish to obtain
     *
     * @return string Key's value
     */
    private static function _server_variable($key)
    {
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return '';
    }

    /**
     * Translate a PHP Error constant into a Sentry log level group.
     *
     * @param string $severity PHP E_$x error constant
     *
     * @return string Sentry log level group
     */
    public function translateSeverity($severity)
    {
        if (is_array($this->severityMap) && isset($this->severityMap[$severity])) {
            return $this->severityMap[$severity];
        }

        switch ($severity) {
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
            case E_RECOVERABLE_ERROR:
                return self::LEVEL_WARNING;
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::LEVEL_ERROR;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                return self::LEVEL_INFO;
            default:
                return self::LEVEL_ERROR;
        }
    }

    /**
     * Provide a map of PHP Error constants to Sentry logging groups to use instead
     * of the defaults in translateSeverity().
     *
     * @param string[] $map
     */
    public function registerSeverityMap($map)
    {
        $this->severityMap = $map;
    }

    public function onShutdown()
    {
        if (!defined('RAVEN_CLIENT_END_REACHED')) {
            define('RAVEN_CLIENT_END_REACHED', true);
        }
        $this->sendUnsentErrors();
    }

    /**
     * @return Context
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param array $processors
     */
    public function setProcessors(array $processors)
    {
        $this->processors = $processors;
    }

    /**
     * @return bool
     */
    public function getShutdownFunctionHasBeenSet()
    {
        return $this->shutdownFunctionHasBeenSet;
    }

    public function setAllObjectSerialize($value)
    {
        $this->serializer->setAllObjectSerialize($value);
        $this->reprSerializer->setAllObjectSerialize($value);
    }

    /**
     * Checks whether the encoding is compressed.
     *
     * @return bool
     */
    private function isEncodingCompressed()
    {
        return 'gzip' === $this->config->getEncoding();
    }

    private function autoloadRavenStacktrace()
    {
        // manually trigger autoloading, as it's not done in some edge cases due to PHP bugs (see #60149)
        if (!class_exists('\\Raven\\Stacktrace')) {
            // @codeCoverageIgnoreStart
            spl_autoload_call('\\Raven\\Stacktrace');
            // @codeCoverageIgnoreEnd
        }
    }
}
