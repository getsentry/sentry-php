<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Exception\JsonException;
use Sentry\Options;
use Sentry\Util\JSON;

final class HttpBodyCollector
{
    public const MAX_HTTP_BODY_LENGTH = 100000;

    private const MAX_REQUEST_BODY_LENGTH = [
        'never' => 0,
        'small' => 1000,
        'medium' => 10000,
        'always' => self::MAX_HTTP_BODY_LENGTH,
    ];

    private function __construct()
    {
    }

    /**
     * @param mixed $body
     *
     * @return array<array-key, mixed>|string|null
     */
    public static function collect(Options $options, HttpMessageType $messageType, $body, string $contentType = '', ?int $bodyLength = null)
    {
        return self::collectSource($options, $messageType, new InMemoryHttpBodySource($body, $contentType, $bodyLength));
    }

    /**
     * @return mixed
     */
    public static function collectServerRequest(Options $options, ServerRequestInterface $request)
    {
        return self::collectSource($options, HttpMessageType::incomingRequest(), new ServerRequestBodySource($request));
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    public static function collectPsr7Message(Options $options, HttpMessageType $messageType, MessageInterface $message)
    {
        return self::collectSource($options, $messageType, new Psr7MessageBodySource($message));
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    public static function collectSource(Options $options, HttpMessageType $messageType, HttpBodySourceInterface $source)
    {
        $limit = self::getBodyLimit($options, $messageType);
        if ($limit === null) {
            return null;
        }

        $length = $source->getKnownLength();
        if ($length !== null && $length > $limit) {
            return null;
        }

        $body = $source->read($limit);
        if ($body === null || $body === '') {
            return null;
        }

        $isParsedBody = !\is_string($body);

        if (\is_string($body)) {
            if (\strlen($body) > $limit) {
                return null;
            }

            $body = self::decodeBody($body, $source->getContentType());
            if ($body === null) {
                return KeyValueDataFilter::FILTERED_VALUE;
            }
        }

        $filtered = (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData($body);

        // We might receive a body that is already parsed, to determine length we have to serialize it ourselves
        // and then count it
        if ($isParsedBody && $length === null) {
            $encodedLength = self::getEncodedLength($filtered);

            // A body that cannot be encoded is treated like a body that cannot be parsed
            if ($encodedLength === null) {
                return KeyValueDataFilter::FILTERED_VALUE;
            }

            if ($encodedLength > $limit) {
                return null;
            }
        }

        return $filtered;
    }

    /**
     * @return int|null `null` if the body is not collected
     */
    private static function getBodyLimit(Options $options, HttpMessageType $messageType): ?int
    {
        if (!$options->getDataCollection()->shouldCollectHttpBody($messageType)) {
            return null;
        }

        if (!$messageType->isRequest()) {
            return self::MAX_HTTP_BODY_LENGTH;
        }

        $limit = self::MAX_REQUEST_BODY_LENGTH[$options->getMaxRequestBodySize()] ?? 0;

        return $limit > 0 ? $limit : null;
    }

    /**
     * @param array<array-key, mixed>|null $data
     */
    private static function getEncodedLength(?array $data): ?int
    {
        try {
            return \strlen(JSON::encode($data));
        } catch (JsonException $exception) {
            return null;
        }
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decodeBody(string $body, string $contentType): ?array
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        if ($mediaType === 'application/x-www-form-urlencoded') {
            return Query::parse($body);
        }

        if ($mediaType !== 'application/json' && preg_match('{^application/[^/;\s]+\+json$}', $mediaType) !== 1) {
            return null;
        }

        try {
            /** @mago-ignore analysis:mixed-assignment */
            $decoded = JSON::decode($body);

            return \is_array($decoded) ? $decoded : null;
        } catch (JsonException $exception) {
            return null;
        }
    }
}
