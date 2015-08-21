<?php
/**
 * Storage for additional client context.
 *
 * @package raven
 */
class Raven_Context
{
    /** @type array */
    public $tags;
    /** @type array */
    public $extra;
    /** @type array|null */
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
        $this->tags = array();
        $this->extra = array();
        $this->user = null;
    }
}
