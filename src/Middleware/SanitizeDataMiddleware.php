<?php

/*
 * This file is part of Raven.
 * Asterisk out passwords from password fields in frames, http,
 * and basic extra data.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;
use Sentry\Stacktrace;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Asterisk out passwords from password fields in frames, http and basic extra
 * data.
 *
 * @author David Cramer <dcramer@gmail.com>
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SanitizeDataMiddleware implements ProcessorMiddlewareInterface
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
        $exception = $event->getException();
        $stacktrace = $event->getStacktrace();
        $request = $event->getRequest();
        $extraContext = $event->getExtraContext()->toArray();

        if (!empty($exception)) {
            $event->setException($this->sanitizeException($exception));
        }

        if ($stacktrace) {
            $event->setStacktrace($this->sanitizeStacktrace($stacktrace));
        }

        if (!empty($request)) {
            $event->setRequest($this->sanitizeHttp($request));
        }

        if (!empty($extraContext)) {
            $this->sanitize($extraContext);

            $event->getExtraContext()->replaceData($extraContext);
        }

        return $next($event, $request, $exception, $payload);
    }

    /**
     * Replace any array values with our mask if the field name or the value matches a respective regex.
     *
     * @param array $data Associative array to be sanitized
     */
    private function sanitize(&$data)
    {
        foreach ($data as $key => &$item) {
            if (preg_match($this->options['fields_re'], $key)) {
                if (\is_array($item)) {
                    array_walk_recursive($item, function (&$value) {
                        $value = self::STRING_MASK;
                    });

                    break;
                }

                $item = self::STRING_MASK;
            }

            if (\is_array($item)) {
                $this->sanitize($item);

                break;
            }

            if (preg_match($this->options['values_re'], $item)) {
                $item = self::STRING_MASK;
            }
        }
    }

    private function sanitizeException(&$data)
    {
        foreach ($data['values'] as &$value) {
            if (!isset($value['stacktrace'])) {
                continue;
            }

            $this->sanitizeStacktrace($value['stacktrace']);
        }

        return $data;
    }

    private function sanitizeHttp(&$data)
    {
        if (!empty($data['cookies']) && \is_array($data['cookies'])) {
            $cookies = &$data['cookies'];
            if (!empty($cookies[$this->options['session_cookie_name']])) {
                $cookies[$this->options['session_cookie_name']] = self::STRING_MASK;
            }
        }

        if (!empty($data['data']) && \is_array($data['data'])) {
            $this->sanitize($data['data']);
        }

        return $data;
    }

    /**
     * @param Stacktrace $data
     *
     * @return Stacktrace
     */
    private function sanitizeStacktrace($data)
    {
        foreach ($data->getFrames() as &$frame) {
            if (empty($frame->getVars())) {
                continue;
            }

            $vars = $frame->getVars();

            $this->sanitize($vars);
            $frame->setVars($vars);
        }

        return $data;
    }

    /**
     * Configures the options for this processor.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'fields_re' => '/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw)/i',
            'values_re' => '/^(?:\d[ -]*?){13,19}$/',
            'session_cookie_name' => ini_get('session.name'),
        ]);

        $resolver->setAllowedTypes('fields_re', 'string');
        $resolver->setAllowedTypes('values_re', 'string');
        $resolver->setAllowedTypes('session_cookie_name', 'string');
    }
}
