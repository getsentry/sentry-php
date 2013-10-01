<?php

namespace Raven\Request\Interfaces;
use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class Http implements ToArrayInterface
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array|null
     */
    private $data;

    /**
     * @var string|null
     */
    private $queryString;

    /**
     * @var array|null
     */
    private $cookies;

    /**
     * @var array|null
     */
    private $headers;

    /**
     * @var array|null
     */
    private $env;

    public function __construct($url, $method)
    {
        $this->url = $url;
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param array|null $data
     */
    public function setData(array $data = null)
    {
        $this->data = $data;
    }

    /**
     * @return array|null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param null|string $queryString
     */
    public function setQueryString($queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * @return null|string
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * @param array|null $cookies
     */
    public function setCookies(array $cookies = null)
    {
        $this->cookies = $cookies;
    }

    /**
     * @return array|null
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * @param array|null $headers
     */
    public function setHeaders(array $headers = null)
    {
        $this->headers = $headers;
    }

    /**
     * @return array|null
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array|null $env
     */
    public function setEnv(array $env = null)
    {
        $this->env = $env;
    }

    /**
     * @return array|null
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array_filter(array(
            'url' => $this->getUrl(),
            'method' => $this->getMethod(),
            'data' => $this->getData(),
            'query_string' => $this->getQueryString(),
            'cookies' => $this->getCookies(),
            'headers' => $this->getHeaders(),
            'env' => $this->getEnv(),
        ));
    }
}
