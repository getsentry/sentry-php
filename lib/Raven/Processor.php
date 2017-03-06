<?php
namespace Raven;

/**
 * Base class for data processing.
 *
 * @package raven
 */
abstract class Processor
{
    protected $client;
    /**
     * \Raven\Processor constructor.
     *
     * @param \Raven\Client $client
     * @codeCoverageIgnore
     */
    public function __construct(\Raven\Client $client)
    {
        $this->client = $client;
    }

    /**
     * Process and sanitize data, modifying the existing value if necessary.
     *
     * @param array $data Array of log data
     */
    abstract public function process(&$data);

    /**
     * Override the default processor options
     *
     * @param array $options Associative array of processor options
     */
    abstract public function setProcessorOptions(array $options);
}
