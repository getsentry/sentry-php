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
     * @var string[][]
     */
    private $headers;

    /**
     * @var string The cURL error and error message
     */
    private $error;

    /**
     * @param string[][] $headers
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

    public function hasHeader(string $name): bool
    {
        return \array_key_exists($name, $this->headers);
    }

    /**
     * @return string[]
     */
    public function getHeader(string $header): array
    {
        if (!$this->hasHeader($header)) {
            return [];
        }

        return $this->headers[$header];
    }

    public function getHeaderLine(string $name): string
    {
        $value = $this->getHeader($name);
        if (empty($value)) {
            return '';
        }

        return implode(',', $value);
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
