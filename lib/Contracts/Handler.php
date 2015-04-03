<?php namespace Raven\Contracts;

use Exception;

/**
 * Contract for Error Handlers
 *
 * @package raven
 */
interface Handler
{
    /**
     * A method compatible with set_error_handler
     *
     * @param int    $code
     * @param string $message
     * @param string $file
     * @param int    $line
     * @param array  $context
     * @return null|mixed
     */
    public function handleError($code, $message, $file = '', $line = 0, $context = array());

    /**
     * Handle a fatal error as a special case.
     *
     * @return void
     */
    public function handleFatalError();

    /**
     * @param \Exception $e       The exception thrown
     * @param bool       $isError True if $e is an ErrorException
     * @param null       $vars    Variables to pass to sentry
     * @return void
     */
    public function handleException(Exception $e, $isError = false, $vars = null);
}
