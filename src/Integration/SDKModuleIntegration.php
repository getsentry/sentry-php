<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Client;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Scope;

/**
 * This integration logs with the event details all the versions of the packages
 * installed with Composer; the root project is included too.
 */
final class SDKModuleIntegration implements IntegrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event): Event {
            $integration = SentrySdk::getCurrentHub()->getIntegration(self::class);

            // The integration could be bound to a client that is not the one
            // attached to the current hub. If this is the case, bail out
            if ($integration !== null && $event->getModules() === []) {
                $event->setModules([
                    'sentry/sentry' => Client::SDK_VERSION,
                ]);
            }

            return $event;
        });
    }
}
