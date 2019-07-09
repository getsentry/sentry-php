<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Integration\IntegrationInterface;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * This class is the main entry point for all the most common SDK features.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SentrySdk
{
    /**
     * @var HubInterface|null The current hub
     */
    private static $currentHub;

    /**
     * Constructor.
     */
    private function __construct()
    {
    }

    /**
     * Initializes the SDK by creating a new hub instance each time this method
     * gets called.
     *
     * @return HubInterface
     */
    public static function init(): HubInterface
    {
        self::$currentHub = new Hub();

        return self::$currentHub;
    }

    /**
     * Gets the current hub. If it's not initialized then creates a new instance
     * and sets it as current hub.
     *
     * @param bool $throwDeprecation Whether to throw a deprecation message when
     *                               using this method. Internal only!
     *
     * @return HubInterface
     *
     * @deprecated since version 2.2, to be removed in 3.0
     *
     * @internal
     */
    public static function getCurrentHub(bool $throwDeprecation = true): HubInterface
    {
        if ($throwDeprecation) {
            @trigger_error(sprintf('The %s() method is deprecated since version 2.2 and will be removed in 3.0.', __METHOD__), E_USER_DEPRECATED);
        }

        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub;
    }

    /**
     * Sets the current hub.
     *
     * @param HubInterface $hub              The hub to set
     * @param bool         $throwDeprecation Whether to throw a deprecation message when
     *                                       using this method. Internal only!
     *
     * @return HubInterface
     *
     * @deprecated since version 2.2, to be removed in 3.0
     *
     * @internal
     */
    public static function setCurrentHub(HubInterface $hub, bool $throwDeprecation = true): HubInterface
    {
        if ($throwDeprecation) {
            @trigger_error(sprintf('The %s() method is deprecated since version 2.2 and will be removed in 3.0.', __METHOD__), E_USER_DEPRECATED);
        }

        self::$currentHub = $hub;

        return $hub;
    }

    /**
     * Gets the client bound to the top of the stack.
     *
     * @return ClientInterface|null
     */
    public static function getClient(): ?ClientInterface
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->getClient();
    }

    /**
     * Gets the ID of the last captured event.
     *
     * @return string|null
     */
    public static function getLastEventId(): ?string
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->getLastEventId();
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
    public static function pushScope(): Scope
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->pushScope();
    }

    /**
     * Removes a previously pushed scope from the stack. This restores the state
     * before the scope was pushed. All breadcrumbs and context information added
     * since the last call to {@see Hub::pushScope} are discarded.
     *
     * @return bool
     */
    public static function popScope(): bool
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->popScope();
    }

    /**
     * Creates a new scope with and executes the given operation within. The scope
     * is automatically removed once the operation finishes or throws.
     *
     * @param callable $callback The callback to be executed
     */
    public static function withScope(callable $callback): void
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        self::$currentHub->withScope($callback);
    }

    /**
     * Calls the given callback passing to it the current scope so that any
     * operation can be run within its context.
     *
     * @param callable $callback The callback to be executed
     */
    public static function configureScope(callable $callback): void
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        self::$currentHub->configureScope($callback);
    }

    /**
     * Binds the given client to the current scope.
     *
     * @param ClientInterface $client The client
     */
    public static function bindClient(ClientInterface $client): void
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        self::$currentHub->bindClient($client);
    }

    /**
     * Captures a message event and sends it to Sentry.
     *
     * @param string   $message The message
     * @param Severity $level   The severity level of the message
     *
     * @return string|null
     */
    public static function captureMessage(string $message, ?Severity $level = null): ?string
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->captureMessage($message, $level);
    }

    /**
     * Captures an exception event and sends it to Sentry.
     *
     * @param \Throwable $exception The exception
     *
     * @return string|null
     */
    public static function captureException(\Throwable $exception): ?string
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->captureException($exception);
    }

    /**
     * Captures a new event using the provided data.
     *
     * @param array $payload The data of the event being captured
     *
     * @return string|null
     */
    public static function captureEvent(array $payload): ?string
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->captureEvent($payload);
    }

    /**
     * Captures an event that logs the last occurred error.
     *
     * @return string|null
     */
    public static function captureLastError(): ?string
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->captureLastError();
    }

    /**
     * Records a new breadcrumb which will be attached to future events. They
     * will be added to subsequent events to provide more context on user's
     * actions prior to an error or crash.
     *
     * @param Breadcrumb $breadcrumb The breadcrumb to record
     *
     * @return bool Whether the breadcrumb was actually added to the current scope
     */
    public static function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->addBreadcrumb($breadcrumb);
    }

    /**
     * Gets the integration whose FQCN matches the given one if it's available on the current client.
     *
     * @param string $className The FQCN of the integration
     *
     * @return IntegrationInterface|null
     */
    public static function getIntegration(string $className): ?IntegrationInterface
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub->getIntegration($className);
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
