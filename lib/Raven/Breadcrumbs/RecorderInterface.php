<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Breadcrumbs;

/**
 * This interface must be implemented by all breadcrumb recorders.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface RecorderInterface extends \Countable, \Iterator
{
    /**
     * Records a new breadcrumb.
     *
     * @param Breadcrumb $breadcrumb The breadcrumb object
     */
    public function record(Breadcrumb $breadcrumb);

    /**
     * Clears all recorded breadcrumbs.
     */
    public function clear();
}
