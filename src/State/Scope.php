<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Breadcrumb;
use Sentry\Context\Context;
use Sentry\Context\TagsContext;
use Sentry\Context\UserContext;
use Sentry\Event;
use Sentry\Severity;

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
     * @var UserContext The user data associated to this scope
     */
    private $user;

    /**
     * @var TagsContext The list of tags associated to this scope
     */
    private $tags;

    /**
     * @var Context A set of extra data associated to this scope
     */
    private $extra;

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
     */
    private $eventProcessors = [];

    /**
     * @var callable[] List of event processors
     */
    private static $globalEventProcessors = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->user = new UserContext();
        $this->tags = new TagsContext();
        $this->extra = new Context();
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
     * Gets the tags contained in the tags context.
     *
     * @return array<string, string>
     *
     * @internal
     */
    public function getTags(): array
    {
        return $this->tags->toArray();
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
     * Gets the information contained in the extra context.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function getExtra(): array
    {
        return $this->extra->toArray();
    }

    /**
     * Sets the given data in the user context.
     *
     * @param array $data The data
     *
     * @return $this
     */
    public function setUser(array $data): self
    {
        $this->user->replaceData($data);

        return $this;
    }

    /**
     * Gets the information contained in the user context.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function getUser(): array
    {
        return $this->user->toArray();
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
     * Gets the list of strings used to dictate the deduplication of this event.
     *
     * @return string[]
     *
     * @internal
     */
    public function getFingerprint(): array
    {
        return $this->fingerprint;
    }

    /**
     * Sets the severity to apply to all events captured in this scope.
     *
     * @param null|Severity $level The severity
     *
     * @return $this
     */
    public function setLevel(?Severity $level): self
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Gets the severity to apply to all events captured in this scope.
     *
     * @return null|Severity
     *
     * @internal
     */
    public function getLevel(): ?Severity
    {
        return $this->level;
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
     * Gets the breadcrumbs.
     *
     * @return Breadcrumb[]
     *
     * @internal
     */
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
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
        $this->tags->clear();
        $this->extra->clear();
        $this->user->clear();

        $this->level = null;
        $this->fingerprint = [];
        $this->breadcrumbs = [];

        return $this;
    }

    /**
     * Applies the current context and fingerprint to the event. If the event has
     * already some breadcrumbs on it, the ones from this scope won't get merged.
     *
     * @param Event $event   The event object that will be enriched with scope data
     * @param array $payload The raw payload of the event that will be propagated to the event processors
     *
     * @return Event|null
     */
    public function applyToEvent(Event $event, array $payload): ?Event
    {
        if (empty($event->getFingerprint())) {
            $event->setFingerprint($this->fingerprint);
        }

        if (empty($event->getBreadcrumbs())) {
            $event->setBreadcrumb($this->breadcrumbs);
        }

        if (null !== $this->level) {
            $event->setLevel($this->level);
        }

        $event->getTagsContext()->merge($this->tags->toArray());
        $event->getExtraContext()->merge($this->extra->toArray());
        $event->getUserContext()->merge($this->user->toArray());

        foreach (array_merge(self::$globalEventProcessors, $this->eventProcessors) as $processor) {
            $event = \call_user_func($processor, $event, $payload);

            if (null === $event) {
                return null;
            }

            if (!$event instanceof Event) {
                throw new \InvalidArgumentException(sprintf('The event processor must return null or an instance of the %s class', Event::class));
            }
        }

        return $event;
    }

    public function __clone()
    {
        $this->user = clone $this->user;
        $this->tags = clone $this->tags;
        $this->extra = clone $this->extra;
    }
}
