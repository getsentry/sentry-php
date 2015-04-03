<?php namespace Raven\Sanitizers;

use Raven\Contracts\Sanitizer;

/**
 * Asterisk out passwords from password fields in frames, http,
 * and basic extra data.
 *
 * @package raven
 */
class PasswordSanitizer implements Sanitizer
{
    /**
     * Keys to redact information from.
     *
     * @var string[]
     */
    protected static $keys = array(
        "authorization",
        "user_password",
        "user_password_confirm",
        "password_again",
        "password",
        "passwd",
        "secret",
        "password_confirmation",
        "password_confirm",
        "auth_pw"
    );

    /**
     * Mask to change values to.
     *
     * @var string
     */
    protected static $mask = "************";

    /**
     * Sanitize incoming data by reference
     *
     * @param array|\ArrayAccess $data
     * @return void
     */
    public function sanitize(&$data)
    {
        array_walk_recursive($data, array(get_class($this), "check"));
    }

    /**
     * @param $value
     * @param $key
     */
    protected static function check(&$value, $key)
    {
        if ( ! is_string($key)) {
            return;
        }

        // Here, strtolower on unicode strings doesn't really matter,
        // because we're matching against our own strings.
        if (in_array(strtolower($key), static::$keys)) {
            $value = static::$mask;
        }
    }
}
