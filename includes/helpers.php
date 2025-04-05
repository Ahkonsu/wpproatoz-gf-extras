<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('rgar')) {
    /**
     * Retrieve a value from an array with a default of an empty string if the key doesn’t exist.
     *
     * @param array $array The array to search.
     * @param string $key The key to look for.
     * @return mixed The value if found, otherwise an empty string.
     */
    function rgar($array, $key) {
        return isset($array[$key]) ? $array[$key] : '';
    }
}