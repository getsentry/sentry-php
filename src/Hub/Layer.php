<?php

namespace Sentry\Hub;

use Sentry\Client;

final class Layer
{
    private $client;
    private $scope;

    public function __construct(?Client $client, ?Scope $scope)
    {
        $this->client = $client;
        $this->scope = $scope;
    }

    /**
     * @return ?Client
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * @param mixed $client
     *
     * @return self
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Scope
     */
    public function getScope(): Scope
    {
        return $this->scope;
    }

    /**
     * @param $scope
     *
     * @return self
     */
    public function setScope($scope): self
    {
        $this->scope = $scope;

        return $this;
    }
}
