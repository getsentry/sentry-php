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
 * This processor removes all the cookies from the request to ensure no sensitive
 * informations are sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RemoveCookiesProcessor implements ProcessorInterface
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

        if (isset($request['cookies'])) {
            foreach ($request['cookies'] as $name => $value) {
                $request['cookies'][$name] = self::STRING_MASK;
            }
        }

        unset($request['headers']['cookie']);

        return $event->withRequest($request);
    }

    /**
     * Configures the options for this processor.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'include' => [],
            'exclude' => [],
        ]);

        $resolver->setAllowedTypes('include', 'array');
        $resolver->setAllowedTypes('exclude', 'array');
    }
}
