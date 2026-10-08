<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

use PHPUnit\Framework\TestCase;
use Sentry\Agent\Transport\AgentClient;
use Sentry\Event;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Tests\StubLogger;

final class AgentClientTest extends TestCase
{
    use TestAgent;

    // Reserved address TEST-NET-1, which should not be bound for anything
    private const UNAVAILABLE_AGENT_HOST = '192.0.2.1';
    private const UNAVAILABLE_AGENT_PORT = 5148;

    protected function setUp(): void
    {
        parent::setUp();

        StubLogger::$logs = [];
    }

    protected function tearDown(): void
    {
        if ($this->agentProcess !== null) {
            $this->stopTestAgent();
        }

        StubLogger::$logs = [];
    }

    public function testClientHandsOffEnvelopeToLocalAgent(): void
    {
        $this->startTestAgent();

        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from agent client test!');

        $request = new Request();
        $request->setStringBody($envelope);

        /** @var HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject $fallbackClient */
        $fallbackClient = $this->createMock(HttpClientInterface::class);
        $fallbackClient->expects($this->never())
            ->method('sendRequest');

        $client = new AgentClient('127.0.0.1', $this->agentPort, static function () use ($fallbackClient): HttpClientInterface {
            return $fallbackClient;
        });
        $response = $client->sendRequest($request, new Options());

        $this->waitForEnvelopeCount(1);
        $agentOutput = $this->stopTestAgent();

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('', $response->getError());
        $this->assertCount(1, $agentOutput['messages']);
        $this->assertStringContainsString('Hello from agent client test!', $agentOutput['messages'][0]);
        $this->assertStringContainsString('"type":"event"', $agentOutput['messages'][0]);
    }

    public function testClientReturnsErrorAndLogsDebugWhenLocalAgentIsUnavailableWithoutFallback(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from unavailable agent test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $logger = StubLogger::getInstance();
        $options = new Options(['logger' => $logger]);
        $client = new AgentClient(self::UNAVAILABLE_AGENT_HOST, self::UNAVAILABLE_AGENT_PORT, null);
        $response = $client->sendRequest($request, $options);

        $this->assertSame(502, $response->getStatusCode());
        $this->assertTrue($response->hasError());
        $this->assertSame('Failed to send envelope to the local Sentry agent and no fallback client is available.', $response->getError());
        $this->assertTrue($this->hasLogMessage('Failed to hand off envelope to local Sentry agent.'));
    }

    public function testClientLazilyInitializesFallbackFactoryOnlyWhenNeeded(): void
    {
        $this->startTestAgent();

        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from lazy fallback factory test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $factoryCallCount = 0;

        /** @var HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject $fallbackClient */
        $fallbackClient = $this->createMock(HttpClientInterface::class);
        $fallbackClient->expects($this->never())
            ->method('sendRequest');

        $client = new AgentClient(
            '127.0.0.1',
            $this->agentPort,
            static function () use (&$factoryCallCount, $fallbackClient): HttpClientInterface {
                ++$factoryCallCount;

                return $fallbackClient;
            }
        );
        $response = $client->sendRequest($request, new Options());

        $this->waitForEnvelopeCount(1);
        $this->stopTestAgent();

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('', $response->getError());
        $this->assertSame(0, $factoryCallCount);
    }

    public function testClientUsesFallbackClientWhenLocalAgentIsUnavailable(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from fallback test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $logger = StubLogger::getInstance();
        $options = new Options([
            'dsn' => 'http://public@example.com/1',
            'logger' => $logger,
        ]);

        $fallbackResponse = new Response(200, [], '');

        /** @var HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject $fallbackClient */
        $fallbackClient = $this->createMock(HttpClientInterface::class);
        $fallbackClient->expects($this->once())
            ->method('sendRequest')
            ->with($request, $options)
            ->willReturn($fallbackResponse);

        $client = new AgentClient(self::UNAVAILABLE_AGENT_HOST, self::UNAVAILABLE_AGENT_PORT, static function () use ($fallbackClient): HttpClientInterface {
            return $fallbackClient;
        });
        $response = $client->sendRequest($request, $options);

        $this->assertSame($fallbackResponse, $response);
        $this->assertTrue($this->hasLogMessage('Failed to hand off envelope to local Sentry agent.'));
        $this->assertTrue($this->hasLogMessage('Using fallback HTTP client because local Sentry agent handoff failed.'));
    }

