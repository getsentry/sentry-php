<?php namespace Raven\Contracts;

use Exception;
use Raven\Raven;

interface Client
{
    /**
     * Capture a given exception
     *
     * @param \Exception $exception
     * @param null       $options
     * @param null       $logger
     * @param null       $vars
     * @return string
     */
    public function captureException(Exception $exception, $options = null, $logger = null, $vars = null);

    /**
     * Send an arbitrary message to Sentry
     *
     * @param string $message
     * @param array $params
     * @param array $options
     * @param bool  $stack
     * @param null  $vars
     * @return string
     */
    public function captureMessage($message, $params = array(), $options = array(), $stack = false, $vars = null);

    /**
     * Send a query to sentry (e.g. from elasticsearch)
     *
     * @param string $query
     * @param string $level
     * @param string $engine
     * @return string
     */
    public function captureQuery($query, $level = Raven::INFO, $engine = '');

    /**
     * Return an id for an event
     *
     * @return string
     */
    public function getIdent($event);

    /**
     * Add a sanitizer to the stack
     *
     * @param \Raven\Contracts\Sanitizer $sanitizer
     * @return $this
     */
    public function addSanitizer(Sanitizer $sanitizer);

    /**
     * Add an array of sanitizers to the stack
     *
     * @param \Raven\Contracts\Sanitizer[] $sanitizers
     * @return $this
     */
    public function addSanitizers(array $sanitizers);

    /**
     * Set the handler used to handle errors
     *
     * @param \Raven\Contracts\Handler $handler
     * @return $this
     */
    public function setHandler(Handler $handler);

    /**
     * Get the handler in use for handling errors
     *
     * @return \Raven\Contracts\Handler
     */
    public function getHandler();
}
