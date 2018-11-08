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

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Context\Context;
use Sentry\Context\RuntimeContext;
use Sentry\Context\ServerOsContext;
use Sentry\Context\TagsContext;
use Sentry\Context\UserContext;

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
     * @var Severity The severity of this event
     */
    private $level;

    /**
     * @var string The name of the logger which created the record
     */
    private $logger;

    /**
     * @var string|null the name of the transaction (or culprit) which caused this exception
     */
    private $transaction;

    /**
     * @var string The name of the server (e.g. the host name)
     */
    private $serverName;

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
     * @var ServerOsContext The server OS context data
     */
    private $serverOsContext;

    /**
     * @var RuntimeContext The runtime context data
     */
    private $runtimeContext;

    /**
     * @var Context The user context data
     */
    private $userContext;

    /**
     * @var Context An arbitrary mapping of additional metadata
     */
    private $extraContext;

    /**
     * @var TagsContext A List of tags associated to this event
     */
    private $tagsContext;

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
     * @var Stacktrace|null The stacktrace that generated this event
     */
    private $stacktrace;

    /**
     * Event constructor.
     */
    public function __construct()
    {
        try {
            $this->id = Uuid::uuid4();
        } catch (\Exception $e) {
            // This should never happen
        }

        $this->timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $this->level = Severity::error();
        $this->serverOsContext = new ServerOsContext();
        $this->runtimeContext = new RuntimeContext();
        $this->userContext = new UserContext();
        $this->extraContext = new Context();
        $this->tagsContext = new TagsContext();
    }

    /**
     * Gets the UUID of this event.
     *
     * @return string
     */
    public function getId(): string
    {
        return str_replace('-', '', $this->id->toString());
    }

    /**
     * Gets the timestamp of when this event was generated.
     *
     * @return string
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    /**
     * Gets the severity of this event.
     *
     * @return Severity
     */
    public function getLevel(): Severity
    {
        return $this->level;
    }

    /**
     * Sets the severity of this event.
     *
     * @param Severity $level The severity
     */
    public function setLevel(Severity $level): void
    {
        $this->level = $level;
    }

    /**
     * Gets the name of the logger which created the event.
     *
     * @return string
     */
    public function getLogger(): string
    {
        return $this->logger;
    }

    /**
     * Sets the name of the logger which created the event.
     *
     * @param string $logger The logger name
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Gets the name of the transaction (or culprit) which caused this
     * exception.
     *
     * @return string|null
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * Sets the name of the transaction (or culprit) which caused this
     * exception.
     *
     * @param string|null $transaction The transaction name
     */
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;
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
     * Gets the parameters to use to format the message.
     *
     * @return string[]
     */
    public function getMessageParams()
    {
        return $this->messageParams;
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
     * @return Context
     */
    public function getExtraContext()
    {
        return $this->extraContext;
    }

    /**
     * Gets a list of tags.
     *
     * @return TagsContext
     */
    public function getTagsContext()
    {
        return $this->tagsContext;
    }

    /**
     * Gets the user context.
     *
     * @return Context
     */
    public function getUserContext()
    {
        return $this->userContext;
    }

    /**
     * Gets the server OS context.
     *
     * @return ServerOsContext
     */
    public function getServerOsContext()
    {
        return $this->serverOsContext;
    }

    /**
     * Gets the runtime context data.
     *
     * @return RuntimeContext
     */
    public function getRuntimeContext()
    {
        return $this->runtimeContext;
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
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Sets the environment in which this event was generated.
     *
     * @param string $environment The name of the environment
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    /**
     * Gets the breadcrumbs.
     *
     * @return Breadcrumb[]
     */
    public function getBreadcrumbs()
    {
        return $this->breadcrumbs;
    }

    /**
     * Set new breadcrumbs to the event.
     *
     * @param Breadcrumb[] $breadcrumbs The breadcrumb array
     */
    public function setBreadcrumb(array $breadcrumbs)
    {
        $this->breadcrumbs = $breadcrumbs;
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
    public function setException(array $exception)
    {
        $this->exception = $exception;
    }

    /**
     * Gets the stacktrace that generated this event.
     *
     * @return Stacktrace|null
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
    public function toArray(): array
    {
        $data = [
            'event_id' => str_replace('-', '', $this->id->toString()),
            'timestamp' => $this->timestamp,
            'level' => (string) $this->level,
            'platform' => 'php',
            'sdk' => [
                'name' => Client::SDK_IDENTIFIER,
                'version' => Client::VERSION,
            ],
        ];

        if (null !== $this->logger) {
            $data['logger'] = $this->logger;
        }

        if (null !== $this->transaction) {
            $data['transaction'] = $this->transaction;
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

        if (!$this->extraContext->isEmpty()) {
            $data['extra'] = $this->extraContext->toArray();
        }

        if (!$this->tagsContext->isEmpty()) {
            $data['tags'] = $this->tagsContext->toArray();
        }

        if (!$this->userContext->isEmpty()) {
            $data['user'] = $this->userContext->toArray();
        }

        if (!$this->serverOsContext->isEmpty()) {
            $data['contexts']['os'] = $this->serverOsContext->toArray();
        }

        if (!$this->runtimeContext->isEmpty()) {
            $data['contexts']['runtime'] = $this->runtimeContext->toArray();
        }

        if (!empty($this->breadcrumbs)) {
            $data['breadcrumbs']['values'] = $this->breadcrumbs;
        }

        if (null !== $this->exception) {
            $reversedException = array_reverse($this->exception);

            foreach ($reversedException as $exception) {
                $exceptionData = [
                    'type' => $exception['type'],
                    'value' => $exception['value'],
                ];

                if (isset($exception['stacktrace'])) {
                    $exceptionData['stacktrace'] = [
                        'frames' => $exception['stacktrace']->toArray(),
                    ];
                }

                $data['exception']['values'][] = $exceptionData;
            }
        }

        if (null !== $this->stacktrace) {
            $data['stacktrace'] = [
                'frames' => $this->stacktrace->toArray(),
            ];
        }

        if (!empty($this->request)) {
            $data['request'] = $this->request;
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
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
