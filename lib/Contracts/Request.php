<?php namespace Raven\Contracts;

interface Request
{
    /**
     * Make a new request
     *
     * @param string $url
     * @param string $method
     * @param array  $headers
     * @param string $body
     */
    public function __construct($url, $method = "GET", $headers = array(), $body = "");

    /**
     * Set a header to $value
     *
     * @param string $header
     * @param string $value
     * @return $this
     */
    public function setHeader($header, $value);

    /**
     * Get a header
     *
     * @param string $header
     * @return string
     */
    public function getHeader($header);

    /**
     * Get all headers
     *
     * @return array
     */
    public function getHeaders();

    /**
     * Set the HTTP verb for this request
     *
     * @param string $method
     * @return $this
     */
    public function setMethod($method);

    /**
     * Get the HTTP verb for this request
     *
     * @return string
     */
    public function getMethod();

    /**
     * Set the body for the request.
     * If $body is an array, convert
     * to query string (or JSON).
     *
     * @param string|array $body
     * @return $this
     */
    public function setBody($body);

    /**
     * Get the request body
     *
     * @return string
     */
    public function getBody();

    /**
     * Set the request URL
     *
     * @param string $url
     * @return $this
     */
    public function setUrl($url);

    /**
     * Get the request URL
     *
     * @return string
     */
    public function getUrl();
}