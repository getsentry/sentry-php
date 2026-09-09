<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Query;
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

    public static function getMaxBodyLength(?Options $options, string $bodyType): int
    {
        $collection = DataCollectionOptions::fromOptions($options);
        if ($options === null || $collection === null || !\in_array($bodyType, $collection->getHttpBodies(), true)) {
            return 0;
        }

        if ($bodyType === 'incomingRequest' || $bodyType === 'outgoingRequest') {
            return min(self::MAX_BODY_LENGTH, self::MAX_REQUEST_BODY_SIZE_OPTION_TO_MAX_LENGTH_MAP[$options->getMaxRequestBodySize()] ?? 0);
        }

        return self::MAX_BODY_LENGTH;
    }

    /**
     * @param mixed $body
     *
     * @return array<array-key, mixed>|string|null Null means omission
     */
    public static function collect(?Options $options, string $bodyType, $body, string $contentType = '')
    {
        $limit = self::getMaxBodyLength($options, $bodyType);
        if ($limit === 0 || $body === null || $body === '') {
            return null;
        }

        if (\is_string($body)) {
            if (\strlen($body) > $limit) {
                return null;
            }
            $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
            if ($mediaType === 'application/json' || preg_match('{^application/[^/;\s]+\+json$}', $mediaType) === 1) {
                /** @mago-ignore analysis:mixed-assignment */
                $body = json_decode($body, true, self::JSON_DEPTH);
                if (json_last_error() !== \JSON_ERROR_NONE || !\is_array($body)) {
                    return KeyValueDataFilter::FILTERED_VALUE;
                }
            } elseif ($mediaType === 'application/x-www-form-urlencoded') {
                $body = Query::parse($body);
            } else {
                return KeyValueDataFilter::FILTERED_VALUE;
            }
        } elseif (\is_array($body)) {
            $body = self::normalize($body, 0);
            try {
                if (\strlen(JSON::encode($body)) > $limit) {
                    return null;
                }
            } catch (JsonException $exception) {
                return KeyValueDataFilter::FILTERED_VALUE;
            }
        } else {
            return KeyValueDataFilter::FILTERED_VALUE;
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
     * Collects event request data, preserving the historical behavior in legacy mode.
     * New collection only reads seekable streams, restoring their original position.
     *
     * @return mixed
     */
    public static function collectServerRequest(Options $options, ServerRequestInterface $request)
    {
        if ($options->getDataCollection() === null) {
            /** @mago-ignore analysis:mixed-assignment */
            $body = self::captureRequestBody($options, $request);

            return empty($body) ? null : $body;
        }

        $limit = self::getMaxBodyLength($options, 'incomingRequest');
        $length = $request->getHeaderLine('Content-Length');
        if ($limit === 0 || (is_numeric($length) && (float) $length > $limit)) {
            return null;
        }
        $body = $request->getParsedBody();
        if ($body !== null) {
            return self::collect($options, 'incomingRequest', $body);
        }

        $stream = $request->getBody();
        if (!$stream->isReadable() || !$stream->isSeekable()) {
            return null;
        }

        try {
            $position = $stream->tell();
            try {
                $stream->rewind();
                $body = '';
                while (\strlen($body) <= $limit && !$stream->eof()) {
                    $buffer = $stream->read(min(10000, $limit + 1 - \strlen($body)));
                    if ($buffer === '') {
                        break;
                    }
                    $body .= $buffer;
                }
            } finally {
                $stream->seek($position);
            }
        } catch (\RuntimeException $exception) {
            return null;
        }

        return self::collect($options, 'incomingRequest', $body, $request->getHeaderLine('Content-Type'));
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
