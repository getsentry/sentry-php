<?php

namespace Sentry\Attachment;

/**
 * Represents a file that is readable by using a path.
 */
class FileAttachment extends Attachment
{

    /**
     * @var string
     */
    private $path;

    public function __construct(string $path, string $contentType)
    {
        parent::__construct(basename($path), $contentType);
        $this->path = $path;
    }

    public function getSize(): ?int
    {
        return @filesize($this->path) ?: null;
    }

    public function getData(): ?string
    {
        return @file_get_contents($this->path) ?: null;
    }
}
