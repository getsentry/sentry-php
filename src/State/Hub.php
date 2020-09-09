<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\EventId;
use Sentry\Integration\IntegrationInterface;
use Sentry\Severity;
use Sentry\Tracing\SamplingContext;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

/**
 * This class is a basic implementation of the {@see HubInterface} interface.
 */
final class Hub implements HubInterface
{
    /**
     * @var Layer[] The stack of client/scope pairs
     */
    private $stack = [];

    /**
     * @var EventId|null The ID of the last captured event
     */
    private $lastEventId;

    /**
     * Hub constructor.
     *
     * @param ClientInterface|null $client The client bound to the hub
     * @param Scope|null           $scope  The scope bound to the hub
     */
    public function __construct(?ClientInterface $client = null, ?Scope $scope = null)
    {
        $this->stack[] = new Layer($client, $scope ?? new Scope());
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ?ClientInterface
    {
        return $this->getStackTop()->getClient();
    }

    /**
     * {@inheritdoc}
     */
    public function getLastEventId(): ?EventId
    {
        return $this->lastEventId;
    }

    /**
     * {@inheritdoc}
     */
    public function pushScope(): Scope
    {
        $clonedScope = clone $this->getScope();

        $this->stack[] = new Layer($this->getClient(), $clonedScope);

        return $clonedScope;
    }

    /**
     * {@inheritdoc}
     */
    public function popScope(): bool
    {
        if (1 === \count($this->stack)) {
            return false;
        }

        return null !== array_pop($this->stack);
    }

    /**
     * {@inheritdoc}
     */
    public function withScope(callable $callback): void
    {
        $scope = $this->pushScope();

        try {
            $callback($scope);
        } finally {
            $this->popScope();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureScope(callable $callback): void
    {
        $callback($this->getScope());
    }

    /**
     * {@inheritdoc}
     */
    public function bindClient(ClientInterface $client): void
    {
        $layer = $this->getStackTop();
        $layer->setClient($client);
    }

    /**
     * {@inheritdoc}
     */
    public function captureMessage(string $message, ?Severity $level = null): ?EventId
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureMessage($message, $level, $this->getScope());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function captureException(\Throwable $exception): ?EventId
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureException($exception, $this->getScope());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function captureEvent($payload): ?EventId
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureEvent($payload, $this->getScope());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function captureLastError(): ?EventId
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureLastError($this->getScope());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        $client = $this->getClient();

        if (null === $client) {
            return false;
        }

        $options = $client->getOptions();
        $beforeBreadcrumbCallback = $options->getBeforeBreadcrumbCallback();
        $maxBreadcrumbs = $options->getMaxBreadcrumbs();

        if ($maxBreadcrumbs <= 0) {
            return false;
        }

        $breadcrumb = $beforeBreadcrumbCallback($breadcrumb);

        if (null !== $breadcrumb) {
            $this->getScope()->addBreadcrumb($breadcrumb, $maxBreadcrumbs);
        }

        return null !== $breadcrumb;
    }

    /**
     * {@inheritdoc}
     */
    public function getIntegration(string $className): ?IntegrationInterface
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $client->getIntegration($className);
        }

        return null;
    }

    /**
     * Gets the scope bound to the top of the stack.
     */
    private function getScope(): Scope
    {
        return $this->getStackTop()->getScope();
    }

    /**
     * Gets the topmost client/layer pair in the stack.
     */
    private function getStackTop(): Layer
    {
        return $this->stack[\count($this->stack) - 1];
    }

    /**
     * {@inheritdoc}
     */
    public function startTransaction(TransactionContext $context): Transaction
    {
        $client = $this->getClient();
        $sampleRate = null;

        if (null !== $client) {
            $sampler = $client->getOptions()->getTracesSampler();

            if (null !== $sampler) {
                if (\is_callable($sampler)) {
                    $sampleRate = \call_user_func($sampler, SamplingContext::getDefault($context));
                }
            }
        }

        // Roll the dice for sampling transaction, all child spans inherit the sampling decision.
        // Only if $sampleRate is `null` (which can only be because then the traces_sampler wasn't defined)
        // we need to roll the dice.
        if (null === $context->sampled && null == $sampleRate) {
            if (null !== $client) {
                $sampleRate = $client->getOptions()->getTracesSampleRate();
            }
        }

        if ($sampleRate < 1 && mt_rand(1, 100) / 100.0 > $sampleRate) {
            // if true = we want to have the transaction
            // if false = we don't want to have it
            $context->sampled = false;
        } else {
            $context->sampled = true;
        }

        $transaction = new Transaction($context, $this);

        // We only want to create a span list if we sampled the transaction
        // If sampled == false, we will discard the span anyway, so we can save memory by not storing child spans
        if ($context->sampled) {
            $transaction->initSpanRecorder();
        }

        return $transaction;
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function getTransaction(): ?Transaction
    {
        return $this->getScope()->getTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function setSpan(?Span $span): HubInterface
    {
        $this->getScope()->setSpan($span);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getSpan(): ?Span
    {
        return $this->getScope()->getSpan();
    }
}
