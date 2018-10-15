<?php

namespace Sentry;

use Sentry\Breadcrumbs\Breadcrumb;

class Scope
{
    private $breadcrumbs = [];
    private $user = null;
    private $tags = [];
    private $extra = [];
    private $fingerprint = [];
    private $level = null;
    private $eventProcessors;

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
     * @return User|null
     */
    public function getUser()
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
     * @return string|null
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Scope
     */
    public function setTag(string $key, $value): self
    {
        $this->tags[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param $value
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
     * @param string $level
     *
     * @return Scope
     */
    public function setLevel(string $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function addBreadcrumb(Breadcrumb $crumb, int $maxBreadcrumbs = 100): self
    {
        $this->breadcrumbs[] = $crumb;
        $this->breadcrumbs = \array_slice($this->breadcrumbs, -$maxBreadcrumbs);

        return $this;
    }

    public function applyToEvent(Event $event, int $maxBreadcrumbs = 100): Event
    {
        if ($this->level) {
            $event->setLevel($this->level);
        }
        if ($this->fingerprint) {
            $event->setFingerprint($this->fingerprint);
        }
        // TODO: extra, tags, breadcrumbs, user
    }

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
