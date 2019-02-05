<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This interface represents a listener that will be invoked by the {@see ErrorHandler}
 * on each caught error.
 */
interface ErrorListenerInterface
{
    /**
     * This is the method that will receive the caught error; i.e. it can
     * be used to capture it through the {@see Hub}.
     *
     * @param \ErrorException $error The error captured by the handler
     */
    public function __invoke(\ErrorException $error): void;
}
