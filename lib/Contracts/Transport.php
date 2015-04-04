<?php namespace Raven\Contracts;

interface Transport
{
    /**
     * Queue a request ready to send
     *
     * @param \Raven\Contracts\Request $request
     * @return $this
     */
    public function queue(Request $request);

    /**
     * Immediately send all queued requests
     *
     * @param bool $async
     * @return $this
     */
    public function send($async = true);
}
