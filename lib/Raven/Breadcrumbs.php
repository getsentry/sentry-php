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
 * Raven Breadcrumbs
 *
 * @package raven
 */

class Breadcrumbs
{
    public $count;
    public $pos;
    public $size;
    /**
     * @var array[]
     */
    public $buffer;

    public function __construct($size = 100)
    {
        $this->size = $size;
        $this->reset();
    }

    public function reset()
    {
        $this->count = 0;
        $this->pos = 0;
        $this->buffer = array();
    }

    public function record($crumb)
    {
        if (empty($crumb['timestamp'])) {
            $crumb['timestamp'] = microtime(true);
        }
        $this->buffer[$this->pos] = $crumb;
        $this->pos = ($this->pos + 1) % $this->size;
        $this->count++;
    }

    /**
     * @return array[]
     */
    public function fetch()
    {
        $results = array();
        for ($i = 0; $i <= ($this->size - 1); $i++) {
            $idx = ($this->pos + $i) % $this->size;
            if (isset($this->buffer[$idx])) {
                $results[] = $this->buffer[$idx];
            }
        }
        return $results;
    }

    public function is_empty()
    {
        return $this->count === 0;
    }

    public function to_json()
    {
        return array(
            'values' => $this->fetch(),
        );
    }
}
