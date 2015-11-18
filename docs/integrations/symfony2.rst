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

    namespace AppBundle\Monolog;

    use AppBundlee\Entity\User;
    use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

    class SentryContextProcessor
    {
        protected $tokenStorage;

        public function __construct(TokenStorage $tokenStorage)
        {
            $this->tokenStorage = $tokenStorage;
        }

        public function processRecord($record)
        {
            $user = $this->tokenStorage->getToken()->getUser();

            if ($user instanceof User) {
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
            class: AppBundle\Monolog\SentryContextProcessor
            arguments:  ["@security.token_storage"]
            tags:
                - { name: monolog.processor, method: processRecord, handler: sentry }
