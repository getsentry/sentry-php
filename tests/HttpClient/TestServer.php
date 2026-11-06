<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

/**
 * This is a test server that can be used to test the HttpClient.
 *
 * It spawns the PHP development server, captures the output and returns it to the caller.
 *
 * In your test call `$this->startTestServer()` to start the server and get the address.
 * After you have made your request, call `$this->stopTestServer()` to stop the server and get the output.
 *
 * Thanks to Stripe for the inspiration: https://github.com/stripe/stripe-php/blob/e0a960c8655b21b21c3ba2e5927f432eeda9105f/tests/TestServer.php
 */
trait TestServer
{
    /**
     * @var string the path to the output file
     */
    protected static $serverOutputFile = __DIR__ . '/../testserver/output.json';

    /**
     * @var resource|null the server process handle
     */
    protected $serverProcess;

    /**
     * @var resource|null the server stderr handle
     */
    protected $serverStderr;

    /**
     * @var int the port on which the server is listening, a free port is picked on every start
     */
    protected $serverPort = 45884;

    public function startTestServer(): string
    {
        if ($this->serverProcess !== null) {
            throw new \RuntimeException('There is already a test server instance running.');
        }

        if (file_exists(self::$serverOutputFile)) {
            unlink(self::$serverOutputFile);
        }

        // Use a fresh port for every server so a port that is already taken cannot
        // keep the server from starting
        $this->serverPort = $this->findFreeServerPort();

        $pipes = [];

        $this->serverProcess = proc_open(
            $command = \sprintf(
                'php -S 127.0.0.1:%d %s',
                $this->serverPort,
                realpath(__DIR__ . '/../testserver/index.php')
            ),
            [2 => ['pipe', 'w']],
            $pipes
        );

        $this->serverStderr = $pipes[2];

        $pid = proc_get_status($this->serverProcess)['pid'];

        if (!\is_resource($this->serverProcess)) {
            throw new \RuntimeException("Error starting test server on pid {$pid}, command failed: {$command}");
        }

        $address = "127.0.0.1:{$this->serverPort}";

        $streamContext = stream_context_create(['http' => ['timeout' => 1]]);

        // Wait for the server to be ready to answer HTTP requests
        $startTime = microtime(true);
        $timeout = 5; // 5 seconds timeout

        while (true) {
            $response = @file_get_contents("http://{$address}/ping", false, $streamContext);

            if ($response === 'pong') {
                break;
            }

            if (!proc_get_status($this->serverProcess)['running']) {
                $this->abortTestServerStart("Error starting test server on pid {$pid}, command failed: {$command}");
            }

            if (microtime(true) - $startTime > $timeout) {
                $this->abortTestServerStart("Timeout waiting for test server to start on {$address}");
            }

            usleep(10000);
        }

        return $address;
    }

    /**
     * Stop the test server and return the output from the server.
     *
     * @return array{
     *     body: string,
     *     status: int,
     *     server: array<string, string>,
     *     headers: array<string, string>,
     *     compressed: bool,
     * }
     */
    public function stopTestServer(): array
    {
        if (!$this->serverProcess) {
            throw new \RuntimeException('There is no test server instance running.');
        }

        for ($i = 0; $i < 20; ++$i) {
            $status = proc_get_status($this->serverProcess);

            if (!$status['running']) {
                break;
            }

            $this->killServerProcess($status['pid']);

            usleep(10000);
        }

        if ($status['running']) {
            throw new \RuntimeException('Could not kill test server');
        }

        if (!file_exists(self::$serverOutputFile)) {
            stream_set_blocking($this->serverStderr, false);
            $stderrOutput = stream_get_contents($this->serverStderr);

            echo $stderrOutput . \PHP_EOL;

            throw new \RuntimeException('Test server did not write output file');
        }

        proc_close($this->serverProcess);

        $this->serverProcess = null;
        $this->serverStderr = null;

        return json_decode(file_get_contents(self::$serverOutputFile), true);
    }

    /**
     * Stops a test server that failed to start and throws, so that the caller
     * does not try to stop it again.
     *
     * @return never
     */
    private function abortTestServerStart(string $message): void
    {
        $status = proc_get_status($this->serverProcess);

        if ($status['running']) {
            $this->killServerProcess($status['pid']);
        }

        stream_set_blocking($this->serverStderr, false);
        $stderrOutput = (string) stream_get_contents($this->serverStderr);

        proc_close($this->serverProcess);

        $this->serverProcess = null;
        $this->serverStderr = null;

        throw new \RuntimeException(trim($message . \PHP_EOL . $stderrOutput));
    }

    private function findFreeServerPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNo, $errorMessage);

        if ($socket === false) {
            throw new \RuntimeException(\sprintf('Failed to find a free port for the test server: [%d] %s', $errorNo, $errorMessage));
        }

        $address = (string) stream_socket_get_name($socket, false);

        fclose($socket);

        return (int) substr($address, (int) strrpos($address, ':') + 1);
    }

    private function killServerProcess(int $pid): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            exec("taskkill /pid {$pid} /f /t");
        } else {
            // Kills any child processes -- the php test server appears to start up a child.
            exec("pkill -P {$pid}");

            // Kill the parent process.
            exec("kill {$pid}");
        }

        proc_terminate($this->serverProcess, 9);
    }
}
