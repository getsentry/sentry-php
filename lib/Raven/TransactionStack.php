<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven;

/**
 * This class is a LIFO collection that only allows access to the value at the
 * top of the stack.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class TransactionStack implements \Countable
{
    /**
     * @var string[] The transaction stack
     */
    private $transactions = [];

    /**
     * Class constructor.
     *
     * @param array $values An array of initial values
     */
    public function __construct(array $values = [])
    {
        $this->push(...$values);
    }

    /**
     * Clears the stack by removing all values.
     */
    public function clear()
    {
        $this->transactions = [];
    }

    /**
     * Checks whether the stack is empty.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->transactions);
    }

    /**
     * Pushes the given values onto the stack.
     *
     * @param array<int, string> $values The values to push
     *
     * @throws \InvalidArgumentException If any of the values is not a string
     */
    public function push(...$values)
    {
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException(sprintf('The $values argument must contain string values only.'));
            }

            $this->transactions[] = $value;
        }
    }

    /**
     * Gets the value at the top of the stack without removing it.
     *
     * @return string|null
     */
    public function peek()
    {
        if (empty($this->transactions)) {
            return null;
        }

        return $this->transactions[count($this->transactions) - 1];
    }

    /**
     * Removes and returns the value at the top of the stack.
     *
     * @return string|null
     */
    public function pop()
    {
        if (empty($this->transactions)) {
            return null;
        }

        return array_pop($this->transactions);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->transactions);
    }
}
