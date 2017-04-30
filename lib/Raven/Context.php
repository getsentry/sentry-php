<?php

namespace Raven;

/**
 * Storage for additional client context.
 *
 * @package raven
 */
class Context
{
    /**
     * @var array
     */
    public $tags;
    /**
     * @var array
     */
    public $extra;
    /**
     * @var array|null
     */
    public $user;

    public function __construct()
    {
        $this->clear();
    }

    /**
     * Clean up existing context.
     */
    public function clear()
    {
        $this->tags = [];
        $this->extra = [];
        $this->user = null;
    }
}
