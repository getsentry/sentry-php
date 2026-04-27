<?php

declare(strict_types=1);

namespace Sentry\Agent\Transport;

use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;

class AgentClient implements HttpClientInterface
{
    private const SOCKET_TIMEOUT_SECONDS = 0.01;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var resource|null
     */
    private $socket;

    /**
     * @var HttpClientInterface|null
     */
    private $fallbackClient;

    /**
     * @var (callable(): HttpClientInterface)|null
     */
    private $fallbackClientFactory;

    /**
     * @var string|null
     */
    private $fallbackClientError;

    /**
     * @var string
     */
    private $lastSendError = '';

    /**
     * @phpstan-param (callable(): HttpClientInterface)|null $fallbackClientFactory
     */
    public function __construct(string $host = '127.0.0.1', int $port = 5148, ?callable $fallbackClientFactory = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->fallbackClientFactory = $fallbackClientFactory;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @phpstan-assert-if-true resource $this->socket
     */
    private function connect(): bool
    {
        if ($this->socket !== null) {
            return true;
        }

        // 10ms connect timeout to avoid blocking the request if the agent is not running
        $errorNo = 0;
        $errorMsg = '';
        $socket = @fsockopen($this->host, $this->port, $errorNo, $errorMsg, self::SOCKET_TIMEOUT_SECONDS);

        if ($socket === false) {
            $this->lastSendError = \sprintf(
                'Failed to connect to the local Sentry agent at %s:%d. [%d] %s',
                $this->host,
                $this->port,
                $errorNo,
                $errorMsg
            );

            return false;
        }

        // Use non-blocking writes with stream_select() so a hung agent cannot block the caller indefinitely.
        stream_set_blocking($socket, false);

        $this->socket = $socket;

        return true;
    }

    private function disconnect(): void
    {
        if ($this->socket === null) {
            return;
        }

        fclose($this->socket);

        $this->socket = null;
    }

    private function send(string $message): bool
    {
        $this->lastSendError = '';

        $payload = pack('N', \strlen($message) + 4) . $message;

        // Attempt to send the payload, retrying once on write failure to handle
        // stale sockets (e.g. agent restarts in long-running workers).
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            if (!$this->connect()) {
                return false;
            }

            if ($this->writePayload($payload)) {
                return true;
            }

            $this->disconnect();
        }

        $this->lastSendError = \sprintf(
            'Failed to write envelope to the local Sentry agent at %s:%d.',
            $this->host,
            $this->port
        );

        return false;
    }

    private function writePayload(string $payload): bool
    {
        if ($this->socket === null) {
            return false;
        }

        $socket = $this->socket;
        $payloadLength = \strlen($payload);
        $totalWrittenBytes = 0;
        $writeDeadline = microtime(true) + self::SOCKET_TIMEOUT_SECONDS;

        while ($totalWrittenBytes < $payloadLength) {
            if (!$this->waitUntilSocketIsWritable($socket, $writeDeadline)) {
                return false;
            }

            $bytesWritten = @fwrite($socket, (string) substr($payload, $totalWrittenBytes));

            if ($bytesWritten === false) {
                return false;
            }

            $totalWrittenBytes += $bytesWritten;
        }

        return true;
    }

    /**
     * @param resource $socket
     */
    private function waitUntilSocketIsWritable($socket, float $deadline): bool
    {
        $remainingSeconds = $deadline - microtime(true);

        if ($remainingSeconds <= 0) {
            return false;
        }

        $readSockets = null;
        $writeSockets = [$socket];
        $exceptSockets = null;
        $selectedSockets = @stream_select(
            $readSockets,
            $writeSockets,
            $exceptSockets,
            0,
            (int) ceil($remainingSeconds * 1000000)
        );

        return $selectedSockets !== false && $selectedSockets > 0;
    }

    private function getFallbackClient(): ?HttpClientInterface
    {
        if ($this->fallbackClient !== null) {
            return $this->fallbackClient;
        }

        if ($this->fallbackClientFactory === null) {
            return null;
        }

        try {
            $fallbackClient = ($this->fallbackClientFactory)();
        } catch (\Throwable $exception) {
            $this->fallbackClientFactory = null;
            $this->fallbackClientError = \sprintf(
                'Failed to initialize fallback HTTP client. Reason: "%s". Fallback delivery has been disabled.',
                $exception->getMessage()
            );

            return null;
        }

        if (!$fallbackClient instanceof HttpClientInterface) {
            $this->fallbackClientFactory = null;
            $this->fallbackClientError = 'The fallback client factory did not return an instance of HttpClientInterface. Fallback delivery has been disabled.';

            return null;
        }

        $this->fallbackClient = $fallbackClient;

        return $this->fallbackClient;
    }

    public function sendRequest(Request $request, Options $options): Response
    {
        $body = $request->getStringBody();

        if (empty($body)) {
            return new Response(400, [], 'Request body is empty');
        }

        if ($this->send($body)) {
            // Since we are sending async there is no feedback so we always return an empty response
            return new Response(202, [], '');
        }

        $logContext = [
            'agent_host' => $this->host,
            'agent_port' => $this->port,
        ];

        if ($this->lastSendError !== '') {
            $logContext['error'] = $this->lastSendError;
        }

        $options->getLoggerOrNullLogger()->debug('Failed to hand off envelope to local Sentry agent.', $logContext);

        $fallbackClient = $this->getFallbackClient();
        if ($fallbackClient !== null) {
            $options->getLoggerOrNullLogger()->debug('Using fallback HTTP client because local Sentry agent handoff failed.', $logContext);

            try {
                return $fallbackClient->sendRequest($request, $options);
            } catch (\Throwable $exception) {
                $options->getLoggerOrNullLogger()->debug(
                    'Fallback HTTP client failed while sending envelope.',
                    array_merge($logContext, ['exception' => $exception])
                );

                return new Response(502, [], \sprintf(
                    'Failed to send envelope using fallback HTTP client. Reason: "%s".',
                    $exception->getMessage()
                ));
            }
        }

        if ($this->fallbackClientError !== null) {
            $options->getLoggerOrNullLogger()->debug($this->fallbackClientError, $logContext);
            $this->fallbackClientError = null;
        }

        return new Response(502, [], 'Failed to send envelope to the local Sentry agent and no fallback client is available.');
    }
}
