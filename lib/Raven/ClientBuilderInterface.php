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

/**
 * A configurable builder for Client objects.
 *
 * @author Stefano Arlandini <stefano.arlandini@nooo.it>
 */
interface ClientBuilderInterface
{
    /**
     * Creates a new instance of this builder.
     *
     * @param array $options The client options
     *
     * @return static
     */
    public static function create(array $options = []);

    /**
     * Gets the instance of the client built using the configured options.
     *
     * @return Client
     */
    public function getClient();
}
