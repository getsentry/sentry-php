<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry;

use Sentry\Breadcrumbs\Breadcrumb;

/**
 * This error handler records a breadcrumb for any raised error.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class BreadcrumbErrorHandler extends AbstractErrorHandler
{
    /**
     * Registers this error handler by associating its instance with the given
     * Raven client.
     *
     * @param ClientInterface $client             The Raven client
     * @param int             $reservedMemorySize The amount of memory to reserve for the fatal error handler
     *
     * @return BreadcrumbErrorHandler
     */
    public static function register(ClientInterface $client, $reservedMemorySize = 10240)
    {
        return new self($client, $reservedMemorySize);
    }

    /**
     * {@inheritdoc}
     */
    public function doHandleException($exception)
    {
        if (!$exception instanceof \ErrorException) {
            return;
        }

        $this->client->addBreadcrumb(new Breadcrumb(
            $this->getSeverityFromErrorException($exception),
            Breadcrumb::TYPE_ERROR,
            'error_reporting',
            $exception->getMessage(),
            [
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]
        ));
    }

    /**
     * Maps the severity of the error to one of the levels supported by the
     * breadcrumbs.
     *
     * @param \ErrorException $exception The exception
     *
     * @return string
     */
    private function getSeverityFromErrorException(\ErrorException $exception): string
    {
        switch ($exception->getSeverity()) {
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_WARNING:
            case E_USER_WARNING:
            case E_RECOVERABLE_ERROR:
                return Breadcrumb::LEVEL_WARNING;
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_ERROR:
            case E_COMPILE_WARNING:
                return Breadcrumb::LEVEL_CRITICAL;
            case E_USER_ERROR:
                return Breadcrumb::LEVEL_ERROR;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                return Breadcrumb::LEVEL_INFO;
            default:
                return Breadcrumb::LEVEL_ERROR;
        }
    }
}
