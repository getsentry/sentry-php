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

        return $this;
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
