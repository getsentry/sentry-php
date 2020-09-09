<?php

declare(strict_types=1);

namespace Sentry\Tracing;

interface TracesSamplerInterface
{
    /**
     * Should return the sample rate for the given SamplingContext.
     */
    public function sample(SamplingContext $samplingContext): float;
}
