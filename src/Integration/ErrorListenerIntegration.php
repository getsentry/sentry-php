<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ErrorHandler;
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
     * ErrorListenerIntegration constructor.
     *
     * @param Options $options The options to be used with this integration
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
        ErrorHandler::addErrorListener(function (\ErrorException $error): void {
            if ($error instanceof SilencedErrorException && !$this->options->shouldCaptureSilencedErrors()) {
                return;
            }

            if ($this->options->getErrorTypes() & $error->getSeverity()) {
                Hub::getCurrent()->captureException($error);
            }
        });
    }
}
