<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * This class represents the Dynamic Sampling Context (dsc).
 *
 * @see https://develop.sentry.dev/sdk/performance/dynamic-sampling-context/
 */
final class DynamicSamplingContext
{
    private const SENTRY_ENTRY_PREFIX = 'sentry-';

    /**
     * The dsc entries.
     *
     * @var array<string, string>
     */
    private $entries = [];

    /**
     * Indicates if the dsc is mutalbe or immutable.
     *
     * @var bool
     */
    private $isFrozen = false;

    /**
     * Construct a new dsc object.
     */
    private function __construct()
    {
    }

    /**
     * Set a new key value pair on the dsc.
     *
     * @param string $key   the list member key
     * @param string $value the list member value
     */
    public function set(string $key, string $value): void
    {
        if ($this->isFrozen) {
            return;
        }

        $this->entries[$key] = $value;
    }

    /**
     * Check if a key value pair is set on the dsc.
     *
     * @param string $key the list member key
     */
    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    /**
     * Get a value from the dsc.
     *
     * @param string      $key     the list member key
     * @param string|null $default the default value to return if no value exists
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return $this->entries[$key] ?? $default;
    }

    /**
     * Mark the dsc as frozen.
     */
    public function freeze(): void
    {
        $this->isFrozen = true;
    }

    /**
     * Indicates that the dsc is frozen and cannot be mutated.
     */
    public function isFrozen(): bool
    {
        return $this->isFrozen;
    }

    /**
     * Check if there are any entries set.
     */
    public function hasEntries(): bool
    {
        return !empty($this->entries);
    }

    /**
     * Gets the dsc entries.
     *
     * @return array<string, string>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Parse the baggage header.
     *
     * @param string $header the baggage header contents
     */
    public static function fromHeader(string $header): self
    {
        $dsc = new self();

        foreach (explode(',', $header) as $listMember) {
            if (empty(trim($listMember))) {
                continue;
            }

            $keyValueAndProperties = explode(';', $listMember, 2);

            $keyValue = trim($keyValueAndProperties[0]);

            if (empty($keyValue) || !str_contains($keyValue, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $keyValue, 2);

            if (0 === strpos($key, self::SENTRY_ENTRY_PREFIX)) {
                $dsc->set(rawurldecode(str_replace(self::SENTRY_ENTRY_PREFIX, '', $key)), rawurldecode($value));
            }
        }

        // Once we receive a baggage header with Sentry entries from an upstream SDK,
        // we freeze the contents so it cannot be mutated anymore by this SDK.
        // It should only be propagated to the next downstream SDK or the Sentry server itself.
        $dsc->isFrozen = $dsc->hasEntries();

        return $dsc;
    }

    /**
     * Create a dsc object.
     *
     * @see https://develop.sentry.dev/sdk/performance/dynamic-sampling-context/#baggage-header
     */
    public static function fromTransaction(Transaction $transaction, HubInterface $hub): self
    {
        $dsc = new self();

        $dsc->set('trace_id', (string) $transaction->getTraceId());
        $dsc->set('sample_rate', (string) $transaction->getMetaData()->getSamplingRate());
        $dsc->set('transaction', $transaction->getName());

        $client = $hub->getClient();

        if (null !== $client) {
            $options = $client->getOptions();

            if (null !== $options) {
                if (null !== $options->getDsn() && null !== $options->getDsn()->getPublicKey()) {
                    $dsc->set('public_key', $options->getDsn()->getPublicKey());
                }
                if (null !== $options->getRelease()) {
                    $dsc->set('release', $options->getRelease());
                }
                if (null !== $options->getEnvironment()) {
                    $dsc->set('environment', $options->getEnvironment());
                }
            }
        }

        $hub->configureScope(static function (Scope $scope) use ($dsc): void {
            if (null !== $scope->getUser() && null !== $scope->getUser()->getSegment()) {
                $dsc->set('user_segment', $scope->getUser()->getSegment());
            }
        });

        $dsc->freeze();

        return $dsc;
    }

    /**
     * Serialize the dsc as a string.
     */
    public function __toString(): string
    {
        $string = '';

        foreach ($this->entries as $key => $value) {
            $string .= rawurlencode(self::SENTRY_ENTRY_PREFIX . $key) . '=' . rawurlencode($value);

            $string .= ',';
        }

        return rtrim($string, ',');
    }
}
