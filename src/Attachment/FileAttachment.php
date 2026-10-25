<?php

declare(strict_types=1);

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
        $size = @filesize($this->path);
        if ($size !== false) {
            return $size;
        }

        return null;
    }

    public function getData(): ?string
    {
        try {
            $content = @file_get_contents($this->path);
            if ($content !== false) {
                return $content;
            }

            return null;
        } catch (\ValueError $e) {
            return null;
        }
    }
}
