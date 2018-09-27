<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Middleware;

/**
 * This interface can be implemented by the middlewares that sanitizes
 * the data.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface ProcessorMiddlewareInterface
{
    /**
     * This constant defines the mask string used to strip sensitive information.
     */
    const STRING_MASK = '********';
}
