<?php

namespace Sentry\State;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Context\Context;
use Sentry\Context\TagsContext;
use Sentry\Context\UserContext;
use Sentry\Event;
use Sentry\Interfaces\Severity;

final class Scope
{
    /**
     * Array holding all breadcrumbs.
     *
     * @var array
     */
    private $breadcrumbs = [];

    /**
     * @var null|UserContext
     */
    private $user = null;

    /**
     * Array holding all tags.
     *
     * @var null|TagsContext
     */
    private $tags = null;

    /**
     * Array holding all extra.
     *
     * @var null|Context
     */
    private $extra = null;

    /**
     * Array holding all fingerprints. This is used to group events together in Sentry.
     *
     * @var string[]
     */
    private $fingerprint = [];

    /**
     * @var null|Severity
     */
    private $level = null;

    /**
     * Array of eventProcessors. Closure receiving the event, they should return an event or null.
     *
     * @var array
     */
    private $eventProcessors;

    /**
     * @param Event $event
     *
     * @return null|Event
     */
    private function notifyEventProcessors(Event $event): ?Event
    {
        foreach ($this->eventProcessors as $processor) {
            $event = $processor($event);
            if (null === $event) {
                return null;
            }
        }

        return $event;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return Scope
     */
    public function setTag(string $key, string $value): self
    {
        if (null === $this->tags) {
            $this->tags = new TagsContext();
        }
        $this->tags->offsetSet($key, $value);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return Scope
     */
    public function setExtra(string $key, $value): self
    {
        if (null === $this->extra) {
            $this->extra = new Context();
        }
        $this->extra[$key] = $value;

        return $this;
    }

    /**
     * @param null|UserContext $user
     *
     * @return Scope
     */
    public function setUser(?UserContext $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param string[] $fingerprint
     *
     * @return Scope
     */
    public function setFingerprint(array $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    /**
     * @param null|Severity $level
     *
     * @return Scope
     */
    public function setLevel(Severity $level): self
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @param Breadcrumb $crumb
     * @param int        $maxBreadcrumbs
     *
     * @return Scope
     */
    public function addBreadcrumb(Breadcrumb $crumb, int $maxBreadcrumbs = 100): self
    {
        $this->breadcrumbs[] = $crumb;
        $this->breadcrumbs = \array_slice($this->breadcrumbs, -$maxBreadcrumbs);

        return $this;
    }

    /**
     * @param Event $event
     * @param int   $maxBreadcrumbs
     *
     * @return null|Event
     */
    public function applyToEvent(Event $event, int $maxBreadcrumbs = 100): ?Event
    {
        if ($this->level) {
            $event->setLevel($this->level);
        }
        if (!empty($this->fingerprint)) {
            $event->setFingerprint($this->fingerprint);
        }
        // TODO: extra, tags, breadcrumbs, user

        return $this->notifyEventProcessors($event);
    }

    /**
     * @param \Closure $callback
     *
     * @return Scope
     */
    public function addEventProcessor(\Closure $callback): self
    {
        $this->eventProcessors[] = $callback;

        return $this;
    }

    /**
     * @return Scope
     */
    public function clear(): self
    {
        $this->tags = [];
        $this->extra = [];
        $this->user = null;
        $this->level = null;
        $this->breadcrumbs = [];

        return $this;
    }
}
