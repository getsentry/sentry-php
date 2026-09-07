<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Query;
use Sentry\Exception\JsonException;
use Sentry\Options;
use Sentry\Util\JSON;

/**
 * Collects bodies already obtained safely by an integration. Never consumes
 * resources or streams, or invokes application serializers.
 *
 * @internal
 */
final class HttpBodyCollector
{
    public const MAX_BODY_LENGTH = 10 ** 5;

    private const MAX_REQUEST_BODY_SIZE_TO_LENGTH = [
        'none' => 0,
        'never' => 0,
        'small' => 10 ** 3,
        'medium' => 10 ** 4,
        'always' => self::MAX_BODY_LENGTH,
    ];

    private function __construct()
    {
    }

    /**
     * @param 'incomingRequest'|'outgoingRequest'|'incomingResponse'|'outgoingResponse' $bodyType
     */
    public static function getMaxBodyLength(Options $options, string $bodyType): int
    {
        $dataCollection = $options->getDataCollection();
        if ($dataCollection === null || !\in_array($bodyType, $dataCollection->getHttpBodies(), true)) {
            return 0;
        }

        return $bodyType === 'incomingRequest' || $bodyType === 'outgoingRequest'
            ? self::MAX_REQUEST_BODY_SIZE_TO_LENGTH[$options->getMaxRequestBodySize()]
            : self::MAX_BODY_LENGTH;
    }

    public static function isSupportedContentType(string $contentType): bool
    {
        return self::getBodyFormat($contentType) !== null;
    }

    /**
     * @return array<array-key, mixed>|null Null means the body is not structured JSON/form data
     */
    public static function parse(string $body, string $contentType): ?array
    {
        $format = self::getBodyFormat($contentType);
        if ($format === null) {
            return null;
        }

        try {
            /** @mago-ignore analysis:mixed-assignment */
            $parsedBody = $format === 'form' ? Query::parse($body) : JSON::decode($body);
        } catch (JsonException $exception) {
            return null;
        }

        return \is_array($parsedBody) ? $parsedBody : null;
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>|null Null means omitted
     */
    public static function collect(array $body): ?array
    {
        $body = self::normalizeArray($body, 0);

        return $body === null ? null : KeyValueDataFilter::filterHttpBodyData($body);
    }

    /**
     * @return 'json'|'form'|null
     */
    private static function getBodyFormat(string $contentType): ?string
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        if ($mediaType === 'application/json' || substr($mediaType, -5) === '+json') {
            return 'json';
        }

        return $mediaType === 'application/x-www-form-urlencoded' ? 'form' : null;
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>|null Null means normalization failed
     */
    private static function normalizeArray(array $body, int $depth): ?array
    {
        if ($depth >= 32) {
            return null;
        }

        $normalized = [];
        /** @mago-ignore analysis:mixed-assignment */
        foreach ($body as $key => $value) {
            if (\is_array($value)) {
                $value = self::normalizeArray($value, $depth + 1);
                if ($value === null) {
                    return null;
                }
            } elseif ($value !== null && !\is_scalar($value)) {
                $value = KeyValueDataFilter::FILTERED_VALUE;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
