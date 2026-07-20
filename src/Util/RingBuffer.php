<?php

declare(strict_types=1);

namespace Sentry\Util;

/**
 * Ring buffer implementation with a fixed size that will overwrite the oldest elements when at capacity.
 * Backed by a regular PHP array that will dynamically grow in size until capacity is reached.
 *
 * `push` and `peek` operations are O(1).
 *
 * `toArray` and `drain` are O(n) where n is the count of the buffer. As long as no element was
 * overwritten or shifted, both are O(1) because the backing array can be returned as-is
 *
 * @template T
 */
class RingBuffer implements \Countable
{
    /**
     * @var array<int, T|null>
     */
    private $buffer = [];

    /**
     * @var int
     */
    private $capacity;

    /**
     * Points at the first element in the buffer.
     *
     * @var int
     */
    private $head = 0;

    /**
     * Points at the index where the next insertion will happen.
     * If the buffer is not full, this will point to an empty array index.
     * When full, it will point to the position where the oldest element is.
     *
     * @var int
     */
    private $tail = 0;

    /**
     * @var int
     */
    private $count = 0;

    /**
     * Creates a new buffer with a fixed capacity.
     */
    public function __construct(int $capacity)
    {
        if ($capacity <= 0) {
            throw new \RuntimeException('RingBuffer capacity must be greater than 0');
        }
        $this->capacity = $capacity;
    }

    /**
     * Returns how many elements can be stored in the buffer before it starts overwriting
     * old elements.
     */
    public function capacity(): int
    {
        return $this->capacity;
    }

    /**
     * The current number of stored elements.
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Whether the buffer contains any element or not.
     */
    public function isEmpty(): bool
    {
        return $this->count === 0;
    }

    /**
     * Whether the buffer is at capacity and will start to overwrite old elements on push.
     */
    public function isFull(): bool
    {
        return $this->count === $this->capacity;
    }

    /**
     * Adds a new element to the back of the buffer. If the buffer is at capacity, it will
     * overwrite the oldest element.
     *
     * Insertion order is still maintained.
     *
     * @param T $value
     */
    public function push($value): void
    {
        $this->buffer[$this->tail] = $value;

        $this->tail = ($this->tail + 1) % $this->capacity;

        if ($this->count === $this->capacity) {
            $this->head = ($this->head + 1) % $this->capacity;
        } else {
            ++$this->count;
        }
    }

    /**
     * Returns and removes the first element in the buffer.
     * If the buffer is empty, it will return null instead.
     *
     * @return T|null
     */
    public function shift()
    {
        if ($this->count === 0) {
            return null;
        }
        $value = $this->buffer[$this->head];

        $this->buffer[$this->head] = null;

        $this->head = ($this->head + 1) % $this->capacity;
        --$this->count;

        return $value;
    }

    /**
     * Returns the last element in the buffer without removing it.
     * If the buffer is empty, it will return null instead.
     *
     * @return T|null
     */
    public function peekBack()
    {
        if ($this->count === 0) {
            return null;
        }

        return $this->buffer[($this->tail - 1 + $this->capacity) % $this->capacity];
    }

    /**
     * Returns the first element in the buffer without removing it.
     * If the buffer is empty, it will return null instead.
     *
     * @return T|null
     */
    public function peekFront()
    {
        if ($this->count === 0) {
            return null;
        }

        return $this->buffer[$this->head];
    }

    /**
     * Resets the count and removes all elements from the buffer.
     */
    public function clear(): void
    {
        $this->buffer = [];
        $this->count = 0;
        $this->head = 0;
        $this->tail = 0;
    }

    /**
     * Returns the content of the buffer as array. The resulting array will have the size of `count`
     * and not `capacity`.
     *
     * @return array<T>
     */
    public function toArray(): array
    {
        if ($this->count === 0) {
            return [];
        }

        // When we have never overwritten an element, or we did a full revolution, we can
        // just return the buffer as-is because it has the correct order
        if ($this->head === 0 && $this->count === \count($this->buffer)) {
            /** @var array<T> */
            return $this->buffer;
        }

        // If the data doesn't wrap around, we can just return the slice from head to count
        if ($this->head + $this->count <= $this->capacity) {
            /** @var array<T> */
            return \array_slice($this->buffer, $this->head, $this->count);
        }

        /** @var array<T> */
        return array_merge(
            \array_slice($this->buffer, $this->head),
            \array_slice($this->buffer, 0, $this->tail)
        );
    }

    /**
     * Returns the content of the buffer and clears all elements that it contains in the process.
     *
     * @return array<T>
     */
    public function drain(): array
    {
        $result = $this->toArray();
        $this->clear();

        return $result;
    }
}
