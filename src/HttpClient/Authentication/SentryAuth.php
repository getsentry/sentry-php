<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
     * @var Options The Raven client configuration
     */
    private $configuration;

    /**
     * Constructor.
     *
     * @param Options $configuration The Raven client configuration
     */
    public function __construct(Options $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request)
    {
        $headerKeys = array_filter([
            'sentry_version' => Client::PROTOCOL_VERSION,
            'sentry_client' => Client::USER_AGENT,
            'sentry_timestamp' => sprintf('%F', microtime(true)),
            'sentry_key' => $this->configuration->getPublicKey(),
            'sentry_secret' => $this->configuration->getSecretKey(),
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
