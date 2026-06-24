<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Attachment\Attachment;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Severity;
use Sentry\Tracing\PropagationContext;
use Sentry\UserDataBag;
use Sentry\Util\DebugType;

/**
 * Represents the internal state of a scope and stores all data that is shared between all types of scope.
 *
 * This class can be cloned safely.
 *
 * @internal
 */
class ScopeData
{
    /**
     * @var PropagationContext
     */
    private $propagationContext;

    /**
     * @var ClientInterface The client bound to this scope
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
     * @phpstan-var array<callable(Event, EventHint): ?Event>
     */
    private $eventProcessors = [];

    /**
     * @var Attachment[]
     */
    private $attachments = [];

    public function getPropagationContext(): PropagationContext
    {
        return $this->propagationContext;
    }

    public function setPropagationContext(PropagationContext $propagationContext): void
    {
        $this->propagationContext = $propagationContext;
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function setClient(ClientInterface $client): void
    {
        $this->client = $client;
    }

    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    public function setBreadcrumbs(array $breadcrumbs): void
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    public function addBreadcrumb(Breadcrumb $breadcrumb, int $maxBreadcrumbs = 100): void
    {
        $this->breadcrumbs[] = $breadcrumb;
        $this->breadcrumbs = \array_slice($this->breadcrumbs, -$maxBreadcrumbs);
    }

    public function clearBreadcrumbs(): void
    {
        $this->breadcrumbs = [];
    }

    public function getUser(): ?UserDataBag
    {
        return $this->user;
    }

    /**
     * @param array<string, mixed>|UserDataBag $user
     */
    public function setUser($user): void
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
    }

    public function removeUser(): void
    {
        $this->user = null;
    }

    public function getContexts(): array
    {
        return $this->contexts;
    }

    public function setContexts(array $contexts): void
    {
        $this->contexts = $contexts;
    }

    public function setContext(string $name, array $value): void
    {
        $this->contexts[$name] = $value;
    }

    public function removeContext(string $key): void
    {
        unset($this->contexts[$key]);
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function setTag(string $key, string $value): void
    {
        $this->tags[$key] = $value;
    }

    public function removeTag(string $key): void
    {
        unset($this->tags[$key]);
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function removeFlag(int $flagIndex): void
    {
        unset($this->flags[$flagIndex]);
    }

    public function setFlags(array $flags): void
    {
        $this->flags = $flags;
    }

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
        if (\count($this->flags) >= Scope::MAX_FLAGS) {
            array_shift($this->flags);
        }

        $this->flags[] = [$key => $result];

        return $this;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }

    public function setExtras(array $extra): void
    {
        $this->extra = $extra;
    }

    public function setExtra(string $key, $value): void
    {
        $this->extra[$key] = $value;
    }

    public function getFingerprint(): array
    {
        return $this->fingerprint;
    }

    public function setFingerprint(array $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    public function getLevel(): ?Severity
    {
        return $this->level;
    }

    public function setLevel(?Severity $level): void
    {
        $this->level = $level;
    }

    public function getEventProcessors(): array
    {
        return $this->eventProcessors;
    }

    public function setEventProcessors(array $eventProcessors): void
    {
        $this->eventProcessors = $eventProcessors;
    }

    public function addEventProcessor(callable $eventProcessor): void
    {
        $this->eventProcessors[] = $eventProcessor;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function addAttachment(Attachment $attachment): void
    {
        $this->attachments[] = $attachment;
    }

    public function clear(): void
    {
        $this->user = null;
        $this->level = null;
        $this->fingerprint = [];
        $this->breadcrumbs = [];
        $this->tags = [];
        $this->flags = [];
        $this->extra = [];
        $this->contexts = [];
        $this->attachments = [];
    }

    public function __clone()
    {
        if ($this->user !== null) {
            $this->user = clone $this->user;
        }
        if ($this->propagationContext !== null) {
            $this->propagationContext = clone $this->propagationContext;
        }
    }

    /**
     * Merges data of one ScopeData object into the other one. Data stored in $other will have precedence over
     * $this. It is generally assumed $this refers to the global scope while $other is the isolation scope.
     */
    public function merge(self $other): self
    {
        $merged = clone $other;
        $merged->tags = array_merge($this->tags, $other->tags);
        $merged->extra = array_merge($this->extra, $other->extra);
        $merged->contexts = array_merge($this->contexts, $other->contexts);

        if ($this->user !== null && $other->user !== null) {
            $merged->user = (clone $this->user)->merge($other->user);
        } elseif ($this->user !== null) {
            $merged->user = clone $this->user;
        }

        $merged->level = $other->level ?? $this->level;
        $merged->fingerprint = array_merge($this->fingerprint, $other->fingerprint);
        $merged->breadcrumbs = \array_slice(array_merge($this->breadcrumbs, $other->breadcrumbs), -100);
        $merged->flags = self::mergeFlags($this->flags, $other->flags);
        $merged->attachments = array_merge($this->attachments, $other->attachments);
        $merged->eventProcessors = array_merge($this->eventProcessors, $other->eventProcessors);

        return $merged;
    }

    /**
     * @param array<int, array<string, bool>> $globalFlags
     * @param array<int, array<string, bool>> $isolationFlags
     *
     * @return array<int, array<string, bool>>
     */
    private static function mergeFlags(array $globalFlags, array $isolationFlags): array
    {
        $flagsByKey = [];

        foreach (array_merge($globalFlags, $isolationFlags) as $flag) {
            $flagKey = key($flag);

            if ($flagKey === null) {
                continue;
            }

            unset($flagsByKey[$flagKey]);
            $flagsByKey[$flagKey] = current($flag);
        }

        $flagsByKey = \array_slice($flagsByKey, -Scope::MAX_FLAGS, Scope::MAX_FLAGS, true);

        $flags = [];

        foreach ($flagsByKey as $flagKey => $flagResult) {
            $flags[] = [$flagKey => $flagResult];
        }

        return $flags;
    }
}
