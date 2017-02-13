<?php
/**
 * Storage for additional client context.
 *
 * @package raven
 */
class Raven_Context
{
    /**
     * @var array
     */
    var $tags;
    /**
     * @var array
     */
    var $extra;
    /**
     * @var array|null
     */
    var $user;

    public function __construct()
    {
        $this->clear();
    }

    /**
     * Clean up existing context.
     */
    public function clear()
    {
        $this->tags = array();
        $this->extra = array();
        $this->user = null;
    }
}
