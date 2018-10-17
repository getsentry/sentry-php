<?php

namespace Sentry\Hub;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Client;

final class Hub
{
    private $stack = [];

    public function __construct(?Client $client = null, ?Scope $scope = null)
    {
        if (null === $scope) {
            $scope = new Scope();
        }
        $this->stack[] = new Layer($client, $scope);
    }

    /**
     * @internal
     *
     * @return array
     */
    public function getStack(): array
    {
        return $this->stack;
    }

    /**
     * @internal
     *
     * @return Layer
     */
    public function getStackTop(): Layer
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
     * TODO: Fix Create Level "enum".
     *
     * @param string $message
     * @param $level
     *
     * @return null|string
     */
    public function captureMessage(string $message, $level): ?string
    {
        if ($client = $this->getClient()) {
            return $client->captureMessage($message);
        }
    }

    /**
     * @param $exception
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
     * @param Breadcrumb $crumb
     */
    public function addBreadcrumb(Breadcrumb $crumb): void
    {
        if ($client = $this->getClient()) {
            $client->leaveBreadcrumb($crumb);
        }
    }
}
