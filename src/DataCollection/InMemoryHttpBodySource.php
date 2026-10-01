<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * @internal
 */
final class InMemoryHttpBodySource implements HttpBodySourceInterface
{
    /**
     * @var array<array-key, mixed>|string|null
     */
    private $body;

    /**
     * @var string
     */
    private $contentType;

    /**
     * @var int|null
     */
    private $bodyLength;

    /**
     * @param mixed $body
     */
    public function __construct($body, string $contentType = '', ?int $bodyLength = null)
    {
        $this->body = \is_array($body) || \is_string($body) ? $body : null;
        $this->contentType = $contentType;
        $this->bodyLength = $bodyLength;
    }

    public function getKnownLength(): ?int
    {
        return \is_string($this->body)
            ? max(\strlen($this->body), $this->bodyLength ?? 0)
            : $this->bodyLength;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $limit)
    {
        return $this->body;
    }
}
