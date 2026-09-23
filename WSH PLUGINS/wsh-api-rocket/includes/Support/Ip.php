<?php
// File: includes/Support/Ip.php

namespace WSH\APIROCKET\Support;

if ( ! defined('ABSPATH') ) exit;

final class Ip {

    const OPT_TRUST_PROXY = 'wsh_ar_trust_proxy';         // 0/1
    const OPT_PROXY_MODE  = 'wsh_ar_proxy_mode';          // 'cloudflare'|'x_forwarded_for'|'auto'

    public static function get_client_ip(): string {

        $trust = (int) get_option(self::OPT_TRUST_PROXY, 0);
        if ($trust !== 1) {
            return self::from_remote_addr();
        }

        $mode = get_option(self::OPT_PROXY_MODE, 'auto');
        $mode = is_string($mode) ? $mode : 'auto';
        if (!in_array($mode, ['auto','cloudflare','x_forwarded_for'], true)) {
            $mode = 'auto';
        }

        // Cloudflare (recommended if you use CF)
        if ($mode === 'cloudflare' || $mode === 'auto') {
            $ip = self::from_header('HTTP_CF_CONNECTING_IP');
            if ($ip !== '') return $ip;
        }

        // X-Forwarded-For: can contain multiple IPs, take first public valid
        if ($mode === 'x_forwarded_for' || $mode === 'auto') {
            $xff = self::from_header('HTTP_X_FORWARDED_FOR');
            $ip  = self::first_valid_ip_from_xff($xff);
            if ($ip !== '') return $ip;
        }

        // X-Real-IP (common)
        if ($mode === 'auto') {
            $ip = self::from_header('HTTP_X_REAL_IP');
            if ($ip !== '') return $ip;
        }

        return self::from_remote_addr();
    }

    private static function from_remote_addr(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return self::sanitize_ip($ip);
    }

    private static function from_header(string $server_key): string {
        $v = $_SERVER[$server_key] ?? '';
        return self::sanitize_ip($v);
    }

    private static function sanitize_ip($ip): string {
        if (!is_string($ip)) return '';
        $ip = trim($ip);

        // Validate IPv4/IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        return '';
    }

    private static function first_valid_ip_from_xff(string $xff): string {
        if ($xff === '') return '';
        $parts = array_map('trim', explode(',', $xff));

        foreach ($parts as $candidate) {
            $ip = self::sanitize_ip($candidate);
            if ($ip === '') continue;

            // Skip private/reserved ranges to reduce spoofing risk
            // (If you need LAN clients, we can make this configurable.)
            if (self::is_private_or_reserved($ip)) continue;

            return $ip;
        }
        return '';
    }

    private static function is_private_or_reserved(string $ip): bool {
        // FILTER_FLAG_NO_PRIV_RANGE + NO_RES_RANGE works only with filter_var on full check;
        // Here we re-check with flags.
        $ok = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        return $ok === false;
    }
}
