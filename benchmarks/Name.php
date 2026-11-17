<?php

declare(strict_types=1);

namespace Sentry\Benchmarks;

/**
 * The name of a benchmark in the pull request comment, instead of its class and method.
 * Tally itself does not know it and keeps reporting the case ID.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Name
{
    public function __construct(public readonly string $name)
    {
    }
}
