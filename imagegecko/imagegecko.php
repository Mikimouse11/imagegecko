<?php
/**
 * Plugin Name: ImageGecko AI Product Images
 * Plugin URI: https://contentgecko.io/woocommerce-product-image-generator/
 * Description: Generates lifestyle images for WooCommerce products using AI.
 * Author: ristorehemagi
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: imagegecko
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
    define( 'IMAGEGECKO_VERSION', '1.0.0' );
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
