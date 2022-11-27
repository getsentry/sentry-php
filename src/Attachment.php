<?php

declare(strict_types=1);

namespace Sentry;

final class Attachment
{
    public const CONTENT_TYPE_DEFAULT = 'application/octet-stream';

    /**
     * @var string
     */
    private $filename;

    /**
     * @var string
     */
    private $contentType;

    /**
     * @var string
     */
    private $data;

    public function __construct(string $data, string $filename, string $contentType = self::CONTENT_TYPE_DEFAULT)
    {
        $this->data = $data;
        $this->filename = $filename;
        $this->contentType = $contentType;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Helper method to create an Attachment from a file in the filesystem.
     */
    public static function fromFile(string $filename, string $contentType = self::CONTENT_TYPE_DEFAULT): self
    {
        $data = file_get_contents($filename);
        if (false === $data) {
            throw new \InvalidArgumentException("File $filename is not readable.");
        }

        return new self(
            $data,
            basename($filename),
            $contentType
        );
    }
}
