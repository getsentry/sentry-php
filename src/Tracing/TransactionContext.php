<?php

declare(strict_types=1);

namespace Sentry\Tracing;

final class TransactionContext extends SpanContext
{
    /**
     * @var string|null Name of the transaction
     */
    public $name;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
