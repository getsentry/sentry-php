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
final class SentryAuthentication implements Authentication
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
     * @var string The SDK version
     */
    private $sdkVersion;

    /**
     * Constructor.
     *
     * @param Options $options       The Sentry client configuration
     * @param string  $sdkIdentifier The Sentry SDK identifier in use
     * @param string  $sdkVersion    The Sentry SDK version in use
     */
    public function __construct(Options $options, string $sdkIdentifier, string $sdkVersion)
    {
        $this->options = $options;
        $this->sdkIdentifier = $sdkIdentifier;
        $this->sdkVersion = $sdkVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        $data = [
            'sentry_version' => Client::PROTOCOL_VERSION,
            'sentry_client' => $this->sdkIdentifier . '/' . $this->sdkVersion,
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

        /** @var RequestInterface $request */
        $request = $request->withHeader('X-Sentry-Auth', 'Sentry ' . implode(', ', $headers));

        return $request;
    }
}
