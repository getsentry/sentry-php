<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Serializer\Traits\BreadcrumbSeralizerTrait;
use Sentry\Util\JSON;

/**
 * @internal
 */
class EventItem implements EnvelopeItemInterface
{
    use BreadcrumbSeralizerTrait;

    public static function toEnvelopeItem(Event $event): string
    {
        $header = [
            'type' => (string) $event->getType(),
            'content_type' => 'application/json',
        ];

        $payload = [
            'timestamp' => $event->getTimestamp(),
            'platform' => 'php',
            'sdk' => [
                'name' => $event->getSdkIdentifier(),
                'version' => $event->getSdkVersion(),
            ],
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

        if ($event->getMessage() !== null) {
            if (empty($event->getMessageParams())) {
                $payload['message'] = $event->getMessage();
            } else {
                $payload['message'] = [
                    'message' => $event->getMessage(),
                    'params' => $event->getMessageParams(),
                    'formatted' => $event->getMessageFormatted() ?? vsprintf($event->getMessage(), $event->getMessageParams()),
                ];
            }
        }

        $exceptions = $event->getExceptions();
        for ($i = \count($exceptions) - 1; $i >= 0; --$i) {
            $payload['exception']['values'][] = self::serializeException($exceptions[$i]);
        }

        $stacktrace = $event->getStacktrace();
        if ($stacktrace !== null) {
            $payload['stacktrace'] = [
                'frames' => array_map([self::class, 'serializeStacktraceFrame'], $stacktrace->getFrames()),
            ];
        }

        return sprintf("%s\n%s", JSON::encode($header), JSON::encode($payload));
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
    protected static function serializeException(ExceptionDataBag $exception): array
    {
        $exceptionMechanism = $exception->getMechanism();
        $exceptionStacktrace = $exception->getStacktrace();
        $result = [
            'type' => $exception->getType(),
            'value' => $exception->getValue(),
        ];

        if ($exceptionStacktrace !== null) {
            $result['stacktrace'] = [
                'frames' => array_map([self::class, 'serializeStacktraceFrame'], $exceptionStacktrace->getFrames()),
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
    protected static function serializeStacktraceFrame(Frame $frame): array
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
}
