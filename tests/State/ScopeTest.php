<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Attachment\Attachment;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\Severity;
use Sentry\State\GlobalScope;
use Sentry\State\IsolationScope;
use Sentry\State\MergedScope;
use Sentry\State\Scope;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\UserDataBag;

final class ScopeTest extends TestCase
{
    private function mergeScope(IsolationScope $scope, ?GlobalScope $globalScope = null): MergedScope
    {
        return ($globalScope ?? new GlobalScope())->merge($scope);
    }

    public function testGetAndSetClient(): void
    {
        $scope = new IsolationScope();

        $this->assertInstanceOf(NoOpClient::class, $scope->getClient());

        $client = $this->createMock(ClientInterface::class);

        $this->assertSame($scope, $scope->setClient($client));
        $this->assertSame($client, $scope->getClient());
    }

    public function testClonedScopeKeepsClientShared(): void
    {
        $client = $this->createMock(ClientInterface::class);

        $scope = new IsolationScope();
        $scope->setClient($client);

        $clonedScope = clone $scope;

        $this->assertSame($client, $clonedScope->getClient());
    }

    public function testGetAndSetLastEventId(): void
    {
        $scope = new IsolationScope();

        $this->assertNull($scope->getLastEventId());

        $eventId = EventId::generate();
        $scope->setLastEventId($eventId);

        $this->assertSame($eventId, $scope->getLastEventId());

        $scope->setLastEventId(null);

        $this->assertNull($scope->getLastEventId());
    }

    public function testSetTag(): void
    {
        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getTags());