    public function testClientReusesFallbackClientWhenLocalAgentRemainsUnavailable(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from cached fallback test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $options = new Options(['dsn' => 'http://public@example.com/1']);
        $fallbackResponse = new Response(200, [], '');
        $factoryCallCount = 0;

        /** @var HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject $fallbackClient */
        $fallbackClient = $this->createMock(HttpClientInterface::class);
        $fallbackClient->expects($this->exactly(2))
            ->method('sendRequest')
            ->with($request, $options)
            ->willReturn($fallbackResponse);

        $client = new AgentClient(self::UNAVAILABLE_AGENT_HOST, self::UNAVAILABLE_AGENT_PORT, static function () use (&$factoryCallCount, $fallbackClient): HttpClientInterface {
            ++$factoryCallCount;

            return $fallbackClient;
        });

        $firstResponse = $client->sendRequest($request, $options);
        $secondResponse = $client->sendRequest($request, $options);

        $this->assertSame($fallbackResponse, $firstResponse);
        $this->assertSame($fallbackResponse, $secondResponse);
        $this->assertSame(1, $factoryCallCount);
    }

    public function testClientDoesNotThrowWhenFallbackClientThrows(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from throwing fallback client test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $logger = StubLogger::getInstance();
        $options = new Options(['logger' => $logger]);

        /** @var HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject $fallbackClient */
        $fallbackClient = $this->createMock(HttpClientInterface::class);
        $fallbackClient->expects($this->once())
            ->method('sendRequest')
            ->with($request, $options)
            ->willThrowException(new \RuntimeException('fallback boom'));

        $client = new AgentClient(self::UNAVAILABLE_AGENT_HOST, self::UNAVAILABLE_AGENT_PORT, static function () use ($fallbackClient): HttpClientInterface {
            return $fallbackClient;
        });
        $response = $client->sendRequest($request, $options);

        $this->assertSame(502, $response->getStatusCode());
        $this->assertTrue($response->hasError());
        $this->assertSame('Failed to send envelope using fallback HTTP client. Reason: "fallback boom".', $response->getError());
        $this->assertTrue($this->hasLogMessage('Fallback HTTP client failed while sending envelope.'));
    }

    public function testClientReturnsErrorWhenBodyIsEmpty(): void
    {
        $client = new AgentClient();
        $response = $client->sendRequest(new Request(), new Options());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertTrue($response->hasError());
        $this->assertSame('Request body is empty', $response->getError());
    }

    public function testClientDoesNotThrowWhenFallbackFactoryThrows(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from throwing fallback factory test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $logger = StubLogger::getInstance();
        $options = new Options(['logger' => $logger]);

        $client = new AgentClient(
            self::UNAVAILABLE_AGENT_HOST,
            self::UNAVAILABLE_AGENT_PORT,
            static function (): HttpClientInterface {
                throw new \RuntimeException('factory boom');
            }
        );
        $response = $client->sendRequest($request, $options);

        $this->assertSame(502, $response->getStatusCode());
        $this->assertTrue($response->hasError());
        $this->assertTrue($this->hasLogMessageContaining('Failed to initialize fallback HTTP client.'));
    }

    public function testClientLogsFallbackFactoryErrorOnlyOnce(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from repeated throwing fallback factory test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $logger = StubLogger::getInstance();
        $options = new Options(['logger' => $logger]);

        $client = new AgentClient(
            self::UNAVAILABLE_AGENT_HOST,
            self::UNAVAILABLE_AGENT_PORT,
            static function (): HttpClientInterface {
                throw new \RuntimeException('factory boom');
            }
        );

        $client->sendRequest($request, $options);
        $client->sendRequest($request, $options);

        $this->assertSame(1, $this->countLogMessagesContaining('Failed to initialize fallback HTTP client.'));
    }

    public function testClientDoesNotThrowWhenFallbackFactoryReturnsUnexpectedValue(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from invalid fallback factory test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $logger = StubLogger::getInstance();
        $options = new Options(['logger' => $logger]);

        $client = new AgentClient(
            self::UNAVAILABLE_AGENT_HOST,
            self::UNAVAILABLE_AGENT_PORT,
            static function () {
                return new \stdClass();
            }
        );
        $response = $client->sendRequest($request, $options);

        $this->assertSame(502, $response->getStatusCode());
        $this->assertTrue($response->hasError());
        $this->assertTrue($this->hasLogMessage('The fallback client factory did not return an instance of HttpClientInterface. Fallback delivery has been disabled.'));
    }

    private function createEnvelope(string $dsn, string $message): string
    {
        $options = new Options(['dsn' => $dsn]);

        $event = Event::createEvent();
        $event->setMessage($message);

        $serializer = new PayloadSerializer($options);

        return $serializer->serialize($event);
    }

    private function hasLogMessage(string $message): bool
    {
        foreach (StubLogger::$logs as $log) {
            if ($log['message'] === $message) {
                return true;
            }
        }

        return false;
    }

    private function countLogMessagesContaining(string $message): int
    {
        $result = array_filter(StubLogger::$logs, static function (array $log) use ($message): bool {
            return strpos($log['message'], $message) !== false;
        });

        return \count($result);
    }

    private function hasLogMessageContaining(string $message): bool
    {
        return $this->countLogMessagesContaining($message) > 0;
    }
}
