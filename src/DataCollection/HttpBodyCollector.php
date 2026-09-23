<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Exception\JsonException;
use Sentry\Util\JSON;

final class HttpBodyCollector
{
    private function __construct()
    {
    }

    /**
     * @param mixed $body
     *
     * @return array<array-key, mixed>|string|null
     */
    public static function collect(DataCollectionPolicy $policy, HttpMessageType $messageType, $body, string $contentType = '', ?int $bodyLength = null)
    {
        return self::collectSource($policy, $messageType, new InMemoryHttpBodySource($body, $contentType, $bodyLength));
    }

    /**
     * @return mixed
     */
    public static function collectServerRequest(DataCollectionPolicy $policy, ServerRequestInterface $request)
    {
        if ($policy->isLegacyMode()) {
            return LegacyRequestBodyCollector::collect($policy, $request);
        }

        return self::collectSource($policy, HttpMessageType::incomingRequest(), new ServerRequestBodySource($request));
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    public static function collectSource(DataCollectionPolicy $policy, HttpMessageType $messageType, HttpBodySourceInterface $source)
    {
        $limit = $policy->getHttpBodyLimit($messageType);
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
