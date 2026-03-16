<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

final class RoadRunnerWorkerModeIntegrationTest extends RuntimeContextWorkerModeIntegrationTestCase
{
    protected function skipUnlessRuntimeIsAvailable(): void
    {
        if (!$this->commandIsAvailable('rr --version')) {
            $this->markTestSkipped('RoadRunner binary is not available on PATH.');
        }

        if (!$this->isRoadRunnerPhpWorkerStackAvailable()) {
            $this->markTestSkipped('RoadRunner worker classes are missing. Install optional dev deps: spiral/roadrunner-worker, spiral/roadrunner-http, nyholm/psr7.');
        }
    }

    protected function startRuntimeServer(): void
    {
        $httpPort = $this->reserveServerPort();
        $rpcPort = $this->reserveServerPort();
        $this->setServerPort($httpPort);

        $fixtureRoot = realpath(__DIR__ . '/../Fixtures/runtime');

        if ($fixtureRoot === false) {
            throw new \RuntimeException('Could not resolve runtime fixture directory.');
        }

        $command = \sprintf(
            'rr serve -c roadrunner.rr.yaml -o http.address=127.0.0.1:%d -o rpc.listen=tcp://127.0.0.1:%d',
            $httpPort,
            $rpcPort
        );

        $this->startServerProcess($command, $fixtureRoot);
        $this->waitUntilServerIsReady();
    }

    private function isRoadRunnerPhpWorkerStackAvailable(): bool
    {
        return class_exists(\Spiral\RoadRunner\Worker::class)
            && class_exists(\Spiral\RoadRunner\Http\PSR7Worker::class)
            && class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class)
            && class_exists(\Nyholm\Psr7\Response::class);
    }
}
