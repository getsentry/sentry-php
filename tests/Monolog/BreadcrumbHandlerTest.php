<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Monolog\BreadcrumbHandler;
use Sentry\State\HubInterface;

final class BreadcrumbHandlerTest extends TestCase
{
    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle($record, Breadcrumb $expectedBreadcrumb): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('addBreadcrumb')
            ->with($this->callback(function (Breadcrumb $breadcrumb) use ($expectedBreadcrumb, $record): bool {
                $this->assertSame($expectedBreadcrumb->getMessage(), $breadcrumb->getMessage());
                $this->assertSame($expectedBreadcrumb->getLevel(), $breadcrumb->getLevel());
                $this->assertSame($expectedBreadcrumb->getType(), $breadcrumb->getType());
                $this->assertEquals($record['datetime']->getTimestamp(), $breadcrumb->getTimestamp());
                $this->assertSame($expectedBreadcrumb->getCategory(), $breadcrumb->getCategory());
                $this->assertEquals($expectedBreadcrumb->getMetadata(), $breadcrumb->getMetadata());

                return true;
            }));

        $handler = new BreadcrumbHandler($hub);
        $handler->handle($record);
    }

    /**
     * @return iterable<LogRecord|array{array<string, mixed>, Breadcrumb}>
     */
    public static function handleDataProvider(): iterable
    {
        $defaultBreadcrumb = new Breadcrumb(
            Breadcrumb::LEVEL_DEBUG,
            Breadcrumb::TYPE_DEFAULT,
            'channel.foo',
            'foo bar',
            []
        );

        $levelsToBeTested = [
            Logger::DEBUG => Breadcrumb::LEVEL_DEBUG,
            Logger::INFO => Breadcrumb::LEVEL_INFO,
            Logger::NOTICE => Breadcrumb::LEVEL_INFO,
            Logger::WARNING => Breadcrumb::LEVEL_WARNING,
        ];

        foreach ($levelsToBeTested as $loggerLevel => $breadcrumbLevel) {
            yield 'with level ' . Logger::getLevelName($loggerLevel) => [
                RecordFactory::create('foo bar', $loggerLevel, 'channel.foo', [], []),
                $defaultBreadcrumb->withLevel($breadcrumbLevel),
            ];
        }

        yield 'with level ERROR' => [
            RecordFactory::create('foo bar', Logger::ERROR, 'channel.foo', [], []),
            $defaultBreadcrumb->withLevel(Breadcrumb::LEVEL_ERROR)
                ->withType(Breadcrumb::TYPE_ERROR),
        ];

        yield 'with level ALERT' => [
            RecordFactory::create('foo bar', Logger::ALERT, 'channel.foo', [], []),
            $defaultBreadcrumb->withLevel(Breadcrumb::LEVEL_FATAL)
                ->withType(Breadcrumb::TYPE_ERROR),
        ];

        yield 'with context' => [
            RecordFactory::create('foo bar', Logger::DEBUG, 'channel.foo', ['context' => ['foo' => 'bar']], []),
            $defaultBreadcrumb->withMetadata('context', ['foo' => 'bar']),
        ];

        yield 'with extra' => [
            RecordFactory::create('foo bar', Logger::DEBUG, 'channel.foo', [], ['extra' => ['foo' => 'bar']]),
            $defaultBreadcrumb->withMetadata('extra', ['foo' => 'bar']),
        ];
    }
}
