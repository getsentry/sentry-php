<?php


namespace Sentry\Tracing;

interface TracesSamplerInterface
{
    /**
     * Should return the sample rate for the given SamplingContext.
     *
     * @param SamplingContext $samplingContext
     * @return float
     */
    public function sample(SamplingContext $samplingContext): float;
}
