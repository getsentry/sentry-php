Symfony2
========

Symfony2 supports Monolog out of the box, which also provides a native Sentry handler.

Simply add a new handler for Sentry to your config (i.e. in ``config_prod.yaml``), and you're good to go:

.. sourcecode:: yaml

    monolog:
      handlers:
        main:
            type:         fingers_crossed
            action_level: error
            handler:      grouped_main

        sentry:
            type:  raven
            dsn:   '___DSN___'
            level: error

        # Groups
        grouped_main:
            type:    group
            members: [sentry, streamed_main]

        # Streams
        streamed_main:
            type:  stream
            path:  %kernel.logs_dir%/%kernel.environment%.log
            level: error

Adding Context
--------------

Capturing context can be done via a monolog processor:

.. sourcecode:: php

    namespace Acme\Bundle\AcmeBundle\Monolog;

    use Symfony\Component\DependencyInjection\ContainerInterface;
    use Acme\Bundle\AcmeBundle\Entity\User;

    class SentryContextProcessor {

        protected $container;

        public function __construct(ContainerInterface $container)
        {
            $this->container = $container;
        }

        public function processRecord($record)
        {
            $securityContext = $this->container->get('security.context');
            $user = $securityContext->getToken()->getUser();

            if($user instanceof User)
            {

                $record['context']['user'] = array(
                    'name' => $user->getName(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                );
            }

            // Add various tags
            $record['context']['tags'] = array('key' => 'value');

            // Add various generic context
            $record['extra']['key'] = 'value';

            return $record;
        }

    }

You'll then register the processor in your config:

.. sourcecode:: php

    services:
        monolog.processor.sentry_context:
            class: Applestump\Bundle\ShowsBundle\Monolog\SentryContextProcessor
            arguments:  ["@service_container"]
            tags:
                - { name: monolog.processor, method: processRecord, handler: sentry }
