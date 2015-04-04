<?php namespace Raven\Util;

use InvalidArgumentException;

class Dsn
{

    /**
     * @var array|null
     */
    public $servers;

    /**
     * @var int|null
     */
    public $project;

    /**
     * @var string|null
     */
    public $publicKey;

    /**
     * @var string|null
     */
    public $secretKey;

    /**
     * Parse out a dsn into properties
     *
     * @param $dsn
     */
    public function __construct($dsn)
    {
        $dsn = $this->parse($dsn);

        $this->servers =   $dsn["servers"];
        $this->project =   $dsn["project"];
        $this->publicKey = $dsn["public_key"];
        $this->secretKey = $dsn["secret_key"];
    }

    /**
     *
     *
     * @param string $dsn
     * @return array
     */
    public function parse($dsn)
    {
        // TODO: refactor
        $url = parse_url($dsn);
        $scheme = (isset($url['scheme']) ? $url['scheme'] : '');
        if ( ! in_array($scheme, array('http', 'https', 'udp'))) {
            throw new InvalidArgumentException('Unsupported Sentry DSN scheme: ' . (! empty($scheme) ? $scheme : '<not set>'));
        }
        $netloc = (isset($url['host']) ? $url['host'] : null);
        $netloc .= (isset($url['port']) ? ':' . $url['port'] : null);
        $rawpath = (isset($url['path']) ? $url['path'] : null);
        if ($rawpath) {
            $pos = strrpos($rawpath, '/', 1);
            if ($pos !== false) {
                $path = substr($rawpath, 0, $pos);
                $project = substr($rawpath, $pos + 1);
            } else {
                $path = '';
                $project = substr($rawpath, 1);
            }
        } else {
            $project = null;
            $path = '';
        }
        $username = (isset($url['user']) ? $url['user'] : null);
        $password = (isset($url['pass']) ? $url['pass'] : null);
        if (empty($netloc) || empty($project) || empty($username) || empty($password)) {
            throw new InvalidArgumentException('Invalid Sentry DSN: ' . $dsn);
        }

        return array(
            'servers' => array(sprintf('%s://%s%s/api/%s/store/', $scheme, $netloc, $path, $project)),
            'project' => $project,
            'public_key' => $username,
            'secret_key' => $password,
        );
    }
}
