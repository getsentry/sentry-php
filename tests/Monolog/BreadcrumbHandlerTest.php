<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Monolog\BreadcrumbHandler;
use Sentry\Options;
use Sentry\SentrySdk;

final class BreadcrumbHandlerTest extends TestCase
{
    /**
     * @dataProvider handleDataProvider
     *
     * @param LogRecord|array<string, mixed> $record
     */
    public function testHandle($record, Breadcrumb $expectedBreadcrumb): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn(new Options(['default_integrations' => false]));

        SentrySdk::init($client);
        $handler = new BreadcrumbHandler();
        $handler->handle($record);

        $event = SentrySdk::getIsolationScope()->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $breadcrumbs = $event->getBreadcrumbs();
        $this->assertCount(1, $breadcrumbs);

        $breadcrumb = $breadcrumbs[0];
        $this->assertSame($expectedBreadcrumb->getMessage(), $breadcrumb->getMessage());
        $this->assertSame($expectedBreadcrumb->getLevel(), $breadcrumb->getLevel());
        $this->assertSame($expectedBreadcrumb->getType(), $breadcrumb->getType());
        $this->assertEquals($expectedBreadcrumb->getTimestamp(), $breadcrumb->getTimestamp());
        $this->assertSame($expectedBreadcrumb->getCategory(), $breadcrumb->getCategory());
        $this->assertEquals($expectedBreadcrumb->getMetadata(), $breadcrumb->getMetadata());
    }

    /**
     * @return iterable<array{LogRecord|array<string, mixed>, Breadcrumb}>
     */
    public static function handleDataProvider(): iterable
    {
        $now = new \DateTimeImmutable();

        $defaultBreadcrumb = new Breadcrumb(
            Breadcrumb::LEVEL_DEBUG,
            Breadcrumb::TYPE_DEFAULT,
            'channel.foo',
            'foo bar',
            [],
            (float) $now->format('U.u')
        );

        $levelsToBeTested = [
            Logger::DEBUG => Breadcrumb::LEVEL_DEBUG,
            Logger::INFO => Breadcrumb::LEVEL_INFO,
            Logger::NOTICE => Breadcrumb::LEVEL_INFO,
            Logger::WARNING => Breadcrumb::LEVEL_WARNING,
        ];

        foreach ($levelsToBeTested as $loggerLevel => $breadcrumbLevel) {
            yield 'with level ' . Logger::getLevelName($loggerLevel) => [
                RecordFactory::create('foo bar', $loggerLevel, 'channel.foo', [], [], $now),
                $defaultBreadcrumb->withLevel($breadcrumbLevel),
            ];
        }

        yield 'with level ERROR' => [
            RecordFactory::create('foo bar', Logger::ERROR, 'channel.foo', [], [], $now),
            $defaultBreadcrumb->withLevel(Breadcrumb::LEVEL_ERROR)
                ->withType(Breadcrumb::TYPE_ERROR),
        ];

        yield 'with level ALERT' => [
            RecordFactory::create('foo bar', Logger::ALERT, 'channel.foo', [], [], $now),
            $defaultBreadcrumb->withLevel(Breadcrumb::LEVEL_FATAL)
                ->withType(Breadcrumb::TYPE_ERROR),
        ];

        yield 'with context' => [
            RecordFactory::create('foo bar', Logger::DEBUG, 'channel.foo', ['context' => ['foo' => 'bar']], [], $now),
            $defaultBreadcrumb->withMetadata('context', ['foo' => 'bar']),
        ];

        yield 'with extra' => [
            RecordFactory::create('foo bar', Logger::DEBUG, 'channel.foo', [], ['extra' => ['foo' => 'bar']], $now),
            $defaultBreadcrumb->withMetadata('extra', ['foo' => 'bar']),
        ];

        yield 'with timestamp' => [
            RecordFactory::create('foo bar', Logger::DEBUG, 'channel.foo', [], [], new \DateTimeImmutable('1970-01-01 00:00:42.1337 UTC')),
            $defaultBreadcrumb->withTimestamp(42.1337),
        ];

        yield 'with zero timestamp' => [
            RecordFactory::create('foo bar', Logger::DEBUG, 'channel.foo', [], [], new \DateTimeImmutable('1970-01-01 00:00:00.000 UTC')),
            $defaultBreadcrumb->withTimestamp(0.0),
        ];

        yield 'with negative timestamp' => [
            RecordFactory::create('foo bar', Logger::DEBUG, 'channel.foo', [], [], new \DateTimeImmutable('1969-12-31 23:59:56.859 UTC')),
            $defaultBreadcrumb->withTimestamp(-3.141),
        ];
    }
}
