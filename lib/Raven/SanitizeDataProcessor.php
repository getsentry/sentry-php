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
    const FIELDS_RE = '/(authorization|password|passwd|secret)/i';
    const VALUES_RE = '/^\d{16}$/';

    function apply($value, $fn, $key=null) {
        if (is_array($value)) {
            foreach ($value as $k=>$v) {
                $value[$k] = $this->apply($v, $fn, $k);
            }
            return $value;
        }
        return call_user_func($fn, $key, $value);
    }

    function sanitize($key, $value)
    {
        if (empty($value)) {
            return $value;
        }

        if (preg_match(self::VALUES_RE, $value)) {
            return self::MASK;
        }

        if (preg_match(self::FIELDS_RE, $key)) {
            return self::MASK;
        }

        return $value;
    }

    function process($data) {
        return $this->apply($data, array($this, 'sanitize'));
    }
}
