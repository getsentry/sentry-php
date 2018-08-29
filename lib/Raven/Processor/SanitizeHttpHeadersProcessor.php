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

use Raven\Event;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This processor sanitizes the configured HTTP headers to ensure no sensitive
 * informations are sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SanitizeHttpHeadersProcessor implements ProcessorInterface
{
    /**
     * @var array The configuration options
     */
    private $options;

    /**
     * Class constructor.
     *
     * @param array $options An optional array of configuration options
     */
    public function __construct(array $options = [])
    {
        $resolver = new OptionsResolver();

        $this->configureOptions($resolver);

        $this->options = $resolver->resolve($options);
    }

    /**
     * {@inheritdoc}
     */
    public function process(Event $event)
    {
        $request = $event->getRequest();

        if (!isset($request['headers'])) {
            return $event;
        }

        foreach ($request['headers'] as $header => &$value) {
            if (\in_array($header, $this->options['sanitize_http_headers'], true)) {
                $value = self::STRING_MASK;
            }
        }

        $event->setRequest($request);

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('sanitize_http_headers', ['Authorization', 'Proxy-Authorization', 'X-Csrf-Token', 'X-CSRFToken', 'X-XSRF-TOKEN']);

        $resolver->setAllowedTypes('sanitize_http_headers', 'array');
    }
}
