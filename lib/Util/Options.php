<?php namespace Raven\Util;

use InvalidArgumentException;

/**
 * Options container/collection
 *
 * @package raven
 *
 * @property-read string      $logger
 * @property-read array       $servers
 * @property-read string|null $secret_key
 * @property-read string|null $public_key
 * @property-read
 */
class Options
{
    /**
     * Options we're storing ourselves
     *
     * @var array
     */
    protected $options;

    /**
     * Defaults for all options
     *
     * @var array
     */
    protected $defaults = array(
        "logger" => "php",

        // dsn setup
        "servers" => null,
        "secret_key" => null,
        "public_key" => null,
        "project" => 1,

        "auto_log_stacks" => false,
        "tags" => array(),
        "release" => null,
        "trace" => true,
        "timeout" => 2,
        "message_limit" => 1024,
        "exclude" => array(),
        "severity_map" => null,
        "shift_vars" => true,
        "http_proxy" => null,
        "extra_data" => array(), // this is "extra" in 0.*. fix?
        "send_callback" => null,

        "handler" => array(
            "name" => "curl",
            "method" => "curl",
            "path" => "curl",
            "ssl_version" => null,
            "ipv4" => true,
            "verify_ssl" => true,
            "ca_cert" => "data/cacert.pem"
        ),
    );

    /**
     * Construct a new Options instance
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;
    }

    /**
     * Magic method to get options
     *
     * @param string $name
     * @return mixed|string
     */
    public function __get($name)
    {
        return $this->find($name);
    }

    /**
     * Public (friendly) method to get options
     *
     * @param string $name
     * @return mixed|string
     */
    public function find($name)
    {
        if (array_key_exists($this->options, $name))
        {
            return $this->options[$name];
        }
        elseif (array_key_exists($this->defaults, $name))
        {
            return $this->defaults[$name];
        }

        // todo: refactor, use dedicated methods
        if ($name == "name")
        {
            return gethostname();
        }

        if ($name == "site")
        {
            return \Raven\get($_SERVER, "SERVER_NAME", "");
        }

        throw new InvalidArgumentException(sprintf("%s is not a valid config property", $name));
    }
}
