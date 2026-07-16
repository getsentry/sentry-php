<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\State\BreadcrumbRecorder;
use Sentry\State\Scope;

final class BreadcrumbRecorderTest extends TestCase
{
    public function testRecordAddsBreadcrumbToGivenScope(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $scope = new Scope();
        $otherScope = new Scope();
        $client = $this->createMock(ClientInterface::class);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());

        $this->assertTrue(BreadcrumbRecorder::record($client, $scope, $breadcrumb));
        $this->assertScopeBreadcrumbs($scope, [$breadcrumb]);
        $this->assertScopeBreadcrumbs($otherScope, []);
    }

    public function testRecordReturnsFalseForNoOpClient(): void
    {
        $scope = new Scope();

        $this->assertFalse(BreadcrumbRecorder::record(
            new NoOpClient(),
            $scope,
            new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting')
        ));
        $this->assertScopeBreadcrumbs($scope, []);
    }

    public function testRecordReturnsFalseWhenMaxBreadcrumbsLimitIsZero(): void
    {
        $scope = new Scope();
        $client = $this->createMock(ClientInterface::class);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['max_breadcrumbs' => 0]));

        $this->assertFalse(BreadcrumbRecorder::record(
            $client,
            $scope,
            new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting')
        ));
        $this->assertScopeBreadcrumbs($scope, []);
    }

    public function testRecordReturnsFalseWhenBeforeBreadcrumbCallbackReturnsNull(): void
    {
        $scope = new Scope();
        $client = $this->createMock(ClientInterface::class);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'before_breadcrumb' => static function () {
                    return null;
                },
            ]));

        $this->assertFalse(BreadcrumbRecorder::record(
            $client,
            $scope,
            new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting')
        ));
        $this->assertScopeBreadcrumbs($scope, []);
    }

    public function testRecordStoresBreadcrumbReturnedByBeforeBreadcrumbCallback(): void
    {
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_DEFAULT, 'custom');
        $scope = new Scope();
        $client = $this->createMock(ClientInterface::class);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'before_breadcrumb' => static function () use ($breadcrumb2): Breadcrumb {
                    return $breadcrumb2;
                },
            ]));

        $this->assertTrue(BreadcrumbRecorder::record($client, $scope, $breadcrumb1));
        $this->assertScopeBreadcrumbs($scope, [$breadcrumb2]);
    }

    public function testRecordRespectsMaxBreadcrumbsLimit(): void
    {
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'one');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'two');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'three');
        $scope = new Scope();
        $client = $this->createMock(ClientInterface::class);

        $client->expects($this->exactly(3))
            ->method('getOptions')
            ->willReturn(new Options(['max_breadcrumbs' => 2]));

        BreadcrumbRecorder::record($client, $scope, $breadcrumb1);
        BreadcrumbRecorder::record($client, $scope, $breadcrumb2);
        BreadcrumbRecorder::record($client, $scope, $breadcrumb3);

        $this->assertScopeBreadcrumbs($scope, [$breadcrumb2, $breadcrumb3]);
    }

    /**
     * @param Breadcrumb[] $expectedBreadcrumbs
     */
    private function assertScopeBreadcrumbs(Scope $scope, array $expectedBreadcrumbs): void
    {
        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame($expectedBreadcrumbs, $event->getBreadcrumbs());
    }
}
