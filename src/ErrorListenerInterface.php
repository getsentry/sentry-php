<?php

declare(strict_types=1);

namespace Sentry;

interface ErrorListenerInterface
{
    /**
     * @param \ErrorException $error The error captured by the handler
     */
    public function __invoke(\ErrorException $error): void;
}
