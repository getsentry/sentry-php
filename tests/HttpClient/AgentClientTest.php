<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

use PHPUnit\Framework\TestCase;
use Sentry\Agent\Transport\AgentClient;
use Sentry\Event;
use Sentry\HttpClient\Request;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;

final class AgentClientTest extends TestCase
{
    use TestAgent;

    protected function tearDown(): void
    {
        if ($this->agentProcess !== null) {
            $this->stopTestAgent();
        }
    }

    public function testClientHandsOffEnvelopeToLocalAgent(): void
    {
        $this->startTestAgent();

        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from agent client test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $client = new AgentClient('127.0.0.1', $this->agentPort);
        $response = $client->sendRequest($request, new Options());

        $this->waitForEnvelopeCount(1);
        $agentOutput = $this->stopTestAgent();

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('', $response->getError());
        $this->assertCount(1, $agentOutput['messages']);
        $this->assertStringContainsString('Hello from agent client test!', $agentOutput['messages'][0]);
        $this->assertStringContainsString('"type":"event"', $agentOutput['messages'][0]);
    }

    public function testClientReturnsAcceptedWhenLocalAgentIsUnavailable(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from unavailable agent test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $client = new AgentClient('127.0.0.1', 65001);

        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $response = $client->sendRequest($request, new Options());
        } finally {
            restore_error_handler();
        }

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('', $response->getError());
    }

    public function testClientReturnsErrorWhenBodyIsEmpty(): void
    {
        $client = new AgentClient();
        $response = $client->sendRequest(new Request(), new Options());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertTrue($response->hasError());
        $this->assertSame('Request body is empty', $response->getError());
    }

    private function createEnvelope(string $dsn, string $message): string
    {
        $options = new Options(['dsn' => $dsn]);

        $event = Event::createEvent();
        $event->setMessage($message);

        $serializer = new PayloadSerializer($options);

        return $serializer->serialize($event);
    }
}
