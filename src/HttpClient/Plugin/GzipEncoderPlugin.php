<?php

declare(strict_types=1);

namespace Sentry\HttpClient\Plugin;

use Http\Client\Common\Plugin as PluginInterface;
use Http\Message\StreamFactory;
use Http\Promise\Promise as PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * This plugin encodes the request body by compressing it with Gzip.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class GzipEncoderPlugin implements PluginInterface
{
    /**
     * @var StreamFactory The PSR-17 stream factory
     */
    private $streamFactory;

    /**
     * Constructor.
     *
     * @param \Http\Message\StreamFactory $streamFactory
     *
     * @throws \RuntimeException If the zlib extension is not enabled
     */
    public function __construct(StreamFactory $streamFactory)
    {
        if (!\extension_loaded('zlib')) {
            throw new \RuntimeException('The "zlib" extension must be enabled to use this plugin.');
        }

        $this->streamFactory = $streamFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(RequestInterface $request, callable $next, callable $first): PromiseInterface
    {
        $requestBody = $request->getBody();

        if ($requestBody->isSeekable()) {
            $requestBody->rewind();
        }

        $encodedBody = gzcompress($requestBody->getContents(), -1, ZLIB_ENCODING_GZIP);

        if (false === $encodedBody) {
            throw new \RuntimeException('Failed to GZIP-encode the request body.');
        }

        $request = $request->withHeader('Content-Encoding', 'gzip');
        $request = $request->withBody($this->streamFactory->createStream($encodedBody));

        return $next($request);
    }
}
