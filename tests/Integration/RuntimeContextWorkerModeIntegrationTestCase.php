<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;

abstract class RuntimeContextWorkerModeIntegrationTestCase extends TestCase
{
    /**
     * @var resource|null
     */
    private $serverProcess;

    /**
     * @var resource|null
     */
    private $serverStdout;

    /**
     * @var resource|null
     */
    private $serverStderr;

    /**
     * @var int|null
     */
    private $serverPort;

    final protected function tearDown(): void
    {
        $this->stopServerProcess();

        parent::tearDown();
    }

    final public function testWithContextPreventsScopeBleedingAcrossWorkerRequests(): void
    {
        $this->skipUnlessRuntimeIsAvailable();
        $this->startRuntimeServer();

        try {
            $firstResponse = $this->requestJson('/scope?request=first&leak=first-only');
            $secondResponse = $this->requestJson('/scope?request=second');
        } finally {
            $this->stopServerProcess();
        }

        $this->assertSame('yes', $firstResponse['tags']['baseline'] ?? null);
        $this->assertSame('yes', $secondResponse['tags']['baseline'] ?? null);

        $this->assertSame('first', $firstResponse['tags']['request'] ?? null);
        $this->assertSame('second', $secondResponse['tags']['request'] ?? null);

        $this->assertSame('first-only', $firstResponse['tags']['leak'] ?? null);
        $this->assertArrayNotHasKey('leak', $secondResponse['tags']);

        $this->assertNotSame($firstResponse['runtime_context_id'], $secondResponse['runtime_context_id']);
        $this->assertNotSame($firstResponse['traceparent'] ?? null, $secondResponse['traceparent'] ?? null);
    }

    abstract protected function skipUnlessRuntimeIsAvailable(): void;

    abstract protected function startRuntimeServer(): void;

    final protected function reserveServerPort(): int
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errorMessage);

        if ($server === false) {
            throw new \RuntimeException(\sprintf('Failed allocating a test port: %s', $errorMessage));
        }

        $address = stream_socket_get_name($server, false);
        fclose($server);

        if (!\is_string($address)) {
            throw new \RuntimeException('Could not determine allocated test port.');
        }

        $parts = explode(':', $address);
        $port = (int) array_pop($parts);

        if ($port <= 0) {
            throw new \RuntimeException(\sprintf('Invalid allocated test port from address "%s".', $address));
        }

        return $port;
    }

    final protected function setServerPort(int $serverPort): void
    {
        $this->serverPort = $serverPort;
    }

    final protected function startServerProcess(string $command, string $workingDirectory): void
    {
        if ($this->serverProcess !== null) {
            throw new \RuntimeException('Server process is already running.');
        }

        $pipes = [];
        $this->serverProcess = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory
        );

        if (!\is_resource($this->serverProcess)) {
            throw new \RuntimeException(\sprintf('Unable to start server process with command: %s', $command));
        }

        $this->serverStdout = $pipes[1];
        $this->serverStderr = $pipes[2];

        stream_set_blocking($this->serverStdout, false);
        stream_set_blocking($this->serverStderr, false);
    }

    final protected function waitUntilServerIsReady(string $path = '/ping', int $attempts = 200, int $sleepMicros = 50000): void
    {
        $context = stream_context_create(['http' => ['timeout' => 1]]);
        $url = \sprintf('http://127.0.0.1:%d%s', $this->getServerPort(), $path);

        for ($i = 0; $i < $attempts; ++$i) {
            $response = @file_get_contents($url, false, $context);

            if ($response === 'pong') {
                return;
            }

            if ($this->serverProcess === null) {
                throw new \RuntimeException('Server process is not running.');
            }

            $status = proc_get_status($this->serverProcess);

            if (!$status['running']) {
                throw new \RuntimeException('Server process exited before becoming ready: ' . $this->collectServerOutput());
            }

            usleep($sleepMicros);
        }

        throw new \RuntimeException('Timed out waiting for server readiness: ' . $this->collectServerOutput());
    }

    /**
     * @return array{runtime_context_id: string, traceparent: string, tags: array<string, string>}
     */
    final protected function requestJson(string $path): array
    {
        $url = \sprintf('http://127.0.0.1:%d%s', $this->getServerPort(), $path);
        $context = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];

        if ($body === false) {
            throw new \RuntimeException(\sprintf('Failed HTTP request to %s.', $url));
        }

        $statusLine = $responseHeaders[0] ?? '';

        if (strpos($statusLine, '200') === false) {
            throw new \RuntimeException(\sprintf('Unexpected HTTP status for %s: %s Body: %s', $url, $statusLine, $body));
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            throw new \RuntimeException(\sprintf('Response body was not valid JSON for %s: %s', $url, $body));
        }

        return $decoded;
    }

    final protected function stopServerProcess(): void
    {
        if ($this->serverProcess === null) {
            return;
        }

        $status = proc_get_status($this->serverProcess);

        if ($status['running']) {
            $this->killProcessTree((int) $status['pid']);
        }

        proc_close($this->serverProcess);

        if (\is_resource($this->serverStdout)) {
            fclose($this->serverStdout);
        }

        if (\is_resource($this->serverStderr)) {
            fclose($this->serverStderr);
        }

        $this->serverProcess = null;
        $this->serverStdout = null;
        $this->serverStderr = null;
        $this->serverPort = null;
    }

    final protected function commandIsAvailable(string $command): bool
    {
        $output = [];
        $exitCode = 1;

        exec($command . ' 2>&1', $output, $exitCode);

        return $exitCode === 0;
    }

    private function getServerPort(): int
    {
        if ($this->serverPort === null) {
            throw new \RuntimeException('Server port has not been set.');
        }

        return $this->serverPort;
    }

    private function collectServerOutput(): string
    {
        $stdout = '';
        $stderr = '';

        if (\is_resource($this->serverStdout)) {
            $stdout = stream_get_contents($this->serverStdout);
        }

        if (\is_resource($this->serverStderr)) {
            $stderr = stream_get_contents($this->serverStderr);
        }

        return trim($stdout . "\n" . $stderr);
    }

    private function killProcessTree(int $pid): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            exec(\sprintf('taskkill /pid %d /f /t', $pid));
        } else {
            exec(\sprintf('pkill -P %d', $pid));
            exec(\sprintf('kill %d', $pid));
        }

        proc_terminate($this->serverProcess, 9);
    }
}
