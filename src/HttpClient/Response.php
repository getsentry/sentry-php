<?php

declare(strict_types=1);

namespace Sentry\HttpClient;

/**
 * @internal
 */
final class Response
{
    /**
     * @var int The HTTP status code
     */
    private $statusCode;

    /**
     * @var string[] The HTTP response headers
     */
    private $headers;

    /**
     * @var string The cURL error and error message
     */
    private $error;

    /**
     * @param string[] $headers
     */
    public function __construct(int $statusCode, array $headers, string $error)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->error = $error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode <= 299;
    }

    public function hasHeader(string $headerName): bool
    {
        return \array_key_exists($headerName, $this->headers);
    }

    public function getHeaderLine(string $headerName): string
    {
        return $this->headers[$headerName] ?? '';
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return '' !== $this->error;
    }
}
