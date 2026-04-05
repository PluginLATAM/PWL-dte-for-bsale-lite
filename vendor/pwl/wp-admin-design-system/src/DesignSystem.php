<?php

namespace UserDOMP\WpAdminDS;

/**
 * WordPress Admin Design System
 *
 * Usage in your plugin:
 *
 *   use UserDOMP\WpAdminDS\DesignSystem;
 *
 *   add_action('admin_enqueue_scripts', function() {
 *       DesignSystem::enqueue(
 *           DesignSystem::assets_url(__FILE__)
 *       );
 *   });
 *
 * Then wrap your admin pages with <div class="wads">...</div>
 */
class DesignSystem
{
    const VERSION = '1.0.0';

    /**
     * Enqueue design system CSS and JS in WordPress.
     *
     * @param string $assets_url Full URL to the /assets/ directory.
     * @param string $version    Optional version string for cache busting.
     */
    public static function enqueue(string $assets_url, string $version = self::VERSION): void
    {
        $base = trailingslashit($assets_url);

        wp_enqueue_style(
            'wads',
            $base . 'css/design-system.css',
            [],
            $version
        );

        wp_enqueue_script(
            'wads',
            $base . 'js/design-system.js',
            [],
            $version,
            true
        );
    }

    /**
     * Register assets without enqueueing (useful for conditional loading).
     *
     * @param string $assets_url Full URL to the /assets/ directory.
     * @param string $version    Optional version string.
     */
    public static function register(string $assets_url, string $version = self::VERSION): void
    {
        $base = trailingslashit($assets_url);

        wp_register_style(
            'wads',
            $base . 'css/design-system.css',
            [],
            $version
        );

        wp_register_script(
            'wads',
            $base . 'js/design-system.js',
            [],
            $version,
            true
        );
    }

    /**
     * Resolve the assets/ URL relative to a plugin's main file.
     *
     * Assumes this package lives in:
     *   {plugin-root}/vendor/dariomunoz/wp-admin-design-system/
     *
     * Call as: DesignSystem::assets_url(__FILE__)
     * from your plugin's main PHP file.
     *
     * @param string $plugin_file Absolute path to the plugin's main file (__FILE__).
     */
    public static function assets_url(string $plugin_file): string
    {
        return plugin_dir_url($plugin_file)
            . 'vendor/dariomunoz/wp-admin-design-system/assets/';
    }

    /**
     * Absolute filesystem path to the assets/ directory.
     */
    public static function assets_path(): string
    {
        return dirname(__DIR__) . '/assets';
    }

    /**
     * Returns the current version of the design system.
     */
    public static function version(): string
    {
        return self::VERSION;
    }
}
