<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Attachment\Attachment;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Severity;
use Sentry\UserDataBag;

/**
 * The scope holds data that should implicitly be sent with Sentry events. It
 * can hold context data, extra parameters, level overrides, fingerprints etc.
 */
abstract class MutableScope extends Scope
{
    /**
     * Returns the client bound to this scope.
     */
    public function getClient(): ClientInterface
    {
        return $this->scopeData->getClient();
    }

    /**
     * Sets the client bound to this scope.
     *
     * @return $this
     */
    public function setClient(ClientInterface $client): self
    {
        $this->scopeData->setClient($client);

        return $this;
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
        $this->scopeData->setTag($key, $value);

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
        $this->scopeData->setTags(array_merge($this->scopeData->getTags(), $tags));

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
        $this->scopeData->removeTag($key);

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
            $this->scopeData->setContext($name, $value);
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
        $this->scopeData->removeContext($name);

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
        $this->scopeData->setExtra($key, $value);

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
        $this->scopeData->setExtras(array_merge($this->scopeData->getExtra(), $extras));

        return $this;
    }

    /**
     * Get the user context.
     */
    public function getUser(): ?UserDataBag
    {
        return $this->scopeData->getUser();
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
        $this->scopeData->setUser($user);

        return $this;
    }

    /**
     * Removes all data of the user context.
     *
     * @return $this
     */
    public function removeUser(): self
    {
        $this->scopeData->removeUser();

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
        $this->scopeData->setFingerprint($fingerprint);

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
        $this->scopeData->setLevel($level);

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
        $this->scopeData->addBreadcrumb($breadcrumb, $maxBreadcrumbs);

        return $this;
    }

    /**
     * Clears all the breadcrumbs.
     *
     * @return $this
     */
    public function clearBreadcrumbs(): self
    {
        $this->scopeData->clearBreadcrumbs();

        return $this;
    }

    /**
     * Adds a new event processor that will be called after {@see MutableScope::applyToEvent}
     * finished its work.
     *
     * @param callable $eventProcessor The event processor
     *
     * @return $this
     */
    public function addEventProcessor(callable $eventProcessor): self
    {
        $this->scopeData->addEventProcessor($eventProcessor);

        return $this;
    }

    /**
     * Clears event payload data from the scope. The client binding and last
     * event ID are preserved.
     */
    public function clear(): void
    {
        $this->scopeData->clear();
    }

    public function __clone()
    {
        $this->scopeData = clone $this->scopeData;
    }

    public function addAttachment(Attachment $attachment): self
    {
        $this->scopeData->addAttachment($attachment);

        return $this;
    }

    public function clearAttachments(): self
    {
        $this->scopeData->setAttachments([]);

        return $this;
    }
}
