<?php

declare(strict_types=1);

namespace Sentry\HttpClient\Plugin;

use Http\Client\Common\Plugin as PluginInterface;
use Http\Promise\Promise as PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\StreamFactory;

/**
 * This plugin encodes the request body by compressing it with Gzip.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class GzipEncoderPlugin implements PluginInterface
{
    /**
     * Constructor.
     *
     * @throws \RuntimeException If the zlib extension is not enabled
     */
    public function __construct()
    {
        if (!\extension_loaded('zlib')) {
            throw new \RuntimeException('The "zlib" extension must be enabled to use this plugin.');
        }
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

        if ($encodedBody === false) {
            throw new \RuntimeException('Failed to GZIP-encode the request body.');
        }

        $request = $request->withHeader('Content-Encoding', 'gzip');
        $request = $request->withBody((new StreamFactory())->createStream($encodedBody));

        return $next($request);
    }
}
