<?php

namespace ImageGecko;

use WP_Post;

/**
 * Coordinates end-to-end generation requests.
 */
class Generation_Controller {
    const CRON_HOOK  = 'imagegecko_generate_product_image';
    const ASYNC_HOOK = 'imagegecko/generate_product_image';
    const NOTICE_KEY = 'imagegecko_admin_notice';

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Mediator_Api_Client
     */
    private $api_client;

    /**
     * @var Image_Handler
     */
    private $image_handler;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct( Settings $settings, Mediator_Api_Client $api_client, Image_Handler $image_handler, Logger $logger ) {
        $this->settings      = $settings;
        $this->api_client    = $api_client;
        $this->image_handler = $image_handler;
        $this->logger        = $logger;
    }

    public function init(): void {
        \add_filter( 'bulk_actions-edit-product', [ $this, 'register_bulk_action' ] );
        \add_filter( 'handle_bulk_actions-edit-product', [ $this, 'handle_bulk_action' ], 10, 3 );
        \add_filter( 'post_row_actions', [ $this, 'add_row_action' ], 10, 2 );
        \add_action( 'admin_init', [ $this, 'maybe_handle_single_trigger' ] );
        \add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );

        \add_action( self::CRON_HOOK, [ $this, 'process_async_job' ], 10, 1 );
        if ( \function_exists( '\as_enqueue_async_action' ) ) {
            \add_action( self::ASYNC_HOOK, [ $this, 'process_async_job' ], 10, 1 );
        }
    }

    public function register_bulk_action( array $actions ): array {
        $actions['imagegecko_generate'] = \__( 'Generate AI Photos (ImageGecko)', 'imagegecko' );

        return $actions;
    }

    public function handle_bulk_action( string $redirect_url, string $action, array $post_ids ): string {
        if ( 'imagegecko_generate' !== $action ) {
            return $redirect_url;
        }

        $queued = 0;
        foreach ( $post_ids as $post_id ) {
            if ( $this->queue_generation( (int) $post_id ) ) {
                $queued++;
            }
        }

        $this->enqueue_notice( 'success', sprintf( \_n( '%d product queued for ImageGecko generation.', '%d products queued for ImageGecko generation.', $queued, 'imagegecko' ), $queued ) );

        return \add_query_arg( 'imagegecko_queued', $queued, $redirect_url );
    }

    public function add_row_action( array $actions, WP_Post $post ): array {
        if ( 'product' !== $post->post_type ) {
            return $actions;
        }

        $url = \wp_nonce_url(
            \add_query_arg(
                [
                    'imagegecko_generate' => $post->ID,
                ]
            ),
            'imagegecko_generate_' . $post->ID
        );

        $actions['imagegecko_generate'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            \esc_url( $url ),
            \esc_html__( 'Generate AI Photo', 'imagegecko' )
        );

        return $actions;
    }

    public function maybe_handle_single_trigger(): void {
        if ( empty( $_GET['imagegecko_generate'] ) ) {
            return;
        }

        $product_id = (int) $_GET['imagegecko_generate'];
        $nonce      = $_GET['_wpnonce'] ?? '';

        if ( ! \wp_verify_nonce( $nonce, 'imagegecko_generate_' . $product_id ) ) {
            $this->enqueue_notice( 'error', \__( 'Security check failed. Please try again.', 'imagegecko' ) );
            return;
        }

        if ( $this->queue_generation( $product_id ) ) {
            $this->enqueue_notice( 'success', \__( 'Product queued for ImageGecko generation.', 'imagegecko' ) );
        }

        \wp_safe_redirect( \remove_query_arg( [ 'imagegecko_generate', '_wpnonce' ] ) );
        exit;
    }

    public function render_admin_notices(): void {
        $user_id = \get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        $notice = \get_user_meta( $user_id, self::NOTICE_KEY, true );
        if ( empty( $notice['message'] ) ) {
            return;
        }

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            \esc_attr( $notice['type'] ?? 'info' ),
            \esc_html( $notice['message'] )
        );

        \delete_user_meta( $user_id, self::NOTICE_KEY );
    }

    public function queue_generation( int $product_id, array $overrides = [] ): bool {
        if ( $product_id <= 0 ) {
            return false;
        }

        if ( ! $this->should_process_product( $product_id ) ) {
            $this->logger->debug( 'Product skipped by targeting rules.', [ 'product_id' => $product_id ] );
            return false;
        }

        $prompt = $overrides['prompt'] ?? $this->settings->get_default_prompt();
        $prompt = \apply_filters( 'imagegecko_generation_prompt', $prompt, $product_id, $overrides );

        $payload = [
            'product_id' => $product_id,
            'prompt'     => $prompt,
            'categories' => $this->settings->get_selected_categories(),
        ];

        $this->update_status( $product_id, 'queued' );

        if ( \function_exists( '\as_enqueue_async_action' ) ) {
            \as_enqueue_async_action( self::ASYNC_HOOK, [ $payload ], 'imagegecko' );
        } else {
            \wp_schedule_single_event( time(), self::CRON_HOOK, [ $payload ] );
        }

        return true;
    }

    public function process_async_job( $payload ): void {
        if ( is_array( $payload ) && isset( $payload[0] ) && is_array( $payload[0] ) && ! isset( $payload['product_id'] ) ) {
            $payload = $payload[0];
        }

        if ( ! is_array( $payload ) || empty( $payload['product_id'] ) ) {
            $this->logger->error( 'Received invalid generation payload.', [ 'payload' => $payload ] );
            return;
        }

        $product_id = (int) $payload['product_id'];
        $prompt     = (string) ( $payload['prompt'] ?? $this->settings->get_default_prompt() );

        $this->update_status( $product_id, 'processing' );

        $image_payload = $this->image_handler->prepare_product_image( $product_id );
        if ( \is_wp_error( $image_payload ) ) {
            $message = $image_payload->get_error_message();
            $this->logger->error( 'Failed to prepare source image.', [ 'product_id' => $product_id, 'error' => $message ] );
            $this->update_status( $product_id, 'failed', $message );
            return;
        }

        $product_sku = '';
        if ( \function_exists( '\wc_get_product' ) ) {
            $product = \wc_get_product( $product_id );
            $product_sku = $product ? (string) $product->get_sku() : '';
        }

        $api_response = $this->api_client->request_generation(
            $product_id,
            $image_payload,
            [
                'prompt'     => $prompt,
                'categories' => $payload['categories'] ?? [],
                'sku'        => $product_sku,
            ]
        );

        if ( ! $api_response['success'] ) {
            $message = $api_response['error'] ?? \__( 'Mediator request failed.', 'imagegecko' );
            $this->logger->error( 'Mediator generation failed.', [ 'product_id' => $product_id, 'error' => $message ] );
            $this->update_status( $product_id, 'failed', $message );
            return;
        }

        $media_payload = [
            'image_base64' => $api_response['data']['image_base64'] ?? null,
            'image_url'    => $api_response['data']['image_url'] ?? null,
            'file_name'    => $api_response['data']['file_name'] ?? 'imagegecko-generated.jpg',
            'prompt'       => $api_response['data']['prompt'] ?? $prompt,
        ];

        $attachment_id = $this->image_handler->persist_generated_media( $product_id, $media_payload );
        if ( \is_wp_error( $attachment_id ) ) {
            $message = $attachment_id->get_error_message();
            $this->logger->error( 'Failed to store generated media.', [ 'product_id' => $product_id, 'error' => $message ] );
            $this->update_status( $product_id, 'failed', $message );
            return;
        }

        $this->update_status( $product_id, 'completed' );
        $this->logger->info( 'Generated image stored.', [ 'product_id' => $product_id, 'attachment_id' => $attachment_id ] );
    }

    private function should_process_product( int $product_id ): bool {
        $selected_products = $this->settings->get_selected_products();
        if ( ! empty( $selected_products ) && ! in_array( $product_id, $selected_products, true ) ) {
            return false;
        }

        $selected_categories = $this->settings->get_selected_categories();
        if ( empty( $selected_categories ) ) {
            return true;
        }

        $product_cats = \wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );

        return ! empty( array_intersect( $selected_categories, $product_cats ) );
    }

    private function update_status( int $product_id, string $status, string $message = '' ): void {
        \update_post_meta( $product_id, '_imagegecko_status', $status );
        \update_post_meta( $product_id, '_imagegecko_status_message', $message );
        if ( 'failed' === $status ) {
            $this->enqueue_notice( 'error', sprintf( \__( 'ImageGecko failed for product #%d: %s', 'imagegecko' ), $product_id, $message ) );
        }
    }

    private function enqueue_notice( string $type, string $message ): void {
        $user_id = \get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        \update_user_meta(
            $user_id,
            self::NOTICE_KEY,
            [
                'type'    => $type,
                'message' => $message,
            ]
        );
    }
}
