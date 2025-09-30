<?php
/**
 * Plugin Name:       ImageGecko AI Product Images
 * Plugin URI:        https://contentgecko.io/woocommerce-product-image-generator/
 * Description:       Generates model lifestyle shots for WooCommerce products using ContentGecko AI.
 * Version:           0.1.2
 * Author:            ContentGecko
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  woocommerce
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Text Domain:       imagegecko
 * Domain Path:       /languages
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access.
}

if ( ! defined( 'IMAGEGECKO_PLUGIN_FILE' ) ) {
    define( 'IMAGEGECKO_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'IMAGEGECKO_PLUGIN_DIR' ) ) {
    define( 'IMAGEGECKO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'IMAGEGECKO_VERSION' ) ) {
    define( 'IMAGEGECKO_VERSION', '0.1.2' );
}

// Basic PSR-4 style autoloader for plugin classes.
spl_autoload_register(
    function ( $class ) {
        if ( 0 !== strpos( $class, 'ImageGecko\\' ) ) {
            return;
        }

        $relative = strtolower( str_replace( [ 'ImageGecko\\', '_' ], [ '', '-' ], $class ) );
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
        $path     = IMAGEGECKO_PLUGIN_DIR . 'includes/class-' . $relative . '.php';

        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
);

// Bootstrap plugin once WooCommerce is ready.
add_action(
    'plugins_loaded',
    function () {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action(
                'admin_notices',
                function () {
                    printf(
                        '<div class="notice notice-error"><p>%s</p></div>',
                        esc_html__( 'ImageGecko AI Photos requires WooCommerce to be installed and active.', 'imagegecko' )
                    );
                }
            );
            return;
        }

        \ImageGecko\Plugin::instance()->boot();
    }
);
