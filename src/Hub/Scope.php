<?php

namespace Sentry\Hub;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Event;
use Sentry\Interfaces\Severity;
use Sentry\Interfaces\User;

final class Scope
{
    /**
     * Array holding all breadcrumbs.
     *
     * @var array
     */
    private $breadcrumbs = [];

    /**
     * @var ?User
     */
    private $user = null;

    /**
     * Array holding all tags.
     *
     * @var array
     */
    private $tags = [];

    /**
     * Array holding all extra.
     *
     * @var array
     */
    private $extra = [];

    /**
     * Array holding all fingerprints. This is used to group events together in Sentry.
     *
     * @var array
     */
    private $fingerprint = [];

    /**
     * @var ?Severity
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
     * @internal
     *
     * @return array
     */
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    /**
     * @internal
     *
     * @return null|User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @internal
     *
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @internal
     *
     * @return array
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * @internal
     *
     * @return array
     */
    public function getFingerprint(): array
    {
        return $this->fingerprint;
    }

    /**
     * @internal
     *
     * @return null|Severity
     */
    public function getLevel(): ?Severity
    {
        return $this->level;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return Scope
     */
    public function setTag(string $key, string $value): self
    {
        $this->tags[$key] = $value;

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
        $this->extra[$key] = $value;

        return $this;
    }

    /**
     * @param User $user
     *
     * @return Scope
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param array $fingerprint
     *
     * @return Scope
     */
    public function setFingerprint(array $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    /**
     * @param Severity $level
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
}
