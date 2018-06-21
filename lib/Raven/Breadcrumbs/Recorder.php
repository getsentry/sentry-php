<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Breadcrumbs;

use Raven\Exception\InvalidArgumentException;

/**
 * This class is a circular FIFO buffer that can store up to a certain amount
 * of breadcrumbs before overwriting the older ones.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Recorder implements \Countable, \Iterator
{
    /**
     * This constant defines the maximum number of breadcrumbs to store.
     */
    const MAX_ITEMS = 100;

    /**
     * @var int The current position of the iterator
     */
    private $position = 0;

    /**
     * @var int The current head position
     */
    private $head = 0;

    /**
     * @var int Current number of stored breadcrumbs
     */
    private $size = 0;

    /**
     * @var \SplFixedArray|Breadcrumb[] The list of recorded breadcrumbs
     */
    private $breadcrumbs;

    /**
     * @var int The maximum number of breadcrumbs to store
     */
    private $maxSize;

    /**
     * Constructor.
     *
     * @param int $maxSize The maximum number of breadcrumbs to store
     */
    public function __construct($maxSize = self::MAX_ITEMS)
    {
        if (!is_int($maxSize) || $maxSize < 1) {
            throw new InvalidArgumentException(sprintf('The $maxSize argument must be an integer greater than 0.'));
        }

        $this->breadcrumbs = new \SplFixedArray($maxSize);
        $this->maxSize = $maxSize;
    }

    /**
     * Records a new breadcrumb.
     *
     * @param Breadcrumb $breadcrumb The breadcrumb object
     */
    public function record(Breadcrumb $breadcrumb)
    {
        $this->breadcrumbs[$this->head] = $breadcrumb;
        $this->head = ($this->head + 1) % $this->maxSize;
        $this->size = min($this->size + 1, $this->maxSize);
    }

    /**
     * Clears all recorded breadcrumbs.
     */
    public function clear()
    {
        $this->breadcrumbs = new \SplFixedArray($this->maxSize);
        $this->position = 0;
        $this->head = 0;
        $this->size = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->breadcrumbs[($this->head + $this->position) % $this->size];
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->position < $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->size;
    }
}
