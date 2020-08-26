<?php

declare(strict_types=1);

namespace Sentry\Tracing;

final class TransactionContext extends SpanContext
{
    /**
     * @var string|null Name of the transaction
     */
    public $name;

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
