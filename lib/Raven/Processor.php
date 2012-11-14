<?php
/**
 * Base class for data processing.
 *
 * @package raven
 */
class Raven_Processor
{
    public function __construct(Raven_Client $client)
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
