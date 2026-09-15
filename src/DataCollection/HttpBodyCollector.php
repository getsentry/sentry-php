<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Sentry\Exception\JsonException;
use Sentry\Options;
use Sentry\Util\JSON;

/**
 * Collects structured HTTP bodies without invoking application serializers.
 */
final class HttpBodyCollector
{
    public const MAX_BODY_LENGTH = 100000;

    private const JSON_DEPTH = 512;

    /**
     * This constant represents the size limit in bytes beyond which the body
     * of the request is not captured when the `max_request_body_size` option
     * is set to `small`.
     */
    private const REQUEST_BODY_SMALL_MAX_CONTENT_LENGTH = 10 ** 3;

    /**
     * This constant represents the size limit in bytes beyond which the body
     * of the request is not captured when the `max_request_body_size` option
     * is set to `medium`.
     */
    private const REQUEST_BODY_MEDIUM_MAX_CONTENT_LENGTH = 10 ** 4;

    /**
     * This constant is a map of maximum allowed sizes for each value of the
     * `max_request_body_size` option.
     */
    private const MAX_REQUEST_BODY_SIZE_OPTION_TO_MAX_LENGTH_MAP = [
        'never' => 0,
        'small' => self::REQUEST_BODY_SMALL_MAX_CONTENT_LENGTH,
        'medium' => self::REQUEST_BODY_MEDIUM_MAX_CONTENT_LENGTH,
        'always' => \PHP_INT_MAX,
    ];

    private function __construct()
    {
    }

    public static function getMaxBodyLength(DataCollectionPolicy $policy, string $bodyType): int
    {
        $options = $policy->getOptions();
        $collection = $policy->getDataCollection();
        if ($options === null || $collection === null || !\in_array($bodyType, $collection->getHttpBodies(), true)) {
            return 0;
        }

        if ($bodyType === DataCollectionOptions::HTTP_BODY_INCOMING_REQUEST || $bodyType === DataCollectionOptions::HTTP_BODY_OUTGOING_REQUEST) {
            return min(self::MAX_BODY_LENGTH, self::MAX_REQUEST_BODY_SIZE_OPTION_TO_MAX_LENGTH_MAP[$options->getMaxRequestBodySize()] ?? 0);
        }

        return self::MAX_BODY_LENGTH;
    }

