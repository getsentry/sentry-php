<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Monolog\BreadcrumbHandler;
use Sentry\State\HubInterface;

final class BreadcrumbHandlerTest extends TestCase
{
    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(array $record, Breadcrumb $expectedBreadcrumb): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('addBreadcrumb')
            ->with($this->callback(function (Breadcrumb $breadcrumb) use ($expectedBreadcrumb): bool {
                $this->assertSame($expectedBreadcrumb->getMessage(), $breadcrumb->getMessage());
                $this->assertSame($expectedBreadcrumb->getLevel(), $breadcrumb->getLevel());
                $this->assertSame($expectedBreadcrumb->getType(), $breadcrumb->getType());
                $this->assertSame($expectedBreadcrumb->getTimestamp(), $breadcrumb->getTimestamp());
                $this->assertSame($expectedBreadcrumb->getCategory(), $breadcrumb->getCategory());
                $this->assertEquals($expectedBreadcrumb->getMetadata(), $breadcrumb->getMetadata());

                return true;
            }));

        $handler = new BreadcrumbHandler($hub);
        $handler->handle($record);
    }

    /**
     * @return iterable<array{array<string, mixed>, Breadcrumb}>
     */
    public function handleDataProvider(): iterable
    {
        $defaultData = [
            'message' => 'foo bar',
            'level' => Logger::DEBUG,
            'level_name' => Logger::getLevelName(Logger::DEBUG),
            'channel' => 'channel.foo',
            'context' => [],
            'extra' => [],
            'datetime' => new \DateTimeImmutable(),
        ];

        $defaultBreadcrumb = new Breadcrumb(
            Breadcrumb::LEVEL_DEBUG,
            Breadcrumb::TYPE_DEFAULT,
            'channel.foo',
            'foo bar',
            [],
            $defaultData['datetime']->getTimestamp()
        );

        $levelsToBeTested = [
            Logger::DEBUG => Breadcrumb::LEVEL_DEBUG,
            Logger::INFO => Breadcrumb::LEVEL_INFO,
            Logger::NOTICE => Breadcrumb::LEVEL_INFO,
            Logger::WARNING => Breadcrumb::LEVEL_WARNING,
        ];

        foreach ($levelsToBeTested as $loggerLevel => $breadcrumbLevel) {
            yield 'with level ' . Logger::getLevelName($loggerLevel) => [
                ['level' => $loggerLevel] + $defaultData,
                $defaultBreadcrumb->withLevel($breadcrumbLevel),
            ];
        }

        yield 'with level ERROR' => [
            ['level' => Logger::ERROR] + $defaultData,
            $defaultBreadcrumb->withLevel(Breadcrumb::LEVEL_ERROR)
                ->withType(Breadcrumb::TYPE_ERROR),
        ];

        yield 'with level ALERT' => [
            ['level' => Logger::ALERT] + $defaultData,
            $defaultBreadcrumb->withLevel(Breadcrumb::LEVEL_FATAL)
                ->withType(Breadcrumb::TYPE_ERROR),
        ];

        yield 'with context' => [
            ['context' => ['foo' => 'bar']] + $defaultData,
            $defaultBreadcrumb->withMetadata('foo', 'bar'),
        ];

        yield 'with extra' => [
            ['extra' => ['foo' => 'bar']] + $defaultData,
            $defaultBreadcrumb->withMetadata('foo', 'bar'),
        ];

        yield 'with context + extra' => [
            [
                'context' => ['foo' => 'bar', 'context' => 'baz'],
                'extra' => ['foo' => 'baz', 'extra' => 'baz'],
            ] + $defaultData,
            $defaultBreadcrumb->withMetadata('foo', 'bar')
                ->withMetadata('context', 'baz')
                ->withMetadata('extra', 'baz'),
        ];
    }
}
