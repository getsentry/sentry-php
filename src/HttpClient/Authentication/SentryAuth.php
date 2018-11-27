<?php

declare(strict_types=1);

namespace Sentry\HttpClient\Authentication;

use Http\Message\Authentication;
use Psr\Http\Message\RequestInterface;
use Sentry\Client;
use Sentry\Options;

/**
 * This authentication method sends the requests along with a X-Sentry-Auth
 * header.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SentryAuth implements Authentication
{
    /**
     * The version of the protocol to communicate with the Sentry server.
     */
    public const PROTOCOL_VERSION = '7';

    /**
     * @var Options The Sentry client configuration
     */
    private $options;

    /**
     * @var string The user agent of the client
     */
    private $userAgent;

    /**
     * Constructor.
     *
     * @param Options $options The Sentry client configuration
     */
    public function __construct(string $userAgent, Options $options)
    {
        $this->userAgent = $userAgent;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        $data = [
            'sentry_version' => self::PROTOCOL_VERSION,
            'sentry_client' => $this->userAgent,
            'sentry_timestamp' => sprintf('%F', microtime(true)),
            'sentry_key' => $this->options->getPublicKey(),
        ];

        if ($this->options->getSecretKey()) {
            $data['sentry_secret'] = $this->options->getSecretKey();
        }

        $headers = [];

        foreach ($data as $headerKey => $headerValue) {
            $headers[] = $headerKey . '=' . $headerValue;
        }

        return $request->withHeader('X-Sentry-Auth', 'Sentry ' . implode(', ', $headers));
    }
}
