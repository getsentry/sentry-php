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

use Raven\Breadcrumbs\Breadcrumb;

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

        $this->client->leaveBreadcrumb(new Breadcrumb(
            $this->client->translateSeverity($exception->getSeverity()),
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
}