        $scope->setTag('foo', 'bar');
        $scope->setTag('bar', 'baz');

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTags());
    }

    public function testSetTags(): void
    {
        $scope = new IsolationScope();
        $scope->setTags(['foo' => 'bar']);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getTags());

        $scope->setTags(['bar' => 'baz']);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTags());
    }

    public function testRemoveTag(): void
    {
        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $scope->setTag('foo', 'bar');
        $scope->setTag('bar', 'baz');

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTags());

        $scope->removeTag('foo');

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['bar' => 'baz'], $event->getTags());
    }

    public function testSetFlag(): void
    {
        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertArrayNotHasKey('flags', $event->getContexts());

        $scope->addFeatureFlag('foo', true);
        $scope->addFeatureFlag('bar', false);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertArrayHasKey('flags', $event->getContexts());
        $this->assertEquals([
            'values' => [
                [
                    'flag' => 'foo',
                    'result' => true,
                ],
                [
                    'flag' => 'bar',
                    'result' => false,
                ],
            ],
        ], $event->getContexts()['flags']);
    }

    public function testSetFlagLimit(): void
    {
        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertArrayNotHasKey('flags', $event->getContexts());

        $expectedFlags = [];

        foreach (range(1, Scope::MAX_FLAGS) as $i) {
            $scope->addFeatureFlag("feature{$i}", true);

            $expectedFlags[] = [
                'flag' => "feature{$i}",
                'result' => true,
            ];
        }

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertArrayHasKey('flags', $event->getContexts());
        $this->assertEquals(['values' => $expectedFlags], $event->getContexts()['flags']);

        array_shift($expectedFlags);

        $scope->addFeatureFlag('should-not-be-discarded', true);

        $expectedFlags[] = [
            'flag' => 'should-not-be-discarded',
            'result' => true,
        ];

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertArrayHasKey('flags', $event->getContexts());
        $this->assertEquals(['values' => $expectedFlags], $event->getContexts()['flags']);
    }

    public function testSetFlagPropagatesToSpan(): void
    {
        $span = new Span();

        $scope = new IsolationScope();
        $scope->setSpan($span);

        $scope->addFeatureFlag('feature', true);

        $this->assertSame(['flag.evaluation.feature' => true], $span->getData());
    }

    public function testSetAndRemoveContext(): void
    {
        $propgationContext = PropagationContext::fromDefaults();

        $scope = new IsolationScope($propgationContext);
        $scope->setContext('foo', ['foo' => 'bar']);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([
            'trace' => [
                'trace_id' => (string) $propgationContext->getTraceId(),
                'span_id' => (string) $propgationContext->getSpanId(),
            ],
            'foo' => ['foo' => 'bar'],
        ], $event->getContexts());

        $scope->removeContext('foo');

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([
            'trace' => [
                'trace_id' => (string) $propgationContext->getTraceId(),
                'span_id' => (string) $propgationContext->getSpanId(),
            ],
        ], $event->getContexts());

        $scope->setContext('foo', []);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([
            'trace' => [
                'trace_id' => (string) $propgationContext->getTraceId(),
                'span_id' => (string) $propgationContext->getSpanId(),
            ],
        ], $event->getContexts());
    }

    public function testSetExtra(): void
    {
        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getExtra());

        $scope->setExtra('foo', 'bar');
        $scope->setExtra('bar', 'baz');

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getExtra());
    }

    public function testSetExtras(): void
    {
        $scope = new IsolationScope();
        $scope->setExtras(['foo' => 'bar']);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getExtra());

        $scope->setExtras(['bar' => 'baz']);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getExtra());
    }

    public function testSetUser(): void
    {
        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getUser());

        $user = UserDataBag::createFromUserIdentifier('unique_id');
        $user->setMetadata('subscription', 'basic');

        $scope->setUser($user);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEquals($user, $event->getUser());

        $expectedUser = UserDataBag::createFromUserIdentifier('unique_id');
        $expectedUser->setMetadata('subscription', 'basic');
        $expectedUser->setMetadata('subscription_expires_at', '2020-08-26');

        $scope->setUser(['ip_address' => '127.0.0.1', 'subscription_expires_at' => '2020-08-26']);

        $event = $this->mergeScope($scope)->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertEquals($expectedUser, $event->getUser());
    }

    public function testSetUserThrowsOnInvalidArgument(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('The $user argument must be either an array or an instance of the "Sentry\UserDataBag" class. Got: "string".');

        $scope = new IsolationScope();
        $scope->setUser('foo');
    }

    public function testRemoveUser(): void
    {
        $scope = new IsolationScope();
        $scope->setUser(UserDataBag::createFromUserIdentifier('unique_id'));

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNotNull($event->getUser());

        $scope->removeUser();

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getUser());
    }

    public function testSetFingerprint(): void
    {
        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getFingerprint());

        $scope->setFingerprint(['foo', 'bar']);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo', 'bar'], $event->getFingerprint());
    }

    public function testSetLevel(): void
    {
        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getLevel());

        $scope->setLevel(Severity::debug());

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEquals(Severity::debug(), $event->getLevel());
    }

    public function testAddBreadcrumb(): void
    {
        $scope = new IsolationScope();
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getBreadcrumbs());

        $scope->addBreadcrumb($breadcrumb1);
        $scope->addBreadcrumb($breadcrumb2);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([$breadcrumb1, $breadcrumb2], $event->getBreadcrumbs());

        $scope->addBreadcrumb($breadcrumb3, 2);

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([$breadcrumb2, $breadcrumb3], $event->getBreadcrumbs());
    }

    public function testClearBreadcrumbs(): void
    {
        $scope = new IsolationScope();

        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));
        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNotEmpty($event->getBreadcrumbs());

        $scope->clearBreadcrumbs();

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getBreadcrumbs());
    }

    public function testAddEventProcessor(): void
    {
        $callback1Called = false;
        $callback2Called = false;
        $callback3Called = false;

        $event = Event::createEvent();
        $scope = new IsolationScope();

        $scope->addEventProcessor(function (Event $eventArg) use (&$callback1Called, $callback2Called, $callback3Called): ?Event {
            $this->assertFalse($callback2Called);
            $this->assertFalse($callback3Called);

            $callback1Called = true;

            return $eventArg;
        });

        $this->assertSame($event, $this->mergeScope($scope)->applyToEvent($event));
        $this->assertTrue($callback1Called);

        $scope->addEventProcessor(function () use ($callback1Called, &$callback2Called, $callback3Called) {
            $this->assertTrue($callback1Called);
            $this->assertFalse($callback3Called);

            $callback2Called = true;

            return null;
        });

        $scope->addEventProcessor(static function () use (&$callback3Called) {
            $callback3Called = true;

            return null;
        });

        $this->assertNull($this->mergeScope($scope)->applyToEvent($event));
        $this->assertTrue($callback2Called);
        $this->assertFalse($callback3Called);
    }

    public function testEventProcessorReceivesTheEventAndEventHint(): void
    {
        $event = Event::createEvent();
        $scope = new IsolationScope();
        $hint = new EventHint();

        $processorCalled = false;
        $processorReceivedHint = null;

        $scope->addEventProcessor(static function (Event $eventArg, EventHint $hint) use (&$processorCalled, &$processorReceivedHint): ?Event {
            $processorCalled = true;
            $processorReceivedHint = $hint;

            return $eventArg;
        });

        $this->assertSame($event, $this->mergeScope($scope)->applyToEvent($event, $hint));
        $this->assertSame($hint, $processorReceivedHint);
        $this->assertTrue($processorCalled);
    }

    public function testClear(): void
    {
        $scope = new IsolationScope();
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $client = $this->createMock(ClientInterface::class);
        $eventId = EventId::generate();

        $scope->setClient($client);
        $scope->setLastEventId($eventId);
        $scope->setLevel(Severity::info());
        $scope->addBreadcrumb($breadcrumb);
        $scope->setFingerprint(['foo']);
        $scope->setExtras(['foo' => 'bar']);
        $scope->setTags(['bar' => 'foo']);
        $scope->addFeatureFlag('feature', true);
        $scope->setUser(UserDataBag::createFromUserIdentifier('unique_id'));
        $scope->clear();

        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getLevel());
        $this->assertEmpty($event->getBreadcrumbs());
        $this->assertEmpty($event->getFingerprint());
        $this->assertEmpty($event->getExtra());
        $this->assertEmpty($event->getTags());
        $this->assertEmpty($event->getUser());
        $this->assertArrayNotHasKey('flags', $event->getContexts());
        $this->assertSame($client, $scope->getClient());
        $this->assertSame($eventId, $scope->getLastEventId());
    }

    public function testApplyToEvent(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $user = UserDataBag::createFromUserIdentifier('unique_id');

        $event = Event::createEvent();
        $event->setContext('foocontext', ['foo' => 'foo', 'bar' => 'bar']);

        $transactionContext = new TransactionContext('foo');
        $transaction = new Transaction($transactionContext);
        $transaction->setSpanId(new SpanId('8c2df92a922b4efe'));
        $transaction->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));

        $span = $transaction->startChild(new SpanContext());
        $span->setSpanId(new SpanId('566e3688a61d4bc8'));

        $scope = new IsolationScope();
        $scope->setLevel(Severity::warning());
        $scope->setFingerprint(['foo']);
        $scope->addBreadcrumb($breadcrumb);
        $scope->setTag('foo', 'bar');
        $scope->setExtra('bar', 'foo');
        $scope->setUser($user);
        $scope->setContext('foocontext', ['foo' => 'bar']);
        $scope->setContext('barcontext', ['bar' => 'foo']);
        $scope->addFeatureFlag('feature', true);
        $scope->setSpan($span);

        $this->assertSame($event, $this->mergeScope($scope)->applyToEvent($event));
        $this->assertTrue($event->getLevel()->isEqualTo(Severity::warning()));
        $this->assertSame(['foo'], $event->getFingerprint());
        $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        $this->assertSame(['foo' => 'bar'], $event->getTags());
        $this->assertSame(['bar' => 'foo'], $event->getExtra());
        $this->assertEquals($user, $event->getUser());
        $this->assertSame([
            'foocontext' => [
                'foo' => 'foo',
                'bar' => 'bar',
            ],
            'flags' => [
                'values' => [
                    [
                        'flag' => 'feature',
                        'result' => true,
                    ],
                ],
            ],
            'trace' => [
                'span_id' => '566e3688a61d4bc8',
                'trace_id' => '566e3688a61d4bc888951642d6f14a19',
                'origin' => 'manual',
                'parent_span_id' => '8c2df92a922b4efe',
            ],
            'barcontext' => [
                'bar' => 'foo',
            ],
        ], $event->getContexts());

        $dynamicSamplingContext = $event->getSdkMetadata('dynamic_sampling_context');

        $this->assertInstanceOf(DynamicSamplingContext::class, $dynamicSamplingContext);
        $this->assertSame('foo', $dynamicSamplingContext->get('transaction'));
        $this->assertSame('566e3688a61d4bc888951642d6f14a19', $dynamicSamplingContext->get('trace_id'));
    }

    public function testMergeScopesAppliesGlobalScopeUnderIsolationScope(): void
    {
        $globalBreadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'global');
        $isolationBreadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'isolation');
        $globalAttachment = Attachment::fromBytes('global.txt', 'global');
        $isolationAttachment = Attachment::fromBytes('isolation.txt', 'isolation');

        $globalUser = UserDataBag::createFromUserIdentifier('global-user');
        $globalUser->setMetadata('shared', 'global');
        $globalUser->setMetadata('global', true);

        $globalScope = new GlobalScope();
        $globalScope->setTag('shared', 'global');
        $globalScope->setTag('global', 'tag');
        $globalScope->setExtra('shared', 'global');
        $globalScope->setExtra('global', true);
        $globalScope->setContext('shared_context', ['value' => 'global']);
        $globalScope->setContext('global_context', ['value' => 'global']);
        $globalScope->setUser($globalUser);
        $globalScope->setLevel(Severity::error());
        $globalScope->setFingerprint(['global-fingerprint']);
        $globalScope->addBreadcrumb($globalBreadcrumb);
        $globalScope->addAttachment($globalAttachment);

        $isolationUser = UserDataBag::createFromUserIdentifier('isolation-user');
        $isolationUser->setMetadata('shared', 'isolation');
        $isolationUser->setMetadata('isolation', true);

        $isolationScope = new IsolationScope();
        $isolationScope->setTag('shared', 'isolation');
        $isolationScope->setTag('isolation', 'tag');
        $isolationScope->setExtra('shared', 'isolation');
        $isolationScope->setExtra('isolation', true);
        $isolationScope->setContext('shared_context', ['value' => 'isolation']);
        $isolationScope->setContext('isolation_context', ['value' => 'isolation']);
        $isolationScope->setUser($isolationUser);
        $isolationScope->setLevel(Severity::warning());
        $isolationScope->setFingerprint(['isolation-fingerprint']);
        $isolationScope->addBreadcrumb($isolationBreadcrumb);
        $isolationScope->addFeatureFlag('shared-flag', true);
        $isolationScope->addFeatureFlag('isolation-flag', false);
        $isolationScope->addAttachment($isolationAttachment);

        $eventUser = UserDataBag::createFromUserIdentifier('event-user');
        $eventUser->setMetadata('shared', 'event');
        $eventUser->setMetadata('event', true);

        $event = Event::createEvent();
        $event->setTag('shared', 'event');
        $event->setTag('event', 'tag');
        $event->setExtra(['shared' => 'event', 'event' => true]);
        $event->setContext('shared_context', ['value' => 'event']);
        $event->setUser($eventUser);
        $event->setFingerprint(['event-fingerprint']);

        $event = $globalScope->merge($isolationScope)->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertTrue($event->getLevel()->isEqualTo(Severity::warning()));
        $this->assertSame(['event-fingerprint', 'global-fingerprint', 'isolation-fingerprint'], $event->getFingerprint());
        $this->assertSame([
            'shared' => 'event',
            'global' => 'tag',
            'isolation' => 'tag',
            'event' => 'tag',
        ], $event->getTags());
        $this->assertSame([
            'shared' => 'event',
            'global' => true,
            'isolation' => true,
            'event' => true,
        ], $event->getExtra());
        $this->assertSame(['value' => 'event'], $event->getContexts()['shared_context']);
        $this->assertSame(['value' => 'global'], $event->getContexts()['global_context']);
        $this->assertSame(['value' => 'isolation'], $event->getContexts()['isolation_context']);
        $this->assertSame([
            'values' => [
                [
                    'flag' => 'shared-flag',
                    'result' => true,
                ],
                [
                    'flag' => 'isolation-flag',
                    'result' => false,
                ],
            ],
        ], $event->getContexts()['flags']);
        $this->assertSame([$globalBreadcrumb, $isolationBreadcrumb], $event->getBreadcrumbs());
        $this->assertSame([$globalAttachment, $isolationAttachment], $event->getAttachments());

        $user = $event->getUser();
        $this->assertNotNull($user);
        $this->assertSame('event-user', $user->getId());
        $this->assertSame([
            'shared' => 'event',
            'global' => true,
            'isolation' => true,
            'event' => true,
        ], $user->getMetadata());
    }

    public function testMergeScopesUsesGlobalLevelWhenIsolationLevelIsUnset(): void
    {
        $globalScope = new GlobalScope();
        $globalScope->setLevel(Severity::error());

        $event = $globalScope->merge(new IsolationScope())->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertTrue($event->getLevel()->isEqualTo(Severity::error()));
    }

    public function testMergeScopesCarriesIsolationClient(): void
    {
        $globalScope = new GlobalScope();
        $globalScope->setClient($this->createMock(ClientInterface::class));

        $isolationClient = $this->createMock(ClientInterface::class);
        $isolationScope = new IsolationScope();
        $isolationScope->setClient($isolationClient);

        $this->assertSame($isolationClient, $globalScope->merge($isolationScope)->getClient());
    }

    public function testMergeScopesCapsBreadcrumbsAndFlags(): void
    {
        $globalScope = new GlobalScope();
        $globalBreadcrumbs = [];

        foreach (range(1, 100) as $i) {
            $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, "global{$i}");
            $globalBreadcrumbs[] = $breadcrumb;
            $globalScope->addBreadcrumb($breadcrumb);
        }

        $isolationBreadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'isolation');
        $isolationScope = new IsolationScope();
        $isolationScope->addBreadcrumb($isolationBreadcrumb);

        foreach (range(1, Scope::MAX_FLAGS) as $i) {
            $isolationScope->addFeatureFlag("feature{$i}", true);
        }

        $isolationScope->addFeatureFlag('feature50', false);
        $isolationScope->addFeatureFlag('feature101', true);

        $event = $globalScope->merge($isolationScope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertCount(100, $event->getBreadcrumbs());
        $this->assertSame($globalBreadcrumbs[1], $event->getBreadcrumbs()[0]);
        $this->assertSame($isolationBreadcrumb, $event->getBreadcrumbs()[99]);

        $flags = $event->getContexts()['flags']['values'];
        $this->assertCount(Scope::MAX_FLAGS, $flags);
        $this->assertSame([
            'flag' => 'feature2',
            'result' => true,
        ], $flags[0]);
        $this->assertSame([
            'flag' => 'feature50',
            'result' => false,
        ], $flags[98]);
        $this->assertSame([
            'flag' => 'feature101',
            'result' => true,
        ], $flags[99]);
        $this->assertFalse(\in_array('feature1', array_column($flags, 'flag'), true));
    }

    public function testMergeScopesKeepsTraceStateFromIsolationScope(): void
    {
        $globalScope = new GlobalScope();

        $isolationPropagationContext = PropagationContext::fromDefaults();
        $isolationPropagationContext->setTraceId(new TraceId('33333333333333333333333333333333'));
        $isolationPropagationContext->setSpanId(new SpanId('3333333333333333'));

        $isolationScope = new IsolationScope($isolationPropagationContext);

        $mergedScope = $globalScope->merge($isolationScope);

        $this->assertNull($mergedScope->getSpan());
        $this->assertNotSame($isolationScope->getPropagationContext(), $mergedScope->getPropagationContext());
        $this->assertSame([
            'trace_id' => '33333333333333333333333333333333',
            'span_id' => '3333333333333333',
        ], $mergedScope->getTraceContext());

        $event = $mergedScope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([
            'trace_id' => '33333333333333333333333333333333',
            'span_id' => '3333333333333333',
        ], $event->getContexts()['trace']);
    }

    public function testMergeScopesKeepsProcessorOrder(): void
    {
        $calls = [];

        Scope::addGlobalEventProcessor(static function (Event $event) use (&$calls): ?Event {
            $calls[] = 'static';

            return $event;
        });

        $globalScope = new GlobalScope();
        $globalScope->addEventProcessor(static function (Event $event) use (&$calls): ?Event {
            $calls[] = 'global';

            return $event;
        });

        $isolationScope = new IsolationScope();
        $isolationScope->addEventProcessor(static function (Event $event) use (&$calls): ?Event {
            $calls[] = 'isolation';

            return $event;
        });

        $event = $globalScope->merge($isolationScope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['static', 'global', 'isolation'], $calls);
    }

    /**
     * @dataProvider eventWithLogCountProvider
     */
    public function testAttachmentsAppliedForType(Event $event, int $attachmentCount): void
    {
        $scope = new IsolationScope();
        $scope->addAttachment(Attachment::fromBytes('test', 'abcde'));
        $this->mergeScope($scope)->applyToEvent($event);
        $this->assertCount($attachmentCount, $event->getAttachments());
    }

    public function eventWithLogCountProvider(): \Generator
    {
        yield 'event' => [Event::createEvent(), 1];
        yield 'transaction' => [Event::createTransaction(), 1];
        yield 'check-in' => [Event::createCheckIn(), 0];
        yield 'logs' => [Event::createLogs(), 0];
    }

    public function testGetTraceContextPrefersExternalPropagationContextOverPropagationContext(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        Scope::registerExternalPropagationContext(static function (): array {
            return [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ];
        });

        $scope = new IsolationScope($propagationContext);

        $this->assertSame([
            'trace_id' => '771a43a4192642f0b136d5159a501700',
            'span_id' => '1234567890abcdef',
        ], $scope->getTraceContext());

        Scope::clearExternalPropagationContext();
    }

    public function testGetTraceContextPrefersLocalSpanOverExternalPropagationContext(): void
    {
        Scope::registerExternalPropagationContext(static function (): array {
            return [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ];
        });

        $transaction = new Transaction(new TransactionContext('foo'));
        $transaction->setSpanId(new SpanId('8c2df92a922b4efe'));
        $transaction->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $span = $transaction->startChild(new SpanContext());
        $span->setSpanId(new SpanId('566e3688a61d4bc8'));

        $scope = new IsolationScope();
        $scope->setSpan($span);

        $this->assertSame([
            'span_id' => '566e3688a61d4bc8',
            'trace_id' => '566e3688a61d4bc888951642d6f14a19',
            'origin' => 'manual',
            'parent_span_id' => '8c2df92a922b4efe',
        ], $scope->getTraceContext());

        Scope::clearExternalPropagationContext();
    }

    public function testApplyToEventSkipsDynamicSamplingContextWhenUsingExternalPropagationContext(): void
    {
        Scope::registerExternalPropagationContext(static function (): array {
            return [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ];
        });

        $scope = new IsolationScope();
        $event = $this->mergeScope($scope)->applyToEvent(Event::createEvent(), null, new Options([
            'dsn' => 'http://public@example.com/1',
            'release' => '1.0.0',
            'environment' => 'test',
            'traces_sample_rate' => 1.0,
        ]));

        $this->assertNotNull($event);
        $this->assertSame([
            'trace' => [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ],
        ], $event->getContexts());
        $this->assertNull($event->getSdkMetadata('dynamic_sampling_context'));

        Scope::clearExternalPropagationContext();
    }
}
