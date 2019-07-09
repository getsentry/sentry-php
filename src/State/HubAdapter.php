<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Integration\IntegrationInterface;
use Sentry\SentrySdk;
use Sentry\Severity;

/**
 * An implementation of {@see HubInterface} which forwards any call to {@see SentrySdk}.
 * This allows testing classes which otherwise would need to depend on it by
 * having them depend on the interface instead, which can be mocked.
 */
final class HubAdapter implements HubInterface
{
    /**
     * @var self The single instance which forwards all calls to {@see SentrySdk}
     */
    private static $instance;

    /**
     * Constructor.
     */
    private function __construct()
    {
    }

    /**
     * Gets the instance of this class. This is a singleton, so once initialized
     * you will always get the same instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ?ClientInterface
    {
        return SentrySdk::getClient();
    }

    /**
     * {@inheritdoc}
     */
    public function getLastEventId(): ?string
    {
        return SentrySdk::getLastEventId();
    }

    /**
     * {@inheritdoc}
     */
    public function pushScope(): Scope
    {
        return SentrySdk::pushScope();
    }

    /**
     * {@inheritdoc}
     */
    public function popScope(): bool
    {
        return SentrySdk::popScope();
    }

    /**
     * {@inheritdoc}
     */
    public function withScope(callable $callback): void
    {
        SentrySdk::withScope($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function configureScope(callable $callback): void
    {
        SentrySdk::configureScope($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function bindClient(ClientInterface $client): void
    {
        SentrySdk::bindClient($client);
    }

    /**
     * {@inheritdoc}
     */
    public function captureMessage(string $message, ?Severity $level = null): ?string
    {
        return SentrySdk::captureMessage($message, $level);
    }

    /**
     * {@inheritdoc}
     */
    public function captureException(\Throwable $exception): ?string
    {
        return SentrySdk::captureException($exception);
    }

    /**
     * {@inheritdoc}
     */
    public function captureEvent(array $payload): ?string
    {
        return SentrySdk::captureEvent($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function captureLastError(): ?string
    {
        return SentrySdk::captureLastError();
    }

    /**
     * {@inheritdoc}
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        return SentrySdk::addBreadcrumb($breadcrumb);
    }

    /**
     * {@inheritdoc}
     */
    public static function getCurrent(): HubInterface
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 2.2 and will be removed in 3.0', __METHOD__), E_USER_DEPRECATED);

        return SentrySdk::getCurrentHub(false);
    }

    /**
     * {@inheritdoc}
     */
    public static function setCurrent(HubInterface $hub): HubInterface
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 2.2 and will be removed in 3.0', __METHOD__), E_USER_DEPRECATED);

        return SentrySdk::setCurrentHub($hub, false);
    }

    /**
     * {@inheritdoc}
     */
    public function getIntegration(string $className): ?IntegrationInterface
    {
        return SentrySdk::getIntegration($className);
    }

    /**
     * @see https://www.php.net/manual/en/language.oop5.cloning.php#object.clone
     */
    public function __clone()
    {
        throw new \BadMethodCallException('Cloning is forbidden.');
    }

    /**
     * @see https://www.php.net/manual/en/language.oop5.magic.php#object.wakeup
     */
    public function __wakeup()
    {
        throw new \BadMethodCallException('Unserializing instances of this class is forbidden.');
    }
}
