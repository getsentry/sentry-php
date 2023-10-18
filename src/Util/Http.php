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

        return [
            'Content-Type' => 'application/x-sentry-envelope',
            'X-Sentry-Auth' => 'Sentry ' . implode(', ', $authHeader),
        ];
    }

    /**
     * @param string[][] $headers
     */
    public static function parseResponseHeaders(string $headerLine, &$headers): int
    {
        if (false === strpos($headerLine, ':')) {
            return \strlen($headerLine);
        }

        [$key, $value] = explode(':', trim($headerLine), 2);
        $headers[trim($key)] = trim($value);

        return \strlen($headerLine);
    }
}
