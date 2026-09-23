<?php
namespace WSH\APIROCKET\Core;

if ( ! defined('ABSPATH') ) exit;

final class Autoloader {
    public static function register(): void {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    private static function autoload(string $class): void {
        if (strpos($class, 'WSH\\APIROCKET\\') !== 0) return;

        $relative = str_replace('WSH\\APIROCKET\\', '', $class);
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $file = WSH_AR_PLUGIN_DIR . 'includes/' . $relative . '.php';

        if (file_exists($file)) require_once $file;
    }
}
