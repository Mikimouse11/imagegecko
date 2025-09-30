<?php

namespace ImageGecko;

use WC_Logger;

/**
 * Light wrapper around WooCommerce logging for consistent context.
 */
class Logger {
    /**
     * Channel slug used in WooCommerce logs.
     */
    const CHANNEL = 'imagegecko';

    /**
     * @var WC_Logger|null
     */
    private $wc_logger;

    /**
     * Lazily grab WooCommerce logger when present.
     */
    private function get_wc_logger() {
        if ( ! \class_exists( 'WC_Logger' ) ) {
            return null;
        }

        if ( null === $this->wc_logger ) {
            $this->wc_logger = \wc_get_logger();
        }

        return $this->wc_logger;
    }

    public function debug( string $message, array $context = [] ): void {
        $this->log( 'debug', $message, $context );
    }

    public function info( string $message, array $context = [] ): void {
        $this->log( 'info', $message, $context );
    }

    public function error( string $message, array $context = [] ): void {
        $this->log( 'error', $message, $context );
    }

    private function log( string $level, string $message, array $context = [] ): void {
        $logger = $this->get_wc_logger();
        if ( $logger ) {
            $logger->log( $level, \wp_json_encode( [ 'message' => $message, 'context' => $context ] ), [ 'source' => self::CHANNEL ] );
            return;
        }

        // Fallback when WooCommerce logger is unavailable.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ImageGecko][%s] %s %s', strtoupper( $level ), $message, \wp_json_encode( $context ) ) );
        }
    }
}
