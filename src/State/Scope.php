<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Attachment\Attachment;
use Sentry\Attributes\AttributeBag;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventType;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
use Sentry\UserDataBag;
use Sentry\Util\DebugType;

/**
 * The scope holds data that should implicitly be sent with Sentry events. It
 * can hold context data, extra parameters, level overrides, fingerprints etc.
 */
class Scope
{
    /**
     * Maximum number of flags allowed. We only track the first flags set.
     *
     * @internal
     */
    public const MAX_FLAGS = 100;

    /**
     * @var PropagationContext
     */
    private $propagationContext;

    /**
     * @var ScopeType|null
     */
    private $type;

    /**
     * @var ClientInterface Client bound to this scope
     */
    private $client;

    /**
     * @var Breadcrumb[] The list of breadcrumbs recorded in this scope
     */
    private $breadcrumbs = [];

    /**
     * @var UserDataBag|null The user data associated to this scope
     */
    private $user;

    /**
     * @var array<string, array<string, mixed>> The list of contexts associated to this scope
     */
    private $contexts = [];

    /**
     * @var array<string, string> The list of tags associated to this scope
     */
    private $tags = [];

    /**
     * @var array<int, array<string, bool>> The list of flags associated to this scope
     */
    private $flags = [];

    /**
     * @var array<string, mixed> A set of extra data associated to this scope
     */
    private $extra = [];

    /**
     * @var string[] List of fingerprints used to group events together in
     *               Sentry
     */
    private $fingerprint = [];

    /**
     * @var Severity|null The severity to associate to the events captured in
     *                    this scope
     */
    private $level;

    /**
     * @var callable[] List of event processors
     *
     * @psalm-var array<callable(Event, EventHint): ?Event>
     */
    private $eventProcessors = [];

    /**
     * @var Span|null Set a Span on the Scope
     */
    private $span;

    /**
     * @var Attachment[]
     */
    private $attachments = [];

    /**
     * @var AttributeBag
     */
    private $attributes;

    /**
     * @var callable[] List of event processors
     *
     * @psalm-var array<callable(Event, EventHint): ?Event>
     */
    private static $globalEventProcessors = [];

    public function __construct(?PropagationContext $propagationContext = null, ?ScopeType $type = null)
    {
        $this->propagationContext = $propagationContext ?? PropagationContext::fromDefaults();
        $this->type = $type;
        $this->client = new NoOpClient();
        $this->attributes = new AttributeBag();
    }

    /**
     * Sets a new tag in the tags context.
     *
     * @param string $key   The key that uniquely identifies the tag
     * @param string $value The value
     *
     * @return $this
     */
    public function setTag(string $key, string $value): self
    {
        $this->tags[$key] = $value;

        return $this;
    }

    /**
     * Merges the given tags into the current tags context.
     *
     * @param array<string, string> $tags The tags to merge into the current context
     *
     * @return $this
     */
    public function setTags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    /**
     * Removes a given tag from the tags context.
     *
     * @param string $key The key that uniquely identifies the tag
     *
     * @return $this
     */
    public function removeTag(string $key): self
    {
        unset($this->tags[$key]);

        return $this;
    }

    /**
     * Adds a feature flag to the scope.
     *
     * @return $this
     */
    public function addFeatureFlag(string $key, bool $result): self
    {
        // If the flag was already set, remove it first
        // This basically mimics an LRU cache so that the most recently added flags are kept
        foreach ($this->flags as $flagIndex => $flag) {
            if (isset($flag[$key])) {
                unset($this->flags[$flagIndex]);
            }
        }

        // Keep only the most recent MAX_FLAGS flags
        if (\count($this->flags) >= self::MAX_FLAGS) {
            array_shift($this->flags);
        }

        $this->flags[] = [$key => $result];

        if ($this->span !== null) {
            $this->span->setFlag($key, $result);
        }

        return $this;
    }

    /**
     * Sets data to the context by a given name.
     *
     * @param string               $name  The name that uniquely identifies the context
     * @param array<string, mixed> $value The value
     *
     * @return $this
     */
    public function setContext(string $name, array $value): self
    {
        if (!empty($value)) {
            $this->contexts[$name] = $value;
        }

        return $this;
    }

    /**
     * Removes the context from the scope.
     *
     * @param string $name The name that uniquely identifies the context
     *
     * @return $this
     */
    public function removeContext(string $name): self
    {
        unset($this->contexts[$name]);

        return $this;
    }

    /**
     * Sets a new information in the extra context.
     *
     * @param string $key   The key that uniquely identifies the information
     * @param mixed  $value The value
     *
     * @return $this
     */
    public function setExtra(string $key, $value): self
    {
        $this->extra[$key] = $value;

        return $this;
    }

