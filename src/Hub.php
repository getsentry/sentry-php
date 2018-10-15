<?php

namespace Sentry;

class Layer
{
    private $client;
    private $scope;

    public function __construct(?Client $client, ?Scope $scope)
    {
        $this->client = $client;
        $this->scope = $scope;
    }

    /**
     * @return mixed
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param mixed $client
     */
    public function setClient($client): void
    {
        $this->client = $client;
    }

    /**
     * @return mixed
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @param mixed $scope
     */
    public function setScope($scope): void
    {
        $this->scope = $scope;
    }
}

final class Hub
{
    private $stack = [];

    public function __construct(?Client $client = null, ?Scope $scope = null)
    {
        $this->stack[] = new Layer($client, $scope);
    }

    public function getStackTop(): Layer
    {
        return \end($this->stack);
    }

    public function getClient(): Client
    {
        return $this->getStackTop()->getClient();
    }

    public function getScope(): Scope
    {
        return $this->getStackTop()->getScope();
    }

    public function pushScope(): Scope
    {
        $currentScope = $this->getScope();
        $this->stack[] = new Layer($this->getClient(), clone $currentScope);
    }

    public function popScope(): bool
    {
        return null !== \array_pop($this->stack);
    }

    public function withScope(\Closure $callback): void
    {
        $scope = $this->pushScope();
        $callback($scope);
        $this->popScope();
    }
}
