<?php

declare(strict_types=1);

namespace Sentry\Agent\Transport;

use Sentry\Client;
use Sentry\HttpClient\HttpClient;
use Sentry\HttpClient\HttpClientInterface;

final class AgentClientBuilder
{
    /**
     * @var string
     */
    private $host = '127.0.0.1';

    /**
     * @var int
     */
    private $port = 5148;

    /**
     * @var (callable(): HttpClientInterface)|null
     */
    private $fallbackClientFactory;

    /**
     * @var bool
     */
    private $isFallbackClientDisabled = false;

    /**
     * @var string
     */
    private $sdkIdentifier = Client::SDK_IDENTIFIER;

    /**
     * @var string
     */
    private $sdkVersion = Client::SDK_VERSION;

    public static function create(): self
    {
        return new self();
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function disableFallbackClient(): self
    {
        $this->isFallbackClientDisabled = true;
        $this->fallbackClientFactory = null;

        return $this;
    }

    public function setFallbackClient(HttpClientInterface $fallbackClient): self
    {
        return $this->setFallbackClientFactory(static function () use ($fallbackClient): HttpClientInterface {
            return $fallbackClient;
        });
    }

    /**
     * @phpstan-param callable(): HttpClientInterface $fallbackClientFactory
     */
    public function setFallbackClientFactory(callable $fallbackClientFactory): self
    {
        $this->isFallbackClientDisabled = false;
        $this->fallbackClientFactory = $fallbackClientFactory;

        return $this;
    }

    public function setSdkIdentifier(string $sdkIdentifier): self
    {
        $this->sdkIdentifier = $sdkIdentifier;

        return $this;
    }

    public function setSdkVersion(string $sdkVersion): self
    {
        $this->sdkVersion = $sdkVersion;

        return $this;
    }

    public function getClient(): AgentClient
    {
        if ($this->isFallbackClientDisabled) {
            return new AgentClient($this->host, $this->port, null);
        }

        if ($this->fallbackClientFactory !== null) {
            return new AgentClient($this->host, $this->port, $this->fallbackClientFactory);
        }

        return new AgentClient($this->host, $this->port, $this->createDefaultFallbackClientFactory());
    }

    /**
     * @return callable(): HttpClientInterface
     */
    private function createDefaultFallbackClientFactory(): callable
    {
        $sdkIdentifier = $this->sdkIdentifier;
        $sdkVersion = $this->sdkVersion;

        return static function () use ($sdkIdentifier, $sdkVersion): HttpClientInterface {
            return new HttpClient($sdkIdentifier, $sdkVersion);
        };
    }
}
