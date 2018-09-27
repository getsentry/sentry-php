<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\HttpClient\Encoding;

use Http\Message\Encoding\FilteredStream;
use Psr\Http\Message\StreamInterface;

/**
 * Stream for encoding to Base 64 (RFC 4648).
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Base64EncodingStream extends FilteredStream
{
    /**
     * {@inheritdoc}
     */
    public function __construct(StreamInterface $stream, $readFilterOptions = null, $writeFilterOptions = null)
    {
        // $readFilterOptions and $writeFilterOptions arguments are overridden
        // because otherwise an error stating that the filter parameter is
        // invalid is thrown when appending the filter to the stream
        parent::__construct($stream, [], []);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        $inputSize = $this->stream->getSize();

        if (null === $inputSize) {
            return $inputSize;
        }

        // See https://stackoverflow.com/questions/1533113/calculate-the-size-to-a-base-64-encoded-message
        $adjustment = (($inputSize % 3) ? (3 - ($inputSize % 3)) : 0);

        return (($inputSize + $adjustment) / 3) * 4;
    }

    /**
     * {@inheritdoc}
     */
    protected function readFilter()
    {
        return 'convert.base64-encode';
    }

    /**
     * {@inheritdoc}
     */
    protected function writeFilter()
    {
        return 'convert.base64-decode';
    }
}
