<?php

declare(strict_types=1);

namespace Sentry\Attachment;

/**
 * Represents an attachment that is stored in memory and will not be read from the filesystem.
 */
class ByteAttachment extends Attachment
{
    /**
     * @var string
     */
    private $data;

    public function __construct(string $filename, string $contentType, string $data)
    {
        parent::__construct($filename, $contentType);
        $this->data = $data;
    }

    public function getSize(): ?int
    {
        return \strlen($this->data);
    }

    public function getData(): ?string
    {
        return $this->data;
    }
}
