<?php

namespace Sentry\Tracing;

class SamplingContext
{
    /**
     * @var TransactionContext|null TransactionContext
     */
    private $transactionContext;

    /**
     * Returns the default instance of for the SamplingContext
     *
     * @param TransactionContext $transactionContext
     * @return self
     */
    public static function getDefault(TransactionContext $transactionContext): self
    {
        $context = new SamplingContext();
        $context->setTransactionContext($transactionContext);

        return $context;
    }

    /**
     * @return TransactionContext|null
     */
    public function getTransactionContext(): ?TransactionContext
    {
        return $this->transactionContext;
    }

    /**
     * @param TransactionContext|null $transactionContext
     */
    public function setTransactionContext(?TransactionContext $transactionContext): void
    {
        $this->transactionContext = $transactionContext;
    }

}
