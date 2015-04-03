<?php namespace Raven\Sanitizers;

use Raven\Contracts\Sanitizer;

/**
 * Asterisk out passwords from password fields in frames, http,
 * and basic extra data.
 *
 * @package raven
 */
class SanitizeDataProcessor implements Sanitizer
{
    const MASK = '********';
    const FIELDS_RE = '/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw)/i';
    const VALUES_RE = '/^(?:\d[ -]*?){13,16}$/';
    
    private $fields_re;
    private $values_re;

    /**
     * Override the default processor options
     *
     * @param array $options    Associative array of processor options
     */
    public function setProcessorOptions(array $options){
        if( isset($options['fields_re']) ){
            $this->fields_re = $options['fields_re'];
        }

        if( isset($options['values_re']) ){
            $this->values_re = $options['values_re'];
        }
    }

    /**
     * Replace any array values with our mask if the field name or the value matches a respective regex
     *
     * @param mixed $item       Associative array value
     * @param string $key       Associative array key
     */
    public function process(&$item, $key)
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

    public function sanitize(&$data)
    {
        array_walk_recursive($data, [$this, 'sanitize']);
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
