<?php namespace Raven\Util;

/**
 * Storage for additional client context.
 *
 * @package raven
 */
class Context
{
    /**
     * Tags to send with each request
     *
     * @var array
     */
    public $tags = array();

    /**
     * Any extra info to send with each request
     *
     * @var array
     */
    public $extra = array();

    /**
     * The user associated with this request
     *
     * @var mixed
     */
    public $user;

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
