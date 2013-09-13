<?php
/**
 * Asterisk out passwords from password fields in frames, http,
 * and basic extra data.
 *
 * @package raven
 */
class Raven_SanitizeDataProcessor extends Raven_Processor
{
    static $mask = '********';
    static $fields_re = '/(authorization|password|passwd|secret|password_confirmation|card_number)/i';
    static $values_re = '/^(?:\d[ -]*?){13,16}$/';

    public function sanitize(&$item, $key)
    {
        if (empty($item)) {
            return;
        }

        if (preg_match(self::$values_re, $item)) {
            $item = self::$mask;
        }

        if (empty($key)) {
            return;
        }

        if (preg_match(self::$fields_re, $key)) {
            $item = self::$mask;
        }
    }

    public function process(&$data)
    {
        array_walk_recursive($data, array($this, 'sanitize'));
    }
}
