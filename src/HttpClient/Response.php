<?php

declare(strict_types=1);

namespace Sentry\HttpClient;

class Response
{
    /**
     * @var int The HTTP status code
     */
    protected $statusCode;

    /**
     * @var string[] The HTTP response headers
     */
    protected $headers;

    /**
     * @var string The cURL error and error message
     */
    protected $error;

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