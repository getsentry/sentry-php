<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class ServerRequestBodySource implements HttpBodySourceInterface
{
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function getKnownLength(): ?int
    {
        $length = $this->request->getHeaderLine('Content-Length');

        return is_numeric($length) ? (int) $length : null;
    }

    public function getContentType(): string
    {
        return $this->request->getHeaderLine('Content-Type');
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $limit)
    {
        $body = $this->request->getParsedBody();
        if ($body !== null) {
            return \is_array($body) ? $body : null;
        }

        return $this->readStream($limit);
    }

    public function readStream(int $limit): ?string
    {
        return Psr7BodyReader::read($this->request->getBody(), $limit);
    }
}
