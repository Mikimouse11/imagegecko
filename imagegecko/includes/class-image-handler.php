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
     * Prepare the base64 payload for the product's current featured image.
     */
    public function prepare_product_image( int $product_id ) {
        if ( ! \function_exists( '\wc_get_product' ) ) {
            return new WP_Error( 'imagegecko_missing_wc', \__( 'WooCommerce is not available.', 'imagegecko' ) );
        }

        $product = \wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'imagegecko_invalid_product', \__( 'Unable to load product.', 'imagegecko' ) );
        }

        $attachment_id = (int) $product->get_image_id();
        if ( ! $attachment_id ) {
            return new WP_Error( 'imagegecko_missing_image', \__( 'Product is missing a featured image.', 'imagegecko' ) );
        }

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
     * Persist mediator output into the Media Library and attach to product.
     */
    public function persist_generated_media( int $product_id, array $payload ) {
        if ( empty( $payload['image_base64'] ) && empty( $payload['image_url'] ) ) {
            return new WP_Error( 'imagegecko_invalid_payload', \__( 'Mediator response missing image data.', 'imagegecko' ) );
        }

        $file_bits = ! empty( $payload['image_base64'] )
            ? $this->create_temp_file_from_base64( $payload['image_base64'], $payload['file_name'] ?? 'imagegecko-generated.jpg' )
            : $this->download_to_temp( $payload['image_url'], $payload['file_name'] ?? null );

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

        $alt_text      = sprintf( \__( 'ImageGecko generated image for product %d', 'imagegecko' ), $product_id );
        $post_overrides = [
            'post_title' => $alt_text,
            'post_content' => '',
            'post_excerpt' => $payload['prompt'] ?? '',
        ];

        $attachment_id = \media_handle_sideload( $file_array, $product_id, $alt_text, $post_overrides );

        if ( \is_wp_error( $attachment_id ) ) {
            @unlink( $file_bits['tmp_name'] );
            $this->logger->error( 'Failed to sideload generated image.', [ 'product_id' => $product_id, 'error' => $attachment_id->get_error_message() ] );
            return $attachment_id;
        }

        if ( \apply_filters( 'imagegecko_set_featured_image', true, $product_id, $attachment_id, $payload ) ) {
            \set_post_thumbnail( $product_id, $attachment_id );
        }

        $this->append_to_gallery( $product_id, $attachment_id );

        \update_post_meta( $product_id, '_imagegecko_generated_attachment', $attachment_id );
        \update_post_meta( $product_id, '_imagegecko_generated_at', time() );

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
            @unlink( $tmp_file );
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

        $filename = $preferred_name ?? basename( parse_url( $url, PHP_URL_PATH ) ?: 'imagegecko-generated.jpg' );
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
}
