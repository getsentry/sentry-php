<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Integration\IntegrationInterface;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\State\Scope;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;

/**
 * This client does not perform any operations, it acts as an interface compatible layer in order to
 * simply workflows where previously the client was null.
 * It also holds options which helps with situations where no options were available if the client was set to `null`.
 */
class NoOpClient implements ClientInterface
{
    /**
     * @var array<string, mixed>
     */
    private $options;

    /**
     * @var Options
     */
    private $sentryOptions;

    /**
     * @var StacktraceBuilder|null
     */
    private $stacktraceBuilder;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getOptions(): Options
    {
        if ($this->sentryOptions === null) {
            $this->sentryOptions = new Options($this->options);
        }

        return $this->sentryOptions;
    }

    public function getCspReportUrl(): ?string
    {
        return null;
    }

    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        return null;
    }

    public function captureException(\Throwable $exception, ?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        return null;
    }

    public function captureLastError(?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        return null;
    }

    public function captureEvent(Event $event, ?EventHint $hint = null, ?Scope $scope = null): ?EventId
    {
        return null;
    }

    public function captureCheckIn(string $slug, CheckInStatus $status, $duration = null, ?MonitorConfig $monitorConfig = null, ?string $checkInId = null): ?string
    {
        return null;
    }

    public function getIntegration(string $className): ?IntegrationInterface
    {
        return null;
    }

    public function flush(?int $timeout = null): Result
    {
        return new Result(ResultStatus::skipped());
    }

    public function getStacktraceBuilder(): StacktraceBuilder
    {
        if ($this->stacktraceBuilder === null) {
            $this->stacktraceBuilder = new StacktraceBuilder($this->getOptions(), new RepresentationSerializer($this->getOptions()));
        }

        return $this->stacktraceBuilder;
    }
}
