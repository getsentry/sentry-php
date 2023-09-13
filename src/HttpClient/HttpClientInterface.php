<?php

declare(strict_types=1);

namespace Sentry\HttpClient;

interface HttpClientInterface
{
    public function sendRequest(string $requestData): Response;
}
