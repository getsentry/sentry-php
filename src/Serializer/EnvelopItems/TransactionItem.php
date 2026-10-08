<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Attributes\Attribute;
use Sentry\Attributes\AttributeBag;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Serializer\Traits\BreadcrumbSeralizerTrait;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionMetadata;
use Sentry\Util\JSON;

/**
 * @internal
 *
 * @phpstan-type MetricsSummary array{
 *     min: int|float,
 *     max: int|float,
 *     sum: int|float,
 *     count: int,
 *     tags: array<string>,
 * }
 */
class TransactionItem implements EnvelopeItemInterface
{
    use BreadcrumbSeralizerTrait;

    public static function toEnvelopeItem(Event $event): string
    {
        $transactionSpans = [];
        $genAiSpans = [];

        foreach ($event->getSpans() as $span) {
            if (strpos($span->getOp() ?? '', 'gen_ai.') === 0) {
                $genAiSpans[] = $span;
            } else {
                $transactionSpans[] = $span;
            }
        }

        $header = [
            'type' => (string) EventType::transaction(),
            'content_type' => 'application/json',
        ];

        $payload = [
            'timestamp' => $event->getTimestamp(),
            'platform' => 'php',
            'sdk' => $event->getSdkPayload(),
        ];

        if ($event->getStartTimestamp() !== null) {
            $payload['start_timestamp'] = $event->getStartTimestamp();
        }

        if ($event->getLevel() !== null) {
            $payload['level'] = (string) $event->getLevel();
        }

        if ($event->getTransaction() !== null) {
            $payload['transaction'] = $event->getTransaction();
        }

        if ($event->getServerName() !== null) {
            $payload['server_name'] = $event->getServerName();
        }

        if ($event->getRelease() !== null) {
            $payload['release'] = $event->getRelease();
        }

        if ($event->getEnvironment() !== null) {
            $payload['environment'] = $event->getEnvironment();
        }

        if (!empty($event->getFingerprint())) {
            $payload['fingerprint'] = $event->getFingerprint();
        }

        if (!empty($event->getModules())) {
            $payload['modules'] = $event->getModules();
        }

        if (!empty($event->getExtra())) {
            $payload['extra'] = $event->getExtra();
        }

        if (!empty($event->getTags())) {
            $payload['tags'] = $event->getTags();
        }

        $user = $event->getUser();
        if ($user !== null) {
            $payload['user'] = array_merge($user->getMetadata(), [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'ip_address' => $user->getIpAddress(),
                'segment' => $user->getSegment(),
            ]);
        }

        $osContext = $event->getOsContext();
        if ($osContext !== null) {
            $payload['contexts']['os'] = [
                'name' => $osContext->getName(),
                'version' => $osContext->getVersion(),
                'build' => $osContext->getBuild(),
                'kernel_version' => $osContext->getKernelVersion(),
            ];
        }

        $runtimeContext = $event->getRuntimeContext();
        if ($runtimeContext !== null) {
            $payload['contexts']['runtime'] = [
                'name' => $runtimeContext->getName(),
                'sapi' => $runtimeContext->getSAPI(),
                'version' => $runtimeContext->getVersion(),
            ];
        }

        if (!empty($event->getContexts())) {
            $payload['contexts'] = array_merge($payload['contexts'] ?? [], $event->getContexts());
        }

        if (!empty($event->getBreadcrumbs())) {
            $payload['breadcrumbs']['values'] = array_map([self::class, 'serializeBreadcrumb'], $event->getBreadcrumbs());
        }

        if (!empty($event->getRequest())) {
            $payload['request'] = $event->getRequest();
        }

        $payload['spans'] = array_values(array_map([self::class, 'serializeSpan'], $transactionSpans));

        $transactionMetadata = $event->getSdkMetadata('transaction_metadata');
        if ($transactionMetadata instanceof TransactionMetadata) {
            $payload['transaction_info']['source'] = (string) $transactionMetadata->getSource();
        }

        if (\count($genAiSpans) > 0) {
            $genAi = [];
            $genAi['items'] = array_map(static function (Span $span) use ($event) {
                return self::serializeSpanV2($span, $event);
            }, $genAiSpans);
            $genAi['version'] = 2;

            $genAiHeaders = [
                'type' => 'span',
                'item_count' => \count($genAiSpans),
                'content_type' => 'application/vnd.sentry.items.span.v2+json',
            ];

            return \sprintf("%s\n%s\n%s\n%s", JSON::encode($header), JSON::encode($payload), JSON::encode($genAiHeaders), JSON::encode($genAi));
        }

        return \sprintf("%s\n%s", JSON::encode($header), JSON::encode($payload));
    }

