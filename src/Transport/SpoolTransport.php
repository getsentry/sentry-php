<?php

declare(strict_types=1);

namespace Sentry\Transport;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Sentry\Event;
use Sentry\Response;
use Sentry\ResponseStatus;
use Sentry\Spool\SpoolInterface;

/**
 * This transport stores the events in a queue to send them later.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SpoolTransport implements TransportInterface
{
    /**
     * @var SpoolInterface The spool instance
     */
    private $spool;

    /**
     * Constructor.
     *
     * @param SpoolInterface $spool The spool instance
     */
    public function __construct(SpoolInterface $spool)
    {
        $this->spool = $spool;
    }

    /**
     * Gets the spool.
     */
    public function getSpool(): SpoolInterface
    {
        return $this->spool;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Event $event): PromiseInterface
    {
        if ($this->spool->queueEvent($event)) {
            return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
        }

        return new RejectedPromise(new Response(ResponseStatus::skipped(), $event));
    }

    /**
     * {@inheritdoc}
     */
    public function close(?int $timeout = null): PromiseInterface
    {
        return new FulfilledPromise(true);
    }
}
