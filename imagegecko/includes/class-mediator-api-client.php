<?php

namespace ImageGecko;


/**
 * Handles communication with the ContentGecko API endpoint.
 */
class Mediator_Api_Client {
    const ENDPOINT = 'https://api.contentgecko.io/product-image';

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct( Settings $settings, Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    /**
     * Dispatch generation request to ContentGecko API.
     *
     * @param int   $product_id Product identifier.
     * @param array $image_payload Prepared image data with base64, mime_type, file_name.
     * @param array $context Additional fields (prompt, product metadata).
     *
     * @return array{success:bool,code:int,data:array|null,error:string|null}
     */
    public function request_generation( int $product_id, array $image_payload, array $context = [] ): array {
        $api_key = $this->settings->get_api_key();
        if ( '' === $api_key ) {
            return [
                'success' => false,
                'code'    => 401,
                'data'    => null,
                'error'   => \__( 'API key is missing. Add it via the ImageGecko settings page.', 'imagegecko' ),
            ];
        }

        $body = [
            'product_id'   => $product_id,
            'prompt'       => isset( $context['prompt'] ) ? (string) $context['prompt'] : '',
            'image'        => [
                'base64'     => $image_payload['base64'] ?? '',
                'mime_type'  => $image_payload['mime_type'] ?? 'image/jpeg',
                'file_name'  => $image_payload['file_name'] ?? 'product.jpg',
            ],
            'metadata'     => [
                'source_image_id' => $image_payload['attachment_id'] ?? null,
                'categories'      => $context['categories'] ?? [],
                'product_sku'     => $context['sku'] ?? '',
            ],
        ];

        $args = [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => \wp_json_encode( $body ),
        ];

        $this->logger->info( 'Dispatching generation request.', [ 'product_id' => $product_id ] );

        $response = \wp_remote_post( self::ENDPOINT, $args );
        if ( \is_wp_error( $response ) ) {
            $this->logger->error( 'API request failed.', [ 'error' => $response->get_error_message(), 'product_id' => $product_id ] );

            return [
                'success' => false,
                'code'    => 500,
                'data'    => null,
                'error'   => $response->get_error_message(),
            ];
        }

        $code    = (int) \wp_remote_retrieve_response_code( $response );
        $payload = json_decode( \wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $message = is_array( $payload ) && isset( $payload['error'] ) ? $payload['error'] : \__( 'ContentGecko returned an error.', 'imagegecko' );
            $this->logger->error( 'API responded with error.', [ 'product_id' => $product_id, 'code' => $code, 'payload' => $payload ] );

            return [
                'success' => false,
                'code'    => $code,
                'data'    => $payload,
                'error'   => $message,
            ];
        }

        if ( ! is_array( $payload ) ) {
            $this->logger->error( 'API response is not valid JSON.', [ 'product_id' => $product_id ] );

            return [
                'success' => false,
                'code'    => $code,
                'data'    => null,
                'error'   => \__( 'API response parsing failed.', 'imagegecko' ),
            ];
        }

        $this->logger->info( 'API request succeeded.', [ 'product_id' => $product_id ] );

        return [
            'success' => true,
            'code'    => $code,
            'data'    => $payload,
            'error'   => null,
        ];
    }
}
