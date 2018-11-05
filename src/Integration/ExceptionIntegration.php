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
use Sentry\Serializer;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Hub;
use Sentry\State\Scope;

/**
 * This middleware collects information about the thrown exceptions.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ExceptionIntegration implements Integration
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
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event, $exception) {
            $self = Hub::getCurrent()->getIntegration($this);
            if ($self instanceof self) {
                self::applyToEvent($self, $event, $exception);
            }

            return $event;
        });
    }

    /**
     * Applies exception to the passed event
     *
     * @param ExceptionIntegration $self
     * @param Event $event
     * @param null|$exception
     * @return null|Event
     */
    public static function applyToEvent(ExceptionIntegration $self, Event $event, $exception = null): ?Event
    {
        if ($exception instanceof \ErrorException) {
            $event->setLevel($self->translateSeverity($exception->getSeverity()));
        }

        if (null !== $exception) {
            $exceptions = [];
            $currentException = $exception;

            do {
                if ($self->options->isExcludedException($currentException)) {
                    continue;
                }

                $data = [
                    'type' => \get_class($currentException),
                    'value' => (new Serializer($self->options->getMbDetectOrder()))->serialize($currentException->getMessage()),
                ];

                if ($self->options->getAutoLogStacks()) {
                    $data['stacktrace'] = Stacktrace::createFromBacktrace($self->options, $currentException->getTrace(), $currentException->getFile(), $currentException->getLine());
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
