<?php

declare(strict_types=1);

namespace Sentry\Tracing;

class TransactionContext extends SpanContext
{
    /**
     * @var string|null Name of the transaction
     */
    public $name;
}
