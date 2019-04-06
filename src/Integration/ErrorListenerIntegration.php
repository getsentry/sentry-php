<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ErrorHandler;
use Sentry\Exception\FatalErrorException;
use Sentry\Exception\SilencedErrorException;
use Sentry\Options;
use Sentry\State\Hub;

/**
 * This integration hooks into the global error handlers and emits events to
 * Sentry.
 */
final class ErrorListenerIntegration implements IntegrationInterface
{
    /**
     * @var Options The options, to know which error level to use
     */
    private $options;

    /**
     * @var bool Whether to handle fatal errors or not
     */
    private $handleFatalErrors;

    /**
     * Constructor.
     *
     * @param Options $options           The options to be used with this integration
     * @param bool    $handleFatalErrors Whether to handle fatal errors or not
     */
    public function __construct(Options $options, bool $handleFatalErrors = true)
    {
        $this->options = $options;
        $this->handleFatalErrors = $handleFatalErrors;

        if ($handleFatalErrors) {
            @trigger_error(sprintf('Handling fatal errors with the "%s" class is deprecated since version 2.1. Use the "%s" integration instead.', self::class, FatalErrorListenerIntegration::class), E_USER_DEPRECATED);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        $errorHandler = ErrorHandler::registerOnce(ErrorHandler::DEFAULT_RESERVED_MEMORY_SIZE, false);
        $errorHandler->addErrorHandlerListener(function (\ErrorException $exception): void {
            if (!$this->handleFatalErrors && $exception instanceof FatalErrorException) {
                return;
            }

            if ($exception instanceof SilencedErrorException && !$this->options->shouldCaptureSilencedErrors()) {
                return;
            }

            if (!($this->options->getErrorTypes() & $exception->getSeverity())) {
                return;
            }

            Hub::getCurrent()->captureException($exception);
        });
    }
}
