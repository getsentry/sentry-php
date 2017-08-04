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

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Raven\Breadcrumbs\Breadcrumb;
use Raven\Exception\InvalidArgumentException;

/**
 * This is the base class for classes containing event data.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Event implements \JsonSerializable
{
    /**
     * @var UuidInterface The UUID
     */
    private $id;

    /**
     * @var string The date and time of when this event was generated
     */
    private $timestamp;

    /**
     * @var string The severity of this event
     */
    private $level;

    /**
     * @var string The name of the logger which created the record
     */
    private $logger;

    /**
     * @var string The name of the transaction (or culprit) which caused this exception.
     */
    private $culprit;

    /**
     * @var string The name of the server (e.g. the host name)
     */
    private $serverName;

    private $checksum;

    /**
     * @var string The release of the program
     */
    private $release;

    /**
     * @var string The error message
     */
    private $message;

    /**
     * @var array The parameters to use to format the message
     */
    private $messageParams = [];

    /**
     * @var string The environment where this event generated (e.g. production)
     */
    private $environment;

    /**
     * @var array A list of relevant modules and their versions
     */
    private $modules = [];

    /**
     * @var array The request data
     */
    private $request = [];

    /**
     * @var array The server OS context data
     */
    private $serverOsContext = [];

    /**
     * @var array $runtimeContext The runtime context data
     */
    private $runtimeContext = [];

    /**
     * @var array The user context data
     */
    private $userContext = [];

    /**
     * @var array An arbitrary mapping of additional metadata
     */
    private $extraContext = [];

    /**
     * @var string[] A List of tags associated to this event
     */
    private $tagsContext = [];

    /**
     * @var string[] An array of strings used to dictate the deduplication of this event
     */
    private $fingerprint = [];

    /**
     * @var Breadcrumb[] The associated breadcrumbs
     */
    private $breadcrumbs = [];

    /**
     * @var array The exception
     */
    private $exception;

    /**
     * @var Stacktrace The stacktrace that generated this event
     */
    private $stacktrace;

    /**
     * Class constructor.
     *
     * @param Configuration $config The client configuration
     */
    public function __construct(Configuration $config)
    {
        $this->id = Uuid::uuid4();
        $this->timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $this->level = Client::LEVEL_ERROR;
        $this->serverName = $config->getServerName();
        $this->release = $config->getRelease();
        $this->environment = $config->getCurrentEnvironment();
    }

    /**
     * Gets the UUID of this event
     *
     * @return UuidInterface
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the timestamp of when this event was generated.
     *
     * @return string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Gets the severity of this event.
     *
     * @return string
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Sets the severity of this event.
     *
     * @param string $level The severity
     */
    public function setLevel($level)
    {
        $this->level = $level;
    }

    /**
     * Gets the name of the logger which created the event.
     *
     * @return mixed
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Sets the name of the logger which created the event.
     *
     * @param mixed $logger The logger name
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Gets the name of the transaction (or culprit) which caused this
     * exception.
     *
     * @return string
     */
    public function getCulprit()
    {
        return $this->culprit;
    }

    /**
     * Sets the name of the transaction (or culprit) which caused this
     * exception.
     *
     * @param string $culprit The transaction name
     */
    public function setCulprit($culprit)
    {
        $this->culprit = $culprit;
    }

    /**
     * Gets the name of the server.
     *
     * @return string
     */
    public function getServerName()
    {
        return $this->serverName;
    }

    /**
     * Sets the name of the server.
     *
     * @param string $serverName The server name
     */
    public function setServerName($serverName)
    {
        $this->serverName = $serverName;
    }

    /**
     * Gets the release of the program.
     *
     * @return string
     */
    public function getRelease()
    {
        return $this->release;
    }

    /**
     * Sets the release of the program.
     *
     * @param string $release The release
     */
    public function setRelease($release)
    {
        $this->release = $release;
    }

    /**
     * Gets the error message.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Sets the error message.
     *
     * @param string $message The message
     * @param array  $params  The parameters to use to format the message
     */
    public function setMessage($message, array $params = [])
    {
        $this->message = $message;
        $this->messageParams = $params;
    }

    /**
     * Gets a list of relevant modules and their versions.
     *
     * @return array
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Sets a list of relevant modules and their versions.
     *
     * @param array $modules
     */
    public function setModules(array $modules)
    {
        $this->modules = $modules;
    }

    /**
     * Gets the request data.
     *
     * @return array
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Sets the request data.
     *
     * @param array $request The request data
     */
    public function setRequest(array $request)
    {
        $this->request = $request;
    }

    /**
     * Gets an arbitrary mapping of additional metadata.
     *
     * @return array
     */
    public function getExtraContext()
    {
        return $this->extraContext;
    }

    /**
     * Sets an arbitrary mapping of additional metadata.
     *
     * @param array $extraContext Additional metadata
     */
    public function setExtraContext(array $extraContext)
    {
        $this->extraContext = $extraContext;
    }

    /**
     * Gets a list of tags.
     *
     * @return string[]
     */
    public function getTagsContext()
    {
        return $this->tagsContext;
    }

    /**
     * Sets a list of tags.
     *
     * @param string[] $tagsContext The tags
     */
    public function setTagsContext(array $tagsContext)
    {
        $this->tagsContext = $tagsContext;
    }

    /**
     * Gets the user context.
     *
     * @return array
     */
    public function getUserContext()
    {
        return $this->userContext;
    }

    /**
     * Sets the user context.
     *
     * @param array $userContext The context data
     */
    public function setUserContext($userContext)
    {
        $this->userContext = $userContext;
    }

    /**
     * Gets the server OS context.
     *
     * @return array
     */
    public function getServerOsContext()
    {
        return $this->serverOsContext;
    }

    /**
     * Gets the server OS context.
     *
     * @param array $serverOsContext The context data
     */
    public function setServerOsContext(array $serverOsContext)
    {
        $this->serverOsContext = $serverOsContext;
    }

    /**
     * Gets the runtime context data.
     *
     * @return array
     */
    public function getRuntimeContext()
    {
        return $this->runtimeContext;
    }

    /**
     * Sets the runtime context data.
     *
     * @param array $runtimeContext The context data
     */
    public function setRuntimeContext(array $runtimeContext)
    {
        $this->runtimeContext = $runtimeContext;
    }

    /**
     * Gets an array of strings used to dictate the deduplication of this
     * event.
     *
     * @return string[]
     */
    public function getFingerprint()
    {
        return $this->fingerprint;
    }

    /**
     * Sets an array of strings used to dictate the deduplication of this
     * event.
     *
     * @param string[] $fingerprint The strings
     */
    public function setFingerprint(array $fingerprint)
    {
        $this->fingerprint = $fingerprint;
    }

    /**
     * Gets the environment in which this event was generated.
     *
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Sets the environment in which this event was generated.
     *
     * @param mixed $environment The name of the environment
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    /**
     * Gets the breadcrumbs.
     *
     * @return mixed
     */
    public function getBreadcrumbs()
    {
        return $this->breadcrumbs;
    }

    /**
     * Adds a new breadcrumb to the event.
     *
     * @param mixed $breadcrumb The breadcrumb
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb)
    {
        $this->breadcrumbs[] = $breadcrumb;
    }

    /**
     * Gets the exception.
     *
     * @return array
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Sets the exception.
     *
     * @param array $exception The exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }

    /**
     * Gets the stacktrace that generated this event.
     *
     * @return Stacktrace
     */
    public function getStacktrace()
    {
        return $this->stacktrace;
    }

    /**
     * Sets the stacktrace that generated this event.
     *
     * @param Stacktrace $stacktrace The stacktrace instance
     */
    public function setStacktrace(Stacktrace $stacktrace)
    {
        $this->stacktrace = $stacktrace;
    }

    /**
     * Gets the event as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $data = [
            'event_id' => str_replace('-', '', $this->id->toString()),
            'timestamp' => $this->timestamp,
            'level' => $this->level,
            'platform' => 'php',
            'sdk' => [
                'name' => 'sentry-php',
                'version' => Client::VERSION,
            ],
        ];

        if (null !== $this->logger) {
            $data['logger'] = $this->logger;
        }

        if (null !== $this->culprit) {
            $data['culprit'] = $this->culprit;
        }

        if (null !== $this->serverName) {
            $data['server_name'] = $this->serverName;
        }

        if (null !== $this->release) {
            $data['release'] = $this->release;
        }

        if (null !== $this->environment) {
            $data['environment'] = $this->environment;
        }

        if (!empty($this->fingerprint)) {
            $data['fingerprint'] = $this->fingerprint;
        }

        if (!empty($this->modules)) {
            $data['modules'] = $this->modules;
        }

        if (!empty($this->extraContext)) {
            $data['extra'] = $this->extraContext;
        }

        if (!empty($this->tagsContext)) {
            $data['tags'] = $this->tagsContext;
        }

        if (!empty($this->userContext)) {
            $data['user'] = $this->userContext;
        }

        if (!empty($this->serverOsContext)) {
            $data['server_os'] = $this->serverOsContext;
        }

        if (!empty($this->runtimeContext)) {
            $data['runtime'] = $this->runtimeContext;
        }

        if (!empty($this->breadcrumbs)) {
            $data['breadcrumbs'] = $this->breadcrumbs;
        }

        if (!empty($this->exception)) {
            $data['exception'] = $this->exception;
        }

        if (null !== $this->stacktrace) {
            $data['stacktrace'] = [
                'frames' => $this->stacktrace->toArray(),
            ];
        }

        if (!empty($this->request)) {
            $data['request'] = $this->request;
        }

        if (null !== $this->checksum) {
            $data['checksum'] = $this->checksum;
        }

        if (null !== $this->message) {
            if (empty($this->messageParams)) {
                $data['message'] = $this->message;
            } else {
                $data['message'] = [
                    'message' => $this->message,
                    'params' => $this->messageParams,
                    'formatted' => vsprintf($this->message, $this->messageParams),
                ];
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Creates a new event from the given throwable.
     *
     * @param Client                $client    The Raven client instance
     * @param \Exception|\Throwable $throwable The throwable instance
     *
     * @return static
     */
    public static function createFromPHPThrowable(Client $client, $throwable)
    {
        if (!$throwable instanceof \Exception && !$throwable instanceof \Throwable) {
            throw new InvalidArgumentException('The $throwable argument must be an instance of either \Throwable or \Exception.');
        }

        $event = new static($client->getConfig());
        $event->setMessage($throwable->getMessage());
        $event->setStacktrace(Stacktrace::createFromBacktrace($client, $throwable->getTrace(), $throwable->getFile(), $throwable->getLine()));

        return $event;
    }

    /**
     * Creates a new event using the given error details.
     *
     * @param Client $client  The Raven client instance
     * @param int    $code    The error code
     * @param string $message The error message
     * @param string $file    The file where the error was thrown
     * @param int    $line    The line at which the error was thrown
     *
     * @return static
     */
    public static function createFromPHPError(Client $client, $code, $message, $file, $line)
    {
        $event = new static($client->getConfig());
        $event->setMessage($message);
        $event->setLevel($client->translateSeverity($code));
        $event->setStacktrace(Stacktrace::create($client));

        return $event;
    }
}
