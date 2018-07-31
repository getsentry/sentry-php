<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Asterisk out passwords from password fields in frames, http,
 * and basic extra data.
 *
 * @package raven
 */
class Raven_Processor_SanitizeDataProcessor extends Raven_Processor
{
    const MASK = self::STRING_MASK;
    const FIELDS_RE = '/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw)/i';
    const VALUES_RE = '/^(?:\d[ -]*?){13,19}$/';

    protected $fields_re;
    protected $values_re;
    protected $session_cookie_name;

    /**
     * {@inheritdoc}
     */
    public function __construct(Raven_Client $client)
    {
        parent::__construct($client);

        $this->fields_re = self::FIELDS_RE;
        $this->values_re = self::VALUES_RE;
        $this->session_cookie_name = ini_get('session.name');
    }

    /**
     * {@inheritdoc}
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
            $item = self::STRING_MASK;
        }

        if (empty($key)) {
            return;
        }

        if (preg_match($this->fields_re, $key)) {
            $item = self::STRING_MASK;
        }
    }

    public function sanitizeException(&$data)
    {
        foreach ($data['exception']['values'] as &$value) {
            $this->sanitizeStacktrace($value['stacktrace']);
        }
    }

    public function sanitizeHttp(&$data)
    {
        $http = &$data['request'];
        if (!empty($http['cookies']) && is_array($http['cookies'])) {
            $cookies = &$http['cookies'];
            if (!empty($cookies[$this->session_cookie_name])) {
                $cookies[$this->session_cookie_name] = self::STRING_MASK;
            }
        }
        if (!empty($http['data']) && is_array($http['data'])) {
            array_walk_recursive($http['data'], array($this, 'sanitize'));
        }
    }

    public function sanitizeStacktrace(&$data)
    {
        foreach ($data['frames'] as &$frame) {
            if (empty($frame['vars'])) {
                continue;
            }
            array_walk_recursive($frame['vars'], array($this, 'sanitize'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(&$data)
    {
        if (!empty($data['exception'])) {
            $this->sanitizeException($data);
        }
        if (!empty($data['stacktrace'])) {
            $this->sanitizeStacktrace($data['stacktrace']);
        }
        if (!empty($data['request'])) {
            $this->sanitizeHttp($data);
        }
        if (!empty($data['extra'])) {
            array_walk_recursive($data['extra'], array($this, 'sanitize'));
        }
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
