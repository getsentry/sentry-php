<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Context\OsContext;
use Sentry\Context\RuntimeContext;
use Sentry\Event;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Util\PHPVersion;

use function Sentry\withScope;

final class EnvironmentIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(bool $isIntegrationEnabled, ?RuntimeContext $initialRuntimeContext, ?OsContext $initialOsContext, ?RuntimeContext $expectedRuntimeContext, ?OsContext $expectedOsContext): void
    {
        $integration = new EnvironmentIntegration();
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->willReturn($isIntegrationEnabled ? $integration : null);

        SentrySdk::getCurrentHub()->bindClient($client);

        withScope(function (Scope $scope) use ($expectedRuntimeContext, $expectedOsContext, $initialRuntimeContext, $initialOsContext): void {
            $event = Event::createEvent();
            $event->setRuntimeContext($initialRuntimeContext);
            $event->setOsContext($initialOsContext);

            $event = $scope->applyToEvent($event);

            $this->assertNotNull($event);

            $runtimeContext = $event->getRuntimeContext();
            $osContext = $event->getOsContext();

            if ($expectedRuntimeContext === null) {
                $this->assertNull($runtimeContext);
            } else {
                $this->assertSame($expectedRuntimeContext->getName(), $runtimeContext->getName());
                $this->assertSame($expectedRuntimeContext->getSAPI(), $runtimeContext->getSAPI());
                $this->assertSame($expectedRuntimeContext->getVersion(), $runtimeContext->getVersion());
            }

            if ($expectedOsContext === null) {
                $this->assertNull($expectedOsContext);
            } else {
                $this->assertSame($expectedOsContext->getName(), $osContext->getName());
                $this->assertSame($expectedOsContext->getVersion(), $osContext->getVersion());
                $this->assertSame($expectedOsContext->getBuild(), $osContext->getBuild());
                $this->assertSame($expectedOsContext->getKernelVersion(), $osContext->getKernelVersion());
            }
        });
    }

    public static function invokeDataProvider(): iterable
    {
        yield 'Integration disabled => do nothing' => [
            false,
            null,
            null,
            null,
            null,
        ];

        yield 'Integration enabled && event context data not filled => replace whole context data' => [
            true,
            null,
            null,
            new RuntimeContext('php', PHPVersion::parseVersion(), 'cli'),
            new OsContext(php_uname('s'), php_uname('r'), php_uname('v'), php_uname('a')),
        ];

        yield 'Integration enabled && event context data filled => do nothing' => [
            true,
            new RuntimeContext('go', '1.15', 'cli'),
            new OsContext('iOS', '13.5.1', '17F80', 'Darwin Kernel Version 19.5.0: Tue May 26 20:56:31 PDT 2020; root:xnu-6153.122.2~1/RELEASE_ARM64_T8015'),
            new RuntimeContext('go', '1.15', 'cli'),
            new OsContext('iOS', '13.5.1', '17F80', 'Darwin Kernel Version 19.5.0: Tue May 26 20:56:31 PDT 2020; root:xnu-6153.122.2~1/RELEASE_ARM64_T8015'),
        ];

        yield 'Integration enabled && event context data partially filled => fill remaining data' => [
            true,
            new RuntimeContext('php'),
            new OsContext('Linux'),
            new RuntimeContext('php', PHPVersion::parseVersion(), 'cli'),
            new OsContext('Linux', php_uname('r'), php_uname('v'), php_uname('a')),
        ];
    }
}
