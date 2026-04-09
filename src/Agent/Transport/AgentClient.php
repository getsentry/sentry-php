<?php

declare(strict_types=1);

namespace Sentry\Agent\Transport;

use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;

class AgentClient implements HttpClientInterface
{
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

    public function __construct(string $host = '127.0.0.1', int $port = 5148)
    {
        $this->host = $host;
        $this->port = $port;
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

        // We set the timeout to 10ms to avoid blocking the request for too long if the agent is not running
        // @TODO: 10ms should be low enough? Do we want to go lower and/or make this configurable? Only applies to initial connection.
        $socket = fsockopen($this->host, $this->port, $errorNo, $errorMsg, 0.01);

        // @TODO: Error handling? See $errorNo and $errorMsg
        if ($socket === false) {
            return false;
        }

        // @TODO: Set a timeout for the socket to prevent blocking (?) if the socket connection stops working after the connection (e.g. the agent is stopped) if needed
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

    private function send(string $message): void
    {
        if (!$this->connect()) {
            return;
        }

        // @TODO: Make sure we don't send more than 2^32 - 1 bytes
        $contentLength = pack('N', \strlen($message) + 4);

        // @TODO: Error handling?
        fwrite($this->socket, $contentLength . $message);
    }

    public function sendRequest(Request $request, Options $options): Response
    {
        $body = $request->getStringBody();

        if (empty($body)) {
            return new Response(400, [], 'Request body is empty');
        }

        $this->send($body);

        // Since we are sending async there is no feedback so we always return an empty response
        return new Response(202, [], '');
    }
}
