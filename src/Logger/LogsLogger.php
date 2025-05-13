<?php

declare(strict_types=1);

namespace Sentry\Logger;

use Psr\Log\AbstractLogger;
use Sentry\Logs\LogAttribute;
use Sentry\Logs\Logs;

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
                Logs::getInstance()->fatal((string) $message, [], $context);
                break;
            case 'error':
                // @phpstan-ignore-next-line
                Logs::getInstance()->error((string) $message, [], $context);
                break;
            case 'warning':
                // @phpstan-ignore-next-line
                Logs::getInstance()->warn((string) $message, [], $context);
                break;
            case 'debug':
                // @phpstan-ignore-next-line
                Logs::getInstance()->debug((string) $message, [], $context);
                break;
            default:
                // @phpstan-ignore-next-line
                Logs::getInstance()->info((string) $message, [], $context);
                break;
        }
    }
}
