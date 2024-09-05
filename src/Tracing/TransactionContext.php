<?php

declare(strict_types=1);

namespace Sentry\Tracing;

final class TransactionContext extends SpanContext
{
    private const SENTRY_TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

    private const W3C_TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<version>[0]{2})?-?(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01]{2})?[ \\t]*$/i';

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
     * @var TransactionMetadata The transaction metadata
     */
    private $metadata;

    /**
     * Constructor.
     *
     * @param string                   $name          The name of the transaction
     * @param bool|null                $parentSampled The parent's sampling decision
     * @param TransactionMetadata|null $metadata      The transaction metadata
     */
    public function __construct(
        string $name = self::DEFAULT_NAME,
        ?bool $parentSampled = null,
        ?TransactionMetadata $metadata = null
    ) {
        $this->name = $name;
        $this->parentSampled = $parentSampled;
        $this->metadata = $metadata ?? new TransactionMetadata();
    }

    /**
     * @return self
     */
    public static function make()
    {
        return new self();
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
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
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
    public function setParentSampled(?bool $parentSampled): self
    {
        $this->parentSampled = $parentSampled;

        return $this;
    }

    /**
     * Gets the transaction metadata.
     */
    public function getMetadata(): TransactionMetadata
    {
        return $this->metadata;
    }

    /**
     * Sets the transaction metadata.
     *
     * @param TransactionMetadata $metadata The transaction metadata
     */
    public function setMetadata(TransactionMetadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Sets the transaction source.
     *
     * @param TransactionSource $transactionSource The transaction source
     */
    public function setSource(TransactionSource $transactionSource): self
    {
        $this->metadata->setSource($transactionSource);

        return $this;
    }

    /**
     * Returns a context populated with the data of the given environment variables.
     *
     * @param string $sentryTrace The sentry-trace value from the environment
     * @param string $baggage     The baggage header value from the environment
     */
    public static function fromEnvironment(string $sentryTrace, string $baggage): self
    {
        return self::parseTraceAndBaggage($sentryTrace, $baggage);
    }

    /**
     * Returns a context populated with the data of the given headers.
     *
     * @param string $sentryTraceHeader The sentry-trace header from an incoming request
     * @param string $baggageHeader     The baggage header from an incoming request
     */
    public static function fromHeaders(string $sentryTraceHeader, string $baggageHeader): self
    {
        return self::parseTraceAndBaggage($sentryTraceHeader, $baggageHeader);
    }

    private static function parseTraceAndBaggage(string $sentryTrace, string $baggage): self
    {
        $context = new self();
        $hasSentryTrace = false;

        if (preg_match(self::SENTRY_TRACEPARENT_HEADER_REGEX, $sentryTrace, $matches)) {
            if (!empty($matches['trace_id'])) {
                $context->traceId = new TraceId($matches['trace_id']);
                $hasSentryTrace = true;
            }

            if (!empty($matches['span_id'])) {
                $context->parentSpanId = new SpanId($matches['span_id']);
                $hasSentryTrace = true;
            }

            if (isset($matches['sampled'])) {
                $context->parentSampled = $matches['sampled'] === '1';
                $hasSentryTrace = true;
            }
        } elseif (preg_match(self::W3C_TRACEPARENT_HEADER_REGEX, $sentryTrace, $matches)) {
            if (!empty($matches['trace_id'])) {
                $context->traceId = new TraceId($matches['trace_id']);
                $hasSentryTrace = true;
            }

            if (!empty($matches['span_id'])) {
                $context->parentSpanId = new SpanId($matches['span_id']);
                $hasSentryTrace = true;
            }

            if (isset($matches['sampled'])) {
                $context->parentSampled = $matches['sampled'] === '01';
                $hasSentryTrace = true;
            }
        }

        $samplingContext = DynamicSamplingContext::fromHeader($baggage);

        if ($hasSentryTrace && !$samplingContext->hasEntries()) {
            // The request comes from an old SDK which does not support Dynamic Sampling.
            // Propagate the Dynamic Sampling Context as is, but frozen, even without sentry-* entries.
            $samplingContext->freeze();
            $context->getMetadata()->setDynamicSamplingContext($samplingContext);
        }

        if ($hasSentryTrace && $samplingContext->hasEntries()) {
            // The baggage header contains Dynamic Sampling Context data from an upstream SDK.
            // Propagate this Dynamic Sampling Context.
            $context->getMetadata()->setDynamicSamplingContext($samplingContext);
        }

        return $context;
    }
}