    /**
     * Merges the given data into the current extras context.
     *
     * @param array<string, mixed> $extras Data to merge into the current context
     *
     * @return $this
     */
    public function setExtras(array $extras): self
    {
        $this->extra = array_merge($this->extra, $extras);

        return $this;
    }

    /**
     * Get the user context.
     */
    public function getUser(): ?UserDataBag
    {
        return $this->user;
    }

    /**
     * Merges the given data in the user context.
     *
     * @param array<string, mixed>|UserDataBag $user The user data
     *
     * @return $this
     */
    public function setUser($user): self
    {
        if (!\is_array($user) && !$user instanceof UserDataBag) {
            throw new \TypeError(\sprintf('The $user argument must be either an array or an instance of the "%s" class. Got: "%s".', UserDataBag::class, DebugType::getDebugType($user)));
        }

        if (\is_array($user)) {
            $user = UserDataBag::createFromArray($user);
        }

        if ($this->user === null) {
            $this->user = $user;
        } else {
            $this->user = $this->user->merge($user);
        }

        return $this;
    }

    /**
     * Removes all data of the user context.
     *
     * @return $this
     */
    public function removeUser(): self
    {
        $this->user = null;

        return $this;
    }

    /**
     * Sets the list of strings used to dictate the deduplication of this event.
     *
     * @param string[] $fingerprint The fingerprint values
     *
     * @return $this
     */
    public function setFingerprint(array $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    /**
     * Sets the severity to apply to all events captured in this scope.
     *
     * @param Severity|null $level The severity
     *
     * @return $this
     */
    public function setLevel(?Severity $level): self
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Add the given breadcrumb to the scope.
     *
     * @param Breadcrumb $breadcrumb     The breadcrumb to add
     * @param int        $maxBreadcrumbs The maximum number of breadcrumbs to record
     *
     * @return $this
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb, int $maxBreadcrumbs = 100): self
    {
        $client = $this->getClient();
        if ($client instanceof NoOpClient) {
            $client = SentrySdk::getClient();
        }

        // No point in storing breadcrumbs if the client will never send them
        if ($client instanceof NoOpClient) {
            return $this;
        }

        $options = $client->getOptions();

        if (\func_num_args() < 2) {
            $maxBreadcrumbs = $options->getMaxBreadcrumbs();
        }

        if ($maxBreadcrumbs <= 0) {
            return $this;
        }

        $beforeBreadcrumbCallback = $options->getBeforeBreadcrumbCallback();
        $breadcrumb = $beforeBreadcrumbCallback($breadcrumb);

        if ($breadcrumb === null) {
            return $this;
        }

        $this->breadcrumbs[] = $breadcrumb;
        $this->breadcrumbs = \array_slice($this->breadcrumbs, -$maxBreadcrumbs);

        return $this;
    }

    /**
     * Clears all the breadcrumbs.
     *
     * @return $this
     */
    public function clearBreadcrumbs(): self
    {
        $this->breadcrumbs = [];

        return $this;
    }

    /**
     * Adds a new event processor that will be called after {@see Scope::applyToEvent}
     * finished its work.
     *
     * @param callable $eventProcessor The event processor
     *
     * @return $this
     */
    public function addEventProcessor(callable $eventProcessor): self
    {
        $this->eventProcessors[] = $eventProcessor;

        return $this;
    }

    /**
     * Adds a new event processor that will be called after {@see Scope::applyToEvent}
     * finished its work.
     *
     * @param callable $eventProcessor The event processor
     */
    public static function addGlobalEventProcessor(callable $eventProcessor): void
    {
        self::$globalEventProcessors[] = $eventProcessor;
    }

