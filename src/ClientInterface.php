<?php

declare(strict_types=1);

namespace Sentry;

use GuzzleHttp\Promise\PromiseInterface;
use Sentry\Integration\IntegrationInterface;
use Sentry\State\Scope;

/**
 * This interface must be implemented by all Raven client classes.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface ClientInterface
{
    /**
     * Returns the options of the client.
     */
    public function getOptions(): Options;

    /**
     * Logs a message.
     *
     * @param string     $message The message (primary description) for the event
     * @param Severity   $level   The level of the message to be sent
     * @param Scope|null $scope   An optional scope keeping the state
     */
    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null): ?EventId;

    /**
     * Logs an exception.
     *
     * @param \Throwable $exception The exception object
     * @param Scope|null $scope     An optional scope keeping the state
     */
    public function captureException(\Throwable $exception, ?Scope $scope = null): ?EventId;

    /**
     * Logs the most recent error (obtained with {@link error_get_last}).
     *
     * @param Scope|null $scope An optional scope keeping the state
     */
    public function captureLastError(?Scope $scope = null): ?EventId;

    /**
     * Captures a new event using the provided data.
     *
     * @param Event          $event The event being captured
     * @param EventHint|null $hint  May contain additional information about the event
     * @param Scope|null     $scope An optional scope keeping the state
     */
    public function captureEvent(Event $event, ?EventHint $hint = null, ?Scope $scope = null): ?EventId;

    /**
     * Returns the integration instance if it is installed on the client.
     *
     * @param string $className The FQCN of the integration
     *
     * @psalm-template T of IntegrationInterface
     *
     * @psalm-param class-string<T> $className
     *
     * @psalm-return T|null
     */
    public function getIntegration(string $className): ?IntegrationInterface;

    /**
     * Flushes the queue of events pending to be sent. If a timeout is provided
     * and the queue takes longer to drain, the promise resolves with `false`.
     *
     * @param int|null $timeout Maximum time in seconds the client should wait
     */
    public function flush(?int $timeout = null): PromiseInterface;
}
