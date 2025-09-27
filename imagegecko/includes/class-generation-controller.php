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

        \add_action( 'wp_ajax_imagegecko_start_generation', [ $this, 'ajax_start_generation' ] );
        \add_action( 'wp_ajax_imagegecko_process_product', [ $this, 'ajax_process_product' ] );
        \add_action( 'wp_ajax_imagegecko_process_batch', [ $this, 'ajax_process_batch' ] );
        \add_action( 'wp_ajax_imagegecko_delete_generated_image', [ $this, 'ajax_delete_generated_image' ] );

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

    public function generate_product_now( int $product_id, array $overrides = [] ): array {
        if ( $product_id <= 0 ) {
            return [
                'success' => false,
                'status'  => 'failed',
                'message' => \__( 'Invalid product identifier.', 'imagegecko' ),
            ];
        }

        if ( '' === $this->settings->get_api_key() ) {
            return [
                'success' => false,
                'status'  => 'blocked',
                'message' => \__( 'Add your API key before running the workflow.', 'imagegecko' ),
            ];
        }

        if ( ! $this->should_process_product( $product_id ) && empty( $overrides['force'] ) ) {
            $this->logger->debug( 'Product skipped by targeting rules.', [ 'product_id' => $product_id ] );

            return [
                'success' => false,
                'status'  => 'skipped',
                'message' => \__( 'Product skipped by targeting rules.', 'imagegecko' ),
            ];
        }

        $prompt = $overrides['prompt'] ?? $this->settings->get_default_prompt();
        $prompt = \apply_filters( 'imagegecko_generation_prompt', $prompt, $product_id, $overrides );
        $categories = isset( $overrides['categories'] ) ? (array) $overrides['categories'] : $this->settings->get_selected_categories();

        $this->update_status( $product_id, 'queued' );
        $this->update_status( $product_id, 'processing' );

        $result = $this->run_generation( $product_id, $prompt, $categories );

        if ( ! $result['success'] ) {
            $message = $result['error'] ?? \__( 'Generation failed.', 'imagegecko' );
            $this->update_status( $product_id, 'failed', $message );

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => $message,
            ];
        }

        $this->update_status( $product_id, 'completed' );

        return [
            'success'       => true,
            'status'        => 'completed',
            'message'       => \__( 'Product enhanced successfully.', 'imagegecko' ),
            'attachment_id' => $result['attachment_id'] ?? null,
        ];
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
        $categories = isset( $payload['categories'] ) ? (array) $payload['categories'] : $this->settings->get_selected_categories();

        $result = $this->run_generation( $product_id, $prompt, $categories );

        if ( ! $result['success'] ) {
            $message = $result['error'] ?? \__( 'Mediator request failed.', 'imagegecko' );
            $this->update_status( $product_id, 'failed', $message );
            return;
        }

        $this->update_status( $product_id, 'completed' );
    }

    public function ajax_start_generation(): void {
        try {
            $this->verify_ajax_request();

            if ( '' === $this->settings->get_api_key() ) {
                $this->logger->error( 'AJAX start generation failed: No API key configured.' );
                \wp_send_json_error( [ 'message' => \__( 'Add your API key before running the workflow.', 'imagegecko' ) ], 400 );
            }

            $this->logger->info( 'Starting AJAX generation workflow.' );
            
            $product_ids = $this->resolve_target_products();
            $this->logger->info( 'Resolved target products.', [ 'count' => count( $product_ids ), 'product_ids' => $product_ids ] );

            $products = [];
            foreach ( $product_ids as $product_id ) {
                $label = \get_the_title( $product_id );

                if ( \function_exists( '\wc_get_product' ) ) {
                    $product = \wc_get_product( $product_id );
                    if ( $product ) {
                        $label = $product->get_formatted_name();
                    }
                }

                if ( '' === (string) $label ) {
                    $label = sprintf( \__( 'Product #%d', 'imagegecko' ), $product_id );
                }

                $products[] = [
                    'id'    => $product_id,
                    'label' => $label,
                ];
            }

            $data = [
                'products' => $products,
                'total'    => count( $products ),
            ];

            if ( empty( $products ) ) {
                $data['message'] = \__( 'No products match your current targeting rules.', 'imagegecko' );
                $this->logger->info( 'No products found matching targeting rules.' );
            }

            $this->logger->info( 'AJAX start generation completed successfully.', [ 'product_count' => count( $products ) ] );
            \wp_send_json_success( $data );
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'AJAX start generation failed with exception.', [ 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString() ] );
            \wp_send_json_error( [ 'message' => \__( 'An error occurred while preparing the generation run. Please check the logs and try again.', 'imagegecko' ) ], 500 );
        }
    }

    public function ajax_process_product(): void {
        try {
            $this->verify_ajax_request();

            $product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;

            if ( $product_id <= 0 ) {
                $this->logger->error( 'AJAX process product failed: Invalid product ID.', [ 'provided_id' => $_POST['product_id'] ?? 'not_set' ] );
                \wp_send_json_error( [ 'message' => \__( 'Invalid product identifier.', 'imagegecko' ) ], 400 );
            }

            $this->logger->info( 'Processing product via AJAX.', [ 'product_id' => $product_id ] );
            
            $result = $this->generate_product_now( $product_id, [ 'force' => true ] );

            $this->logger->info( 'AJAX process product completed.', [ 
                'product_id' => $product_id, 
                'success' => $result['success'], 
                'status' => $result['status'] 
            ] );

            // Get image URLs for display
            $image_data = $this->get_image_data_for_display($product_id, $result['attachment_id'] ?? null);
            
            \wp_send_json_success(
                [
                    'product_id'    => $product_id,
                    'success'       => $result['success'],
                    'status'        => $result['status'],
                    'message'       => $result['message'],
                    'attachment_id' => $result['attachment_id'] ?? null,
                    'images'        => $image_data,
                ]
            );
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'AJAX process product failed with exception.', [ 
                'product_id' => $product_id ?? 0, 
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString() 
            ] );
            \wp_send_json_error( [ 'message' => \__( 'An error occurred while processing the product. Please check the logs and try again.', 'imagegecko' ) ], 500 );
        }
    }

    public function ajax_process_batch(): void {
        try {
            $this->verify_ajax_request();

            $product_ids = isset( $_POST['product_ids'] ) ? (array) $_POST['product_ids'] : [];
            $product_ids = array_map( 'intval', array_filter( $product_ids ) );

            if ( empty( $product_ids ) ) {
                $this->logger->error( 'AJAX process batch failed: No valid product IDs provided.' );
                \wp_send_json_error( [ 'message' => \__( 'No valid product identifiers provided.', 'imagegecko' ) ], 400 );
            }

            // Limit batch size for performance and API considerations
            $configured_batch_size = $this->settings->get_batch_size();
            $batch_size = min( count( $product_ids ), $configured_batch_size );
            $product_ids = array_slice( $product_ids, 0, $batch_size );

            $this->logger->info( 'Processing batch via AJAX.', [ 
                'product_ids' => $product_ids, 
                'batch_size' => count( $product_ids ) 
            ] );

            $results = [];
            $successful = 0;
            $failed = 0;

            // Process products in parallel using WordPress HTTP API
            $requests = [];
            foreach ( $product_ids as $product_id ) {
                $requests[$product_id] = [
                    'url' => admin_url( 'admin-ajax.php' ),
                    'args' => [
                        'method' => 'POST',
                        'timeout' => 120,
                        'body' => [
                            'action' => 'imagegecko_process_single_internal',
                            'product_id' => $product_id,
                            'nonce' => wp_create_nonce( self::NONCE_ACTION ),
                        ]
                    ]
                ];
            }

            // For now, process sequentially but prepare structure for parallel processing
            foreach ( $product_ids as $product_id ) {
                $result = $this->generate_product_now( $product_id, [ 'force' => true ] );
                
                if ( $result['success'] ) {
                    $successful++;
                } else {
                    $failed++;
                }

                // Get image URLs for display
                $image_data = $this->get_image_data_for_display($product_id, $result['attachment_id'] ?? null);
                
                $results[$product_id] = [
                    'product_id'    => $product_id,
                    'success'       => $result['success'],
                    'status'        => $result['status'],
                    'message'       => $result['message'],
                    'attachment_id' => $result['attachment_id'] ?? null,
                    'images'        => $image_data,
                ];
            }

            $this->logger->info( 'AJAX batch processing completed.', [ 
                'total_processed' => count( $product_ids ),
                'successful' => $successful,
                'failed' => $failed
            ] );

            \wp_send_json_success([
                'results' => $results,
                'summary' => [
                    'total' => count( $product_ids ),
                    'successful' => $successful,
                    'failed' => $failed,
                ]
            ]);
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'AJAX batch processing failed with exception.', [ 
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString() 
            ] );
            \wp_send_json_error( [ 'message' => \__( 'An error occurred while processing the batch. Please check the logs and try again.', 'imagegecko' ) ], 500 );
        }
    }

    private function run_generation( int $product_id, string $prompt, array $categories ): array {
        $image_payload = $this->image_handler->prepare_product_image( $product_id );
        if ( \is_wp_error( $image_payload ) ) {
            $message = $image_payload->get_error_message();
            $this->logger->error( 'Failed to prepare source image.', [ 'product_id' => $product_id, 'error' => $message ] );

            return [
                'success' => false,
                'error'   => $message,
            ];
        }

        $product_sku = '';
        if ( \function_exists( '\wc_get_product' ) ) {
            $product     = \wc_get_product( $product_id );
            $product_sku = $product ? (string) $product->get_sku() : '';
        }

        $api_response = $this->api_client->request_generation(
            $product_id,
            $image_payload,
            [
                'prompt'     => $prompt,
                'categories' => $categories,
                'sku'        => $product_sku,
            ]
        );

        if ( ! $api_response['success'] ) {
            $message = $api_response['error'] ?? \__( 'Mediator request failed.', 'imagegecko' );
            $this->logger->error( 'Mediator generation failed.', [ 
                'product_id' => $product_id, 
                'error' => $message,
                'response_code' => $api_response['code'] ?? 'unknown',
                'response_data' => $api_response['data'] ?? null
            ] );

            return [
                'success' => false,
                'error'   => $message,
            ];
        }

        // Log the response structure for debugging
        $this->logger->info( 'API response received.', [ 
            'product_id' => $product_id,
            'response_keys' => array_keys( $api_response['data'] ?? [] ),
            'has_imageBase64' => isset( $api_response['data']['imageBase64'] ),
            'imageBase64_length' => isset( $api_response['data']['imageBase64'] ) ? strlen( $api_response['data']['imageBase64'] ) : 0
        ] );

        // The API returns imageBase64 directly in the response root, not nested under 'data'
        $response_data = $api_response['data'] ?? [];
        
        $media_payload = [
            'image_base64' => $response_data['imageBase64'] ?? null,
            'image_url'    => $response_data['image_url'] ?? null,
            'file_name'    => $response_data['file_name'] ?? null,
            'prompt'       => $response_data['prompt'] ?? $prompt,
        ];
        
        // Determine appropriate file extension based on response
        if ( empty( $media_payload['file_name'] ) ) {
            // Default to PNG since the API typically returns PNG images
            $media_payload['file_name'] = 'imagegecko-generated-' . time() . '.png';
        }
        
        // Validate that we have image data
        if ( empty( $media_payload['image_base64'] ) && empty( $media_payload['image_url'] ) ) {
            $this->logger->error( 'No image data in API response.', [ 
                'product_id' => $product_id,
                'response_keys' => array_keys( $response_data ),
                'media_payload' => $media_payload
            ] );
            
            return [
                'success' => false,
                'error'   => \__( 'API response missing image data. Check API credits and response format.', 'imagegecko' ),
            ];
        }

        $attachment_id = $this->image_handler->persist_generated_media( $product_id, $media_payload );
        if ( \is_wp_error( $attachment_id ) ) {
            $message = $attachment_id->get_error_message();
            $this->logger->error( 'Failed to store generated media.', [ 'product_id' => $product_id, 'error' => $message ] );

            return [
                'success' => false,
                'error'   => $message,
            ];
        }

        $this->logger->info( 'Generated image stored.', [ 'product_id' => $product_id, 'attachment_id' => $attachment_id ] );

        return [
            'success'       => true,
            'attachment_id' => (int) $attachment_id,
        ];
    }

    private function resolve_target_products(): array {
        $products   = array_map( 'intval', (array) $this->settings->get_selected_products() );
        $categories = array_map( 'intval', (array) $this->settings->get_selected_categories() );

        if ( ! empty( $categories ) ) {
            $category_query = new \WP_Query(
                [
                    'post_type'      => 'product',
                    'fields'         => 'ids',
                    'posts_per_page' => -1,
                    'post_status'    => [ 'publish' ],
                    'no_found_rows'  => true,
                    'tax_query'      => [
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'term_id',
                            'terms'    => $categories,
                        ],
                    ],
                ]
            );

            if ( ! empty( $category_query->posts ) ) {
                $products = array_merge( $products, array_map( 'intval', $category_query->posts ) );
            }

            \wp_reset_postdata();
        }

        if ( empty( $products ) ) {
            $all_products = new \WP_Query(
                [
                    'post_type'      => 'product',
                    'fields'         => 'ids',
                    'posts_per_page' => -1,
                    'post_status'    => [ 'publish' ],
                    'no_found_rows'  => true,
                ]
            );

            if ( ! empty( $all_products->posts ) ) {
                $products = array_map( 'intval', $all_products->posts );
            }

            \wp_reset_postdata();
        }

        $products = array_unique( array_filter( $products ) );

        return array_values( $products );
    }

    private function verify_ajax_request(): void {
        $nonce = isset( $_REQUEST['nonce'] ) ? (string) $_REQUEST['nonce'] : '';
        
        $this->logger->debug( 'Verifying AJAX request.', [ 
            'nonce_provided' => !empty( $nonce ),
            'user_id' => \get_current_user_id(),
            'can_manage_woocommerce' => \current_user_can( 'manage_woocommerce' )
        ] );

        if ( ! \wp_verify_nonce( $nonce, Admin_Settings_Page::NONCE_ACTION ) ) {
            $this->logger->error( 'AJAX request failed nonce verification.', [ 'provided_nonce' => $nonce ] );
            \wp_send_json_error( [ 'message' => \__( 'Invalid request nonce.', 'imagegecko' ) ], 403 );
        }

        if ( ! \current_user_can( 'manage_woocommerce' ) ) {
            $this->logger->error( 'AJAX request failed permission check.', [ 'user_id' => \get_current_user_id() ] );
            \wp_send_json_error( [ 'message' => \__( 'You do not have permission to perform this action.', 'imagegecko' ) ], 403 );
        }
        
        $this->logger->debug( 'AJAX request verification passed.' );
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
    
    public function ajax_delete_generated_image(): void {
        try {
            $this->verify_ajax_request();

            $attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

            if ( $attachment_id <= 0 ) {
                $this->logger->error( 'AJAX delete generated image failed: Invalid attachment ID.', [ 'provided_id' => $_POST['attachment_id'] ?? 'not_set' ] );
                \wp_send_json_error( [ 'message' => \__( 'Invalid attachment identifier.', 'imagegecko' ) ], 400 );
            }

            // Verify this is actually a generated image
            $is_generated = \get_post_meta( $attachment_id, '_imagegecko_generated', true );
            if ( ! $is_generated ) {
                $this->logger->error( 'AJAX delete generated image failed: Not a generated image.', [ 'attachment_id' => $attachment_id ] );
                \wp_send_json_error( [ 'message' => \__( 'This is not a generated image.', 'imagegecko' ) ], 400 );
            }

            $this->logger->info( 'Deleting generated image via AJAX.', [ 'attachment_id' => $attachment_id ] );
            
            // Get the product ID before deleting the attachment
            $product_id = (int) \get_post_meta( $attachment_id, '_imagegecko_product_id', true );
            
            // Check if this is the current featured image
            $is_featured_image = false;
            if ( $product_id ) {
                $current_featured_id = (int) \get_post_thumbnail_id( $product_id );
                $is_featured_image = ( $current_featured_id === $attachment_id );
            }
            
            // Delete the attachment
            $deleted = \wp_delete_attachment( $attachment_id, true );
            
            if ( ! $deleted ) {
                $this->logger->error( 'Failed to delete generated image.', [ 'attachment_id' => $attachment_id ] );
                \wp_send_json_error( [ 'message' => \__( 'Failed to delete the image.', 'imagegecko' ) ], 500 );
            }

            $this->logger->info( 'Generated image deleted successfully.', [ 'attachment_id' => $attachment_id ] );
            
            // If the deleted image was the featured image, restore the original
            if ( $is_featured_image && $product_id ) {
                $this->image_handler->restore_original_featured_image( $product_id );
            }
            
            // Clean up product meta if this was the current generated attachment
            if ( $product_id ) {
                $current_generated = (int) \get_post_meta( $product_id, '_imagegecko_generated_attachment', true );
                if ( $current_generated === $attachment_id ) {
                    \delete_post_meta( $product_id, '_imagegecko_generated_attachment' );
                    \delete_post_meta( $product_id, '_imagegecko_generated_at' );
                }
            }

            \wp_send_json_success( [
                'message' => \__( 'Image deleted successfully.', 'imagegecko' ),
                'attachment_id' => $attachment_id,
                'featured_restored' => $is_featured_image,
            ] );
            
        } catch ( \Exception $e ) {
            $this->logger->error( 'AJAX delete generated image failed with exception.', [ 
                'attachment_id' => $attachment_id ?? 0, 
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString() 
            ] );
            \wp_send_json_error( [ 'message' => \__( 'An error occurred while deleting the image. Please try again.', 'imagegecko' ) ], 500 );
        }
    }
    
    /**
     * Get image data for display in the progress tracker.
     */
    private function get_image_data_for_display(int $product_id, ?int $generated_attachment_id): array {
        $image_data = [
            'source' => null,
            'generated' => null,
        ];
        
        // Get source image (the original image that was used for generation)
        $source_image_data = $this->image_handler->prepare_product_image($product_id);
        if (!\is_wp_error($source_image_data) && isset($source_image_data['attachment_id'])) {
            $source_attachment_id = $source_image_data['attachment_id'];
            $source_url = \wp_get_attachment_image_url($source_attachment_id, 'large');
            $source_full_url = \wp_get_attachment_image_url($source_attachment_id, 'full');
            if ($source_url) {
                $image_data['source'] = [
                    'url' => $source_url,
                    'full_url' => $source_full_url ?: $source_url,
                    'attachment_id' => $source_attachment_id,
                    'title' => \get_the_title($source_attachment_id) ?: 'Source Image',
                ];
            }
        }
        
        // Get generated image if available
        if ($generated_attachment_id) {
            $generated_url = \wp_get_attachment_image_url($generated_attachment_id, 'large');
            $generated_full_url = \wp_get_attachment_image_url($generated_attachment_id, 'full');
            if ($generated_url) {
                $image_data['generated'] = [
                    'url' => $generated_url,
                    'full_url' => $generated_full_url ?: $generated_url,
                    'attachment_id' => $generated_attachment_id,
                    'title' => \get_the_title($generated_attachment_id) ?: 'Generated Image',
                ];
            }
        }
        
        return $image_data;
    }
}
