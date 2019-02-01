<?php

declare(strict_types=1);

namespace Sentry;

interface ExceptionListenerInterface
{
    /**
     * @param \Throwable $throwable The exception captured by the handler
     */
    public function __invoke(\Throwable $throwable): void;
}
