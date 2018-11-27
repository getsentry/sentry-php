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
     * @var Options The Sentry client options
     */
    private $options;

    /**
     * @var string The user agent of the client
     */
    private $userAgent;

    /**
     * Constructor.
     *
     * @param Options $configuration The Raven client configuration
     */
    public function __construct(string $userAgent, Options $configuration)
    {
        $this->userAgent = $userAgent;
        $this->options = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        $headerKeys = array_filter([
            'sentry_version' => self::PROTOCOL_VERSION,
            'sentry_client' => $this->userAgent,
            'sentry_timestamp' => sprintf('%F', microtime(true)),
            'sentry_key' => $this->options->getPublicKey(),
            'sentry_secret' => $this->options->getSecretKey(),
        ]);

        $isFirstItem = true;
        $header = 'Sentry ';

        foreach ($headerKeys as $headerKey => $headerValue) {
            if (!$isFirstItem) {
                $header .= ', ';
            }

            $header .= $headerKey . '=' . $headerValue;

            $isFirstItem = false;
        }

        return $request->withHeader('X-Sentry-Auth', $header);
    }
}
