<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Attachment\Attachment;
use Sentry\Breadcrumb;
use Sentry\CheckInStatus;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\Integration\IntegrationInterface;
use Sentry\MonitorConfig;
use Sentry\NoOpClient;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSampler;

/**
 * An implementation of {@see HubInterface} that uses {@see SentrySdk} internally
 * to manage the current hub.
 */
final class HubAdapter implements HubInterface
{
    /**
     * @var self|null The single instance which forwards all calls to {@see SentrySdk}
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
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ClientInterface
    {
        return SentrySdk::getClient();
    }

    /**
     * {@inheritdoc}
     */
    public function getLastEventId(): ?EventId
    {
        return SentrySdk::getIsolationScope()->getLastEventId();
    }

    /**
     * {@inheritdoc}
     */
    public function withScope(callable $callback)
    {
        return \Sentry\withIsolationScope($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function configureScope(callable $callback): void
    {
        $callback(SentrySdk::getIsolationScope());
    }

    /**
     * {@inheritdoc}
     */
    public function bindClient(ClientInterface $client): void
    {
        SentrySdk::getGlobalScope()->setClient($client);
    }

    /**
     * {@inheritdoc}
     */
    public function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null): ?EventId
    {
        return EventCapturer::captureMessage($message, $level, $hint);
    }

    /**
     * {@inheritdoc}
     */
    public function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
    {
        return EventCapturer::captureException($exception, $hint);
    }

    /**
     * {@inheritdoc}
     */
    public function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
    {
        return EventCapturer::captureEvent($event, $hint);
    }

    /**
     * {@inheritdoc}
     */
    public function captureLastError(?EventHint $hint = null): ?EventId
    {
        return EventCapturer::captureLastError($hint);
    }

    /**
     * {@inheritdoc}
     *
     * @param int|float|null $duration
     */
    public function captureCheckIn(string $slug, CheckInStatus $status, $duration = null, ?MonitorConfig $monitorConfig = null, ?string $checkInId = null): ?string
    {
        return EventCapturer::captureCheckIn($slug, $status, $duration, $monitorConfig, $checkInId);
    }

    /**
     * {@inheritdoc}
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        $scope = SentrySdk::getIsolationScope();

        return BreadcrumbRecorder::record(SentrySdk::getClient($scope), $scope, $breadcrumb);
    }

    /**
     * {@inheritDoc}
     */
    public function addAttachment(Attachment $attachment): bool
    {
        if (SentrySdk::getClient() instanceof NoOpClient) {
            return false;
        }

        SentrySdk::getIsolationScope()->addAttachment($attachment);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIntegration(string $className): ?IntegrationInterface
    {
        return SentrySdk::getClient()->getIntegration($className);
    }

    /**
     * {@inheritdoc}
     */
    public function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
    {
        return TransactionSampler::startTransaction(SentrySdk::getClient()->getOptions(), $context, $customSamplingContext);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransaction(): ?Transaction
    {
        return SentrySdk::getIsolationScope()->getTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function getSpan(): ?Span
    {
        return SentrySdk::getIsolationScope()->getSpan();
    }

    /**
     * {@inheritdoc}
     */
    public function setSpan(?Span $span): HubInterface
    {
        SentrySdk::getIsolationScope()->setSpan($span);

        return $this;
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
    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Unserializing instances of this class is forbidden.');
    }

    /**
     * @see https://www.php.net/manual/en/language.oop5.magic.php#object.sleep
     */
    public function __sleep()
    {
        throw new \BadMethodCallException('Serializing instances of this class is forbidden.');
    }
}
