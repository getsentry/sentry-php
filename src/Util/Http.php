<?php

declare(strict_types=1);

namespace Sentry\Util;

use Sentry\Client;
use Sentry\Dsn;

/**
 * @internal
 */
final class Http
{
    /**
     * @return string[]
     */
    public static function getRequestHeaders(Dsn $dsn, string $sdkIdentifier, string $sdkVersion): array
    {
        $headers = [
            'Content-Type' => 'application/x-sentry-envelope',
        ];

        $data = [
            'sentry_version' => Client::PROTOCOL_VERSION,
            'sentry_client' => $sdkIdentifier . '/' . $sdkVersion,
            'sentry_key' => $dsn->getPublicKey(),
        ];

        if (null !== $dsn->getSecretKey()) {
            $data['sentry_secret'] = $dsn->getSecretKey();
        }

        $authHeader = [];
        foreach ($data as $headerKey => $headerValue) {
            $authHeader[] = $headerKey . '=' . $headerValue;
        }

        return array_merge($headers, [
            'X-Sentry-Auth' => 'Sentry ' . implode(', ', $authHeader),
        ]);
    }

    /**
     * @return string[][]
     */
    public static function getResponseHeaders(?int $headerSize, string $body): array
    {
        $headers = [];
        $rawHeaders = explode("\r\n", trim(substr($body, 0, $headerSize)));

        foreach ($rawHeaders as $value) {
            if (!str_contains($value, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $value, 2);
            $value = trim($value);
            $name = trim($name);

            if (isset($headers[$name])) {
                $headers[$name][] = $value;
            } else {
                $headers[$name] = (array) $value;
            }
        }

        return $headers;
    }
}
