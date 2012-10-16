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
        return Raven_Util::apply($data, array($this, 'sanitize'));
    }
}
