<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ErrorHandler;
use Sentry\Exception\FatalErrorException;
use Sentry\Options;
use Sentry\State\Hub;

/**
 * This integration hooks into the error handler and captures fatal errors.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class FatalErrorListenerIntegration implements IntegrationInterface
{
    /**
     * @var Options The options, to know which error level to use
     */
    private $options;

    /**
     * Constructor.
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
        $errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
        $errorHandler->addFatalErrorHandlerListener(function (FatalErrorException $exception): void {
            if (!($this->options->getErrorTypes() & $exception->getSeverity())) {
                return;
            }

            Hub::getCurrent()->captureException($exception);
        });
    }
}
