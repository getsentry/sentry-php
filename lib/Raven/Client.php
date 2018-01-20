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
use Psr\Http\Message\ServerRequestInterface;
use Raven\Breadcrumbs\Breadcrumb;
use Raven\Breadcrumbs\Recorder;
use Raven\HttpClient\Encoding\Base64EncodingStream;
use Raven\Middleware\BreadcrumbInterfaceMiddleware;
use Raven\Middleware\ContextInterfaceMiddleware;
use Raven\Middleware\ExceptionInterfaceMiddleware;
use Raven\Middleware\MessageInterfaceMiddleware;
use Raven\Middleware\ProcessorMiddleware;
use Raven\Middleware\RequestInterfaceMiddleware;
use Raven\Middleware\UserInterfaceMiddleware;
use Raven\Processor\ProcessorInterface;
use Raven\Processor\ProcessorRegistry;
use Raven\Util\JSON;
use Zend\Diactoros\ServerRequestFactory;

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
     * @var Recorder The breadcrumbs recorder
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
     * @var SerializerInterface
     */
    protected $serializer;
    /**
     * @var SerializerInterface
     */
    protected $reprSerializer;

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
     * @var ProcessorRegistry The registry of processors
     */
    private $processorRegistry;

    /**
     * @var Promise[] The list of pending requests
     */
    private $pendingRequests = [];

    /**
     * @var callable The tip of the middleware call stack
     */
    private $middlewareStackTip;

    /**
     * @var bool Whether the stack of middleware callables is locked
     */
    private $stackLocked = false;

    /**
     * @var Event The last event that was captured
     */
    private $lastEvent;

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
        $this->processorRegistry = new ProcessorRegistry();
        $this->context = new Context();
        $this->recorder = new Recorder();
        $this->transaction = new TransactionStack();
        $this->serializer = $this->config->getSerializer();
        $this->reprSerializer = $this->config->getReprSerializer();
        $this->middlewareStackTip = function (Event $event) {
            return $event;
        };

        $this->addMiddleware(new ProcessorMiddleware($this->processorRegistry));
        $this->addMiddleware(new MessageInterfaceMiddleware());
        $this->addMiddleware(new RequestInterfaceMiddleware());
        $this->addMiddleware(new UserInterfaceMiddleware());
        $this->addMiddleware(new ContextInterfaceMiddleware($this->context));
        $this->addMiddleware(new BreadcrumbInterfaceMiddleware($this->recorder));
        $this->addMiddleware(new ExceptionInterfaceMiddleware($this));

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

    /**
     * Gets the configuration of the client.
     *
     * @return Configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Adds a new middleware to the end of the stack.
     *
     * @param callable $callable The middleware
     *
     * @throws \RuntimeException If this method is called while the stack is dequeuing
     */
    public function addMiddleware(callable $callable)
    {
        if ($this->stackLocked) {
            throw new \RuntimeException('Middleware can\'t be added once the stack is dequeuing');
        }

        $next = $this->middlewareStackTip;

        $this->middlewareStackTip = function (Event $event, ServerRequestInterface $request = null, \Exception $exception = null, array $payload = []) use ($callable, $next) {
            $result = $callable($event, $next, $request, $exception, $payload);

            if (!$result instanceof Event) {
                throw new \UnexpectedValueException(sprintf('Middleware must return an instance of the "%s" class.', Event::class));
            }

            return $result;
        };
    }

    /**
     * Adds a new processor to the processors chain with the specified priority.
     *
     * @param ProcessorInterface $processor The processor instance
     * @param int                $priority  The priority. The higher this value,
     *                                      the earlier a processor will be
     *                                      executed in the chain (defaults to 0)
     */
    public function addProcessor(ProcessorInterface $processor, $priority = 0)
    {
        $this->processorRegistry->addProcessor($processor, $priority);
    }

    /**
     * Removes the given processor from the list.
     *
     * @param ProcessorInterface $processor The processor instance
     */
    public function removeProcessor(ProcessorInterface $processor)
    {
        $this->processorRegistry->removeProcessor($processor);
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
     * Logs a message.
     *
     * @param string $message The message (primary description) for the event
     * @param array  $params  Params to use when formatting the message
     * @param array  $payload Additional attributes to pass with this event
     *
     * @return string
     */
    public function captureMessage($message, array $params = [], array $payload = [])
    {
        $payload['message'] = $message;
        $payload['message_params'] = $params;

        return $this->capture($payload);
    }

    /**
     * Logs an exception.
     *
     * @param \Throwable|\Exception $exception The exception object
     * @param array                 $payload   Additional attributes to pass with this event
     *
     * @return string
     */
    public function captureException($exception, array $payload = [])
    {
        $payload['exception'] = $exception;

        return $this->capture($payload);
    }

    /**
     * Logs the most recent error (obtained with {@link error_get_last}).
     *
     * @return string
     */
    public function captureLastError()
    {
        $error = error_get_last();

        if (null === $error || !isset($error['message'][0])) {
            return null;
        }

        $exception = new \ErrorException(@$error['message'], 0, @$error['type'], @$error['file'], @$error['line']);

        return $this->captureException($exception);
    }

    /**
     * Gets the last event that was captured by the client. However, it could
     * have been sent or still sit in the queue of pending events.
     *
     * @return Event
     */
    public function getLastEvent()
    {
        return $this->lastEvent;
    }

    /**
     * Return the last captured event's ID or null if none available.
     *
     * @deprecated since version 2.0, to be removed in 3.0. Use getLastEvent() instead.
     */
    public function getLastEventId()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 2.0. Use getLastEvent() instead.', __METHOD__), E_USER_DEPRECATED);

        if (null === $this->lastEvent) {
            return null;
        }

        return str_replace('-', '', $this->lastEvent->getId()->toString());
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

    /**
     * Captures a new event using the provided data.
     *
     * @param array $payload The data of the event being captured
     *
     * @return string
     */
    public function capture(array $payload)
    {
        $event = new Event($this->config);

        if (isset($payload['culprit'])) {
            $event = $event->withCulprit($payload['culprit']);
        } else {
            $event = $event->withCulprit($this->transaction->peek());
        }

        if (isset($payload['level'])) {
            $event = $event->withLevel($payload['level']);
        }

        if (isset($payload['logger'])) {
            $event = $event->withLogger($payload['logger']);
        }

        if (isset($payload['tags_context'])) {
            $event = $event->withTagsContext($payload['tags_context']);
        }

        if (isset($payload['extra_context'])) {
            $event = $event->withExtraContext($payload['extra_context']);
        }

        if (isset($payload['user_context'])) {
            $event = $event->withUserContext($payload['user_context']);
        }

        if (isset($payload['message'])) {
            $payload['message'] = substr($payload['message'], 0, static::MESSAGE_LIMIT);
        }

        $event = $this->callMiddlewareStack($event, static::isHttpRequest() ? ServerRequestFactory::fromGlobals() : null, isset($payload['exception']) ? $payload['exception'] : null, $payload);

        $payload = $event->toArray();

        $this->sanitize($payload);

        if (!$this->storeErrorsForBulkSend) {
            $this->send($payload);
        } else {
            $this->pendingEvents[] = $payload;
        }

        $this->lastEvent = $event;

        return $payload['event_id'];
    }

    public function sanitize(&$data)
    {
        // attempt to sanitize any user provided data
        if (!empty($data['request'])) {
            $data['request'] = $this->serializer->serialize($data['request']);
        }
        if (!empty($data['user'])) {
            $data['user'] = $this->serializer->serialize($data['user'], ['max_depth' => 3]);
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
            $data['contexts'] = $this->serializer->serialize($data['contexts'], ['max_depth' => 5]);
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

    /**
     * Calls the middleware stack.
     *
     * @param Event                       $event     The event object
     * @param ServerRequestInterface|null $request   The request object, if available
     * @param \Exception|null             $exception The thrown exception, if any
     * @param array                       $payload   Additional payload data
     *
     * @return Event
     */
    private function callMiddlewareStack(Event $event, ServerRequestInterface $request = null, \Exception $exception = null, array $payload = [])
    {
        $start = $this->middlewareStackTip;

        $this->stackLocked = true;

        /** @var Event $event */
        $event = $start($event, $request, $exception, $payload);

        $this->stackLocked = false;

        return $event;
    }
}
