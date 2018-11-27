<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Integration\IntegrationInterface;
use Sentry\Severity;

final class Hub implements HubInterface
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

    public function getClient(): ?ClientInterface
    {
        return $this->getStackTop()->getClient();
    }

    public function getScope(): Scope
    {
        return $this->getStackTop()->getScope();
    }

    public function getStack(): array
    {
        return $this->stack;
    }

    public function getStackTop(): Layer
    {
        return $this->stack[\count($this->stack) - 1];
    }

    public function getLastEventId(): ?string
    {
        return $this->lastEventId;
    }

    public function pushScope(): Scope
    {
        $clonedScope = clone $this->getScope();

        $this->stack[] = new Layer($this->getClient(), $clonedScope);

        return $clonedScope;
    }

    public function popScope(): bool
    {
        if (1 === \count($this->stack)) {
            return false;
        }

        return null !== \array_pop($this->stack);
    }

    public function withScope(callable $callback): void
    {
        $scope = $this->pushScope();

        try {
            $callback($scope);
        } finally {
            $this->popScope();
        }
    }

    public function configureScope(callable $callback): void
    {
        $callback($this->getScope());
    }

    public function bindClient(ClientInterface $client): void
    {
        $layer = $this->getStackTop();
        $layer->setClient($client);
    }

    public function captureMessage(string $message, ?Severity $level = null): ?string
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureMessage($message, $level, $this->getScope());
        }

        return null;
    }

    public function captureException(\Throwable $exception): ?string
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureException($exception, $this->getScope());
        }

        return null;
    }

    public function captureEvent(array $payload): ?string
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureEvent($payload, $this->getScope());
        }

        return null;
    }

    public function captureLastError(): ?string
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureLastError($this->getScope());
        }

        return null;
    }

    public function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        $client = $this->getClient();

        if (null === $client) {
            return false;
        }

        $options = $client->getOptions();
        $beforeBreadcrumbCallback = $options->getBeforeBreadcrumbCallback();
        $maxBreadcrumbs = $options->getMaxBreadcrumbs();

        if ($maxBreadcrumbs <= 0) {
            return false;
        }

        $breadcrumb = \call_user_func($beforeBreadcrumbCallback, $breadcrumb);

        if (null !== $breadcrumb) {
            $this->getScope()->addBreadcrumb($breadcrumb, $maxBreadcrumbs);
        }

        return null !== $breadcrumb;
    }

    public static function getCurrent(): self
    {
        if (null === self::$currentHub) {
            self::$currentHub = new self();
        }

        return self::$currentHub;
    }

    public static function setCurrent(self $hub): self
    {
        self::$currentHub = $hub;

        return $hub;
    }

    public function getIntegration(string $className): ?IntegrationInterface
    {
        $client = $this->getClient();
        if (null !== $client) {
            return $client->getIntegration($className);
        }

        return null;
    }
}
