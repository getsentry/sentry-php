<?php
/**
 * Base class for data processing.
 *
 * @package raven
 */
class Raven_Processor
{
    function __construct($client)
    {
        $this->client = $client;
    }

    /** 
     * Process data and return updated message.
     */
    public function process($data)
    {
        return $data;
    }
}
