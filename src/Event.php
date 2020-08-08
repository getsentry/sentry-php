<?php

declare(strict_types=1);

namespace Sentry;

use Jean85\PrettyVersions;
use Sentry\Context\Context;
use Sentry\Context\RuntimeContext;
use Sentry\Context\ServerOsContext;
use Sentry\Context\TagsContext;
use Sentry\Context\UserContext;
use Sentry\Tracing\Span;
use Sentry\Util\JSON;

/**
 * This is the base class for classes containing event data.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Event implements \JsonSerializable
{
    /**
     * @var EventId The ID
     */
    private $id;

    /**
     * @var string|float The date and time of when this event was generated
     */
    private $timestamp;

    /**
     * This property is used if it's a Transaction event together with $timestamp it's the duration of the transaction.
     *
     * @var string|float|null The date and time of when this event was generated
     */
    private $startTimestamp;

    /**
     * @var Severity|null The severity of this event
     */
    private $level;

    /**
     * @var string|null The name of the logger which created the record
     */
    private $logger;

    /**
     * @var string|null the name of the transaction (or culprit) which caused this exception
     */
    private $transaction;

    /**
     * @var string|null The name of the server (e.g. the host name)
     */
    private $serverName;

    /**
     * @var string|null The release of the program
     */
    private $release;

    /**
     * @var string|null The error message
     */
    private $message;

    /**
     * @var string|null The formatted error message
     */
    private $messageFormatted;

    /**
     * @var mixed[] The parameters to use to format the message
     */
    private $messageParams = [];

    /**
     * @var string|null The environment where this event generated (e.g. production)
     */
    private $environment;

    /**
     * @var array<string, string> A list of relevant modules and their versions
     */
    private $modules = [];

    /**
     * @var array<string, mixed> The request data
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
     * @var UserContext The user context data
     */
    private $userContext;

    /**
     * @var array<string, array<string, mixed>> An arbitrary mapping of additional contexts associated to this event
     */
    private $contexts = [];

    /**
     * @var Context<mixed> An arbitrary mapping of additional metadata
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
     * @var Span[] The array of spans if it's a transaction
     */
    private $spans = [];

    /**
     * @var ExceptionDataBag[] The exceptions
     */
    private $exceptions = [];

    /**
     * @var Stacktrace|null The stacktrace that generated this event
     */
    private $stacktrace;

    /**
     * @var string The Sentry SDK identifier
     */
    private $sdkIdentifier = Client::SDK_IDENTIFIER;

    /**
     * @var string The Sentry SDK version
     */
    private $sdkVersion;

    /**
     * @var string|null The type of the Event "default" | "transaction"
     */
    private $type;

    /**
     * Class constructor.
     *
     * @param EventId|null $eventId The ID of the event
     */
    public function __construct(?EventId $eventId = null)
    {
        $this->id = $eventId ?? EventId::generate();
        $this->timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $this->level = Severity::error();
        $this->serverOsContext = new ServerOsContext();
        $this->runtimeContext = new RuntimeContext();
        $this->userContext = new UserContext();
        $this->extraContext = new Context();
        $this->tagsContext = new TagsContext();
        $this->sdkVersion = PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion();
    }

    /**
     * Gets the ID of this event.
     */
    public function getId(): EventId
    {
        return $this->id;
    }

    /**
     * Gets the identifier of the SDK package that generated this event.
     *
     * @internal
     */
    public function getSdkIdentifier(): string
    {
        return $this->sdkIdentifier;
    }

    /**
     * Sets the identifier of the SDK package that generated this event.
     *
     * @internal
     */
    public function setSdkIdentifier(string $sdkIdentifier): void
    {
        $this->sdkIdentifier = $sdkIdentifier;
    }

    /**
     * Gets the version of the SDK package that generated this Event.
     *
     * @internal
     */
    public function getSdkVersion(): string
    {
        return $this->sdkVersion;
    }

    /**
     * Sets the version of the SDK package that generated this Event.
     *
     * @internal
     */
    public function setSdkVersion(string $sdkVersion): void
    {
        $this->sdkVersion = $sdkVersion;
    }

    /**
     * Gets the timestamp of when this event was generated.
     *
     * @return string|float
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Sets the timestamp of when the Event was created.
     *
     * @param float|string $timestamp
     */
    public function setTimestamp($timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * Gets the severity of this event.
     */
    public function getLevel(): ?Severity
    {
        return $this->level;
    }

    /**
     * Sets the severity of this event.
     *
     * @param Severity|null $level The severity
     */
    public function setLevel(?Severity $level): void
    {
        $this->level = $level;
    }

    /**
     * Gets the name of the logger which created the event.
     */
    public function getLogger(): ?string
    {
        return $this->logger;
    }

    /**
     * Sets the name of the logger which created the event.
     *
     * @param string|null $logger The logger name
     */
    public function setLogger(?string $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Gets the name of the transaction (or culprit) which caused this
     * exception.
     */
    public function getTransaction(): ?string
    {
        return $this->transaction;
    }

    /**
     * Sets the name of the transaction (or culprit) which caused this
     * exception.
     *
     * @param string|null $transaction The transaction name
     */
    public function setTransaction(?string $transaction): void
    {
        $this->transaction = $transaction;
    }

    /**
     * Gets the name of the server.
     */
    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    /**
     * Sets the name of the server.
     *
     * @param string|null $serverName The server name
     */
    public function setServerName(?string $serverName): void
    {
        $this->serverName = $serverName;
    }

    /**
     * Gets the release of the program.
     */
    public function getRelease(): ?string
    {
        return $this->release;
    }

    /**
     * Sets the release of the program.
     *
     * @param string|null $release The release
     */
    public function setRelease(?string $release): void
    {
        $this->release = $release;
    }

    /**
     * Gets the error message.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Gets the formatted message.
     */
    public function getMessageFormatted(): ?string
    {
        return $this->messageFormatted;
    }

    /**
     * Gets the parameters to use to format the message.
     *
     * @return string[]
     */
    public function getMessageParams(): array
    {
        return $this->messageParams;
    }

    /**
     * Sets the error message.
     *
     * @param string      $message   The message
     * @param mixed[]     $params    The parameters to use to format the message
     * @param string|null $formatted The formatted message
     */
    public function setMessage(string $message, array $params = [], ?string $formatted = null): void
    {
        $this->message = $message;
        $this->messageParams = $params;
        $this->messageFormatted = $formatted;
    }

    /**
     * Gets a list of relevant modules and their versions.
     *
     * @return array<string, string>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Sets a list of relevant modules and their versions.
     *
     * @param array<string, string> $modules
     */
    public function setModules(array $modules): void
    {
        $this->modules = $modules;
    }

    /**
     * Gets the request data.
     *
     * @return array<string, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Sets the request data.
     *
     * @param array<string, mixed> $request The request data
     */
    public function setRequest(array $request): void
    {
        $this->request = $request;
    }

    /**
     * Gets an arbitrary mapping of additional contexts.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getContexts(): array
    {
        return $this->contexts;
    }

    /**
     * Sets the data of the context with the given name.
     *
     * @param string               $name The name that uniquely identifies the context
     * @param array<string, mixed> $data The data of the context
     */
    public function setContext(string $name, array $data): self
    {
        $this->contexts[$name] = $data;

        return $this;
    }

    /**
     * Gets an arbitrary mapping of additional metadata.
     *
     * @return Context<mixed>
     */
    public function getExtraContext(): Context
    {
        return $this->extraContext;
    }

    /**
     * Gets a list of tags.
     */
    public function getTagsContext(): TagsContext
    {
        return $this->tagsContext;
    }

    /**
     * Gets the user context.
     */
    public function getUserContext(): UserContext
    {
        return $this->userContext;
    }

    /**
     * Gets the server OS context.
     */
    public function getServerOsContext(): ServerOsContext
    {
        return $this->serverOsContext;
    }

    /**
     * Gets the runtime context data.
     */
    public function getRuntimeContext(): RuntimeContext
    {
        return $this->runtimeContext;
    }

    /**
     * Gets an array of strings used to dictate the deduplication of this
     * event.
     *
     * @return string[]
     */
    public function getFingerprint(): array
    {
        return $this->fingerprint;
    }

    /**
     * Sets an array of strings used to dictate the deduplication of this
     * event.
     *
     * @param string[] $fingerprint The strings
     */
    public function setFingerprint(array $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    /**
     * Gets the environment in which this event was generated.
     */
    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    /**
     * Sets the environment in which this event was generated.
     *
     * @param string|null $environment The name of the environment
     */
    public function setEnvironment(?string $environment): void
    {
        $this->environment = $environment;
    }

    /**
     * Gets the breadcrumbs.
     *
     * @return Breadcrumb[]
     */
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    /**
     * Set new breadcrumbs to the event.
     *
     * @param Breadcrumb[] $breadcrumbs The breadcrumb array
     */
    public function setBreadcrumb(array $breadcrumbs): void
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    /**
     * Gets the exception.
     *
     * @return ExceptionDataBag[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * Sets the exceptions.
     *
     * @param ExceptionDataBag[] $exceptions The exceptions
     */
    public function setExceptions(array $exceptions): void
    {
        foreach ($exceptions as $exception) {
            if (!$exception instanceof ExceptionDataBag) {
                throw new \UnexpectedValueException(sprintf('Expected an instance of the "%s" class. Got: "%s".', ExceptionDataBag::class, get_debug_type($exception)));
            }
        }

        $this->exceptions = $exceptions;
    }

    /**
     * Gets the stacktrace that generated this event.
     */
    public function getStacktrace(): ?Stacktrace
    {
        return $this->stacktrace;
    }

    /**
     * Sets the stacktrace that generated this event.
     *
     * @param Stacktrace|null $stacktrace The stacktrace instance
     */
    public function setStacktrace(?Stacktrace $stacktrace): void
    {
        $this->stacktrace = $stacktrace;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        if ('default' !== $type && 'transaction' !== $type) {
            $type = null;
        }
        $this->type = $type;
    }

    /**
     * @param string|float|null $startTimestamp The start time of the event
     */
    public function setStartTimestamp($startTimestamp): void
    {
        $this->startTimestamp = $startTimestamp;
    }

    /**
     * @param Span[] $spans Array of spans
     */
    public function setSpans(array $spans): void
    {
        $this->spans = $spans;
    }

    /**
     * Gets the event as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'event_id' => (string) $this->id,
            'timestamp' => $this->timestamp,
            'platform' => 'php',
            'sdk' => [
                'name' => $this->sdkIdentifier,
                'version' => $this->getSdkVersion(),
            ],
        ];

        if (null !== $this->level) {
            $data['level'] = (string) $this->level;
        }

        if (null !== $this->startTimestamp) {
            $data['start_timestamp'] = $this->startTimestamp;
        }

        if (null !== $this->type) {
            $data['type'] = $this->type;
        }

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

        if (!empty($this->contexts)) {
            $data['contexts'] = array_merge($data['contexts'] ?? [], $this->contexts);
        }

        if (!empty($this->breadcrumbs)) {
            $data['breadcrumbs']['values'] = $this->breadcrumbs;
        }

        if ('transaction' === $this->getType()) {
            $data['spans'] = array_values(array_map(function (Span $span): array {
                return $span->toArray();
            }, $this->spans));
        }

        foreach (array_reverse($this->exceptions) as $exception) {
            $exceptionMechanism = $exception->getMechanism();
            $exceptionStacktrace = $exception->getStacktrace();
            $exceptionValue = [
                'type' => $exception->getType(),
                'value' => $exception->getValue(),
            ];

            if (null !== $exceptionStacktrace) {
                $exceptionValue['stacktrace'] = [
                    'frames' => $exceptionStacktrace->toArray(),
                ];
            }

            if (null !== $exceptionMechanism) {
                $exceptionValue['mechanism'] = [
                    'type' => $exceptionMechanism->getType(),
                    'handled' => $exceptionMechanism->isHandled(),
                ];
            }

            $data['exception']['values'][] = $exceptionValue;
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
                    'formatted' => $this->messageFormatted ?? vsprintf($this->message, $this->messageParams),
                ];
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Converts an Event to an Envelope.
     *
     * @throws Exception\JsonException
     */
    public function toEnvelope(): string
    {
        $rawEvent = $this->jsonSerialize();
        $envelopeHeader = JSON::encode(['event_id' => $rawEvent['event_id'], 'sent_at' => gmdate('Y-m-d\TH:i:s\Z')]);
        $itemHeader = JSON::encode(['type' => $rawEvent['type'] ?? 'event', 'content_type' => 'application/json']);

        return vsprintf("%s\n%s\n%s", [$envelopeHeader, $itemHeader, JSON::encode($rawEvent)]);
    }
}
