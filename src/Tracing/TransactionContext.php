<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\ClientInterface;

final class TransactionContext extends SpanContext
{
    private const SENTRY_TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

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
     * @param string               $sentryTrace The sentry-trace value from the environment
     * @param string               $baggage     The baggage header value from the environment
     * @param ClientInterface|null $client      The client to use for validation (optional)
     */
    public static function fromEnvironment(string $sentryTrace, string $baggage, ?ClientInterface $client = null): self
    {
        return self::parseTraceAndBaggage($sentryTrace, $baggage, $client);
    }

    /**
     * Returns a context populated with the data of the given headers.
     *
     * @param string               $sentryTraceHeader The sentry-trace header from an incoming request
     * @param string               $baggageHeader     The baggage header from an incoming request
     * @param ClientInterface|null $client            The client to use for validation (optional)
     */
    public static function fromHeaders(string $sentryTraceHeader, string $baggageHeader, ?ClientInterface $client = null): self
    {
        return self::parseTraceAndBaggage($sentryTraceHeader, $baggageHeader, $client);
    }

    private static function parseTraceAndBaggage(string $sentryTrace, string $baggage, ?ClientInterface $client = null): self
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
        }

        $samplingContext = DynamicSamplingContext::fromHeader($baggage);

        // Check for org ID mismatch - always validate when both local and remote org IDs are present
        if ($client !== null && $hasSentryTrace) {
            $options = $client->getOptions();
            // Get org ID from either the org_id option or the DSN
            $localOrgId = $options->getOrgId();
            if ($localOrgId === null && $options->getDsn() !== null) {
                $localOrgId = $options->getDsn()->getOrgId();
            }
            $remoteOrgId = $samplingContext->has('org_id') ? (int) $samplingContext->get('org_id') : null;

            // If we have both a local org ID and a remote org ID, and they don't match, create a new trace
            if ($localOrgId !== null && $remoteOrgId !== null && $localOrgId !== $remoteOrgId) {
                // Create a new trace context instead of continuing the existing one
                $context = new self();
                $context->traceId = TraceId::generate();
                $context->parentSpanId = null;
                $context->parentSampled = null;

                // Generate a new sample rand since we're starting a new trace
                $context->getMetadata()->setSampleRand(round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(), 6));

                return $context;
            }
        }

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

        // Store the propagated traces sample rate
        if ($samplingContext->has('sample_rate')) {
            $context->getMetadata()->setParentSamplingRate((float) $samplingContext->get('sample_rate'));
        }

        // Store the propagated trace sample rand or generate a new one
        if ($samplingContext->has('sample_rand')) {
            $context->getMetadata()->setSampleRand((float) $samplingContext->get('sample_rand'));
        } else {
            if ($samplingContext->has('sample_rate') && $context->parentSampled !== null) {
                if ($context->parentSampled === true) {
                    // [0, rate)
                    $context->getMetadata()->setSampleRand(round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax() * (float) $samplingContext->get('sample_rate'), 6));
                } else {
                    // [rate, 1)
                    $context->getMetadata()->setSampleRand(round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax() * (1 - (float) $samplingContext->get('sample_rate')) + (float) $samplingContext->get('sample_rate'), 6));
                }
            } elseif ($context->parentSampled !== null) {
                // [0, 1)
                $context->getMetadata()->setSampleRand(round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(), 6));
            }
        }

        return $context;
    }
}
