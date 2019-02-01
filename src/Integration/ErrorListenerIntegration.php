<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ErrorHandler;
use Sentry\ErrorListenerInterface;
use Sentry\Options;
use Sentry\State\Hub;

/**
 * This integration hooks into the global error handlers and emits events to
 * Sentry.
 */
final class ErrorListenerIntegration implements IntegrationInterface, ErrorListenerInterface
{
    /** @var Options The options, to know which error level to use */
    private $options;

    /**
     * ErrorListenerIntegration constructor.
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    public function setupOnce(): void
    {
        ErrorHandler::addErrorListener($this);
    }

    public function __invoke(\ErrorException $error): void
    {
        if ($this->options->getErrorTypes() & $error->getSeverity()) {
            $client = Hub::getCurrent()->getClient();

            if ($client) {
                $client->captureException($error);
            }
        }
    }
}
