<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Processor;

use Raven\Client;
use Raven\Processor;

/**
 * This processor sanitizes the configured HTTP headers to ensure no sensitive
 * informations are sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SanitizeHttpHeadersProcessor extends Processor
{
    /**
     * @var string[] $httpHeadersToSanitize The list of HTTP headers to sanitize
     */
    private $httpHeadersToSanitize = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(Client $client)
    {
        parent::__construct($client);
    }

    /**
     * {@inheritdoc}
     */
    public function setProcessorOptions(array $options)
    {
        $this->httpHeadersToSanitize = array_merge(
            $this->getDefaultHeaders(),
            isset($options['sanitize_http_headers']) ? $options['sanitize_http_headers'] : []
        );
    }

    /**
     * {@inheritdoc}
     */
    public function process(&$data)
    {
        if (isset($data['request']) && isset($data['request']['headers'])) {
            foreach ($data['request']['headers'] as $header => &$value) {
                if (in_array($header, $this->httpHeadersToSanitize)) {
                    $value = self::STRING_MASK;
                }
            }
        }
    }

    /**
     * Gets the list of default headers that must be sanitized.
     *
     * @return string[]
     */
    private function getDefaultHeaders()
    {
        return ['Authorization', 'Proxy-Authorization', 'X-Csrf-Token', 'X-CSRFToken', 'X-XSRF-TOKEN'];
    }
}
