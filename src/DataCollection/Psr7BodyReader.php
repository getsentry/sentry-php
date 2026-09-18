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

    /**
     * @param int  $limit    -1 means unlimited
     * @param bool $truncate Return the body up to the limit instead of omitting oversized bodies
     */
    public static function read(StreamInterface $stream, int $limit, bool $truncate = false): ?string
    {
        $unlimited = $limit === -1;
        if ((!$unlimited && $limit <= 0) || !$stream->isReadable() || !$stream->isSeekable()) {
            return null;
        }

        try {
            $position = $stream->tell();
            try {
                $stream->rewind();
                $body = '';
                $readLimit = $truncate ? $limit : $limit + 1;
                while (($unlimited || \strlen($body) < $readLimit) && !$stream->eof()) {
                    $buffer = $stream->read($unlimited ? 10000 : min(10000, $readLimit - \strlen($body)));
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

        return !$unlimited && !$truncate && \strlen($body) > $limit ? null : $body;
    }
}
