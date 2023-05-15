<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Severity;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
use Sentry\UserDataBag;

/**
 * The scope holds data that should implicitly be sent with Sentry events. It
 * can hold context data, extra parameters, level overrides, fingerprints etc.
 */
final class Scope
{
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
     * @var callable[] List of event processors
     *
     * @psalm-var array<callable(Event, EventHint): ?Event>
     */
    private static $globalEventProcessors = [];

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
            throw new \TypeError(sprintf('The $user argument must be either an array or an instance of the "%s" class. Got: "%s".', UserDataBag::class, get_debug_type($user)));
        }

        if (\is_array($user)) {
            $user = UserDataBag::createFromArray($user);
        }

        if (null === $this->user) {
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
        $this->extra = [];
        $this->contexts = [];

        return $this;
    }

    /**
     * Applies the current context and fingerprint to the event. If the event has
     * already some breadcrumbs on it, the ones from this scope won't get merged.
     *
     * @param Event $event The event object that will be enriched with scope data
     */
    public function applyToEvent(Event $event, ?EventHint $hint = null): ?Event
    {
        $event->setFingerprint(array_merge($event->getFingerprint(), $this->fingerprint));

        if (empty($event->getBreadcrumbs())) {
            $event->setBreadcrumb($this->breadcrumbs);
        }

        if (null !== $this->level) {
            $event->setLevel($this->level);
        }

        if (!empty($this->tags)) {
            $event->setTags(array_merge($this->tags, $event->getTags()));
        }

        if (!empty($this->extra)) {
            $event->setExtra(array_merge($this->extra, $event->getExtra()));
        }

        if (null !== $this->user) {
            $user = $event->getUser();

            if (null === $user) {
                $user = $this->user;
            } else {
                $user = $this->user->merge($user);
            }

            $event->setUser($user);
        }

        // Apply the trace context to errors if there is a Span on the Scope
        if (null !== $this->span) {
            $event->setContext('trace', $this->span->getTraceContext());

            // Apply the dynamic sampling context to errors if there is a Transaction on the Scope
            $transaction = $this->span->getTransaction();
            if (null !== $transaction) {
                $event->setSdkMetadata('dynamic_sampling_context', $transaction->getDynamicSamplingContext());
            }
        }

        foreach (array_merge($this->contexts, $event->getContexts()) as $name => $data) {
            $event->setContext($name, $data);
        }

        // We create a empty `EventHint` instance to allow processors to always receive a `EventHint` instance even if there wasn't one
        if (null === $hint) {
            $hint = new EventHint();
        }

        foreach (array_merge(self::$globalEventProcessors, $this->eventProcessors) as $processor) {
            $event = $processor($event, $hint);

            if (null === $event) {
                return null;
            }

            if (!$event instanceof Event) {
                throw new \InvalidArgumentException(sprintf('The event processor must return null or an instance of the %s class', Event::class));
            }
        }

        return $event;
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
        if (null !== $this->span) {
            return $this->span->getTransaction();
        }

        return null;
    }

    public function __clone()
    {
        if (null !== $this->user) {
            $this->user = clone $this->user;
        }
    }
}
