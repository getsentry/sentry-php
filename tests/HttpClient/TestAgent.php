<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

/**
 * This is a test agent that can be used to test the AgentClient.
 *
 * It spawns a tiny TCP server that implements the same 4-byte big-endian
 * length prefix protocol as the real agent and captures received envelopes.
 *
 * In your test call `$this->startTestAgent()` to start the agent.
 * After you are done, call `$this->stopTestAgent()` to stop the agent and get
 * the captured envelopes.
 */
trait TestAgent
{
    /**
     * @var resource|null the agent process handle
     */
    protected $agentProcess;

    /**
     * @var resource|null the agent stderr handle
     */
    protected $agentStderr;

    /**
     * @var string|null the path to the output file
     */
    protected $agentOutputFile;

    /**
     * @var int the port on which the agent is listening, this default value was randomly chosen
     */
    protected $agentPort = 45848;

    /**
     * Start the test agent.
     *
     * @return string the address the agent is listening on
     */
    public function startTestAgent(): string
    {
        if ($this->agentProcess !== null) {
            throw new \RuntimeException('There is already a test agent instance running.');
        }

        $outputFile = tempnam(sys_get_temp_dir(), 'sentry-agent-client-output-');

        if ($outputFile === false) {
            throw new \RuntimeException('Failed to create the output file for the test agent.');
        }

        $this->agentOutputFile = $outputFile;

        $pipes = [];

        $this->agentProcess = proc_open(
            $command = \sprintf(
                'php %s %d %s',
                escapeshellarg((string) realpath(__DIR__ . '/agent-server.php')),
                $this->agentPort,
                escapeshellarg($this->agentOutputFile)
            ),
            [
                0 => ['pipe', 'r'], // stdin
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ],
            $pipes
        );

        $this->agentStderr = $pipes[2];

        $pid = proc_get_status($this->agentProcess)['pid'];

        if (!\is_resource($this->agentProcess)) {
            throw new \RuntimeException("Error starting test agent on pid {$pid}, command failed: {$command}");
        }

        $address = "127.0.0.1:{$this->agentPort}";

        // Wait for the agent to be ready to accept connections
        $startTime = microtime(true);
        $timeout = 5; // 5 seconds timeout

        while (true) {
            $socket = @stream_socket_client("tcp://{$address}", $errno, $errstr, 1);

            if ($socket !== false) {
                fclose($socket);
                break;
            }

            if (microtime(true) - $startTime > $timeout) {
                $this->stopTestAgent();
                throw new \RuntimeException("Timeout waiting for test agent to start on {$address}");
            }

            usleep(10000);
        }

        // Ensure the process is still running
        if (!proc_get_status($this->agentProcess)['running']) {
            throw new \RuntimeException("Error starting test agent on pid {$pid}, command failed: {$command}");
        }

        return $address;
    }

    /**
     * Wait for the test agent to receive the expected number of envelopes.
     *
     * @return array{
     *     messages: string[],
     *     connections: int,
     * }
     */
    public function waitForEnvelopeCount(int $expectedCount, float $timeout = 5.0): array
    {
        if ($this->agentProcess === null) {
            throw new \RuntimeException('There is no test agent instance running.');
        }

        $startTime = microtime(true);

        while (true) {
            $output = $this->readAgentOutput();

            if (\count($output['messages']) >= $expectedCount) {
                return $output;
            }

            if (microtime(true) - $startTime > $timeout) {
                throw new \RuntimeException(
                    \sprintf(
                        'Timeout waiting for %d envelope(s), got %d.',
                        $expectedCount,
                        \count($output['messages'])
                    )
                );
            }

            usleep(10000);
        }
    }

    /**
     * Stop the test agent and return the captured envelopes.
     *
     * @return array{
     *     messages: string[],
     *     connections: int,
     * }
     */
    public function stopTestAgent(): array
    {
        if (!$this->agentProcess) {
            throw new \RuntimeException('There is no test agent instance running.');
        }

        $output = $this->readAgentOutput();

        for ($i = 0; $i < 20; ++$i) {
            $status = proc_get_status($this->agentProcess);

            if (!$status['running']) {
                break;
            }

            $this->killAgentProcess($status['pid']);

            usleep(10000);
        }

        if ($status['running']) {
            throw new \RuntimeException('Could not kill test agent');
        }

        proc_close($this->agentProcess);

        if ($this->agentOutputFile !== null && file_exists($this->agentOutputFile)) {
            unlink($this->agentOutputFile);
        }

        $this->agentProcess = null;
        $this->agentStderr = null;
        $this->agentOutputFile = null;

        return $output;
    }

    /**
     * @return array{
     *     messages: string[],
     *     connections: int,
     * }
     */
    private function readAgentOutput(): array
    {
        if ($this->agentOutputFile === null || !file_exists($this->agentOutputFile)) {
            return ['messages' => [], 'connections' => 0];
        }

        $output = file_get_contents($this->agentOutputFile);

        if ($output === false || $output === '') {
            return ['messages' => [], 'connections' => 0];
        }

        $decoded = json_decode($output, true);

        if (!\is_array($decoded)) {
            return ['messages' => [], 'connections' => 0];
        }

        return [
            'messages' => $decoded['messages'] ?? [],
            'connections' => $decoded['connections'] ?? 0,
        ];
    }

    private function killAgentProcess(int $pid): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            exec("taskkill /pid {$pid} /f /t");
        } else {
            // Kills any child processes
            exec("pkill -P {$pid}");

            // Kill the parent process
            exec("kill {$pid}");
        }

        proc_terminate($this->agentProcess, 9);
    }
}
