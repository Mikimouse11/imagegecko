<?php

namespace ImageGecko;

/**
 * Manages plugin configuration persisted in the options table.
 */
class Settings {
    const OPTION_KEY          = 'imagegecko_settings';
    const OPTION_KEY_API      = 'imagegecko_api_key';
    const SETTINGS_FIELD_NAME = 'imagegecko_settings_field';

    /**
     * Cached options.
     *
     * @var array|null
     */
    private $cache;

    /**
     * Return full settings payload.
     */
    public function all(): array {
        if ( null === $this->cache ) {
            $defaults    = [
                'default_prompt'      => '',
                'selected_categories' => [],
                'selected_products'   => [],
                'batch_size'          => 10,
            ];
            $this->cache = \wp_parse_args( \get_option( self::OPTION_KEY, [] ), $defaults );
        }

        return $this->cache;
    }

    /**
     * Convenience accessor for default prompt.
     */
    public function get_default_prompt(): string {
        $settings = $this->all();

        return isset( $settings['default_prompt'] ) ? (string) $settings['default_prompt'] : '';
    }

    /**
     * Selected product IDs (explicit).
     */
    public function get_selected_products(): array {
        $settings = $this->all();

        return isset( $settings['selected_products'] ) ? array_map( 'intval', (array) $settings['selected_products'] ) : [];
    }

    /**
     * Selected category term IDs.
     */
    public function get_selected_categories(): array {
        $settings = $this->all();

        return isset( $settings['selected_categories'] ) ? array_map( 'intval', (array) $settings['selected_categories'] ) : [];
    }

    /**
     * Get batch size for simultaneous processing.
     */
    public function get_batch_size(): int {
        $settings = $this->all();
        $batch_size = isset( $settings['batch_size'] ) ? (int) $settings['batch_size'] : 10;
        
        // Ensure batch size is within reasonable bounds
        return max( 1, min( 20, $batch_size ) );
    }

    /**
     * Return sanitized API key (decrypted when possible).
     */
    public function get_api_key(): string {
        $stored = \get_option( self::OPTION_KEY_API );
        if ( empty( $stored ) ) {
            return '';
        }

        $maybe_decrypted = $this->decrypt( $stored );
        if ( false === $maybe_decrypted ) {
            // When decryption fails, fall back to raw value in case it was stored plaintext.
            return (string) $stored;
        }

        return (string) $maybe_decrypted;
    }

    /**
     * Persist settings payload.
     */
    public function save( array $settings ): void {
        $sanitized = $this->sanitize_settings( $settings );

        \update_option( self::OPTION_KEY, $sanitized, 'no' );
        $this->flush_cache();
    }

    /**
     * Persist API key after encrypting it when possible.
     */
    public function store_api_key( string $api_key ): void {
        $api_key = trim( $api_key );
        if ( '' === $api_key ) {
            \delete_option( self::OPTION_KEY_API );
            return;
        }

        $encrypted = $this->encrypt( $api_key );
        \update_option( self::OPTION_KEY_API, $encrypted, 'no' );
    }

    /**
     * Register hooks to keep cache coherent.
     */
    public function init(): void {
        \add_action( 'update_option_' . self::OPTION_KEY, [ $this, 'flush_cache' ] );
        \add_action( 'delete_option_' . self::OPTION_KEY, [ $this, 'flush_cache' ] );
    }

    /**
     * Flush cached option payload.
     */
    public function flush_cache(): void {
        $this->cache = null;
    }

    /**
     * Sanitize settings array consistently across entry points.
     */
    public function sanitize_settings( array $settings ): array {
        return [
            'default_prompt'      => isset( $settings['default_prompt'] ) ? \wp_kses_post( $settings['default_prompt'] ) : '',
            'selected_categories' => isset( $settings['selected_categories'] ) ? array_map( '\absint', (array) $settings['selected_categories'] ) : [],
            'selected_products'   => isset( $settings['selected_products'] ) ? array_map( '\absint', (array) $settings['selected_products'] ) : [],
            'batch_size'          => isset( $settings['batch_size'] ) ? max( 1, min( 20, (int) $settings['batch_size'] ) ) : 10,
        ];
    }

    /**
     * Try to encrypt a value using OpenSSL. Falls back to plain text when unavailable.
     */
    private function encrypt( string $value ): string {
        if ( ! \function_exists( 'openssl_encrypt' ) ) {
            return $value;
        }

        $key = $this->encryption_key();
        $iv  = random_bytes( 16 );
        $enc = \openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $enc ) {
            return $value;
        }

        return \wp_json_encode(
            [
                'v' => base64_encode( $iv ),
                'd' => base64_encode( $enc ),
            ]
        );
    }

    /**
     * Attempt to decrypt a stored value.
     */
    private function decrypt( $payload ) {
        if ( ! \function_exists( 'openssl_decrypt' ) ) {
            return $payload;
        }

        if ( ! is_string( $payload ) ) {
            return false;
        }

        $decoded = json_decode( $payload, true );
        if ( empty( $decoded['v'] ) || empty( $decoded['d'] ) ) {
            return $payload;
        }

        $iv        = base64_decode( $decoded['v'], true );
        $cipherraw = base64_decode( $decoded['d'], true );

        if ( ! $iv || ! $cipherraw ) {
            return false;
        }

        $key = $this->encryption_key();
        $dec = \openssl_decrypt( $cipherraw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        return false === $dec ? false : $dec;
    }

    /**
     * Build deterministic encryption key per-site.
     */
    private function encryption_key(): string {
        $seed = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : \get_bloginfo( 'url' ) );

        return hash( 'sha256', \wp_salt( 'auth' ) . $seed );
    }
}
