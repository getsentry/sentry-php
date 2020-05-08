<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Severity;
use Sentry\State\Scope;

final class ScopeTest extends TestCase
{
    public function testSetTag(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertTrue($event->getTagsContext()->isEmpty());

        $scope->setTag('foo', 'bar');
        $scope->setTag('bar', 'baz');

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTagsContext()->toArray());
    }

    public function testSetTags(): void
    {
        $scope = new Scope();
        $scope->setTags(['foo' => 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getTagsContext()->toArray());

        $scope->setTags(['bar' => 'baz']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTagsContext()->toArray());
    }

    public function testSetAndRemoveContext(): void
    {
        $scope = new Scope();
        $scope->setContext('foo', ['foo' => 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => ['foo' => 'bar']], $event->getContexts());

        $scope->removeContext('foo');

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame([], $event->getContexts());
    }

    public function testSetExtra(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertTrue($event->getExtraContext()->isEmpty());

        $scope->setExtra('foo', 'bar');
        $scope->setExtra('bar', 'baz');

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getExtraContext()->toArray());
    }

    public function testSetExtras(): void
    {
        $scope = new Scope();
        $scope->setExtras(['foo' => 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getExtraContext()->toArray());

        $scope->setExtras(['bar' => 'baz']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getExtraContext()->toArray());
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Replacing the data is deprecated since version 2.3 and will stop working from version 3.0. Set the second argument to `true` to merge the data instead.
     */
    public function testSetUserThrowsDeprecationError(): void
    {
        $scope = new Scope();

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame([], $event->getUserContext()->toArray());

        $scope->setUser(['foo' => 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getUserContext()->toArray());

        $scope->setUser(['bar' => 'baz']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['bar' => 'baz'], $event->getUserContext()->toArray());
    }

    public function testSetUser(): void
    {
        $scope = new Scope();

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame([], $event->getUserContext()->toArray());

        $scope->setUser(['foo' => 'bar'], true);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getUserContext()->toArray());

        $scope->setUser(['bar' => 'baz'], true);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getUserContext()->toArray());
    }

    public function testSetFingerprint(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEmpty($event->getFingerprint());

        $scope->setFingerprint(['foo', 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo', 'bar'], $event->getFingerprint());
    }

    public function testSetLevel(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::error(), $event->getLevel());

        $scope->setLevel(Severity::debug());

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::debug(), $event->getLevel());
    }

    public function testAddBreadcrumb(): void
    {
        $scope = new Scope();
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEmpty($event->getBreadcrumbs());

        $scope->addBreadcrumb($breadcrumb1);
        $scope->addBreadcrumb($breadcrumb2);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame([$breadcrumb1, $breadcrumb2], $event->getBreadcrumbs());

        $scope->addBreadcrumb($breadcrumb3, 2);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame([$breadcrumb2, $breadcrumb3], $event->getBreadcrumbs());
    }

    public function testClearBreadcrumbs(): void
    {
        $scope = new Scope();

        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));
        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertNotEmpty($event->getBreadcrumbs());

        $scope->clearBreadcrumbs();

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEmpty($event->getBreadcrumbs());
    }

    public function testAddEventProcessor(): void
    {
        $callback1Called = false;
        $callback2Called = false;
        $callback3Called = false;

        $event = new Event();
        $scope = new Scope();

        $scope->addEventProcessor(function (Event $eventArg) use (&$callback1Called, $callback2Called, $callback3Called): ?Event {
            $this->assertFalse($callback2Called);
            $this->assertFalse($callback3Called);

            $callback1Called = true;

            return $eventArg;
        });

        $this->assertSame($event, $scope->applyToEvent($event, []));
        $this->assertTrue($callback1Called);

        $scope->addEventProcessor(function () use ($callback1Called, &$callback2Called, $callback3Called) {
            $this->assertTrue($callback1Called);
            $this->assertFalse($callback3Called);

            $callback2Called = true;

            return null;
        });

        $scope->addEventProcessor(function () use (&$callback3Called) {
            $callback3Called = true;

            return null;
        });

        $this->assertNull($scope->applyToEvent($event, []));
        $this->assertTrue($callback2Called);
        $this->assertFalse($callback3Called);
    }

    public function testClear(): void
    {
        $scope = new Scope();
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $scope->setLevel(Severity::info());
        $scope->addBreadcrumb($breadcrumb);
        $scope->setFingerprint(['foo']);
        $scope->setExtras(['foo' => 'bar']);
        $scope->setTags(['bar' => 'foo']);
        $scope->setUser(['foobar' => 'barfoo'], true);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::info(), $event->getLevel());
        $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        $this->assertSame(['foo'], $event->getFingerprint());
        $this->assertSame(['foo' => 'bar'], $event->getExtraContext()->toArray());
        $this->assertSame(['bar' => 'foo'], $event->getTagsContext()->toArray());
        $this->assertSame(['foobar' => 'barfoo'], $event->getUserContext()->toArray());

        $scope->clear();

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::error(), $event->getLevel());
        $this->assertEmpty($event->getBreadcrumbs());
        $this->assertEmpty($event->getFingerprint());
        $this->assertEmpty($event->getExtraContext()->toArray());
        $this->assertEmpty($event->getTagsContext()->toArray());
        $this->assertEmpty($event->getUserContext()->toArray());
    }

    public function testApplyToEvent(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $event = new Event();
        $event->setContext('foocontext', ['foo' => 'foo', 'bar' => 'bar']);

        $scope = new Scope();
        $scope->setLevel(Severity::warning());
        $scope->setFingerprint(['foo']);
        $scope->addBreadcrumb($breadcrumb);
        $scope->setTag('foo', 'bar');
        $scope->setExtra('bar', 'foo');
        $scope->setUser(['foo' => 'baz'], true);
        $scope->setContext('foocontext', ['foo' => 'bar']);
        $scope->setContext('barcontext', ['bar' => 'foo']);

        $this->assertSame($event, $scope->applyToEvent($event, []));
        $this->assertTrue($event->getLevel()->isEqualTo(Severity::warning()));
        $this->assertSame(['foo'], $event->getFingerprint());
        $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        $this->assertSame(['foo' => 'bar'], $event->getTagsContext()->toArray());
        $this->assertSame(['bar' => 'foo'], $event->getExtraContext()->toArray());
        $this->assertSame(['foo' => 'baz'], $event->getUserContext()->toArray());
        $this->assertSame(['foocontext' => ['foo' => 'foo', 'bar' => 'bar'], 'barcontext' => ['bar' => 'foo']], $event->getContexts());
    }
}
