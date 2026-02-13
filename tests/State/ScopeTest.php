<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Attachment\Attachment;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\Severity;
use Sentry\State\Scope;
use Sentry\State\ScopeType;
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
    private function createClientWithOptions(array $options = []): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn(new Options($options));

        return $client;
    }

    public function testScopeTypeAssignmentAndClone(): void
    {
        $scope = new Scope(null, ScopeType::global());
        $this->assertSame(ScopeType::global(), $scope->getType());

        $scope->setType(ScopeType::current());
        $this->assertSame(ScopeType::current(), $scope->getType());

        $clone = clone $scope;
        $this->assertSame(ScopeType::current(), $clone->getType());
    }

    public function testScopeReturnsNoOpClientByDefault(): void
    {
        $scope = new Scope();

        $this->assertInstanceOf(NoOpClient::class, $scope->getClient());
    }

    public function testGetClientFromScopesPrefersCurrentThenIsolationThenGlobal(): void
    {
        $globalClient = $this->createMock(ClientInterface::class);
        $isolationClient = $this->createMock(ClientInterface::class);
        $currentClient = $this->createMock(ClientInterface::class);

        $globalScope = new Scope(null, ScopeType::global());
        $globalScope->bindClient($globalClient);
        $isolationScope = new Scope(null, ScopeType::isolation());
        $isolationScope->bindClient($isolationClient);
        $currentScope = new Scope(null, ScopeType::current());
        $currentScope->bindClient($currentClient);

        // If all scopes have clients then this will give us the current scope client
        $this->assertSame($currentClient, Scope::getClientFromScopes($globalScope, $isolationScope, $currentScope));

        // If the current scope has no client then we will get the isolation scope client
        $currentScope->setClient(null);
        $this->assertSame($isolationClient, Scope::getClientFromScopes($globalScope, $isolationScope, $currentScope));

        // If no current or isolation scope client exists, we will get the global scope client
        $isolationScope->setClient(null);
        $this->assertSame($globalClient, Scope::getClientFromScopes($globalScope, $isolationScope, $currentScope));
    }

    public function testMergeOrderPrefersCurrentScope(): void
    {
        $globalScope = new Scope(null, ScopeType::global());
        $globalScope->setTag('global', 'foo');
        $globalScope->setTag('scope', 'global');

        $isolationScope = new Scope(null, ScopeType::isolation());
        $isolationScope->setTag('isolation', 'foo');
        $isolationScope->setTag('scope', 'isolation');

        $currentScope = new Scope(null, ScopeType::current());
        $currentScope->setTag('current', 'foo');
        $currentScope->setTag('scope', 'current');

        $mergedScope = Scope::mergeScopes($globalScope, $isolationScope, $currentScope);
        $event = Event::createEvent();
        $event = $mergedScope->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertEquals([
            'global' => 'foo',
            'isolation' => 'foo',
            'current' => 'foo',
            'scope' => 'current',
        ], $event->getTags());
    }

    public function testBreadcrumbsAreMergedAndSorted(): void
    {
        $client = $this->createClientWithOptions();
        $globalScope = new Scope(null, ScopeType::global());
        $globalScope->bindClient($client);
        $globalScope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'global', null, [], 2000.0));

        $isolationScope = new Scope(null, ScopeType::isolation());
        $isolationScope->bindClient($client);
        $isolationScope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'isolation', null, [], 1500.0));

        $currentScope = new Scope(null, ScopeType::current());
        $currentScope->bindClient($client);
        $currentScope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'current', null, [], 1000.0));

        $mergedScope = Scope::mergeScopes($globalScope, $isolationScope, $currentScope);
        $event = Event::createEvent();
        $event = $mergedScope->applyToEvent($event);

        $this->assertNotNull($event);
        $breadcrumbs = $event->getBreadcrumbs();

        $this->assertSame('current', $breadcrumbs[0]->getCategory());
        $this->assertSame('isolation', $breadcrumbs[1]->getCategory());
        $this->assertSame('global', $breadcrumbs[2]->getCategory());
    }

    public function testMergeScopesSortsAndCapsBreadcrumbs(): void
    {
        $client = $this->createClientWithOptions(['max_breadcrumbs' => 2]);

        $globalScope = new Scope(null, ScopeType::global());
        $globalScope->bindClient($client);
        $globalScope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, '100', null, [], 100.0));
        $globalScope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, '400', null, [], 400.0));

        $isolationScope = new Scope(null, ScopeType::isolation());
        $isolationScope->bindClient($client);
        $isolationScope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, '200', null, [], 200.0));

        $currentScope = new Scope(null, ScopeType::current());
        $currentScope->bindClient($client);
        $currentScope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, '300', null, [], 300.0));

        $mergedScope = Scope::mergeScopes($globalScope, $isolationScope, $currentScope);
        $event = Event::createEvent();
        $event = $mergedScope->applyToEvent($event);

        $this->assertNotNull($event);
        $breadcrumbs = $event->getBreadcrumbs();
        $this->assertCount(2, $breadcrumbs);
        $this->assertSame('300', $breadcrumbs[0]->getCategory());
        $this->assertSame('400', $breadcrumbs[1]->getCategory());
    }

    public function testEventProcessorsRunInScopeOrder(): void
    {
        $globalScope = new Scope(null, ScopeType::global());
        $globalScope->addEventProcessor(function (Event $event) {
            $order = $event->getExtra()['order'] ?? [];
            $order[] = 'global';
            $event->setExtra(['order' => $order]);

            return $event;
        });

        $isolationScope = new Scope(null, ScopeType::isolation());
        $isolationScope->addEventProcessor(function (Event $event) {
            $order = $event->getExtra()['order'] ?? [];
            $order[] = 'isolation';
            $event->setExtra(['order' => $order]);

            return $event;
        });

        $currentScope = new Scope(null, ScopeType::current());
        $currentScope->addEventProcessor(function (Event $event) {
            $order = $event->getExtra()['order'] ?? [];
            $order[] = 'current';
            $event->setExtra(['order' => $order]);

            return $event;
        });

        $mergedScope = Scope::mergeScopes($globalScope, $isolationScope, $currentScope);
        $event = Event::createEvent();
        $event = $mergedScope->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertSame(['global', 'isolation', 'current'], $event->getExtra()['order']);
    }

    public function testScopeAttributesApplyToScope(): void
    {
        $scope = new Scope();
        $scope->setAttribute('app.feature', true);
        $scope->setAttributes([
            'app.session' => 42,
        ]);

        $this->assertTrue($scope->getAttributes()->get('app.feature')->getValue());
        $this->assertSame(42, $scope->getAttributes()->get('app.session')->getValue());
    }

    public function testAttributesAreMergedWithPrecedence(): void
    {
        $globalScope = new Scope(null, ScopeType::global());
        $globalScope->setAttribute('global.attribute', 'global');
        $globalScope->setAttribute('overwritten.attribute', 'global');

        $isolationScope = new Scope(null, ScopeType::isolation());
        $isolationScope->setAttribute('isolation.attribute', 'isolation');
        $isolationScope->setAttribute('overwritten.attribute', 'isolation');

        $currentScope = new Scope(null, ScopeType::current());
        $currentScope->setAttribute('current.attribute', 'current');
        $currentScope->setAttribute('overwritten.attribute', 'current');

        $scope = Scope::mergeScopes($globalScope, $isolationScope, $currentScope);

        $this->assertSame('global', $scope->getAttributes()->get('global.attribute')->getValue());
        $this->assertSame('isolation', $scope->getAttributes()->get('isolation.attribute')->getValue());
        $this->assertSame('current', $scope->getAttributes()->get('current.attribute')->getValue());
        $this->assertSame('current', $scope->getAttributes()->get('overwritten.attribute')->getValue());
    }

    public function testRemoveAttribute(): void
    {
        $scope = new Scope();
        $scope->setAttribute('app.feature', true);
        $scope->removeAttribute('app.feature');

        $this->assertNull($scope->getAttributes()->get('app.feature'));
    }

    public function testSetTag(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getTags());

        $scope->setTag('foo', 'bar');
        $scope->setTag('bar', 'baz');

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTags());
    }

    public function testSetTags(): void
    {
        $scope = new Scope();
        $scope->setTags(['foo' => 'bar']);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getTags());

        $scope->setTags(['bar' => 'baz']);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTags());
    }

    public function testRemoveTag(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(Event::createEvent());

        $scope->setTag('foo', 'bar');
        $scope->setTag('bar', 'baz');

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTags());

        $scope->removeTag('foo');

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['bar' => 'baz'], $event->getTags());
    }

    public function testSetFlag(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertArrayNotHasKey('flags', $event->getContexts());

        $scope->addFeatureFlag('foo', true);
        $scope->addFeatureFlag('bar', false);

        $event = $scope->applyToEvent(Event::createEvent());

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
        $scope = new Scope();
        $event = $scope->applyToEvent(Event::createEvent());

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

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertArrayHasKey('flags', $event->getContexts());
        $this->assertEquals(['values' => $expectedFlags], $event->getContexts()['flags']);

        array_shift($expectedFlags);

        $scope->addFeatureFlag('should-not-be-discarded', true);

        $expectedFlags[] = [
            'flag' => 'should-not-be-discarded',
            'result' => true,
        ];

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertArrayHasKey('flags', $event->getContexts());
        $this->assertEquals(['values' => $expectedFlags], $event->getContexts()['flags']);
    }

    public function testSetFlagPropagatesToSpan(): void
    {
        $span = new Span();

        $scope = new Scope();
        $scope->setSpan($span);

        $scope->addFeatureFlag('feature', true);

        $this->assertSame(['flag.evaluation.feature' => true], $span->getData());
    }

    public function testSetAndRemoveContext(): void
    {
        $propgationContext = PropagationContext::fromDefaults();

        $scope = new Scope($propgationContext);
        $scope->setContext('foo', ['foo' => 'bar']);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([
            'trace' => [
                'trace_id' => (string) $propgationContext->getTraceId(),
                'span_id' => (string) $propgationContext->getSpanId(),
            ],
            'foo' => ['foo' => 'bar'],
        ], $event->getContexts());

        $scope->removeContext('foo');

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([
            'trace' => [
                'trace_id' => (string) $propgationContext->getTraceId(),
                'span_id' => (string) $propgationContext->getSpanId(),
            ],
        ], $event->getContexts());

        $scope->setContext('foo', []);

        $event = $scope->applyToEvent(Event::createEvent());

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
        $scope = new Scope();
        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getExtra());

        $scope->setExtra('foo', 'bar');
        $scope->setExtra('bar', 'baz');

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getExtra());
    }

    public function testSetExtras(): void
    {
        $scope = new Scope();
        $scope->setExtras(['foo' => 'bar']);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getExtra());

        $scope->setExtras(['bar' => 'baz']);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getExtra());
    }

    public function testSetUser(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getUser());

        $user = UserDataBag::createFromUserIdentifier('unique_id');
        $user->setMetadata('subscription', 'basic');

        $scope->setUser($user);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame($user, $event->getUser());

        $user = UserDataBag::createFromUserIpAddress('127.0.0.1');
        $user->setMetadata('subscription', 'basic');
        $user->setMetadata('subscription_expires_at', '2020-08-26');

        $scope->setUser(['ip_address' => '127.0.0.1', 'subscription_expires_at' => '2020-08-26']);

        $event = $scope->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertEquals($user, $event->getUser());
    }

    public function testSetUserThrowsOnInvalidArgument(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('The $user argument must be either an array or an instance of the "Sentry\UserDataBag" class. Got: "string".');

        $scope = new Scope();
        $scope->setUser('foo');
    }

    public function testRemoveUser(): void
    {
        $scope = new Scope();
        $scope->setUser(UserDataBag::createFromUserIdentifier('unique_id'));

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNotNull($event->getUser());

        $scope->removeUser();

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getUser());
    }

    public function testSetFingerprint(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getFingerprint());

        $scope->setFingerprint(['foo', 'bar']);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo', 'bar'], $event->getFingerprint());
    }

    public function testSetLevel(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getLevel());

        $scope->setLevel(Severity::debug());

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEquals(Severity::debug(), $event->getLevel());
    }

    public function testAddBreadcrumb(): void
    {
        $scope = new Scope();
        $scope->bindClient($this->createClientWithOptions());
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getBreadcrumbs());

        $scope->addBreadcrumb($breadcrumb1);
        $scope->addBreadcrumb($breadcrumb2);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([$breadcrumb1, $breadcrumb2], $event->getBreadcrumbs());

        $scope->addBreadcrumb($breadcrumb3, 2);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([$breadcrumb2, $breadcrumb3], $event->getBreadcrumbs());
    }

    public function testClearBreadcrumbs(): void
    {
        $scope = new Scope();
        $scope->bindClient($this->createClientWithOptions());

        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));
        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNotEmpty($event->getBreadcrumbs());

        $scope->clearBreadcrumbs();

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertEmpty($event->getBreadcrumbs());
    }

    public function testAddEventProcessor(): void
    {
        $callback1Called = false;
        $callback2Called = false;
        $callback3Called = false;

        $event = Event::createEvent();
        $scope = new Scope();

        $scope->addEventProcessor(function (Event $eventArg) use (&$callback1Called, $callback2Called, $callback3Called): ?Event {
            $this->assertFalse($callback2Called);
            $this->assertFalse($callback3Called);

            $callback1Called = true;

            return $eventArg;
        });

        $this->assertSame($event, $scope->applyToEvent($event));
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

        $this->assertNull($scope->applyToEvent($event));
        $this->assertTrue($callback2Called);
        $this->assertFalse($callback3Called);
    }

    public function testEventProcessorReceivesTheEventAndEventHint(): void
    {
        $event = Event::createEvent();
        $scope = new Scope();
        $hint = new EventHint();

        $processorCalled = false;
        $processorReceivedHint = null;

        $scope->addEventProcessor(static function (Event $eventArg, EventHint $hint) use (&$processorCalled, &$processorReceivedHint): ?Event {
            $processorCalled = true;
            $processorReceivedHint = $hint;

            return $eventArg;
        });

        $this->assertSame($event, $scope->applyToEvent($event, $hint));
        $this->assertSame($hint, $processorReceivedHint);
        $this->assertTrue($processorCalled);
    }

    public function testClear(): void
    {
        $scope = new Scope();
        $scope->bindClient($this->createClientWithOptions());
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $scope->setLevel(Severity::info());
        $scope->addBreadcrumb($breadcrumb);
        $scope->setFingerprint(['foo']);
        $scope->setExtras(['foo' => 'bar']);
        $scope->setTags(['bar' => 'foo']);
        $scope->addFeatureFlag('feature', true);
        $scope->setUser(UserDataBag::createFromUserIdentifier('unique_id'));
        $scope->clear();

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getLevel());
        $this->assertEmpty($event->getBreadcrumbs());
        $this->assertEmpty($event->getFingerprint());
        $this->assertEmpty($event->getExtra());
        $this->assertEmpty($event->getTags());
        $this->assertEmpty($event->getUser());
        $this->assertArrayNotHasKey('flags', $event->getContexts());
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

        $scope = new Scope();
        $scope->bindClient($this->createClientWithOptions());
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

        $this->assertSame($event, $scope->applyToEvent($event));
        $this->assertTrue($event->getLevel()->isEqualTo(Severity::warning()));
        $this->assertSame(['foo'], $event->getFingerprint());
        $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        $this->assertSame(['foo' => 'bar'], $event->getTags());
        $this->assertSame(['bar' => 'foo'], $event->getExtra());
        $this->assertSame($user, $event->getUser());
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

    /**
     * @dataProvider eventWithLogCountProvider
     */
    public function testAttachmentsAppliedForType(Event $event, int $attachmentCount): void
    {
        $scope = new Scope();
        $scope->bindClient($this->createClientWithOptions());
        $scope->addAttachment(Attachment::fromBytes('test', 'abcde'));
        $scope->applyToEvent($event);
        $this->assertCount($attachmentCount, $event->getAttachments());
    }

    public function eventWithLogCountProvider(): \Generator
    {
        yield 'event' => [Event::createEvent(), 1];
        yield 'transaction' => [Event::createTransaction(), 1];
        yield 'check-in' => [Event::createCheckIn(), 0];
        yield 'logs' => [Event::createLogs(), 0];
    }
}
