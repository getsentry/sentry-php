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
 * @property-read int|null    $project
 * @property-read bool        $auto_log_stacks
 * @property-read array       $tags
 * @property-read string|null $release
 * @property-read bool        $trace
 * @property-read int         $timeout
 * @property-read int         $message_limit
 * @property-read array       $exclude
 * @property-read array|null  $severity_map
 * @property-read bool        $shift_vars
 * @property-read array|null  $http_proxy
 * @property-read array       $extra_data
 * @property-read callable    $send_callback
 * @property-read array       $handler
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

        return $this->{"get" . ucfirst($name)}();
    }

    /**
     * Get data about the current user
     *
     * @return array
     */
    public function getUser()
    {
        if (is_null($user = \Raven\get($this->options, "user")))
        {
            if ( ! session_id()) return array();

            $user = array('id' => session_id());

            if ( ! empty($_SESSION)) {
                $user['data'] = $_SESSION;
            }
        }
        return array(
            'sentry.interfaces.User' => $user,
        );
    }

    /**
     * Get this server's hostname
     *
     * @return string
     */
    public function getHostname()
    {
        return gethostname();
    }

    /**
     * Get the current site's name
     *
     * @return string
     */
    public function getSite()
    {
        return \Raven\get($_SERVER, "SERVER_NAME", "");
    }

    /**
     * Get all options as an array
     *
     * @return array
     */
    public function toArray()
    {
        $values = array_merge($this->defaults, $this->options);

        return $values;
    }

    /**
     * Serialize our options to json
     *
     * @param int $options JSON_* constants
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * String handler for (string) casts
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}
