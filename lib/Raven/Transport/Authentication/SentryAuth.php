<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Transport\Authentication;

use Http\Message\Authentication;
use Psr\Http\Message\RequestInterface;
use Raven\Client;
use Raven\Configuration;

/**
 * This authentication method sends an X-Sentry-Auth header compatible with a
 * Sentry server that contains the protocol version, the current timestamp, the
 * user agent of the SDK and finally the public and secret key.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SentryAuth implements Authentication
{
    /**
     * @var Configuration The Raven client configuration
     */
    private $configuration;

    /**
     * Constructor.
     *
     * @param Configuration $configuration  The Raven client configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request)
    {
        $header = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_timestamp=%F, sentry_key=%s, sentry_secret=%s',
            Client::PROTOCOL,
            'sentry-php/' . Client::VERSION,
            microtime(true),
            $this->configuration->getPublicKey(),
            $this->configuration->getSecretKey()
        );

        return $request->withHeader('X-Sentry-Auth', $header);
    }
}