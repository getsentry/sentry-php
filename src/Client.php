<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Context\RuntimeContext;
use Sentry\Context\ServerOsContext;
use Sentry\Middleware\MiddlewareStack;
use Sentry\State\Scope;
use Sentry\Transport\TransportInterface;
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
    const PROTOCOL_VERSION = '7';

    /**
     * The identifier of the SDK.
     */
    const SDK_IDENTIFIER = 'sentry.php';

    /**
     * This constant defines the client's user-agent string.
     */
    const USER_AGENT = self:: SDK_IDENTIFIER . '/' . self::VERSION;

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
     * @var Options The client configuration
     */
    private $config;

    /**
     * @var TransactionStack The transaction stack
     */
    private $transactionStack;

    /**
     * @var TransportInterface The transport
     */
    private $transport;

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
     * @param Options            $config    The client configuration
     * @param TransportInterface $transport The transport
     */
    public function __construct(Options $config, TransportInterface $transport)
    {
        $this->config = $config;
        $this->transport = $transport;
        $this->runtimeContext = new RuntimeContext();
        $this->serverOsContext = new ServerOsContext();
        $this->transactionStack = new TransactionStack();
        $this->serializer = new Serializer($this->config->getMbDetectOrder());
        $this->representationSerializer = new ReprSerializer($this->config->getMbDetectOrder());
        $this->middlewareStack = new MiddlewareStack(function (Event $event) {
            return $event;
        });

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
    public function addBreadcrumb(Breadcrumb $breadcrumb, ?Scope $scope = null)
    {
        $beforeBreadcrumbCallback = $this->config->getBeforeBreadcrumbCallback();
        $maxBreadcrumbs = $this->config->getMaxBreadcrumbs();

        if ($maxBreadcrumbs <= 0) {
            return;
        }

        $breadcrumb = $beforeBreadcrumbCallback($breadcrumb);

        if (null !== $breadcrumb && null !== $scope) {
            $scope->addBreadcrumb($breadcrumb, $maxBreadcrumbs);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
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
    public function captureMessage(string $message, array $params = [], array $payload = [])
    {
        $payload['message'] = $message;
        $payload['message_params'] = $params;

        return $this->captureEvent($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function captureException(\Throwable $exception, array $payload = [])
    {
        $payload['exception'] = $exception;

        return $this->captureEvent($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function captureEvent(array $payload)
    {
        $event = new Event($this->config);

        if (isset($payload['transaction'])) {
            $event->setTransaction($payload['transaction']);
        } else {
            $event->setTransaction($this->transactionStack->peek());
        }

        if (isset($payload['logger'])) {
            $event->setLogger($payload['logger']);
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
    public function translateSeverity(int $severity)
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
}
