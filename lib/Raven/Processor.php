<?php

namespace Raven;
/**
 * Base class for data processing.
 *
 * @package raven
 */
class Processor
{
    function __construct(Client $client)
    {
        $this->client = $client;
    }

    /** 
     * Process and sanitize data, modifying the existing value if nescesary.
     */
    public function process(&$data)
    {
    }
}
