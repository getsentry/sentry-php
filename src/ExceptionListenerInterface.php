<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This interface represents a listener that will be invoked by the {@see ErrorHandler}
 * on each caught exception.
 */
interface ExceptionListenerInterface
{
    /**
     * This is the method that will receive the caught exception; i.e. it can
     * be used to capture it through the {@see Hub}.
     *
     * @param \Throwable $throwable The exception captured by the handler
     */
    public function __invoke(\Throwable $throwable): void;
}
