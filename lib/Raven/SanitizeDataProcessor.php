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

    public function sanitize(&$item, $key)
    {
        if (empty($item)) {
            return;
        }

        if (preg_match(self::VALUES_RE, $item)) {
            $item = self::MASK;
        }

        if (empty($key)) {
            return;
        }

        if (preg_match(self::FIELDS_RE, $key)) {
            $item = self::MASK;
        }
    }

    public function process(&$data)
    {
        array_walk_recursive($data, array($this, 'sanitize'));
    }
}
