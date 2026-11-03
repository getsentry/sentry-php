<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Sentry\Exception\JsonException;
use Sentry\Util\JSON;

/**
 * @internal
 */
final class LegacyRequestBodyCollector
{
    private function __construct()
    {
    }

    /**
     * @return mixed
     */
    public static function collect(DataCollectionPolicy $policy, ServerRequestInterface $request)
    {
        $limit = $policy->getLegacyRequestBodyLimit();
        if ($limit === null) {
            return null;
        }

        $length = (int) $request->getHeaderLine('Content-Length');
        if ($length <= 0 || ($limit !== -1 && $length > $limit)) {
            return null;
        }

        $body = $request->getParsedBody();
        $body = array_replace(
            self::parseUploadedFiles($request->getUploadedFiles()),
            \is_array($body) ? $body : []
        );
        if ($body !== []) {
            return $body;
        }

        $body = Psr7BodyReader::read($request->getBody(), $limit, true);
        if ($body === null) {
            return null;
        }

        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            try {
                /** @mago-ignore analysis:mixed-assignment */
                $body = JSON::decode($body);
            } catch (JsonException $exception) {
            }
        }

        return empty($body) ? null : $body;
    }

    /**
     * @param array<array-key, mixed> $uploadedFiles
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
}
