<?php

declare(strict_types=1);

namespace Sentry\HttpClient;

/**
 * @internal
 */
final class Request
{
    /**
     * @var string
     */
    private $stringBody;

    public function getStringBody(): ?string
    {
        return $this->stringBody;
    }

    public function setStringBody(string $stringBody): void
    {
        $this->stringBody = $stringBody;
    }
}
