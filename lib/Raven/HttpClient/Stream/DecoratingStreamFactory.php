<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\HttpClient\Stream;

use Http\Message\Encoding\CompressStream;
use Http\Message\StreamFactory;
use Raven\HttpClient\Stream\Encoding\Base64EncodingStream;

/**
 * This factory decorates another stream with one capable of encoding the data
 * to the base 64 format.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class DecoratingStreamFactory implements StreamFactory
{
    /**
     * @var StreamFactory The decorated stream factory
     */
    private $streamFactory;

    /**
     * Constructor.
     *
     * @param StreamFactory $streamFactory The decorated stream factory
     */
    public function __construct(StreamFactory $streamFactory)
    {
        $this->streamFactory = $streamFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createStream($body = null)
    {
        $stream = $this->streamFactory->createStream($body);

        return new Base64EncodingStream(new CompressStream($stream));
    }
}
