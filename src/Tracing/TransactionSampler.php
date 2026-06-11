<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\Options;

/**
 * @internal
 */
final class TransactionSampler
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $customSamplingContext Additional context that will be passed to the {@see SamplingContext}
     */
    public static function startTransaction(Options $options, TransactionContext $context, array $customSamplingContext = []): Transaction
    {
        $transaction = new Transaction($context);
        $logger = $options->getLoggerOrNullLogger();

        if (!$options->isTracingEnabled()) {
            $transaction->setSampled(false);

            $logger->warning(\sprintf('Transaction [%s] was started but tracing is not enabled.', (string) $transaction->getTraceId()), ['context' => $context]);

            return $transaction;
        }

        $samplingContext = SamplingContext::getDefault($context);
        $samplingContext->setAdditionalContext($customSamplingContext);

        $sampleSource = 'context';
        $sampleRand = $context->getMetadata()->getSampleRand() ?? 0.0;

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

        $profilesSampleSource = 'config:profiles_sample_rate';
        $profilesSampler = $options->getProfilesSampler();

        if ($profilesSampler !== null) {
            $profilesSampleRate = $profilesSampler($samplingContext);
            $profilesSampleSource = 'config:profiles_sampler';
        } else {
            $profilesSampleRate = $options->getProfilesSampleRate();
        }

        if ($profilesSampleRate === null) {
            $logger->info(\sprintf('Transaction [%s] is not profiling because neither `profiles_sample_rate` nor `profiles_sampler` option is set.', (string) $transaction->getTraceId()));
        } elseif (!self::isValidSampleRate($profilesSampleRate)) {
            $logger->warning(\sprintf('Transaction [%s] is not profiling because profile sample rate (decided by %s) is invalid.', (string) $transaction->getTraceId(), $profilesSampleSource));
        } elseif (self::sampleRate($profilesSampleRate)) {
            $logger->info(\sprintf('Transaction [%s] started profiling because it was sampled.', (string) $transaction->getTraceId()));

            $transaction->initProfiler()->start();
        } else {
            $logger->info(\sprintf('Transaction [%s] is not profiling because it was not sampled.', (string) $transaction->getTraceId()));
        }

        return $transaction;
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
    private static function sampleRate($sampleRate): bool
    {
        if (!\is_float($sampleRate) && !\is_int($sampleRate)) {
            return false;
        }

        if ($sampleRate === 0.0) {
            return false;
        }

        if ($sampleRate === 1.0) {
            return true;
        }

        return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax() < (float) $sampleRate;
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
