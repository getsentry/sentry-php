<?php

declare(strict_types=1);

namespace Sentry\Util;

/**
 * Creates a new data container for Telemetry data such as Logs or Metrics.
 * If a size parameter is passed, it will create a RingBuffer under the hood to restrict the number of
 * items, if no size is used then it will be backed by a regular array.
 *
 * The TelemetryStorage operates under the same constraints as the RingBuffer, meaning that it's possible to
 * add/remove from the front and the back, but it's not possible to remove from the middle or based on an offset.
 * To do that, one has to either drain or convert it into an array (which will be basically free if unbounded)
 *
 * @template T
 *
 * @internal
 */
class TelemetryStorage implements \Countable
{
    /**
     * @var T[]|RingBuffer<T>
     */
    private $data;

    private function __construct(?int $size = null)
    {
        if ($size !== null) {
            $this->data = new RingBuffer($size);
        } else {
            $this->data = [];
        }
    }

    public function count(): int
    {
        return \count($this->data);
    }

    /**
     * @param T $value
     */
    public function push($value): void
    {
        if ($this->data instanceof RingBuffer) {
            $this->data->push($value);
        } else {
            $this->data[] = $value;
        }
    }

    /**
     * @return T[]
     */
    public function drain(): array
    {
        if ($this->data instanceof RingBuffer) {
            return $this->data->drain();
        }
        $data = $this->data;
        $this->data = [];

        return $data;
    }

    /**
     * @return T[]
     */
    public function toArray(): array
    {
        if ($this->data instanceof RingBuffer) {
            return $this->data->toArray();
        }

        return $this->data;
    }

    public function isEmpty(): bool
    {
        if ($this->data instanceof RingBuffer) {
            return $this->data->isEmpty();
        }

        return empty($this->data);
    }

    /**
     * Creates a new TelemetryStorage that is not bounded in size. This version should only be used if there
     * is another flushing signal available.
     *
     * @return self<T>
     */
    public static function unbounded(): self
    {
        return new self();
    }

    /**
     * Creates a TelemetryStorage that has an upper bound of $size. It will drop the oldest items when new items
     * are added while being at capacity.
     *
     * @return self<T>
     */
    public static function bounded(int $size): self
    {
        return new self($size);
    }
}
