<?php

declare(strict_types=1);

namespace Sentry\Logger;

use Psr\Log\AbstractLogger;

class DebugFileLogger extends AbstractLogger
{
    /**
     * @var string
     */
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @param mixed   $level
     * @param mixed[] $context
     */
    public function log($level, $message, array $context = []): void
    {
        file_put_contents($this->filePath, sprintf("sentry/sentry: [%s] %s\n", $level, (string) $message), \FILE_APPEND);
    }
}
