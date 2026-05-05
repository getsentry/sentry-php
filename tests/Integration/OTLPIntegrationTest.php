<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use Http\Discovery\ClassDiscovery;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery as HttpClientDiscovery;
use OpenTelemetry\SDK\SdkBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Integration\OTLPIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tests\Fixtures\OpenTelemetry\StubOtelHttpClient;
use Sentry\Tests\Fixtures\OpenTelemetry\TestClientDiscoverer;
use Sentry\Tests\Fixtures\OpenTelemetry\TestDiscoveryStrategy;
use Sentry\Tests\StubLogger;

final class OTLPIntegrationTest extends TestCase
{
    /**
     * @var string[]|null
     */
    private $discoveryStrategies;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(Context::class) && class_exists(ContextStorage::class)) {
            Context::setStorage(new ContextStorage());
        }

        if (class_exists(ClassDiscovery::class)) {
            $strategies = ClassDiscovery::getStrategies();

            if ($strategies instanceof \Traversable) {
                $this->discoveryStrategies = iterator_to_array($strategies);
            } else {
                $this->discoveryStrategies = $strategies;
            }
        }

        if (class_exists(StubOtelHttpClient::class, false)) {
            StubOtelHttpClient::reset();
        }

        StubLogger::$logs = [];
    }

    protected function tearDown(): void
    {
        if (class_exists(Context::class) && class_exists(ContextStorage::class)) {
            Context::setStorage(new ContextStorage());
        }

        if ($this->discoveryStrategies !== null && class_exists(ClassDiscovery::class)) {
            ClassDiscovery::setStrategies($this->discoveryStrategies);
        }

        if (class_exists(HttpClientDiscovery::class) && method_exists(HttpClientDiscovery::class, 'reset')) {
            HttpClientDiscovery::reset();
        }

        if (class_exists(StubOtelHttpClient::class, false)) {
            StubOtelHttpClient::reset();
        }

        parent::tearDown();
    }

    public function testSetupOnceLogsAndSkipsWhenSentryTracingIsEnabled(): void
    {
        $integration = new OTLPIntegration(false);
        $integration->setOptions(new Options([
            'logger' => StubLogger::getInstance(),
            'traces_sample_rate' => 1.0,
        ]));

        $integration->setupOnce();

        $this->assertNull(Scope::getExternalPropagationContext());
        $this->assertCount(1, StubLogger::$logs);
        $this->assertSame('debug', StubLogger::$logs[0]['level']);
        $this->assertStringContainsString('Skipping OTLPIntegration because Sentry tracing is enabled.', StubLogger::$logs[0]['message']);
    }

    public function testSetupOnceRegistersExternalPropagationContext(): void
    {
        $this->requireOpenTelemetry();

        $integration = new OTLPIntegration(false);
        $integration->setOptions(new Options([
            'dsn' => null,
        ]));
        $integration->setupOnce();

        $otelScope = $this->activateOpenTelemetrySpan();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(OTLPIntegration::class)
            ->willReturn($integration);

        try {
            SentrySdk::setCurrentHub(new Hub($client));

            $this->assertSame([
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ], Scope::getExternalPropagationContext());
        } finally {
            $otelScope->detach();
        }
    }

    public function testExternalPropagationContextIsIgnoredWhenCurrentClientDoesNotHaveIntegration(): void
    {
        $this->requireOpenTelemetry();

        $integration = new OTLPIntegration(false);
        $integration->setOptions(new Options([
            'dsn' => null,
        ]));
        $integration->setupOnce();

        $otelScope = $this->activateOpenTelemetrySpan();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(OTLPIntegration::class)
            ->willReturn(null);

        try {
            SentrySdk::setCurrentHub(new Hub($client));

            $this->assertNull(Scope::getExternalPropagationContext());
        } finally {
            $otelScope->detach();
        }
    }

    public function testSetupOnceCreatesTracerProviderWhenMissing(): void
    {
        $this->requireOpenTelemetry();
        $this->useCapturingHttpClient();

        $integration = new OTLPIntegration(true);
        $integration->setOptions(new Options([
            'dsn' => 'https://public@example.com/1',
        ]));
        $integration->setupOnce();

        $tracerProvider = Globals::tracerProvider();
        $this->assertSame($tracerProvider, Globals::tracerProvider());
        $this->assertInstanceOf(TracerProvider::class, $tracerProvider);

        $this->exportSpan($tracerProvider);

        $this->assertCount(1, StubOtelHttpClient::$requests);
        $this->assertSame('https://example.com/api/1/integration/otlp/v1/traces/', (string) StubOtelHttpClient::$requests[0]->getUri());
        $this->assertStringContainsString('sentry_key=public', StubOtelHttpClient::$requests[0]->getHeaderLine('X-Sentry-Auth'));
    }

    public function testSetupOnceLogsAndSkipsWhenExistingTracerProviderCannotBeModified(): void
    {
        $this->requireOpenTelemetry();
        $this->useCapturingHttpClient();

        $existingTracerProvider = new TracerProvider();
        (new SdkBuilder())
            ->setTracerProvider($existingTracerProvider)
            ->buildAndRegisterGlobal();

        $integration = new OTLPIntegration(true);
        $integration->setOptions(new Options([
            'logger' => StubLogger::getInstance(),
            'dsn' => 'https://public@example.com/1',
        ]));
        $integration->setupOnce();

        $tracerProvider = Globals::tracerProvider();
        $this->assertInstanceOf(TracerProvider::class, $tracerProvider);
        $this->assertSame($existingTracerProvider, $tracerProvider);

        $this->exportSpan($tracerProvider);

        $this->assertCount(0, StubOtelHttpClient::$requests);
        $this->assertCount(1, StubLogger::$logs);
        $this->assertStringContainsString('existing OpenTelemetry tracer provider cannot be modified after construction', StubLogger::$logs[0]['message']);
    }

    public function testSetupOnceUsesCollectorUrlWithoutSentryAuthHeader(): void
    {
        $this->requireOpenTelemetry();
        $this->useCapturingHttpClient();

        $integration = new OTLPIntegration(true, 'http://collector:4318/v1/traces');
        $integration->setOptions(new Options([
            'dsn' => 'https://public@example.com/1',
        ]));
        $integration->setupOnce();

        $tracerProvider = Globals::tracerProvider();
        $this->assertInstanceOf(TracerProvider::class, $tracerProvider);

        $this->exportSpan($tracerProvider);

        $this->assertCount(1, StubOtelHttpClient::$requests);
        $this->assertSame('http://collector:4318/v1/traces', (string) StubOtelHttpClient::$requests[0]->getUri());
        $this->assertSame('', StubOtelHttpClient::$requests[0]->getHeaderLine('X-Sentry-Auth'));
    }

    public function testSetupOnceLogsAndSkipsExporterSetupWhenEndpointCannotBeResolved(): void
    {
        $this->requireOpenTelemetry();

        $integration = new OTLPIntegration(true);
        $integration->setOptions(new Options([
            'dsn' => null,
            'logger' => StubLogger::getInstance(),
        ]));

        $integration->setupOnce();

        $this->assertNotInstanceOf(TracerProvider::class, Globals::tracerProvider());
        $this->assertCount(1, StubLogger::$logs);
        $this->assertSame('debug', StubLogger::$logs[0]['level']);
        $this->assertStringContainsString('Skipping automatic OTLP exporter setup because neither a DSN nor a collector URL is configured.', StubLogger::$logs[0]['message']);
    }

    private function requireOpenTelemetry(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('OpenTelemetry integration tests require PHP 8.1 or newer.');
        }

        foreach ([
            Globals::class,
            Span::class,
            SpanContext::class,
            Context::class,
            ContextStorage::class,
            HttpClientDiscovery::class,
            TracerProvider::class,
            SdkBuilder::class,
            ClassDiscovery::class,
        ] as $className) {
            if (!class_exists($className) && !interface_exists($className)) {
                $this->markTestSkipped(\sprintf('OpenTelemetry integration tests require the optional package that provides "%s".', $className));
            }
        }
    }

    private function activateOpenTelemetrySpan()
    {
        return Span::wrap(SpanContext::create(
            '771a43a4192642f0b136d5159a501700',
            '1234567890abcdef'
        ))->activate();
    }

    private function useCapturingHttpClient(): void
    {
        $this->requireOpenTelemetry();

        if (method_exists(HttpClientDiscovery::class, 'setDiscoverers')) {
            HttpClientDiscovery::setDiscoverers([new TestClientDiscoverer()]);
        } else {
            ClassDiscovery::prependStrategy(TestDiscoveryStrategy::class);
        }

        StubOtelHttpClient::reset();
    }

    private function exportSpan(TracerProvider $tracerProvider): void
    {
        $span = $tracerProvider
            ->getTracer('sentry.tests.otlp')
            ->spanBuilder('otlp-test-span')
            ->startSpan();

        $span->end();
        $tracerProvider->shutdown();
    }
}
