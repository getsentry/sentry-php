<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * Interface to represent different HTTP body sources. It can either wrap a stream
 * or already contain a parsed body.
 */
interface HttpBodySourceInterface
{
    /**
     * Returns the length of the body or null if it's unknown.
     */
    public function getKnownLength(): ?int;

    /**
     * Returns the content type of the body. Will be used to determine
     * how to interpret the body while reading.
     */
    public function getContentType(): string;

    /**
     * Reads the provided source and returns either a parsed body as an array or
     * a string which needs to be parsed according to its content type.
     *
     * @see Psr7BodyReader::read() For bounded PSR-7 stream reads
     *
     * @return array<array-key, mixed>|string|null
     */
    public function read(int $limit);
}
