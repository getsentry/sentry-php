<?php

namespace Sentry\State;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Client;
use Sentry\Event;
use Sentry\Interfaces\Severity;

/**
 * Class Hub.
 *
 * Responsible for maintaining a stack of Client <-> Scope. Basically it's the main entry point to talk to the Client.
 */
final class Hub
{
    /**
     * @var Layer[]
     */
    private $stack = [];

    /**
     * @var Hub
     */
    public static $currentHub;

    /**
     * Hub constructor.
     *
     * @param null|Client $client
     * @param null|Scope  $scope
     */
    public function __construct(?Client $client = null, ?Scope $scope = null)
    {
        if (null === $scope) {
            $scope = new Scope();
        }
        $this->stack[] = new Layer($client, $scope);
    }

    /**
     * @return Layer
     */
    private function getStackTop(): Layer
    {
        return \end($this->stack);
    }

    /**
     * @return null|Client
     */
    public function getClient(): ?Client
    {
        return $this->getStackTop()->getClient();
    }

    /**
     * @return Scope
     */
    public function getScope(): Scope
    {
        return $this->getStackTop()->getScope();
    }

    /**
     * @return Scope
     */
    public function pushScope(): Scope
    {
        $clonedScope = clone $this->getScope();
        $this->stack[] = new Layer($this->getClient(), $clonedScope);

        return $clonedScope;
    }

    /**
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
     * @param \Closure $callback
     */
    public function withScope(\Closure $callback): void
    {
        $scope = $this->pushScope();
        $callback($scope);
        $this->popScope();
    }

    /**
     * @param \Closure $callback
     */
    public function configureScope(\Closure $callback): void
    {
        $top = $this->getStackTop();
        if (null !== $top->getClient()) {
            $callback($top->getScope());
        }
    }

    /**
     * @param Client $client
     */
    public function bindClient(Client $client): void
    {
        $top = $this->getStackTop();
        $top->setClient($client);
    }

    /**
     * @param string   $message
     * @param Severity $level
     *
     * @return null|string
     */
    public function captureMessage(string $message, ?Severity $level = null): ?string
    {
        if ($client = $this->getClient()) {
            // TODO: add level to call
            return $client->captureMessage($message);
        }
    }

    /**
     * @param \Throwable $exception
     *
     * @return null|string
     */
    public function captureException($exception): ?string
    {
        if ($client = $this->getClient()) {
            return $client->captureException($exception);
        }
    }

    /**
     * Send a event to Sentry.
     *
     * @param Event $event
     *
     * @return null|string
     */
    public function captureEvent(Event $event): ?string
    {
        if ($client = $this->getClient()) {
            // TODO
            // $client->captureEvent
        }
    }

    /**
     * @param Breadcrumb $breadcrumb
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb): void
    {
        if ($client = $this->getClient()) {
            $client->leaveBreadcrumb($breadcrumb);
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
     * @param Hub $hub
     *
     * @return Hub
     */
    public static function setCurrent(self $hub): self
    {
        self::$currentHub = $hub;

        return $hub;
    }
}
