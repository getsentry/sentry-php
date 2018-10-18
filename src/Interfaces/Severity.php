<?php

namespace Sentry\Interfaces;

final class Severity
{
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const FATAL = 'fatal';

    private $value;

    public function __construct(string $value = self::INFO)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value;
    }
}
