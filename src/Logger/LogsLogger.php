<?php

declare(strict_types=1);

namespace Sentry\Logger;

use Psr\Log\AbstractLogger;
use Sentry\Attributes\Attribute;

use function Sentry\logger;

/**
 * @phpstan-import-type AttributeValue from Attribute
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
                logger()->fatal((string) $message, [], $context);
                break;
            case 'error':
                logger()->error((string) $message, [], $context);
                break;
            case 'warning':
                logger()->warn((string) $message, [], $context);
                break;
            case 'debug':
                logger()->debug((string) $message, [], $context);
                break;
            default:
                logger()->info((string) $message, [], $context);
                break;
        }
    }
}