    /**
     * Clears the scope and resets any data it contains.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->user = null;
        $this->level = null;
        $this->span = null;
        $this->fingerprint = [];
        $this->breadcrumbs = [];
        $this->tags = [];
        $this->flags = [];
        $this->extra = [];
        $this->contexts = [];
        $this->attachments = [];
        $this->attributes = new AttributeBag();

        return $this;
    }

    /**
     * Applies the current context and fingerprint to the event. If the event has
     * already some breadcrumbs on it, the ones from this scope won't get merged.
     *
     * @param Event $event The event object that will be enriched with scope data
     */
    public function applyToEvent(Event $event, ?EventHint $hint = null, ?Options $options = null): ?Event
    {
        $event->setFingerprint(array_merge($event->getFingerprint(), $this->fingerprint));

        if (empty($event->getBreadcrumbs())) {
            $event->setBreadcrumb($this->breadcrumbs);
        }

        if ($this->level !== null) {
            $event->setLevel($this->level);
        }

        if (!empty($this->tags)) {
            $event->setTags(array_merge($this->tags, $event->getTags()));
        }

        if (!empty($this->flags)) {
            $event->setContext('flags', [
                'values' => array_map(static function (array $flag) {
                    return [
                        'flag' => key($flag),
                        'result' => current($flag),
                    ];
                }, array_values($this->flags)),
            ]);
        }

        if (!empty($this->extra)) {
            $event->setExtra(array_merge($this->extra, $event->getExtra()));
        }

        if ($this->user !== null) {
            $user = $event->getUser();

            if ($user === null) {
                $user = $this->user;
            } else {
                $user = $this->user->merge($user);
            }

            $event->setUser($user);
        }

        /**
         * Apply the trace context to errors if there is a Span on the Scope.
         * Else fallback to the propagation context.
         * But do not override a trace context already present.
         */
        if ($this->span !== null) {
            if (!\array_key_exists('trace', $event->getContexts())) {
                $event->setContext('trace', $this->span->getTraceContext());
            }

            // Apply the dynamic sampling context to errors if there is a Transaction on the Scope
            $transaction = $this->span->getTransaction();
            if ($transaction !== null) {
                $event->setSdkMetadata('dynamic_sampling_context', $transaction->getDynamicSamplingContext());
            }
        } else {
            if (!\array_key_exists('trace', $event->getContexts())) {
                $event->setContext('trace', $this->propagationContext->getTraceContext());
            }

            $dynamicSamplingContext = $this->propagationContext->getDynamicSamplingContext();
            if ($dynamicSamplingContext === null && $options !== null) {
                $dynamicSamplingContext = DynamicSamplingContext::fromOptions($options, $this);
            }
            $event->setSdkMetadata('dynamic_sampling_context', $dynamicSamplingContext);
        }

        foreach (array_merge($this->contexts, $event->getContexts()) as $name => $data) {
            $event->setContext($name, $data);
        }

        // We create a empty `EventHint` instance to allow processors to always receive a `EventHint` instance even if there wasn't one
        if ($hint === null) {
            $hint = new EventHint();
        }

        if ($event->getType() === EventType::event() || $event->getType() === EventType::transaction()) {
            if (empty($event->getAttachments())) {
                $event->setAttachments($this->attachments);
            }
        }

        foreach (array_merge(self::$globalEventProcessors, $this->eventProcessors) as $processor) {
            $event = $processor($event, $hint);

            if ($event === null) {
                return null;
            }

            if (!$event instanceof Event) {
                throw new \InvalidArgumentException(\sprintf('The event processor must return null or an instance of the %s class', Event::class));
            }
        }

        return $event;
    }

    /**
     * Sets attributes on the scope.
     *
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Sets an attribute on the scope.
     *
     * @param mixed $value
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes->set($key, $value);

        return $this;
    }

    public function removeAttribute(string $key): self
    {
        $this->attributes->forget($key);

        return $this;
    }

    /**
     * Returns the span that is on the scope.
     */
    public function getSpan(): ?Span
    {
        return $this->span;
    }

    /**
     * Sets the span on the scope.
     *
     * @param Span|null $span The span
     *
     * @return $this
     */
    public function setSpan(?Span $span): self
    {
        $this->span = $span;

        return $this;
    }

    /**
     * Returns the transaction attached to the scope (if there is one).
     */
    public function getTransaction(): ?Transaction
    {
        if ($this->span !== null) {
            return $this->span->getTransaction();
        }

        return null;
    }