    /**
     * @return array<string, mixed>
     *
     * @phpstan-return array{
     *     span_id: string,
     *     trace_id: string,
     *     parent_span_id?: string,
     *     start_timestamp: float,
     *     timestamp?: float,
     *     status?: string,
     *     description?: string,
     *     op?: string,
     *     origin: string,
     *     data?: array<string, mixed>,
     *     tags?: array<string, string>
     * }
     */
    protected static function serializeSpan(Span $span): array
    {
        $result = [
            'span_id' => (string) $span->getSpanId(),
            'trace_id' => (string) $span->getTraceId(),
            'start_timestamp' => $span->getStartTimestamp(),
            'origin' => $span->getOrigin() ?? 'manual',
        ];

        if ($span->getParentSpanId() !== null) {
            $result['parent_span_id'] = (string) $span->getParentSpanId();
        }

        if ($span->getEndTimestamp() !== null) {
            $result['timestamp'] = $span->getEndTimestamp();
        }

        if ($span->getStatus() !== null) {
            $result['status'] = (string) $span->getStatus();
        }

        if ($span->getDescription() !== null) {
            $result['description'] = $span->getDescription();
        }

        if ($span->getOp() !== null) {
            $result['op'] = $span->getOp();
        }

        if (!empty($span->getData())) {
            $result['data'] = $span->getData();
        }

        if (!empty($span->getTags())) {
            $result['tags'] = $span->getTags();
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     *
     * @phpstan-return array{
     *     trace_id: string,
     *     span_id: string,
     *     name: string|null,
     *     is_segment: false,
     *     start_timestamp: float,
     *     attributes: array<string, mixed>,
     *     status: 'ok'|'error',
     *     end_timestamp?: float|null,
     *     parent_span_id?: string,
     * }
     */
    protected static function serializeSpanV2(Span $span, Event $event): array
    {
        $result = [
            'trace_id' => (string) $span->getTraceId(),
            'span_id' => (string) $span->getSpanId(),
            'name' => $span->getDescription() ?? $span->getOp(),
            'is_segment' => false,
            'start_timestamp' => $span->getStartTimestamp(),
            'attributes' => array_map(static function (Attribute $value) {
                return [
                    'type' => $value->getType(),
                    'value' => $value->getValue(),
                ];
            }, self::collectV2Attributes($span, $event)->all()),
            'status' => 'ok',
        ];
        if ($span->getEndTimestamp() !== null) {
            $result['end_timestamp'] = $span->getEndTimestamp();
        }
        if ($span->getStatus() !== null) {
            $result['status'] = $span->getStatus() === SpanStatus::ok() ? 'ok' : 'error';
        }
        if ($span->getParentSpanId() !== null) {
            $result['parent_span_id'] = (string) $span->getParentSpanId();
        }

        return $result;
    }

    /***
     * @mago-ignore analysis:redundant-null-coalesce
     * @mago-ignore analysis:mixed-assignment
     */
    private static function collectV2Attributes(Span $span, Event $event): AttributeBag
    {
        $attributes = new AttributeBag();
        $attributes->setUnlessNull('sentry.op', $span->getOp());
        $attributes->set('sentry.origin', $span->getOrigin() ?? 'manual');
        $attributes->setUnlessNull('sentry.release', $event->getRelease());
        $attributes->setUnlessNull('sentry.environment', $event->getEnvironment());
        $attributes->setUnlessNull('server.address', $event->getServerName());
        $attributes->setUnlessNull('sentry.segment.name', $event->getTransaction());
        $attributes->set('sentry.sdk.name', $event->getSdkPayload()['name'] ?? null);
        $attributes->set('sentry.sdk.version', $event->getSdkPayload()['version'] ?? null);
        $attributes->set('sentry.segment.id', $event->getContexts()['trace']['span_id'] ?? null);

        foreach ($span->getTags() as $key => $value) {
            $attributes->set($key, $value);
        }

        foreach ($span->getData() as $key => $value) {
            $attributes->set($key, $value);
        }

        $attributes->forget('status');

        return $attributes;
    }
}
