<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This integration decides whether an event should not be captured according
 * to a series of options that must match with its data.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class IgnoreErrorsIntegration implements IntegrationInterface
{
    /**
     * @var array<string, mixed> The options
     */
    private $options;

    /**
     * Creates a new instance of this integration and configures it with the
     * given options.
     *
     * @param array<string, mixed> $options The options
     *
     * @psalm-param array{
     *     ignore_exceptions?: list<class-string<\Throwable>>
     * } $options
     */
    public function __construct(array $options = [])
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'ignore_exceptions' => [],
        ]);

        $resolver->setAllowedTypes('ignore_exceptions', ['array']);

        $this->options = $resolver->resolve($options);
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event): ?Event {
            $integration = SentrySdk::getCurrentHub()->getIntegration(self::class);

            if (null !== $integration && $integration->shouldDropEvent($event, $integration->options)) {
                return null;
            }

            return $event;
        });
    }

    /**
     * Checks whether the given event should be dropped according to the options
     * that configures the current instance of this integration.
     *
     * @param Event                $event   The event to check
     * @param array<string, mixed> $options The options of the integration
     */
    private function shouldDropEvent(Event $event, array $options): bool
    {
        if ($this->isIgnoredException($event, $options)) {
            return true;
        }

        return false;
    }

    /**
     * Checks whether the given event should be dropped or not according to the
     * criteria defined in the integration's options.
     *
     * @param Event                $event   The event instance
     * @param array<string, mixed> $options The options of the integration
     */
    private function isIgnoredException(Event $event, array $options): bool
    {
        $exceptions = $event->getExceptions();

        if (empty($exceptions) || !isset($exceptions[0]['type'])) {
            return false;
        }

        foreach ($options['ignore_exceptions'] as $ignoredException) {
            if (is_a($exceptions[0]['type'], $ignoredException, true)) {
                return true;
            }
        }

        return false;
    }
}
