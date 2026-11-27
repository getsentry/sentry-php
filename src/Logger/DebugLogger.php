<?php

declare(strict_types=1);

namespace Sentry\Logger;

use Psr\Log\AbstractLogger;

abstract class DebugLogger extends AbstractLogger
{
    /**
     * @param mixed              $level
     * @param string|\Stringable $message
     * @param mixed[]            $context
     */
    public function log($level, $message, array $context = []): void
    {
        $formattedLevel = \is_scalar($level) || $level instanceof \Stringable ? (string) $level : get_debug_type($level);
        $formattedMessageAndContext = implode(' ', array_filter([(string) $message, json_encode($context)]));

        $this->write(
            \sprintf("sentry/sentry: [%s] %s\n", $formattedLevel, $formattedMessageAndContext)
        );
    }

    abstract public function write(string $message): void;
}
