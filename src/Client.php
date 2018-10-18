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
use Sentry\Breadcrumbs\Recorder;
use Sentry\Context\Context;
use Sentry\Context\RuntimeContext;
use Sentry\Context\ServerOsContext;
use Sentry\Context\TagsContext;
use Sentry\Context\UserContext;
use Sentry\Middleware\MiddlewareStack;
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
     * @var TagsContext The tags context
     */
    private $tagsContext;

    /**
     * @var UserContext The user context
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
        $this->tagsContext = new TagsContext();
        $this->userContext = new UserContext();
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
    public function getBreadcrumbsRecorder()
    {
        return $this->breadcrumbRecorder;
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
}
