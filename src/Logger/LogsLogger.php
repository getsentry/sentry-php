<?php

declare(strict_types=1);

namespace Sentry\Logger;

use Psr\Log\AbstractLogger;
use Sentry\Logs\LogAttribute;

use function Sentry\logger;

/**
 * @phpstan-import-type AttributeValue from LogAttribute
 */
class LogsLogger extends AbstractLogger
{
    /**
     * @param mixed              $level
     * @param string|\Stringable $message
     * @param mixed[]            $context
     */
    public function log($level, $message, array $context = []): void
    {
        switch ($level) {
            case 'emergency':
            case 'critical':
                // @phpstan-ignore-next-line
                logger()->fatal((string) $message, [], $context);
                break;
            case 'error':
                // @phpstan-ignore-next-line
                logger()->error((string) $message, [], $context);
                break;
            case 'warning':
                // @phpstan-ignore-next-line
                logger()->warn((string) $message, [], $context);
                break;
            case 'debug':
                // @phpstan-ignore-next-line
                logger()->debug((string) $message, [], $context);
                break;
            default:
                // @phpstan-ignore-next-line
                logger()->info((string) $message, [], $context);
                break;
        }
    }
}
