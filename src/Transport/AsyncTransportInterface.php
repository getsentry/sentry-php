<?php

declare(strict_types=1);

namespace Sentry\Transport;

/**
 * This interface must be implemented by all classes willing to provide a way
 * of sending events to a Sentry server in an async matter.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface AsyncTransportInterface extends TransportInterface
{
    /**
     * Wait for all events to be transported.
     */
    public function flush(): void;
}
