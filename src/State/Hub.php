<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\State;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Integration\IntegrationInterface;
use Sentry\Severity;

/**
 * This class is responsible for maintaining a stack of pairs of clients and
 * scopes. It is the main entry point to talk with the Sentry client.
 */
final class Hub
{
    /**
     * @var Layer[] The stack of client/scope pairs
     */
    private $stack = [];

    /**
     * @var string|null The ID of the last captured event
     */
    private $lastEventId;

    /**
     * Constructor.
     *
     * @var Hub
     */
    private static $currentHub;

    /**
     * Hub constructor.
     *
     * @param ClientInterface|null $client The client bound to the hub
     * @param Scope|null           $scope  The scope bound to the hub
     */
    public function __construct(?ClientInterface $client = null, ?Scope $scope = null)
    {
        if (null === $scope) {
            $scope = new Scope();
        }

        $this->stack[] = new Layer($client, $scope);
    }

    /**
     * Gets the client binded to the top of the stack.
     *
     * @return ClientInterface|null
     */
    public function getClient(): ?ClientInterface
    {
        return $this->getStackTop()->getClient();
    }

    /**
     * Gets the scope binded to the top of the stack.
     *
     * @return Scope
     */
    public function getScope(): Scope
    {
        return $this->getStackTop()->getScope();
    }

    /**
     * Gets the stack of clients and scopes.
     *
     * @return Layer[]
     */
    public function getStack(): array
    {
        return $this->stack;
    }

    /**
     * Gets the topmost client/layer pair in the stack.
     *
     * @return Layer
     */
    public function getStackTop(): Layer
    {
        return $this->stack[\count($this->stack) - 1];
    }

    /**
     * Gets the ID of the last captured event.
     *
     * @return null|string
     */
    public function getLastEventId(): ?string
    {
        return $this->lastEventId;
    }

    /**
     * Creates a new scope to store context information that will be layered on
     * top of the current one. It is isolated, i.e. all breadcrumbs and context
     * information added to this scope will be removed once the scope ends. Be
     * sure to always remove this scope with {@see Hub::popScope} when the
     * operation finishes or throws.
     *
     * @return Scope
     */
    public function pushScope(): Scope
    {
        $clonedScope = clone $this->getScope();

        $this->stack[] = new Layer($this->getClient(), $clonedScope);

        return $clonedScope;
    }

    /**
     * Removes a previously pushed scope from the stack. This restores the state
     * before the scope was pushed. All breadcrumbs and context information added
     * since the last call to {@see Hub::pushScope} are discarded.
     *
     * @return bool
     */
    public function popScope(): bool
    {
        if (1 === \count($this->stack)) {
            return false;
        }

        return null !== \array_pop($this->stack);
    }

    /**
     * Creates a new scope with and executes the given operation within. The scope
     * is automatically removed once the operation finishes or throws.
     *
     * @param callable $callback The callback to be executed
     */
    public function withScope(callable $callback): void
    {
        $scope = $this->pushScope();

        try {
            $callback($scope);
        } finally {
            $this->popScope();
        }
    }

    /**
     * Calls the given callback passing to it the current scope so that any
     * operation can be run within its context.
     *
     * @param callable $callback The callback to be executed
     */
    public function configureScope(callable $callback): void
    {
        $callback($this->getScope());
    }

    /**
     * Binds the given client to the current scope.
     *
     * @param ClientInterface $client The client
     */
    public function bindClient(ClientInterface $client): void
    {
        $layer = $this->getStackTop();
        $layer->setClient($client);
    }

    /**
     * Captures a message event and sends it to Sentry.
     *
     * @param string   $message The message
     * @param Severity $level   The severity level of the message
     *
     * @return null|string
     */
    public function captureMessage(string $message, ?Severity $level = null): ?string
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureMessage($message, $level, $this->getScope());
        }

        return null;
    }

    /**
     * Captures an exception event and sends it to Sentry.
     *
     * @param \Throwable $exception The exception
     *
     * @return null|string
     */
    public function captureException(\Throwable $exception): ?string
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureException($exception, $this->getScope());
        }

        return null;
    }

    /**
     * Captures a new event using the provided data.
     *
     * @param array $payload The data of the event being captured
     *
     * @return null|string
     */
    public function captureEvent(array $payload): ?string
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureEvent($payload, $this->getScope());
        }

        return null;
    }

    /**
     * Records a new breadcrumb which will be attached to future events. They
     * will be added to subsequent events to provide more context on user's
     * actions prior to an error or crash.
     *
     * @param Breadcrumb $breadcrumb The breadcrumb to record
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb): void
    {
        $client = $this->getClient();

        if (null !== $client) {
            $client->addBreadcrumb($breadcrumb, $this->getScope());
        }
    }

    /**
     * Returns the current global Hub.
     *
     * @return Hub
     */
    public static function getCurrent(): self
    {
        if (null === self::$currentHub) {
            self::$currentHub = new self();
        }

        return self::$currentHub;
    }

    /**
     * Sets the Hub as the current.
     *
     * @param self $hub
     *
     * @return Hub
     */
    public static function setCurrent(self $hub): self
    {
        self::$currentHub = $hub;

        return $hub;
    }

    public function getIntegration(IntegrationInterface $integration): ?IntegrationInterface
    {
        if ($client = $this->getClient()) {
            return $client->getIntegration($integration);
        }

        return null;
    }
}
