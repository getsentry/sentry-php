<?php

declare(strict_types=1);

namespace Sentry\Serializer;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventType;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Options;
use Sentry\Profiling\Profile;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\Span;
use Sentry\Tracing\TransactionMetadata;
use Sentry\Util\JSON;

/**
 * This is a simple implementation of a serializer that takes in input an event
 * object and returns a serialized string ready to be sent off to Sentry.
 *
 * @internal
 */
final class PayloadSerializer implements PayloadSerializerInterface
{
    /**
     * @var Options The SDK client options
     */
    private $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(Event $event): string
    {
        if (EventType::transaction() === $event->getType()) {
            $transactionEnvelope = $this->serializeAsEnvelope($event);

            // Attach a new envelope item containing the profile data
            if ($event->getSdkMetadata('profile') !== null) {
                $profileEnvelope = $this->seralizeProfileAsEnvelope($event);
                if ($profileEnvelope !== null) {
                    return sprintf("%s\n%s", $transactionEnvelope, $profileEnvelope);
                }
            }

            return $transactionEnvelope;
        }

        if (EventType::checkIn() === $event->getType()) {
            return $this->serializeAsEnvelope($event);
        }

        if ($this->options->isTracingEnabled()) {
            return $this->serializeAsEnvelope($event);
        }

        return $this->serializeAsEvent($event);
    }

    private function serializeAsEvent(Event $event): string
    {
        $result = $this->toArray($event);

        return JSON::encode($result);
    }

