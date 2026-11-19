<?php

declare(strict_types=1);

namespace Sentry\Monolog;

/**
 * Allows a single log record to opt out of being reported as a Sentry issue
 * by setting the `sentry` key of its context to `false`, e.g.:
 *
 *     $logger->warning('Invalid CSV row', ['sentry' => false]);
 *
 * The record is still handled by every other handler (including the
 * {@see BreadcrumbHandler} and the {@see LogsHandler}), so it remains
 * available as context should another record trigger an issue.
 *
 * @internal
 */
trait SentryContextFlagTrait
{
    /**
     * @param mixed[] $context The context of the log record
     */
    private static function isSentryIssueDisabled(array $context): bool
    {
        return ($context['sentry'] ?? null) === false;
    }
}