    /**
     * Byte limits apply to raw strings. Integrations supplying parsed arrays
     * should check the original body length when available; arrays are never
     * serialized just to measure their size.
     *
     * @param mixed $body
     *
     * @return array<array-key, mixed>|string|null Null means omission
     */
    public static function collect(DataCollectionPolicy $policy, string $bodyType, $body, string $contentType = '')
    {
        $limit = self::getMaxBodyLength($policy, $bodyType);
        if ($limit === 0) {
            return null;
        }

        if (\is_array($body)) {
            $body = self::normalize($body, 0);
        } elseif (\is_string($body)) {
            if ($body === '' || \strlen($body) > $limit) {
                return null;
            }

            $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
            if ($mediaType === 'application/json' || preg_match('{^application/[^/;\s]+\+json$}', $mediaType) === 1) {
                try {
                    /** @mago-ignore analysis:mixed-assignment */
                    $body = JSON::decode($body);
                } catch (JsonException $exception) {
                    return KeyValueDataFilter::FILTERED_VALUE;
                }
                if (!\is_array($body)) {
                    return KeyValueDataFilter::FILTERED_VALUE;
                }
            } elseif ($mediaType === 'application/x-www-form-urlencoded') {
                $body = Query::parse($body);
            } else {
                return KeyValueDataFilter::FILTERED_VALUE;
            }
        } else {
            return null;
        }

        return KeyValueDataFilter::filterKeyValueData($body, ['mode' => 'denyList', 'terms' => []]);
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    private static function normalize(array $body, int $depth): array
    {
        $normalized = [];
        /** @mago-ignore analysis:mixed-assignment */
        foreach ($body as $key => $value) {
            if (\is_array($value)) {
                $value = $depth >= self::JSON_DEPTH - 2 ? KeyValueDataFilter::FILTERED_VALUE : self::normalize($value, $depth + 1);
            } elseif (($value !== null && !\is_scalar($value)) || (\is_float($value) && !is_finite($value))) {
                $value = KeyValueDataFilter::FILTERED_VALUE;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Collects a PSR-7 message body without consuming its stream.
     *
     * @return array<array-key, mixed>|string|null Null means omission
     */
    public static function collectPsr7Message(DataCollectionPolicy $policy, string $bodyType, MessageInterface $message)
    {
        $limit = self::getMaxBodyLength($policy, $bodyType);
        $length = $message->getHeaderLine('Content-Length');
        if ($limit === 0 || (is_numeric($length) && (float) $length > $limit)) {
            return null;
        }

        $stream = $message->getBody();
        $size = $stream->getSize();
        if ($size !== null && $size > $limit) {
            return null;
        }

        $body = Psr7BodyReader::read($stream, $limit);
        if ($body === null) {
            return null;
        }

        return self::collect($policy, $bodyType, $body, $message->getHeaderLine('Content-Type'));
    }

    /**
     * Collects event request data, preserving the historical behavior in legacy mode.
     * New collection only reads seekable streams, restoring their original position.
     *
     * @return mixed
     */
    public static function collectServerRequest(DataCollectionPolicy $policy, ServerRequestInterface $request)
    {
        $options = $policy->getOptions();
        if ($options === null) {
            return null;
        }

        if ($policy->isLegacyMode()) {
            /** @mago-ignore analysis:mixed-assignment */
            $body = self::captureRequestBody($options, $request);

            return empty($body) ? null : $body;
        }

        $limit = self::getMaxBodyLength($policy, DataCollectionOptions::HTTP_BODY_INCOMING_REQUEST);
        $length = $request->getHeaderLine('Content-Length');
        if ($limit === 0 || (is_numeric($length) && (float) $length > $limit)) {
            return null;
        }

        $body = $request->getParsedBody();
        if ($body !== null) {
            return self::collect($policy, DataCollectionOptions::HTTP_BODY_INCOMING_REQUEST, $body);
        }

        return self::collectPsr7Message($policy, DataCollectionOptions::HTTP_BODY_INCOMING_REQUEST, $request);
    }

    /**
     * Gets the decoded body of the request, if available. If the Content-Type
     * header contains "application/json" then the content is decoded and if
     * the parsing fails then the raw data is returned. If there are submitted
     * fields or files, all of their information are parsed and returned.
     *
     * @param Options                $options The options of the client
     * @param ServerRequestInterface $request The server request
     *
     * @return mixed
     */
    private static function captureRequestBody(Options $options, ServerRequestInterface $request)
    {
        $maxRequestBodySize = $options->getMaxRequestBodySize();
        $requestBodySize = (int) $request->getHeaderLine('Content-Length');

        if (!self::isRequestBodySizeWithinReadBounds($requestBodySize, $maxRequestBodySize)) {
            return null;
        }

        $requestData = $request->getParsedBody();
        $requestData = array_replace(
            self::parseUploadedFiles($request->getUploadedFiles()),
            \is_array($requestData) ? $requestData : []
        );

        if (!empty($requestData)) {
            return $requestData;
        }

        $requestBody = '';
        $maxLength = self::MAX_REQUEST_BODY_SIZE_OPTION_TO_MAX_LENGTH_MAP[$maxRequestBodySize];

        if ($maxLength > 0) {
            $stream = $request->getBody();
            while ($maxLength > 0 && !$stream->eof()) {
                if ('' === $buffer = $stream->read(min($maxLength, self::REQUEST_BODY_MEDIUM_MAX_CONTENT_LENGTH))) {
                    break;
                }
                $requestBody .= $buffer;
                $maxLength -= \strlen($buffer);
            }
        }

        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            try {
                return JSON::decode($requestBody);
            } catch (JsonException $exception) {
                // Fallback to returning the raw data from the request body
            }
        }

        return $requestBody;
    }

    /**
     * Create an array with the same structure as $uploadedFiles, but replacing
     * each UploadedFileInterface with an array of info.
     *
     * @param array<array-key, mixed> $uploadedFiles The uploaded files info from a PSR-7 server request
     *
     * @return array<array-key, mixed>
     */
    private static function parseUploadedFiles(array $uploadedFiles): array
    {
        $result = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($uploadedFiles as $key => $item) {
            if ($item instanceof UploadedFileInterface) {
                $result[$key] = [
                    'client_filename' => $item->getClientFilename(),
                    'client_media_type' => $item->getClientMediaType(),
                    'size' => $item->getSize(),
                ];
            } elseif (\is_array($item)) {
                $result[$key] = self::parseUploadedFiles($item);
            } else {
                throw new \UnexpectedValueException(\sprintf('Expected either an object implementing the "%s" interface or an array. Got: "%s".', UploadedFileInterface::class, \is_object($item) ? \get_class($item) : \gettype($item)));
            }
        }

        return $result;
    }

    private static function isRequestBodySizeWithinReadBounds(int $requestBodySize, string $maxRequestBodySize): bool
    {
        if ($requestBodySize <= 0) {
            return false;
        }

        if ($maxRequestBodySize === 'none' || $maxRequestBodySize === 'never') {
            return false;
        }

        if ($maxRequestBodySize === 'small' && $requestBodySize > self::REQUEST_BODY_SMALL_MAX_CONTENT_LENGTH) {
            return false;
        }

        if ($maxRequestBodySize === 'medium' && $requestBodySize > self::REQUEST_BODY_MEDIUM_MAX_CONTENT_LENGTH) {
            return false;
        }

        return true;
    }
}