    private function serializeAsCheckInEvent(Event $event): string
    {
        $result = [];

        $checkIn = $event->getCheckIn();
        if ($checkIn !== null) {
            $result = [
                'check_in_id' => $checkIn->getId(),
                'monitor_slug' => $checkIn->getMonitorSlug(),
                'status' => (string) $checkIn->getStatus(),
                'duration' => $checkIn->getDuration(),
                'release' => $checkIn->getRelease(),
                'environment' => $checkIn->getEnvironment(),
            ];

            if ($checkIn->getMonitorConfig() !== null) {
                $result['monitor_config'] = $checkIn->getMonitorConfig()->toArray();
            }

            if (!empty($event->getContexts()['trace'])) {
                $result['contexts']['trace'] = $event->getContexts()['trace'];
            }
        }

        return JSON::encode($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Event $event): array
    {
        $result = [
            'event_id' => (string) $event->getId(),
            'timestamp' => $event->getTimestamp(),
            'platform' => 'php',
            'sdk' => [
                'name' => $event->getSdkIdentifier(),
                'version' => $event->getSdkVersion(),
            ],
        ];

        if ($event->getStartTimestamp() !== null) {
            $result['start_timestamp'] = $event->getStartTimestamp();
        }

        if ($event->getLevel() !== null) {
            $result['level'] = (string) $event->getLevel();
        }

        if ($event->getLogger() !== null) {
            $result['logger'] = $event->getLogger();
        }

        if ($event->getTransaction() !== null) {
            $result['transaction'] = $event->getTransaction();
        }

        if ($event->getServerName() !== null) {
            $result['server_name'] = $event->getServerName();
        }

        if ($event->getRelease() !== null) {
            $result['release'] = $event->getRelease();
        }

        if ($event->getEnvironment() !== null) {
            $result['environment'] = $event->getEnvironment();
        }

        if (!empty($event->getFingerprint())) {
            $result['fingerprint'] = $event->getFingerprint();
        }

        if (!empty($event->getModules())) {
            $result['modules'] = $event->getModules();
        }

        if (!empty($event->getExtra())) {
            $result['extra'] = $event->getExtra();
        }

        if (!empty($event->getTags())) {
            $result['tags'] = $event->getTags();
        }

        $user = $event->getUser();

        if ($user !== null) {
            $result['user'] = array_merge($user->getMetadata(), [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'ip_address' => $user->getIpAddress(),
                'segment' => $user->getSegment(),
            ]);
        }

        $osContext = $event->getOsContext();
        $runtimeContext = $event->getRuntimeContext();

        if ($osContext !== null) {
            $result['contexts']['os'] = [
                'name' => $osContext->getName(),
                'version' => $osContext->getVersion(),
                'build' => $osContext->getBuild(),
                'kernel_version' => $osContext->getKernelVersion(),
            ];
        }

        if ($runtimeContext !== null) {
            $result['contexts']['runtime'] = [
                'name' => $runtimeContext->getName(),
                'version' => $runtimeContext->getVersion(),
            ];
        }

        if (!empty($event->getContexts())) {
            $result['contexts'] = array_merge($result['contexts'] ?? [], $event->getContexts());
        }

        if (!empty($event->getBreadcrumbs())) {
            $result['breadcrumbs']['values'] = array_map([$this, 'serializeBreadcrumb'], $event->getBreadcrumbs());
        }

        if (!empty($event->getRequest())) {
            $result['request'] = $event->getRequest();
        }

        if ($event->getMessage() !== null) {
            if (empty($event->getMessageParams())) {
                $result['message'] = $event->getMessage();
            } else {
                $result['message'] = [
                    'message' => $event->getMessage(),
                    'params' => $event->getMessageParams(),
                    'formatted' => $event->getMessageFormatted() ?? vsprintf($event->getMessage(), $event->getMessageParams()),
                ];
            }
        }

        $exceptions = $event->getExceptions();

        for ($i = \count($exceptions) - 1; $i >= 0; --$i) {
            $result['exception']['values'][] = $this->serializeException($exceptions[$i]);
        }

        if (EventType::transaction() === $event->getType()) {
            $result['spans'] = array_values(array_map([$this, 'serializeSpan'], $event->getSpans()));

            $transactionMetadata = $event->getSdkMetadata('transaction_metadata');
            if ($transactionMetadata instanceof TransactionMetadata) {
                $result['transaction_info']['source'] = (string) $transactionMetadata->getSource();
            }
        }

        /**
         * In case of error events, with tracing being disabled, we set the Replay ID
         * as a context into the payload.
         */
        if (
            EventType::event() === $event->getType()
            && !$this->options->isTracingEnabled()
        ) {
            $dynamicSamplingContext = $event->getSdkMetadata('dynamic_sampling_context');
            if ($dynamicSamplingContext instanceof DynamicSamplingContext) {
                $replayId = $dynamicSamplingContext->get('replay_id');

                if ($replayId !== null) {
                    $result['contexts']['replay'] = [
                        'replay_id' => $replayId,
                    ];
                }
            }
        }

        $stacktrace = $event->getStacktrace();

        if ($stacktrace !== null) {
            $result['stacktrace'] = [
                'frames' => array_map([$this, 'serializeStacktraceFrame'], $stacktrace->getFrames()),
            ];
        }

        return $result;
    }

    private function serializeAsEnvelope(Event $event): string
    {
        // @see https://develop.sentry.dev/sdk/envelopes/#envelope-headers
        $envelopeHeader = [
            'event_id' => (string) $event->getId(),
            'sent_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'dsn' => (string) $this->options->getDsn(),
            'sdk' => [
                'name' => $event->getSdkIdentifier(),
                'version' => $event->getSdkVersion(),
            ],
        ];

        $dynamicSamplingContext = $event->getSdkMetadata('dynamic_sampling_context');

        if ($dynamicSamplingContext instanceof DynamicSamplingContext) {
            $entries = $dynamicSamplingContext->getEntries();

            if (!empty($entries)) {
                $envelopeHeader['trace'] = $entries;
            }
        }

        $itemHeader = [
            'type' => (string) $event->getType(),
            'content_type' => 'application/json',
        ];

        if (EventType::checkIn() === $event->getType()) {
            $seralizedEvent = $this->serializeAsCheckInEvent($event);
        } else {
            $seralizedEvent = $this->serializeAsEvent($event);
        }

        return sprintf("%s\n%s\n%s", JSON::encode($envelopeHeader), JSON::encode($itemHeader), $seralizedEvent);
    }

    private function seralizeProfileAsEnvelope(Event $event): ?string
    {
        $itemHeader = [
            'type' => 'profile',
            'content_type' => 'application/json',
        ];

        $profile = $event->getSdkMetadata('profile');
        if (!$profile instanceof Profile) {
            return null;
        }

        $profileData = $profile->getFormattedData($event);
        if ($profileData === null) {
            return null;
        }

        return sprintf("%s\n%s", JSON::encode($itemHeader), JSON::encode($profileData));
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-return array{
     *     type: string,
     *     category: string,
     *     level: string,
     *     timestamp: float,
     *     message?: string,
     *     data?: array<string, mixed>
     * }
     */
    private function serializeBreadcrumb(Breadcrumb $breadcrumb): array
    {
        $result = [
            'type' => $breadcrumb->getType(),
            'category' => $breadcrumb->getCategory(),
            'level' => $breadcrumb->getLevel(),
            'timestamp' => $breadcrumb->getTimestamp(),
        ];

        if ($breadcrumb->getMessage() !== null) {
            $result['message'] = $breadcrumb->getMessage();
        }

        if (!empty($breadcrumb->getMetadata())) {
            $result['data'] = $breadcrumb->getMetadata();
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-return array{
     *     type: string,
     *     value: string,
     *     stacktrace?: array{
     *         frames: array<array<string, mixed>>
     *     },
     *     mechanism?: array{
     *         type: string,
     *         handled: boolean,
     *         data?: array<string, mixed>
     *     }
     * }
     */
    private function serializeException(ExceptionDataBag $exception): array
    {
        $exceptionMechanism = $exception->getMechanism();
        $exceptionStacktrace = $exception->getStacktrace();
        $result = [
            'type' => $exception->getType(),
            'value' => $exception->getValue(),
        ];

        if ($exceptionStacktrace !== null) {
            $result['stacktrace'] = [
                'frames' => array_map([$this, 'serializeStacktraceFrame'], $exceptionStacktrace->getFrames()),
            ];
        }

        if ($exceptionMechanism !== null) {
            $result['mechanism'] = [
                'type' => $exceptionMechanism->getType(),
                'handled' => $exceptionMechanism->isHandled(),
            ];

            if ($exceptionMechanism->getData() !== []) {
                $result['mechanism']['data'] = $exceptionMechanism->getData();
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-return array{
     *     filename: string,
     *     lineno: int,
     *     in_app: bool,
     *     abs_path?: string,
     *     function?: string,
     *     raw_function?: string,
     *     pre_context?: string[],
     *     context_line?: string,
     *     post_context?: string[],
     *     vars?: array<string, mixed>
     * }
     */
    private function serializeStacktraceFrame(Frame $frame): array
    {
        $result = [
            'filename' => $frame->getFile(),
            'lineno' => $frame->getLine(),
            'in_app' => $frame->isInApp(),
        ];

        if ($frame->getAbsoluteFilePath() !== null) {
            $result['abs_path'] = $frame->getAbsoluteFilePath();
        }

        if ($frame->getFunctionName() !== null) {
            $result['function'] = $frame->getFunctionName();
        }

        if ($frame->getRawFunctionName() !== null) {
            $result['raw_function'] = $frame->getRawFunctionName();
        }

        if (!empty($frame->getPreContext())) {
            $result['pre_context'] = $frame->getPreContext();
        }

        if ($frame->getContextLine() !== null) {
            $result['context_line'] = $frame->getContextLine();
        }

        if (!empty($frame->getPostContext())) {
            $result['post_context'] = $frame->getPostContext();
        }

        if (!empty($frame->getVars())) {
            $result['vars'] = $frame->getVars();
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-return array{
     *     span_id: string,
     *     trace_id: string,
     *     parent_span_id?: string,
     *     start_timestamp: float,
     *     timestamp?: float,
     *     status?: string,
     *     description?: string,
     *     op?: string,
     *     data?: array<string, mixed>,
     *     tags?: array<string, string>
     * }
     */
    private function serializeSpan(Span $span): array
    {
        $result = [
            'span_id' => (string) $span->getSpanId(),
            'trace_id' => (string) $span->getTraceId(),
            'start_timestamp' => $span->getStartTimestamp(),
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
}
