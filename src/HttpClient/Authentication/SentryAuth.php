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
     * @var Options The Sentry client configuration
     */
    private $options;

    /**
     * @var string The SDK identifier
     */
    private $sdkIdentifier;

    /**
     * Constructor.
     *
     * @param Options $options The Sentry client configuration
     * @param string $sdkIdentifier The Sentry SDK identifier to be used
     */
    public function __construct(Options $options, string $sdkIdentifier)
    {
        $this->options = $options;
        $this->sdkIdentifier = $sdkIdentifier;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        $data = [
            'sentry_version' => Client::PROTOCOL_VERSION,
            'sentry_client' => $this->sdkIdentifier . '/' . Client::VERSION,
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
