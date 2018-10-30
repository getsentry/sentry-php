<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Integration;

use Sentry\Event;
use Sentry\Options;
use Sentry\Severity;
use Sentry\Stacktrace;

/**
 * This middleware collects information about the thrown exceptions.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ExceptionInterfaceMiddleware
{
    /**
     * @var Options The client option
     */
    private $options;

    /**
     * Constructor.
     *
     * @param Options $options The client options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * Collects the needed data and sets it in the given event object.
     *
     * @param Event                       $event     The event being processed
     * @param \Exception|\Throwable|null  $exception The thrown exception, if available
     *
     * @return Event
     */
    public function __invoke(Event $event, $exception = null)
    {
        if ($exception instanceof \ErrorException) {
            $event->setLevel($this->translateSeverity($exception->getSeverity()));
        }

        if (null !== $exception) {
            $exceptions = [];
            $currentException = $exception;

            do {
                if ($this->options->isExcludedException($currentException)) {
                    continue;
                }

                $data = [
                    'type' => \get_class($currentException),
//                    TODO
//                    'value' => $this->client->getSerializer()->serialize($currentException->getMessage()),
                ];

                if ($this->options->getAutoLogStacks()) {
//                    TODO
//                    $data['stacktrace'] = Stacktrace::createFromBacktrace($this->client, $currentException->getTrace(), $currentException->getFile(), $currentException->getLine());
                }

                $exceptions[] = $data;
            } while ($currentException = $currentException->getPrevious());

            $exceptions = [
                'values' => array_reverse($exceptions),
            ];

            $event->setException($exceptions);
        }

        return $event;
    }

    /**
     * Translate a PHP Error constant into a Sentry log level group.
     *
     * @param int $severity PHP E_$x error constant
     *
     * @return Severity
     */
    private function translateSeverity(int $severity): Severity
    {
        switch ($severity) {
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_WARNING:
            case E_USER_WARNING:
            case E_RECOVERABLE_ERROR:
                return Severity::warning();
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_ERROR:
            case E_COMPILE_WARNING:
                return Severity::fatal();
            case E_USER_ERROR:
                return Severity::error();
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                return Severity::info();
            default:
                return Severity::error();
        }
    }
}
