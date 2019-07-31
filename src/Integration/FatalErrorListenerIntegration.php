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
     * @var Options|null The options, to know which error level to use
     */
    private $options;

    /**
     * Constructor.
     *
     * @param Options|null $options The options to be used with this integration
     */
    public function __construct(?Options $options = null)
    {
        if (null !== $options) {
            @trigger_error(sprintf('Passing the options as argument of the constructor of the "%s" class is deprecated since version 2.1 and will not work in 3.0.', self::class), E_USER_DEPRECATED);
        }

        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        $errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
        $errorHandler->addFatalErrorHandlerListener(function (FatalErrorException $exception): void {
            $currentHub = Hub::getCurrent();
            $client = $currentHub->getClient();

            // The client could have been detached from the hub. If this is the
            // case this integration should not run
            if (null === $client) {
                return;
            }

            $integration = $client->getIntegration(self::class);

            // The integration could be binded to a client that is not the one
            // attached to the current hub. If this is the case, bail out
            if (null === $integration) {
                return;
            }

            $options = $this->options ?? $client->getOptions();

            if (!($options->getErrorTypes() & $exception->getSeverity())) {
                return;
            }

            $currentHub->captureException($exception);
        });
    }
}
