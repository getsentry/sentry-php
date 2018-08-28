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

use Raven\Breadcrumbs\Breadcrumb;
use Raven\Breadcrumbs\Recorder;
use Raven\Context\Context;
use Raven\Context\RuntimeContext;
use Raven\Context\ServerOsContext;
use Raven\Context\TagsContext;
use Raven\Middleware\BreadcrumbInterfaceMiddleware;
use Raven\Middleware\ContextInterfaceMiddleware;
use Raven\Middleware\ExceptionInterfaceMiddleware;
use Raven\Middleware\MessageInterfaceMiddleware;
use Raven\Middleware\MiddlewareStack;
use Raven\Middleware\ProcessorMiddleware;
use Raven\Middleware\RequestInterfaceMiddleware;
use Raven\Middleware\SanitizerMiddleware;
use Raven\Middleware\UserInterfaceMiddleware;
use Raven\Processor\ProcessorInterface;
use Raven\Processor\ProcessorRegistry;
use Raven\Transport\TransportInterface;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Default implementation of the {@see ClientInterface} interface.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class Client implements ClientInterface
{
    /**
     * The version of the library.
     */
    const VERSION = '2.0.x-dev';

    /**
     * The version of the protocol to communicate with the Sentry server.
     */
    const PROTOCOL_VERSION = '6';

    /**
     * This constant defines the client's user-agent string.
     */
    const USER_AGENT = 'sentry-php/' . self::VERSION;

    /**
     * This constant defines the maximum length of the message captured by the
     * message SDK interface.
     */
    const MESSAGE_MAX_LENGTH_LIMIT = 1024;

    /**
     * Debug log levels.
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_FATAL = 'fatal';

    /**
     * @var string[]|null
     */
    public $severityMap;

    /**
     * @var Serializer The serializer
     */
    private $serializer;

    /**
     * @var ReprSerializer The representation serializer
     */
    private $representationSerializer;

    /**
     * @var Configuration The client configuration
     */
    private $config;

    /**
     * @var Recorder The breadcrumbs recorder
     */
    private $breadcrumbRecorder;

    /**
     * @var TransactionStack The transaction stack
     */
    private $transactionStack;

    /**
     * @var TransportInterface The transport
     */
    private $transport;

    /**
     * @var ProcessorRegistry The registry of processors
     */
    private $processorRegistry;

    /**
     * @var TagsContext The tags context
     */
    private $tagsContext;

    /**
     * @var Context The user context
     */
    private $userContext;

    /**
     * @var Context The extra context
     */
    private $extraContext;

    /**
     * @var RuntimeContext The runtime context
     */
    private $runtimeContext;

    /**
     * @var ServerOsContext The server OS context
     */
    private $serverOsContext;

    /**
     * @var MiddlewareStack The stack of middlewares to compose an event to send
     */
    private $middlewareStack;

    /**
     * @var Event The last event that was captured
     */
    private $lastEvent;

    /**
     * Constructor.
     *
     * @param Configuration      $config    The client configuration
     * @param TransportInterface $transport The transport
     */
    public function __construct(Configuration $config, TransportInterface $transport)
    {
        $this->config = $config;
        $this->transport = $transport;
        $this->processorRegistry = new ProcessorRegistry();
        $this->tagsContext = new TagsContext();
        $this->userContext = new Context();
        $this->extraContext = new Context();
        $this->runtimeContext = new RuntimeContext();
        $this->serverOsContext = new ServerOsContext();
        $this->breadcrumbRecorder = new Recorder();
        $this->transactionStack = new TransactionStack();
        $this->serializer = new Serializer($this->config->getMbDetectOrder());
        $this->representationSerializer = new ReprSerializer($this->config->getMbDetectOrder());
        $this->middlewareStack = new MiddlewareStack(function (Event $event) {
            return $event;
        });

        $this->addDefaultMiddlewares();

        $request = ServerRequestFactory::fromGlobals();
        $serverParams = $request->getServerParams();

        if (isset($serverParams['PATH_INFO'])) {
            $this->transactionStack->push($serverParams['PATH_INFO']);
        }

        if ($this->config->getSerializeAllObjects()) {
            $this->setAllObjectSerialize(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function leaveBreadcrumb(Breadcrumb $breadcrumb)
    {
        $this->breadcrumbRecorder->record($breadcrumb);
    }

    /**
     * {@inheritdoc}
     */
    public function clearBreadcrumbs()
    {
        $this->breadcrumbRecorder->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function getTransactionStack()
    {
        return $this->transactionStack;
    }

    /**
     * {@inheritdoc}
     */
    public function addMiddleware(callable $middleware, $priority = 0)
    {
        $this->middlewareStack->addMiddleware($middleware, $priority);
    }

    /**
     * {@inheritdoc}
     */
    public function removeMiddleware(callable $middleware)
    {
        $this->middlewareStack->removeMiddleware($middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function addProcessor(ProcessorInterface $processor, $priority = 0)
    {
        $this->processorRegistry->addProcessor($processor, $priority);
    }

    /**
     * {@inheritdoc}
     */
    public function removeProcessor(ProcessorInterface $processor)
    {
        $this->processorRegistry->removeProcessor($processor);
    }

    /**
     * {@inheritdoc}
     */
    public function setAllObjectSerialize($value)
    {
        $this->serializer->setAllObjectSerialize($value);
        $this->representationSerializer->setAllObjectSerialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getRepresentationSerializer()
    {
        return $this->representationSerializer;
    }

    /**
     * {@inheritdoc}
     */
    public function setRepresentationSerializer(ReprSerializer $representationSerializer)
    {
        $this->representationSerializer = $representationSerializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function captureMessage($message, array $params = [], array $payload = [])
    {
        $payload['message'] = $message;
        $payload['message_params'] = $params;

        return $this->capture($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function captureException($exception, array $payload = [])
    {
        $payload['exception'] = $exception;

        return $this->capture($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function captureLastError(array $payload = [])
    {
        $error = error_get_last();

        if (null === $error || !isset($error['message'][0])) {
            return null;
        }

        $exception = new \ErrorException(@$error['message'], 0, @$error['type'], @$error['file'], @$error['line']);

        return $this->captureException($exception, $payload);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastEvent()
    {
        return $this->lastEvent;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastEventId()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 2.0. Use getLastEvent() instead.', __METHOD__), E_USER_DEPRECATED);

        if (null === $this->lastEvent) {
            return null;
        }

        return str_replace('-', '', $this->lastEvent->getId()->toString());
    }

    /**
     * {@inheritdoc}
     */
    public function capture(array $payload)
    {
        $event = new Event($this->config);

        if (isset($payload['transaction'])) {
            $event = $event->withTransaction($payload['transaction']);
        } else {
            $event = $event->withTransaction($this->transactionStack->peek());
        }

        if (isset($payload['logger'])) {
            $event = $event->withLogger($payload['logger']);
        }

        $event = $this->middlewareStack->executeStack(
            $event,
            isset($_SERVER['REQUEST_METHOD']) && \PHP_SAPI !== 'cli' ? ServerRequestFactory::fromGlobals() : null,
            isset($payload['exception']) ? $payload['exception'] : null,
            $payload
        );

        $this->send($event);

        $this->lastEvent = $event;

        return str_replace('-', '', $event->getId()->toString());
    }

    /**
     * {@inheritdoc}
     */
    public function send(Event $event)
    {
        if (!$this->config->shouldCapture($event)) {
            return;
        }

        // should this event be sampled?
        if (mt_rand(1, 100) / 100.0 > $this->config->getSampleRate()) {
            return;
        }

        $this->transport->send($event);
    }

    /**
     * {@inheritdoc}
     */
    public function translateSeverity($severity)
    {
        if (\is_array($this->severityMap) && isset($this->severityMap[$severity])) {
            return $this->severityMap[$severity];
        }

        switch ($severity) {
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_WARNING:
            case E_USER_WARNING:
            case E_RECOVERABLE_ERROR:
                return self::LEVEL_WARNING;
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_ERROR:
            case E_COMPILE_WARNING:
                return self::LEVEL_FATAL;
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
     * {@inheritdoc}
     */
    public function registerSeverityMap($map)
    {
        $this->severityMap = $map;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserContext()
    {
        return $this->userContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getTagsContext()
    {
        return $this->tagsContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtraContext()
    {
        return $this->extraContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getRuntimeContext()
    {
        return $this->runtimeContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerOsContext()
    {
        return $this->serverOsContext;
    }

    /**
     * Adds the default middlewares to this client instance.
     */
    private function addDefaultMiddlewares()
    {
        $this->addMiddleware(new SanitizerMiddleware($this->serializer), -255);
        $this->addMiddleware(new ProcessorMiddleware($this->processorRegistry), -250);
        $this->addMiddleware(new MessageInterfaceMiddleware());
        $this->addMiddleware(new RequestInterfaceMiddleware());
        $this->addMiddleware(new UserInterfaceMiddleware());
        $this->addMiddleware(new ContextInterfaceMiddleware($this->tagsContext, Context::CONTEXT_TAGS));
        $this->addMiddleware(new ContextInterfaceMiddleware($this->userContext, Context::CONTEXT_USER));
        $this->addMiddleware(new ContextInterfaceMiddleware($this->extraContext, Context::CONTEXT_EXTRA));
        $this->addMiddleware(new ContextInterfaceMiddleware($this->runtimeContext, Context::CONTEXT_RUNTIME));
        $this->addMiddleware(new ContextInterfaceMiddleware($this->serverOsContext, Context::CONTEXT_SERVER_OS));
        $this->addMiddleware(new BreadcrumbInterfaceMiddleware($this->breadcrumbRecorder));
        $this->addMiddleware(new ExceptionInterfaceMiddleware($this));
    }
}
