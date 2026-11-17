<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Tracing\FunctionTracingCallbacks;

/**
 * Registers the Sentry PHP tracer extension callbacks.
 */
final class FunctionTracingIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
        if (!\function_exists('Sentry\\setStartCallback') || !\function_exists('Sentry\\setEndCallback')) {
            return;
        }

        \call_user_func('Sentry\\setStartCallback', [FunctionTracingCallbacks::class, 'handleStart']);
        \call_user_func('Sentry\\setEndCallback', [FunctionTracingCallbacks::class, 'handleEnd']);
    }
}
