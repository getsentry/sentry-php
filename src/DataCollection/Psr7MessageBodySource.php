<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Psr\Http\Message\MessageInterface;

/**
 * @internal
 */
final class Psr7MessageBodySource implements HttpBodySourceInterface
{
    /**
     * @var MessageInterface
     */
    private $message;

    public function __construct(MessageInterface $message)
    {
        $this->message = $message;
    }

    public function getKnownLength(): ?int
    {
        $length = $this->message->getHeaderLine('Content-Length');

        return is_numeric($length) ? (int) $length : null;
    }

    public function getContentType(): string
    {
        return $this->message->getHeaderLine('Content-Type');
    }

    public function read(int $limit): ?string
    {
        $stream = $this->message->getBody();
        $size = $stream->getSize();
        if ($limit !== -1 && $size !== null && $size > $limit) {
            return null;
        }

        return Psr7BodyReader::read($stream, $limit);
    }
}
