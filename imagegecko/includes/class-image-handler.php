<?php

namespace ImageGecko;

use WP_Error;

/**
 * Encapsulates retrieval of source assets and persistence of generated images.
 */
class Image_Handler {
    /**
     * @var Logger
     */
    private $logger;

    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Prepare the base64 payload for the product's original (non-AI-generated) image.
     * Only original images should be sent to the API to avoid generating from AI images.
     */
    public function prepare_product_image( int $product_id ) {
        if ( ! \function_exists( '\wc_get_product' ) ) {
            return new WP_Error( 'imagegecko_missing_wc', \__( 'WooCommerce is not available.', 'imagegecko' ) );
        }

        $product = \wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'imagegecko_invalid_product', \__( 'Unable to load product.', 'imagegecko' ) );
        }

        // Always use the product's featured image as the base
        $attachment_id = $this->find_original_image( $product_id );
        if ( ! $attachment_id ) {
            $featured_id = (int) $product->get_image_id();
            $this->logger->warning( 'No featured image found for product or featured image is AI-generated.', [ 
                'product_id' => $product_id,
                'featured_image_id' => $featured_id,
                'has_featured' => $featured_id > 0,
                'is_featured_generated' => $featured_id > 0 ? (bool) \get_post_meta( $featured_id, '_imagegecko_generated', true ) : false
            ] );
            return new WP_Error( 'imagegecko_no_featured_image', \__( 'Product must have a featured image (non-AI-generated) to use as the base for generation.', 'imagegecko' ) );
        }
        
        $this->logger->info( 'Using featured image for generation.', [ 
            'product_id' => $product_id, 
            'attachment_id' => $attachment_id,
            'is_featured' => true
        ] );

        $file_path = \get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new WP_Error( 'imagegecko_missing_file', \__( 'Cannot locate product image on disk.', 'imagegecko' ) );
        }

        $contents = file_get_contents( $file_path );
        if ( false === $contents ) {
            return new WP_Error( 'imagegecko_read_error', \__( 'Failed to read product image.', 'imagegecko' ) );
        }

        $mime_type = \get_post_mime_type( $attachment_id );
        if ( ! $mime_type ) {
            $type      = \wp_check_filetype( basename( $file_path ) );
            $mime_type = $type['type'] ?? 'image/jpeg';
        }

        return [
            'attachment_id' => $attachment_id,
            'file_path'     => $file_path,
            'file_name'     => basename( $file_path ),
            'mime_type'     => $mime_type,
            'base64'        => base64_encode( $contents ),
        ];
    }

    /**
     * Persist API response into the Media Library and attach to product.
     */
    public function persist_generated_media( int $product_id, array $payload ) {
        if ( empty( $payload['image_base64'] ) && empty( $payload['image_url'] ) ) {
            return new WP_Error( 'imagegecko_invalid_payload', \__( 'API response missing image data.', 'imagegecko' ) );
        }

        // Get product name for filename and alt text
        $product_name = $this->get_product_name( $product_id );
        
        // Generate unique hash (8-character hexadecimal)
        $unique_hash = \substr( \md5( \uniqid( '', true ) ), 0, 8 );
        
        // Determine file extension from payload or default
        $file_extension = $this->get_file_extension_from_payload( $payload );
        
        // Generate filename: {productName}-imagegecko-{uniqueHash}.{ext}
        $sanitized_product_name = \sanitize_file_name( $product_name );
        $filename = $sanitized_product_name . '-imagegecko-' . $unique_hash . '.' . $file_extension;

        $file_bits = ! empty( $payload['image_base64'] )
            ? $this->create_temp_file_from_base64( $payload['image_base64'], $filename )
            : $this->download_to_temp( $payload['image_url'], $filename );

        if ( \is_wp_error( $file_bits ) ) {
            return $file_bits;
        }

        $this->include_media_dependencies();

        $file_array = [
            'name'     => $file_bits['name'],
            'type'     => $file_bits['type'],
            'tmp_name' => $file_bits['tmp_name'],
            'error'    => 0,
            'size'     => filesize( $file_bits['tmp_name'] ),
        ];

        // Alt text is just the product name
        $alt_text = $product_name;
        $post_overrides = [
            'post_title' => $alt_text,
            'post_content' => '',
            'post_excerpt' => $payload['prompt'] ?? '',
        ];

        $attachment_id = \media_handle_sideload( $file_array, $product_id, $alt_text, $post_overrides );

        if ( \is_wp_error( $attachment_id ) ) {
            \wp_delete_file( $file_bits['tmp_name'] );
            $this->logger->error( 'Failed to sideload generated image.', [ 'product_id' => $product_id, 'error' => $attachment_id->get_error_message() ] );
            return $attachment_id;
        }

        // Mark this attachment as AI-generated
        \update_post_meta( $attachment_id, '_imagegecko_generated', true );
        \update_post_meta( $attachment_id, '_imagegecko_product_id', $product_id );
        \update_post_meta( $attachment_id, '_imagegecko_generated_date', current_time( 'mysql' ) );
        
        // Add the generated image to the product gallery (not as featured image)
        $this->append_to_gallery( $product_id, $attachment_id );

        \update_post_meta( $product_id, '_imagegecko_generated_attachment', $attachment_id );
        \update_post_meta( $product_id, '_imagegecko_generated_at', time() );
        
        // Mark the attachment itself as AI-generated for future reference
        \update_post_meta( $attachment_id, '_imagegecko_generated', true );
        \update_post_meta( $attachment_id, '_imagegecko_source_product', $product_id );

        return $attachment_id;
    }

    private function include_media_dependencies(): void {
        if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
            define( 'WP_LOAD_IMPORTERS', true );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    private function create_temp_file_from_base64( string $base64, string $filename ) {
        $this->include_media_dependencies();

        $decoded = base64_decode( $base64 );
        if ( false === $decoded ) {
            return new WP_Error( 'imagegecko_decode_error', \__( 'Failed to decode base64 image.', 'imagegecko' ) );
        }

        $tmp_file = \wp_tempnam( $filename );
        if ( ! $tmp_file ) {
            return new WP_Error( 'imagegecko_tmp_error', \__( 'Unable to allocate temporary file.', 'imagegecko' ) );
        }

        $written = file_put_contents( $tmp_file, $decoded );
        if ( false === $written ) {
            \wp_delete_file( $tmp_file );
            return new WP_Error( 'imagegecko_tmp_write', \__( 'Failed to write temporary file.', 'imagegecko' ) );
        }

        $type = \wp_check_filetype( $filename );

        return [
            'name'     => $filename,
            'type'     => $type['type'] ?? 'image/jpeg',
            'tmp_name' => $tmp_file,
        ];
    }

    private function download_to_temp( string $url, ?string $preferred_name = null ) {
        $this->include_media_dependencies();

        $tmp_file = \download_url( $url );
        if ( \is_wp_error( $tmp_file ) ) {
            return $tmp_file;
        }

        $parsed_url = \wp_parse_url( $url );
        $filename = $preferred_name ?? basename( isset( $parsed_url['path'] ) ? $parsed_url['path'] : 'imagegecko-generated.jpg' );
        $type     = \wp_check_filetype( $filename );

        return [
            'name'     => $filename,
            'type'     => $type['type'] ?? 'image/jpeg',
            'tmp_name' => $tmp_file,
        ];
    }

    private function append_to_gallery( int $product_id, int $attachment_id ): void {
        $gallery = \get_post_meta( $product_id, '_product_image_gallery', true );
        $items   = $gallery ? explode( ',', $gallery ) : [];

        if ( ! in_array( (string) $attachment_id, $items, true ) ) {
            $items[] = (string) $attachment_id;
            \update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_filter( $items ) ) );
        }
    }


    /**
     * Find the product's featured image (must be original, non-AI-generated).
     * Always uses the featured image as the base for generation.
     */
    private function find_original_image( int $product_id ): int {
        if ( ! \function_exists( '\wc_get_product' ) ) {
            return 0;
        }

        $product = \wc_get_product( $product_id );
        if ( ! $product ) {
            return 0;
        }

        // Always use the featured image as the base
        $featured_id = (int) $product->get_image_id();
        if ( ! $featured_id ) {
            return 0;
        }

        // Verify the featured image is original (not AI-generated)
        if ( ! $this->is_original_image( $featured_id, $product_id ) ) {
            return 0;
        }

        return $featured_id;
    }

    /**
     * Check if an attachment is an original image (not AI-generated by this plugin).
     */
    private function is_original_image( int $attachment_id, int $product_id ): bool {
        if ( ! $attachment_id ) {
            return false;
        }

        // First check if the attachment itself is marked as AI-generated
        $is_generated = \get_post_meta( $attachment_id, '_imagegecko_generated', true );
        if ( $is_generated ) {
            return false;
        }

        // Also check if this attachment is in the list of generated attachments for this product
        $generated_attachments = $this->get_generated_attachment_ids( $product_id );
        
        // If this attachment is in the list of generated attachments, it's not original
        return ! in_array( $attachment_id, $generated_attachments, true );
    }

    /**
     * Get all AI-generated attachment IDs for a product.
     */
    private function get_generated_attachment_ids( int $product_id ): array {
        $generated_ids = [];
        
        // Get the current generated attachment (most recent)
        $current_generated = (int) \get_post_meta( $product_id, '_imagegecko_generated_attachment', true );
        if ( $current_generated ) {
            $generated_ids[] = $current_generated;
        }
        
        // Get all historical generated attachments (if we store them)
        $all_generated = \get_post_meta( $product_id, '_imagegecko_generated_attachment', false );
        if ( is_array( $all_generated ) ) {
            foreach ( $all_generated as $id ) {
                $id = (int) $id;
                if ( $id && ! in_array( $id, $generated_ids, true ) ) {
                    $generated_ids[] = $id;
                }
            }
        }
        
        return array_filter( $generated_ids );
    }
    
    /**
     * Restore the original featured image after deleting a generated image.
     */
    public function restore_original_featured_image( int $product_id ): bool {
        // Find the first original image that can serve as featured image
        $original_attachment_id = $this->find_original_image( $product_id );
        
        if ( ! $original_attachment_id ) {
            $this->logger->warning( 'No original image found to restore as featured image.', [ 'product_id' => $product_id ] );
            return false;
        }
        
        // Set the original image as featured image
        $result = \set_post_thumbnail( $product_id, $original_attachment_id );
        
        if ( $result ) {
            $this->logger->info( 'Restored original featured image after deletion.', [ 
                'product_id' => $product_id, 
                'restored_attachment_id' => $original_attachment_id 
            ] );
        } else {
            $this->logger->error( 'Failed to restore original featured image.', [ 
                'product_id' => $product_id, 
                'attempted_attachment_id' => $original_attachment_id 
            ] );
        }
        
        return $result;
    }

    /**
     * Get gallery image IDs for a product.
     */
    private function get_gallery_image_ids( int $product_id ): array {
        $gallery = \get_post_meta( $product_id, '_product_image_gallery', true );
        if ( empty( $gallery ) ) {
            return [];
        }
        
        $ids = explode( ',', $gallery );
        return array_filter( array_map( 'intval', $ids ) );
    }

    /**
     * Get product name for use in filename and alt text.
     */
    private function get_product_name( int $product_id ): string {
        if ( ! \function_exists( '\wc_get_product' ) ) {
            $post = \get_post( $product_id );
            return $post ? $post->post_title : '';
        }

        $product = \wc_get_product( $product_id );
        if ( ! $product ) {
            return '';
        }

        // Use formatted name if available, otherwise fall back to title
        $name = $product->get_formatted_name();
        if ( empty( $name ) ) {
            $name = $product->get_name();
        }
        
        if ( empty( $name ) ) {
            $name = \get_the_title( $product_id );
        }

        return $name ?: '';
    }

    /**
     * Determine file extension from payload or default to png.
     */
    private function get_file_extension_from_payload( array $payload ): string {
        // Check if file_name is provided and has extension
        if ( ! empty( $payload['file_name'] ) ) {
            $file_info = \wp_check_filetype( $payload['file_name'] );
            if ( ! empty( $file_info['ext'] ) ) {
                return $file_info['ext'];
            }
        }

        // Check mime type if available
        if ( ! empty( $payload['mime_type'] ) ) {
            $mime_to_ext = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            
            if ( isset( $mime_to_ext[ $payload['mime_type'] ] ) ) {
                return $mime_to_ext[ $payload['mime_type'] ];
            }
        }

        // Default to png
        return 'png';
    }
}
