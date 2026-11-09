<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\SentrySdk;

/**
 * This integration sets the `transaction` attribute of the event to the value
 * found in the raw event payload, to the name of the active transaction or to
 * the path of the current request if present.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class TransactionIntegration implements IntegrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        SentrySdk::getGlobalScope()->addEventProcessor(static function (Event $event, EventHint $hint): Event {
            $integration = SentrySdk::getClient()->getIntegration(self::class);

            if ($integration === null) {
                return $event;
            }

            if ($event->getTransaction() !== null) {
                return $event;
            }

            $transaction = SentrySdk::getIsolationScope()->getTransaction();

            if (isset($hint->extra['transaction']) && \is_string($hint->extra['transaction'])) {
                $event->setTransaction($hint->extra['transaction']);
            } elseif ($transaction !== null) {
                $event->setTransaction($transaction->getName());
            } else {
                $event->setTransaction(self::getRequestPath());
            }

            return $event;
        });
    }

    /**
     * Returns the `PATH_INFO` server var or, as front controllers behind URL
     * rewriting usually lack it, the path of the `REQUEST_URI` server var.
     */
    private static function getRequestPath(): ?string
    {
        if (isset($_SERVER['PATH_INFO']) && \is_string($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '') {
            return $_SERVER['PATH_INFO'];
        }

        if (!isset($_SERVER['REQUEST_URI']) || !\is_string($_SERVER['REQUEST_URI'])) {
            return null;
        }

        $path = explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        return $path !== '' ? $path : null;
    }
}
