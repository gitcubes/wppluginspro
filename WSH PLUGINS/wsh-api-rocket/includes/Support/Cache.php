<?php
// File: includes/Support/Cache.php

namespace WSH\APIROCKET\Support;

if ( ! defined('ABSPATH') ) exit;

final class Cache {

    public static function get(string $key) {
        return get_transient($key);
    }

    public static function set(string $key, $value, int $ttl_seconds): void {
        set_transient($key, $value, max(1, $ttl_seconds));
    }

    public static function delete(string $key): void {
        delete_transient($key);
    }

    /**
     * Delete transients by prefix (stored in options table).
     * Use sparingly; OK for small number of keys.
     */
    public static function delete_by_prefix(string $prefix): void {
        global $wpdb;

        $like = $wpdb->esc_like('_transient_' . $prefix) . '%';
        $sql  = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like,
            str_replace('_transient_', '_transient_timeout_', $like)
        );
        $wpdb->query($sql);
    }
}
