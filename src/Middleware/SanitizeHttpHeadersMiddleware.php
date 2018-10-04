<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This middleware sanitizes the configured HTTP headers to ensure no sensitive
 * informations are sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SanitizeHttpHeadersMiddleware implements ProcessorMiddlewareInterface
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
     * Collects the needed data and sets it in the given event object.
     *
     * @param Event                       $event     The event being processed
     * @param callable                    $next      The next middleware to call
     * @param ServerRequestInterface|null $request   The request, if available
     * @param \Exception|\Throwable|null  $exception The thrown exception, if available
     * @param array                       $payload   Additional data
     *
     * @return Event
     */
    public function __invoke(Event $event, callable $next, ServerRequestInterface $request = null, $exception = null, array $payload = [])
    {
        $requestData = $event->getRequest();

        if (!isset($requestData['headers'])) {
            return $event;
        }

        foreach ($requestData['headers'] as $header => &$value) {
            if (\in_array($header, $this->options['sanitize_http_headers'], true)) {
                $value = self::STRING_MASK;
            }
        }

        // Break the reference and free some memory
        unset($value);

        $event->setRequest($requestData);

        return $next($event, $request, $exception, $payload);
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
