<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Options;
use Sentry\State\PropagationContext;
use Sentry\State\Scope;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;

final class PropagationContextTest extends TestCase
{
    public function testFromDefaults()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testFromHeaders()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testFromEnvironment()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testToTraceparent()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testToBaggage()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testGetTraceContext()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters(string $getterMethod, string $setterMethod, $expectedData): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->$setterMethod($expectedData);

        $this->assertEquals($expectedData, $propagationContext->$getterMethod());
    }

    public function gettersAndSettersDataProvider(): array
    {
        $scope = new Scope();
        $options = new Options([
            'dsn' => 'http://public@example.com/sentry/1',
            'release' => '1.0.0',
            'environment' => 'test',
        ]);

        $dynamicSamplingContext = DynamicSamplingContext::fromOptions($options, $scope);

        return [
            ['getTraceId', 'setTraceId', new TraceId('566e3688a61d4bc888951642d6f14a19')],
            ['getParentSpanId', 'setParentSpanId', new SpanId('566e3688a61d4bc8')],
            ['getSpanId', 'setSpanId', new SpanId('8c2df92a922b4efe')],
            ['getDynamicSamplingContext', 'setDynamicSamplingContext', $dynamicSamplingContext],
        ];
    }
}
