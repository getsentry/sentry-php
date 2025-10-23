<?php

declare(strict_types=1);

namespace Sentry\Attachment;

abstract class Attachment
{
    private const DEFAULT_CONTENT_TYPE = 'application/octet-stream';

    /**
     * @var string
     */
    private $filename;

    /**
     * @var string
     */
    private $contentType;

    public function __construct(string $filename, string $contentType)
    {
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

    /**
     * Returns the size in bytes for the attachment. This method should aim to use a low overhead
     * way of determining the size because it will be called more than once.
     * For example, for file attachments it should read the file size from the filesystem instead of
     * reading the file in memory and then calculating the length.
     * If no low overhead way exists, then the result should be cached so that calling it multiple times
     * does not decrease performance.
     *
     * @return int the size in bytes or null if the length could not be determined, for example if the file
     *             does not exist
     */
    abstract public function getSize(): ?int;

    /**
     * Fetches and returns the data. Calling this can have a non-trivial impact on memory usage, depending
     * on the type and size of attachment.
     *
     * @return string the content as bytes or null if the content could not be retrieved, for example if the file
     *                does not exist
     */
    abstract public function getData(): ?string;

    /**
     * Creates a new attachment representing a file referenced by a path.
     * The file is not validated and the content is not read when creating the attachment.
     */
    public static function fromFile(string $path, string $contentType = self::DEFAULT_CONTENT_TYPE): Attachment
    {
        return new FileAttachment($path, $contentType);
    }

    /**
     * Creates a new attachment representing a slice of bytes that lives in memory.
     */
    public static function fromBytes(string $filename, string $data, string $contentType = self::DEFAULT_CONTENT_TYPE): Attachment
    {
        return new ByteAttachment($filename, $contentType, $data);
    }
}
