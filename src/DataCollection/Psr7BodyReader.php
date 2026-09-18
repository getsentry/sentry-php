<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Psr\Http\Message\StreamInterface;

/**
 * Reads a PSR-7 body and restores it's original position after reading finished.
 * It will only read body streams that are seekable, otherwise it could consume the body
 * for the application itself.
 */
final class Psr7BodyReader
{
    private function __construct()
    {
    }

    public static function read(StreamInterface $stream, int $limit): ?string
    {
        if ($limit <= 0 || !$stream->isReadable() || !$stream->isSeekable()) {
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

        return \strlen($body) > $limit ? null : $body;
    }
}
