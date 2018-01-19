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
use Raven\Processor\ProcessorRegistry;

/**
 * This middleware loops through all registered processors and execute them
 * in their order.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ProcessorMiddleware
{
    /**
     * @var ProcessorRegistry The registry of processors
     */
    private $processorRegistry;

    /**
     * Constructor.
     *
     * @param ProcessorRegistry $processorRegistry The registry of processors
     */
    public function __construct(ProcessorRegistry $processorRegistry)
    {
        $this->processorRegistry = $processorRegistry;
    }

    /**
     * Invokes all the processors to process the event before it's sent.
     *
     * @param Event                       $event     The event being processed
     * @param callable                    $next      The next middleware to call
     * @param ServerRequestInterface|null $request   The request, if available
     * @param \Exception|null             $exception The thrown exception, if available
     * @param array                       $payload   Additional data
     *
     * @return Event
     */
    public function __invoke(Event $event, callable $next, ServerRequestInterface $request = null, \Exception $exception = null, array $payload = [])
    {
        foreach ($this->processorRegistry->getProcessors() as $processor) {
            $event = $processor->process($event);

            if (!$event instanceof Event) {
                throw new \UnexpectedValueException(sprintf('The processor must return an instance of the "%s" class.', Event::class));
            }
        }

        return $next($event, $request, $exception, $payload);
    }
}
