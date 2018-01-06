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

namespace Raven\Processor;

use Raven\Event;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Asterisk out passwords from password fields in frames, http and basic extra
 * data.
 *
 * @author David Cramer <dcramer@gmail.com>
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SanitizeDataProcessor implements ProcessorInterface
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
     * Configures the options for this processor.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'fields_re' => '/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw)/i',
            'values_re' => '/^(?:\d[ -]*?){13,16}$/',
            'session_cookie_name' => ini_get('session.name'),
        ]);

        $resolver->setAllowedTypes('fields_re', 'string');
        $resolver->setAllowedTypes('values_re', 'string');
        $resolver->setAllowedTypes('session_cookie_name', 'string');
    }

    /**
     * Replace any array values with our mask if the field name or the value matches a respective regex.
     *
     * @param mixed  $item Associative array value
     * @param string $key  Associative array key
     */
    public function sanitize(&$item, $key)
    {
        if (empty($item)) {
            return;
        }

        if (preg_match($this->options['values_re'], $item)) {
            $item = self::STRING_MASK;
        }

        if (empty($key)) {
            return;
        }

        if (preg_match($this->options['fields_re'], $key)) {
            $item = self::STRING_MASK;
        }
    }

    public function sanitizeException(&$data)
    {
        foreach ($data['values'] as &$value) {
            if (!isset($value['stacktrace'])) {
                continue;
            }

            $this->sanitizeStacktrace($value['stacktrace']);
        }

        return $data;
    }

    public function sanitizeHttp(&$data)
    {
        if (!empty($data['cookies']) && is_array($data['cookies'])) {
            $cookies = &$data['cookies'];
            if (!empty($cookies[$this->options['session_cookie_name']])) {
                $cookies[$this->options['session_cookie_name']] = self::STRING_MASK;
            }
        }

        if (!empty($data['data']) && is_array($data['data'])) {
            array_walk_recursive($data['data'], [$this, 'sanitize']);
        }

        return $data;
    }

    public function sanitizeStacktrace(&$data)
    {
        foreach ($data['frames'] as &$frame) {
            if (empty($frame['vars'])) {
                continue;
            }

            array_walk_recursive($frame['vars'], [$this, 'sanitize']);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Event $event)
    {
        $exception = $event->getException();
        $stacktrace = $event->getStacktrace();
        $request = $event->getRequest();
        $extraContext = $event->getExtraContext();

        if (!empty($exception)) {
            $event = $event->withException($this->sanitizeException($exception));
        }

        if (!empty($stacktrace)) {
            $event = $event->withStacktrace($this->sanitizeStacktrace($stacktrace));
        }

        if (!empty($request)) {
            $event = $event->withRequest($this->sanitizeHttp($request));
        }

        if (!empty($extraContext)) {
            array_walk_recursive($extraContext, [$this, 'sanitize']);

            $event = $event->withExtraContext($extraContext);
        }

        return $event;
    }
}
