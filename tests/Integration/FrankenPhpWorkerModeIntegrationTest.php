<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

final class FrankenPhpWorkerModeIntegrationTest extends RuntimeContextWorkerModeIntegrationTestCase
{
    protected function skipUnlessRuntimeIsAvailable(): void
    {
        if (!$this->commandIsAvailable('frankenphp version')) {
            $this->markTestSkipped('FrankenPHP is not available on PATH.');
        }
    }

    protected function startRuntimeServer(): void
    {
        $serverPort = $this->reserveServerPort();
        $this->setServerPort($serverPort);

        $fixtureRoot = realpath(__DIR__ . '/../Fixtures/runtime/frankenphp');

        if ($fixtureRoot === false) {
            throw new \RuntimeException('Could not resolve FrankenPHP fixture directory.');
        }

        $command = \sprintf(
            'frankenphp php-server --root . --worker index.php --listen 127.0.0.1:%d',
            $serverPort
        );

        $this->startServerProcess($command, $fixtureRoot);
        $this->waitUntilServerIsReady();
    }
}
