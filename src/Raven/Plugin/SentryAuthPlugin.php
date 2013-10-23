<?php

namespace Raven\Plugin;

use Guzzle\Common\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class SentryAuthPlugin implements EventSubscriberInterface
{
    private $publicKey;
    private $secretKey;
    private $version;
    private $client;

    public function __construct(
        $publicKey,
        $secretKey,
        $version,
        $client
    ) {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->version = $version;
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'request.before_send' => array('onRequestBeforeSend', -1000),
        );
    }

    public function onRequestBeforeSend(Event $event)
    {
        $timestamp = $this->getTimestamp($event);
        $request = $event['request'];

        $request->setHeader(
            'X-Sentry-Auth',
            $this->getHeader($timestamp)
        );
    }

    private function getTimestamp(Event $event)
    {
        return $event['timestamp'] ?: time();
    }

    private function getHeader($timestamp)
    {
        $parts = array();
        $parts[] = sprintf('sentry_version=%s', $this->version);
        $parts[] = sprintf('sentry_client=%s', $this->client);
        $parts[] = sprintf('sentry_timestamp=%d', $timestamp);
        $parts[] = sprintf('sentry_key=%s', $this->publicKey);
        $parts[] = sprintf('sentry_secret=%s', $this->secretKey);

        return sprintf('Sentry %s', implode(', ', $parts));
    }
}
