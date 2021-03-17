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

        yield [
            $defaultData,
            $defaultBreadcrumb,
        ];
    }
}
