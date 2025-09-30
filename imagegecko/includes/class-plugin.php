<?php

namespace ImageGecko;

/**
 * Central plugin orchestrator.
 */
class Plugin {
    /**
     * Singleton instance.
     *
     * @var Plugin
     */
    protected static $instance;

    /**
     * Registered services.
     *
     * @var array
     */
    private $services = [];

    /**
     * Retrieve singleton instance.
     */
    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot plugin services.
     */
    public function boot(): void {
        $this->register_services();
        $this->init_services();
    }

    /**
     * Register service objects used by the plugin.
     */
    private function register_services(): void {
        $settings = new Settings();
        $logger   = new Logger();

        $api_client = new Mediator_Api_Client( $settings, $logger );

        $image_handler = new Image_Handler( $logger );
        $controller    = new Generation_Controller( $settings, $api_client, $image_handler, $logger );

        $admin = new Admin_Settings_Page( $settings, $controller, $logger );

        $this->services = compact( 'settings', 'logger', 'api_client', 'image_handler', 'controller', 'admin' );
    }

    /**
     * Initialize registered services by calling their init hooks when available.
     */
    private function init_services(): void {
        foreach ( $this->services as $service ) {
            if ( is_object( $service ) && method_exists( $service, 'init' ) ) {
                $service->init();
            }
        }
    }
}
