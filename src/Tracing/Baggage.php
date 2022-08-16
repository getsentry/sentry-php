<?php

declare(strict_types=1);

namespace Sentry\Tracing;

/**
 * This class represents the baggage header contents used for Dynamic Sampling Context.
 *
 * @see https://develop.sentry.dev/sdk/performance/dynamic-sampling-context/
 */
final class Baggage
{
    public const SENTRY_ENTRY_PREFIX = 'sentry-';

    /**
     * The baggage header entries.
     *
     * @var array<string, string>
     */
    private $entries;

    /**
     * The baggage header entry properties.
     *
     * @var array<string, string>
     */
    private $properties;

    /**
     * Indicates that the baggage contents are frozen and cannot be mutated.
     *
     * @var bool
     */
    private $frozen = false;

    /**
     * Construct a new DSC information object.
     */
    private function __construct()
    {
        $this->entries = [];
        $this->properties = [];
    }

    /**
     * Set a new key value pair for the baggage header.
     *
     * @param string $key the list member key
     * @param string $value the list member value
     * @param string $properties the list member properties (raw)
     */
    public function set(string $key, string $value, string $properties = ''): void
    {
        if ($this->frozen) {
            return;
        }

        $this->entries[$key] = $value;
        $this->properties[$key] = $properties;
    }

    /**
     * Check if a key value pair is set on the baggage header.
     *
     * @param string $key the list member key
     */
    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    /**
     * Get a value from the baggage header.
     *
     * @param string $key the list member key
     * @param string|null $default the default value to return if no value exists
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return $this->entries[$key] ?? $default;
    }

    /**
     * Indicates that the baggage contents are frozen and cannot be mutated.
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Check if there are Sentry entries set.
     *
     * @return bool
     */
    public function hasSentryEntries(): bool
    {
        foreach (array_keys($this->entries) as $key) {
            if (str_starts_with(self::SENTRY_ENTRY_PREFIX, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Serialize the baggage contents to a string.
     */
    public function __toString(): string
    {
        $string = '';

        foreach ($this->entries as $key => $value) {
            $string .= urlencode($key) . '=' . urlencode($value);

            if (!empty($this->properties[$key])) {
                $string .= ";{$this->properties[$key]}";
            }

            $string .= ',';
        }

        return rtrim($string, ',');
    }

    /**
     * Parse the baggage header according to the w3 spec.
     *
     * @see https://www.w3.org/TR/baggage/#definition
     *
     * @param string $header the baggage header contents
     */
    public static function fromBaggageHeader(string $header): self
    {
        $baggage = new self();

        foreach (explode(',', $header) as $listMember) {
            if (empty(trim($listMember))) {
                continue;
            }

            $keyValueAndProperties = explode(';', $listMember, 2);

            $keyValue = trim($keyValueAndProperties[0]);

            if (empty($keyValue) || !str_contains($keyValue, '=')) {
                continue;
            }

            $propertiesString = $keyValueAndProperties[1] ?? null;

            if (null !== $propertiesString && !empty(trim($propertiesString))) {
                $properties = trim($propertiesString);
            } else {
                $properties = '';
            }

            [$key, $value] = explode('=', $keyValue, 2);

            $baggage->set(urldecode($key), urldecode($value), $properties);
        }

        // Once we receive a baggage header with Sentry entries from an upstream SDK we
        // freeze the contents so it cannot be mutated anymore by this SDK it should
        // only be propagated downstream to the next SDK or Sentry server itself.
        $baggage->frozen = $baggage->hasSentryEntries();

        return $baggage;
    }
}
