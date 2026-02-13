<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\State\Scope;
use Sentry\State\ScopeManager;
use Sentry\Tracing\SamplingContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

/**
 * This class is the main entry point for all the most common SDK features.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SentrySdk
{
    /**
     * @var ScopeManager|null The scope manager
     */
    private static $scopeManager;

    /**
     * Constructor.
     */
    private function __construct()
    {
    }

    /**
     * Initializes the SDK by binding a client to the global scope.
     */
    public static function init(?ClientInterface $client = null): void
    {
        if ($client === null) {
            $client = new NoOpClient();
        }

        $scopeManager = self::getScopeManager();
        $scopeManager->resetScopes();
        $scopeManager->getGlobalScope()->bindClient($client);
    }

    public static function getGlobalScope(): Scope
    {
        return self::getScopeManager()->getGlobalScope();
    }

    public static function getIsolationScope(): Scope
    {
        return self::getScopeManager()->getIsolationScope();
    }

    public static function getCurrentScope(): Scope
    {
        return self::getScopeManager()->getCurrentScope();
    }

    /**
     * Forks the current scope and executes the given callback within it.
     *
     * @param callable $callback The callback to be executed
     *
     * @psalm-template T
     *
     * @psalm-param callable(Scope): T $callback
     *
     * @return mixed|void The callback's return value, upon successful execution
     *
     * @psalm-return T
     */
    public static function withScope(callable $callback)
    {
        return self::getScopeManager()->withScope($callback);
    }

    /**
     * Forks the isolation scope (and current scope) and executes the callback within it.
     *
     * @param callable $callback The callback to be executed
     *
     * @psalm-template T
     *
     * @psalm-param callable(Scope): T $callback
     *
     * @return mixed|void The callback's return value, upon successful execution
     *
     * @psalm-return T
     */
    public static function withIsolationScope(callable $callback)
    {
        return self::getScopeManager()->withIsolationScope($callback);
    }

    /**
     * Configures the isolation scope by invoking the callback with it.
     *
     * @param callable $callback The callback to be executed
     */
    public static function configureScope(callable $callback): void
    {
        $callback(self::getIsolationScope());
    }

    /**
     * Starts a new `Transaction` and returns it. This is the entry point to manual
     * tracing instrumentation.
     *
     * @param TransactionContext   $context               Properties of the new transaction
     * @param array<string, mixed> $customSamplingContext Additional context that will be passed to the {@see SamplingContext}
     */
    public static function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
    {
        $client = self::getClient();
        $transaction = new Transaction($context, $client);
        $options = $client->getOptions();
        $logger = $options->getLoggerOrNullLogger();

        if (!$options->isTracingEnabled()) {
            $transaction->setSampled(false);

            $logger->warning(\sprintf('Transaction [%s] was started but tracing is not enabled.', (string) $transaction->getTraceId()), ['context' => $context]);

            return $transaction;
        }

        $samplingContext = SamplingContext::getDefault($context);
        $samplingContext->setAdditionalContext($customSamplingContext);

        $sampleSource = 'context';
        $sampleRand = $context->getMetadata()->getSampleRand();

        if ($transaction->getSampled() === null) {
            $tracesSampler = $options->getTracesSampler();

            if ($tracesSampler !== null) {
                $sampleRate = $tracesSampler($samplingContext);
                $sampleSource = 'config:traces_sampler';
            } else {
                $parentSampleRate = $context->getMetadata()->getParentSamplingRate();
                if ($parentSampleRate !== null) {
                    $sampleRate = $parentSampleRate;
                    $sampleSource = 'parent:sample_rate';
                } else {
                    $sampleRate = self::getSampleRate(
                        $samplingContext->getParentSampled(),
                        $options->getTracesSampleRate() ?? 0
                    );
                    $sampleSource = $samplingContext->getParentSampled() !== null ? 'parent:sampling_decision' : 'config:traces_sample_rate';
                }
            }

            if (!self::isValidSampleRate($sampleRate)) {
                $transaction->setSampled(false);

                $logger->warning(\sprintf('Transaction [%s] was started but not sampled because sample rate (decided by %s) is invalid.', (string) $transaction->getTraceId(), $sampleSource), ['context' => $context]);

                return $transaction;
            }

            $transaction->getMetadata()->setSamplingRate($sampleRate);

            // Always overwrite the sample_rate in the DSC
            $dynamicSamplingContext = $context->getMetadata()->getDynamicSamplingContext();
            if ($dynamicSamplingContext !== null) {
                $dynamicSamplingContext->set('sample_rate', (string) $sampleRate, true);
            }

            if ($sampleRate === 0.0) {
                $transaction->setSampled(false);

                $logger->info(\sprintf('Transaction [%s] was started but not sampled because sample rate (decided by %s) is %s.', (string) $transaction->getTraceId(), $sampleSource, $sampleRate), ['context' => $context]);

                return $transaction;
            }

            $transaction->setSampled($sampleRand < $sampleRate);
        }

        if (!$transaction->getSampled()) {
            $logger->info(\sprintf('Transaction [%s] was started but not sampled, decided by %s.', (string) $transaction->getTraceId(), $sampleSource), ['context' => $context]);

            return $transaction;
        }

        $logger->info(\sprintf('Transaction [%s] was started and sampled, decided by %s.', (string) $transaction->getTraceId(), $sampleSource), ['context' => $context]);

        $transaction->initSpanRecorder();

        $profilesSampleRate = $options->getProfilesSampleRate();
        if ($profilesSampleRate === null) {
            $logger->info(\sprintf('Transaction [%s] is not profiling because `profiles_sample_rate` option is not set.', (string) $transaction->getTraceId()));
        } elseif (self::sample($profilesSampleRate)) {
            $logger->info(\sprintf('Transaction [%s] started profiling because it was sampled.', (string) $transaction->getTraceId()));

            $transaction->initProfiler()->start();
        } else {
            $logger->info(\sprintf('Transaction [%s] is not profiling because it was not sampled.', (string) $transaction->getTraceId()));
        }

        return $transaction;
    }

    public static function getMergedScope(): Scope
    {
        return Scope::mergeScopes(
            self::getGlobalScope(),
            self::getIsolationScope(),
            self::getCurrentScope()
        );
    }

    public static function getClient(): ClientInterface
    {
        return Scope::getClientFromScopes(
            self::getGlobalScope(),
            self::getIsolationScope(),
            self::getCurrentScope()
        );
    }

    private static function getScopeManager(): ScopeManager
    {
        if (self::$scopeManager === null) {
            self::$scopeManager = new ScopeManager();
        }

        return self::$scopeManager;
    }

    private static function getSampleRate(?bool $hasParentBeenSampled, float $fallbackSampleRate): float
    {
        if ($hasParentBeenSampled === true) {
            return 1.0;
        }

        if ($hasParentBeenSampled === false) {
            return 0.0;
        }

        return $fallbackSampleRate;
    }

    /**
     * @param mixed $sampleRate
     */
    private static function sample($sampleRate): bool
    {
        if ($sampleRate === 0.0 || $sampleRate === null) {
            return false;
        }

        if ($sampleRate === 1.0) {
            return true;
        }

        return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax() < $sampleRate;
    }

    /**
     * @param mixed $sampleRate
     */
    private static function isValidSampleRate($sampleRate): bool
    {
        if (!\is_float($sampleRate) && !\is_int($sampleRate)) {
            return false;
        }

        if ($sampleRate < 0 || $sampleRate > 1) {
            return false;
        }

        return true;
    }
}
