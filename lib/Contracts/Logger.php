<?php namespace Raven\Contracts;

use Exception;
use Raven\Raven;

interface Logger
{
    /**
     * Log a generic message
     *
     * @param string $message
     * @param string $level
     * @return $this
     */
    public function logMessage($message, $level = Raven::ERROR);

    /**
     * Log an exception
     *
     * @param \Exception $exception
     * @param string $level
     * @return $this
     */
    public function logException(Exception $exception, $level = Raven::ERROR);

    /**
     * Log an error
     *
     * @param        $error
     * @param string $level
     * @return $this
     */
    public function logError($error, $level = Raven::ERROR);

    /**
     * Log a fatal error
     *
     * @param        $error
     * @param string $level
     * @return $this
     */
    public function logFatalError($error, $level = Raven::FATAL);
}
