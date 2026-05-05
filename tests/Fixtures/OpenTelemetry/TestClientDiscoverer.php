<?php

declare(strict_types=1);

namespace Sentry\Tests\Fixtures\OpenTelemetry;

final class TestClientDiscoverer
{
    public function available(): bool
    {
        return true;
    }

    /**
     * @param mixed $options
     */
    public function create($options)
    {
        return new StubOtelHttpClient();
    }
}
