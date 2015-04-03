<?php namespace Raven\Contracts;

/**
 * Sanitizer Contract
 *
 * @package raven
 */
interface Sanitizer {

    /**
     * Sanitize incoming data by reference
     *
     * @param array|\ArrayAccess $data
     * @return void
     */
    public function sanitize(&$data);
}
