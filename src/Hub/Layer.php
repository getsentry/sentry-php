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
     * @return null|Client
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * @param mixed $client
     *
     * @return Layer
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
     * @return Layer
     */
    public function setScope($scope): self
    {
        $this->scope = $scope;

        return $this;
    }
}
