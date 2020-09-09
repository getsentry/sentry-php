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
     * Returns the default instance of for the SamplingContext.
     */
    public static function getDefault(TransactionContext $transactionContext): self
    {
        $context = new SamplingContext();
        $context->setTransactionContext($transactionContext);

        return $context;
    }

    public function getTransactionContext(): ?TransactionContext
    {
        return $this->transactionContext;
    }

    public function setTransactionContext(?TransactionContext $transactionContext): void
    {
        $this->transactionContext = $transactionContext;
    }
}