    public function getPropagationContext(): PropagationContext
    {
        return $this->propagationContext;
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function setClient(?ClientInterface $client): self
    {
        $this->client = $client ?? new NoOpClient();

        return $this;
    }

    /**
     * Binds the given client to this scope.
     */
    public function bindClient(ClientInterface $client): self
    {
        return $this->setClient($client);
    }

    public function getType(): ?ScopeType
    {
        return $this->type;
    }

    public function setType(ScopeType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setPropagationContext(PropagationContext $propagationContext): self
    {
        $this->propagationContext = $propagationContext;

        return $this;
    }

    /**
     * @internal
     */
    public function getAttributes(): AttributeBag
    {
        return $this->attributes;
    }

    /**
     * @internal
     */
    public function mergeFrom(self $scope): self
    {
        if ($scope->level !== null) {
            $this->level = $scope->level;
        }

        if (!empty($scope->fingerprint)) {
            $this->fingerprint = array_merge($this->fingerprint, $scope->fingerprint);
        }

        if (!empty($scope->breadcrumbs)) {
            $this->breadcrumbs = array_merge($this->breadcrumbs, $scope->breadcrumbs);
        }

        if (!empty($scope->tags)) {
            $this->tags = array_merge($this->tags, $scope->tags);
        }

        if (!empty($scope->flags)) {
            $this->flags = array_merge($this->flags, $scope->flags);

            if (\count($this->flags) > self::MAX_FLAGS) {
                $this->flags = \array_slice($this->flags, -self::MAX_FLAGS);
            }
        }

        if (!empty($scope->extra)) {
            $this->extra = array_merge($this->extra, $scope->extra);
        }

        if (!empty($scope->contexts)) {
            $this->contexts = array_merge($this->contexts, $scope->contexts);
        }

        if ($scope->user !== null) {
            if ($this->user === null) {
                $this->user = clone $scope->user;
            } else {
                $user = clone $this->user;
                $user->merge($scope->user);
                $this->user = $user;
            }
        }

        if ($scope->span !== null) {
            $this->span = $scope->span;
        }

        if (!empty($scope->attachments)) {
            $this->attachments = array_merge($this->attachments, $scope->attachments);
        }

        if (!empty($scope->attributes->all())) {
            foreach ($scope->attributes->all() as $key => $attribute) {
                $this->attributes->set($key, $attribute);
            }
        }

        if (!empty($scope->eventProcessors)) {
            $this->eventProcessors = array_merge($this->eventProcessors, $scope->eventProcessors);
        }

        if ($scope->propagationContext !== null && $scope->getType() !== ScopeType::current()) {
            $this->propagationContext = $scope->propagationContext;
        }

        return $this;
    }

    /**
     * @internal
     */
    public static function mergeScopes(Scope $globalScope, ?Scope $isolationScope, ?Scope $currentScope): self
    {
        $mergedScope = clone $globalScope;

        if ($isolationScope !== null) {
            $mergedScope->mergeFrom($isolationScope);
        }

        if ($currentScope !== null) {
            $mergedScope->mergeFrom($currentScope);
        }

        $mergedScope->setType(ScopeType::merged());
        $mergedScope->sortBreadcrumbsByTimestamp();

        $client = self::getClientFromScopes($globalScope, $isolationScope, $currentScope);
        $mergedScope->trimBreadcrumbs($client->getOptions()->getMaxBreadcrumbs());

        return $mergedScope;
    }

    /**
     * @internal
     */
    public static function getClientFromScopes(Scope $globalScope, ?Scope $isolationScope, ?Scope $currentScope): ClientInterface
    {
        if ($currentScope !== null) {
            $currentScopeClient = $currentScope->getClient();
            if (!$currentScopeClient instanceof NoOpClient) {
                return $currentScopeClient;
            }
        }

        if ($isolationScope !== null) {
            $isolationScopeClient = $isolationScope->getClient();
            if (!$isolationScopeClient instanceof NoOpClient) {
                return $isolationScopeClient;
            }
        }

        return $globalScope->getClient();
    }

    /**
     * @internal
     *
     * Sorts breadcrumbs by timestamp. This is important because merging scopes can lead to an invalid ordering.
     * If we trim after merge without sorting, we cannot guarantee that we keep the last max_breadcrumbs.
     */
    public function sortBreadcrumbsByTimestamp(): self
    {
        if (\count($this->breadcrumbs) <= 1) {
            return $this;
        }

        usort($this->breadcrumbs, static function (Breadcrumb $left, Breadcrumb $right): int {
            return $left->getTimestamp() <=> $right->getTimestamp();
        });

        return $this;
    }

    /**
     * @internal
     */
    public function trimBreadcrumbs(int $maxBreadcrumbs): self
    {
        if ($maxBreadcrumbs <= 0) {
            $this->breadcrumbs = [];

            return $this;
        }

        if (\count($this->breadcrumbs) > $maxBreadcrumbs) {
            $this->breadcrumbs = \array_slice($this->breadcrumbs, -$maxBreadcrumbs);
        }

        return $this;
    }

    public function __clone()
    {
        if ($this->user !== null) {
            $this->user = clone $this->user;
        }
        if ($this->propagationContext !== null) {
            $this->propagationContext = clone $this->propagationContext;
        }
        $this->attributes = clone $this->attributes;
    }

    public function addAttachment(Attachment $attachment): self
    {
        $client = $this->getClient();
        if ($client instanceof NoOpClient) {
            $client = SentrySdk::getClient();
        }

        // No point in storing attachments if the client will never send them
        if ($client instanceof NoOpClient) {
            return $this;
        }

        $this->attachments[] = $attachment;

        return $this;
    }

    public function clearAttachments(): self
    {
        $this->attachments = [];

        return $this;
    }
}
