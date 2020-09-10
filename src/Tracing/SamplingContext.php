<?php

declare(strict_types=1);

namespace Sentry\Tracing;

final class SamplingContext
{
    /**
     * @var TransactionContext|null The context of the transaction
     */
    private $transactionContext;

    /**
     * @var bool|null Sampling decision from the parent transaction, if any
     */
    private $parentSampled;

    /**
     * Returns an instance populated with the data of the transaction context.
     */
    public static function getDefault(TransactionContext $transactionContext): self
    {
        $context = new self();
        $context->transactionContext = $transactionContext;
        $context->parentSampled = $transactionContext->getParentSampled();

        return $context;
    }

    public function getTransactionContext(): ?TransactionContext
    {
        return $this->transactionContext;
    }

    /**
     * Gets the sampling decision from the parent transaction, if any.
     */
    public function getParentSampled(): ?bool
    {
        return $this->parentSampled;
    }

    /**
     * Sets the sampling decision from the parent transaction, if any.
     */
    public function setParentSampled(?bool $parentSampled): void
    {
        $this->parentSampled = $parentSampled;
    }
}
