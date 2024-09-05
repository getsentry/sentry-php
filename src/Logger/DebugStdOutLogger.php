<?php

declare(strict_types=1);

namespace Sentry\Logger;

use Psr\Log\AbstractLogger;

class DebugStdOutLogger extends AbstractLogger
{
    /**
     * @param mixed              $level
     * @param string|\Stringable $message
     * @param mixed[]            $context
     */
    public function log($level, $message, array $context = []): void
    {
        file_put_contents('php://stdout', \sprintf("sentry/sentry: [%s] %s\n", $level, (string) $message));
    }
}
