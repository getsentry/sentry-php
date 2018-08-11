<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Raven\Event;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This middleware sanitizes the cookies to ensure no sensitive information are
 * sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SanitizeCookiesMiddleware implements ProcessorMiddlewareInterface
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

        if (!empty($this->options['only']) && !empty($this->options['except'])) {
            throw new InvalidOptionsException('You can configure only one of "only" and "except" options.');
        }
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
        $request = $event->getRequest();

        if (isset($request['cookies'])) {
            $cookiesToSanitize = array_keys($request['cookies']);

            if (!empty($this->options['only'])) {
                $cookiesToSanitize = $this->options['only'];
            }

            if (!empty($this->options['except'])) {
                $cookiesToSanitize = array_diff($cookiesToSanitize, $this->options['except']);
            }

            foreach ($request['cookies'] as $name => $value) {
                if (!\in_array($name, $cookiesToSanitize)) {
                    continue;
                }

                $request['cookies'][$name] = self::STRING_MASK;
            }
        }

        unset($request['headers']['cookie']);

        $event->setRequest($request);

        return $next($event, $request, $exception, $payload);
    }

    /**
     * Configures the options for this processor.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'only' => [],
            'except' => [],
        ]);

        $resolver->setAllowedTypes('only', 'array');
        $resolver->setAllowedTypes('except', 'array');
    }
}
