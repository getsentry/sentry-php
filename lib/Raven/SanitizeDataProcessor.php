<?php
/**
 * Asterisk out passwords from password fields in frames, http,
 * and basic extra data.
 *
 * @package raven
 */
class Raven_SanitizeDataProcessor extends Raven_Processor
{
    const MASK = '********';
    const FIELDS_RE = '/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw|cvv|cardNumber|expirationDate)/i';
    const VALUES_RE = '/^(?:\d[ -]*?){13,16}$/';

    private $client;
    private $fields_re;
    private $values_re;

    public function __construct(Raven_Client $client)
    {
        $this->client       = $client;
        $this->fields_re    = self::FIELDS_RE;
        $this->values_re    = self::VALUES_RE;
        $this->session_cookie_name = ini_get('session.name');
    }

    /**
     * Override the default processor options
     *
     * @param array $options    Associative array of processor options
     */
    public function setProcessorOptions(array $options)
    {
        if (isset($options['fields_re'])) {
            $this->fields_re = $options['fields_re'];
        }

        if (isset($options['values_re'])) {
            $this->values_re = $options['values_re'];
        }
    }

    /**
     * Replace any array values with our mask if the field name or the value matches a respective regex
     *
     * @param mixed $item       Associative array value
     * @param string $key       Associative array key
     */
    public function sanitize(&$item, $key)
    {
        if (empty($item)) {
            return;
        }

        if (preg_match($this->values_re, $item)) {
            $item = self::MASK;
        }

        if (empty($key)) {
            return;
        }

        if (preg_match($this->fields_re, $key)) {
            $item = self::MASK;
        }
    }

    public function sanitizeHttp(&$data)
    {
        if (empty($data['request'])) {
            return;
        }
        $http = &$data['request'];
        if (empty($http['cookies'])) {
            return;
        }

        $cookies = &$http['cookies'];
        if (!empty($cookies[$this->session_cookie_name])) {
            $cookies[$this->session_cookie_name] = self::MASK;
        }
    }

    public function process(&$data)
    {
        array_walk_recursive($data, array($this, 'sanitize'));
        $this->sanitizeHttp($data);
    }

    /**
     * @return string
     */
    public function getFieldsRe()
    {
        return $this->fields_re;
    }

    /**
     * @param string $fields_re
     */
    public function setFieldsRe($fields_re)
    {
        $this->fields_re = $fields_re;
    }

    /**
     * @return string
     */
    public function getValuesRe()
    {
        return $this->values_re;
    }

    /**
     * @param string $values_re
     */
    public function setValuesRe($values_re)
    {
        $this->values_re = $values_re;
    }
}
