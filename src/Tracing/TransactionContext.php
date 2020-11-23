<?php

declare(strict_types=1);

namespace Sentry\Tracing;

final class TransactionContext extends SpanContext
{
    private const TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

    public const DEFAULT_NAME = '<unlabeled transaction>';

    /**
     * @var string Name of the transaction
     */
    private $name;

    /**
     * @var bool|null The parent's sampling decision
     */
    private $parentSampled;

    /**
     * Constructor.
     *
     * @param string    $name          The name of the transaction
     * @param bool|null $parentSampled The parent's sampling decision
     */
    public function __construct(string $name = self::DEFAULT_NAME, ?bool $parentSampled = null)
    {
        $this->name = $name;
        $this->parentSampled = $parentSampled;
    }

    /**
     * Gets the name of the transaction.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name of the transaction.
     *
     * @param string $name The name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Gets the parent's sampling decision.
     */
    public function getParentSampled(): ?bool
    {
        return $this->parentSampled;
    }

    /**
     * Sets the parent's sampling decision.
     *
     * @param bool|null $parentSampled The decision
     */
    public function setParentSampled(?bool $parentSampled): void
    {
        $this->parentSampled = $parentSampled;
    }

    /**
     * Returns a context populated with the data of the given header.
     *
     * @param string $header The sentry-trace header from the request
     *
     * @return self
     */
    public static function fromSentryTrace(string $header)
    {
        $context = new self();

        if (!preg_match(self::TRACEPARENT_HEADER_REGEX, $header, $matches)) {
            return $context;
        }

        if (!empty($matches['trace_id'])) {
            $context->traceId = new TraceId($matches['trace_id']);
        }

        if (!empty($matches['span_id'])) {
            $context->parentSpanId = new SpanId($matches['span_id']);
        }

        if (isset($matches['sampled'])) {
            $context->parentSampled = '1' === $matches['sampled'];
        }

        return $context;
    }
}
