<?php

namespace ImageGecko;

/**
 * Admin UI for configuring the plugin.
 */
class Admin_Settings_Page {
    const MENU_SLUG    = 'imagegecko-settings';
    const OPTION_GROUP = 'imagegecko_settings_group';
    const NONCE_ACTION = 'imagegecko_admin';

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Generation_Controller
     */
    private $controller;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct( Settings $settings, Generation_Controller $controller, Logger $logger ) {
        $this->settings   = $settings;
        $this->controller = $controller;
        $this->logger     = $logger;
    }

    public function init(): void {
        \add_action( 'admin_menu', [ $this, 'register_menu' ] );
        \add_action( 'admin_init', [ $this, 'register_settings' ] );
        \add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        \add_action( 'wp_ajax_imagegecko_search_categories', [ $this, 'ajax_search_categories' ] );
        \add_action( 'wp_ajax_imagegecko_search_products', [ $this, 'ajax_search_products' ] );
    }

    public function register_menu(): void {
        \add_submenu_page(
            'woocommerce',
            \__( 'ImageGecko AI Photos', 'imagegecko' ),
            \__( 'ImageGecko AI Photos', 'imagegecko' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings(): void {
        \register_setting(
            self::OPTION_GROUP,
            Settings::OPTION_KEY,
            [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
        );

        \add_settings_section(
            'imagegecko_connection',
            \__( 'ContentGecko Connection', 'imagegecko' ),
            [ $this, 'render_connection_intro' ],
            self::MENU_SLUG
        );

        \add_settings_field(
            'imagegecko_api_key',
            \__( 'API Key', 'imagegecko' ),
            [ $this, 'render_api_key_field' ],
            self::MENU_SLUG,
            'imagegecko_connection'
        );

        \add_settings_field(
            'imagegecko_default_prompt',
            \__( 'Default Style Prompt', 'imagegecko' ),
            [ $this, 'render_prompt_field' ],
            self::MENU_SLUG,
            'imagegecko_connection'
        );

        \add_settings_field(
            'imagegecko_selected_categories',
            \__( 'Target Categories', 'imagegecko' ),
            [ $this, 'render_categories_field' ],
            self::MENU_SLUG,
            'imagegecko_connection'
        );

        \add_settings_field(
            'imagegecko_selected_products',
            \__( 'Target Products', 'imagegecko' ),
            [ $this, 'render_products_field' ],
            self::MENU_SLUG,
            'imagegecko_connection'
        );
    }

    public function render_connection_intro(): void {
        echo '<p>' . \esc_html__( 'Paste your ContentGecko-issued API key and define how ImageGecko should style generated photos. Select entire categories or hand-pick products to enable.', 'imagegecko' ) . '</p>';
    }

    public function render_api_key_field(): void {
        $api_key = $this->settings->get_api_key();
        ?>
        <input
            type="password"
            name="imagegecko_api_key"
            id="imagegecko_api_key"
            class="regular-text"
            value="<?php echo \esc_attr( $api_key ); ?>"
            autocomplete="off"
            placeholder="<?php \esc_attr_e( 'Paste ContentGecko API key here', 'imagegecko' ); ?>"
        />
        <p class="description"><?php \esc_html_e( 'Generate this key inside your ContentGecko dashboard, then paste it here. It is stored encrypted in your database.', 'imagegecko' ); ?></p>
        <?php
    }

    public function render_prompt_field(): void {
        $settings = $this->settings->all();
        ?>
        <textarea
            name="<?php echo \esc_attr( Settings::OPTION_KEY ); ?>[default_prompt]"
            id="imagegecko_default_prompt"
            class="large-text"
            rows="4"
        ><?php echo \esc_textarea( $settings['default_prompt'] ?? '' ); ?></textarea>
        <p class="description"><?php \esc_html_e( 'Describe the look-and-feel you want for generated photos. You can override per request later.', 'imagegecko' ); ?></p>
        <?php
    }

    public function render_categories_field(): void {
        $settings       = $this->settings->all();
        $selected_ids   = isset( $settings['selected_categories'] ) ? (array) $settings['selected_categories'] : [];
        $categories     = [];

        if ( ! empty( $selected_ids ) ) {
            $fetched = \get_terms(
                [
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                    'include'    => array_map( '\absint', $selected_ids ),
                ]
            );

            if ( ! \is_wp_error( $fetched ) ) {
                foreach ( $fetched as $category ) {
                    $categories[ $category->term_id ] = $category;
                }
            }
        }

        $total_products = 0;
        foreach ( $selected_ids as $category_id ) {
            if ( isset( $categories[ $category_id ] ) ) {
                $total_products += (int) $categories[ $category_id ]->count;
            }
        }
        ?>
        <div
            class="imagegecko-autocomplete"
            data-lookup="categories"
            data-placeholder="<?php echo \esc_attr__( 'Type to search product categories…', 'imagegecko' ); ?>"
            data-remove-label="<?php echo \esc_attr__( 'Remove category', 'imagegecko' ); ?>"
        >
            <ul
                class="imagegecko-autocomplete__selections"
                data-name="<?php echo \esc_attr( Settings::OPTION_KEY ); ?>[selected_categories][]"
            >
                <?php foreach ( $selected_ids as $category_id ) :
                    if ( ! isset( $categories[ $category_id ] ) ) {
                        continue;
                    }

                    $category      = $categories[ $category_id ];
                    $product_count = (int) $category->count;
                    $label         = sprintf(
                        /* translators: 1: category name, 2: product count */
                        \_n( '%1$s — %2$d product', '%1$s — %2$d products', $product_count, 'imagegecko' ),
                        $category->name,
                        $product_count
                    );
                    ?>
                    <li class="imagegecko-pill" data-id="<?php echo \esc_attr( (string) $category->term_id ); ?>" data-count="<?php echo \esc_attr( (string) $product_count ); ?>">
                        <span class="imagegecko-pill__label"><?php echo \esc_html( $label ); ?></span>
                        <button type="button" class="button-link-delete imagegecko-pill__remove" aria-label="<?php \esc_attr_e( 'Remove category', 'imagegecko' ); ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <input type="hidden" name="<?php echo \esc_attr( Settings::OPTION_KEY ); ?>[selected_categories][]" value="<?php echo \esc_attr( (string) $category->term_id ); ?>" />
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="imagegecko-autocomplete__controls">
                <input
                    type="text"
                    id="imagegecko_selected_categories"
                    class="imagegecko-autocomplete__input"
                    autocomplete="off"
                />
            </div>
            <p class="imagegecko-autocomplete__empty"<?php echo empty( $selected_ids ) ? '' : ' style="display:none;"'; ?>><?php \esc_html_e( 'No categories selected yet.', 'imagegecko' ); ?></p>
            <p
                class="imagegecko-autocomplete__summary"<?php echo $total_products > 0 ? '' : ' style="display:none;"'; ?>
                data-summary-target="categories"
            >
                <?php
                if ( $total_products > 0 ) {
                    echo \esc_html( sprintf( \_n( 'Selected categories currently cover approximately %d published product.', 'Selected categories currently cover approximately %d published products.', $total_products, 'imagegecko' ), $total_products ) );
                }
                ?>
            </p>
        </div>
        <p class="description"><?php \esc_html_e( 'Categories help you bulk-enable ImageGecko. We show the number of published products in each category so you can estimate credit usage.', 'imagegecko' ); ?></p>
        <?php
    }

    public function render_products_field(): void {
        $settings     = $this->settings->all();
        $selected_ids = isset( $settings['selected_products'] ) ? (array) $settings['selected_products'] : [];
        $products     = [];

        if ( ! empty( $selected_ids ) ) {
            $query = new \WP_Query(
                [
                    'post_type'      => 'product',
                    'post__in'       => array_map( '\absint', $selected_ids ),
                    'posts_per_page' => -1,
                    'orderby'        => 'post__in',
                ]
            );

            if ( ! empty( $query->posts ) ) {
                foreach ( $query->posts as $post ) {
                    $product = \function_exists( 'wc_get_product' ) ? \wc_get_product( $post ) : null;

                    if ( $product ) {
                        $products[ $product->get_id() ] = $product->get_formatted_name();
                    } else {
                        $products[ $post->ID ] = \get_the_title( $post );
                    }
                }
            }

            \wp_reset_postdata();
        }
        ?>
        <div
            class="imagegecko-autocomplete"
            data-lookup="products"
            data-placeholder="<?php echo \esc_attr__( 'Type to search products…', 'imagegecko' ); ?>"
            data-remove-label="<?php echo \esc_attr__( 'Remove product', 'imagegecko' ); ?>"
        >
            <ul
                class="imagegecko-autocomplete__selections"
                data-name="<?php echo \esc_attr( Settings::OPTION_KEY ); ?>[selected_products][]"
            >
                <?php foreach ( $selected_ids as $product_id ) :
                    if ( empty( $products[ $product_id ] ) ) {
                        continue;
                    }

                    ?>
                    <li class="imagegecko-pill" data-id="<?php echo \esc_attr( (string) $product_id ); ?>">
                        <span class="imagegecko-pill__label"><?php echo \esc_html( $products[ $product_id ] ); ?></span>
                        <button type="button" class="button-link-delete imagegecko-pill__remove" aria-label="<?php \esc_attr_e( 'Remove product', 'imagegecko' ); ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <input type="hidden" name="<?php echo \esc_attr( Settings::OPTION_KEY ); ?>[selected_products][]" value="<?php echo \esc_attr( (string) $product_id ); ?>" />
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="imagegecko-autocomplete__controls">
                <input
                    type="text"
                    id="imagegecko_selected_products"
                    class="imagegecko-autocomplete__input"
                    autocomplete="off"
                />
            </div>
            <p class="imagegecko-autocomplete__empty"<?php echo empty( $selected_ids ) ? '' : ' style="display:none;"'; ?>><?php \esc_html_e( 'No products selected yet.', 'imagegecko' ); ?></p>
        </div>
        <p class="description"><?php \esc_html_e( 'Leave blank to include all products (subject to category filtering). Start typing to find products by name.', 'imagegecko' ); ?></p>
        <?php
    }

    public function enqueue_assets( $hook ): void {
        if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
            return;
        }

        \wp_enqueue_style(
            'imagegecko-admin-settings',
            \plugins_url( 'assets/css/admin-settings.css', IMAGEGECKO_PLUGIN_FILE ),
            [],
            IMAGEGECKO_VERSION
        );

        \wp_enqueue_script(
            'imagegecko-admin-settings',
            \plugins_url( 'assets/js/admin-settings.js', IMAGEGECKO_PLUGIN_FILE ),
            [ 'jquery', 'jquery-ui-autocomplete' ],
            IMAGEGECKO_VERSION,
            true
        );

        \wp_localize_script(
            'imagegecko-admin-settings',
            'imageGeckoAdmin',
            [
                'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
                'nonce'   => \wp_create_nonce( self::NONCE_ACTION ),
                'i18n'    => [
                    'noResults' => \__( 'No matches found.', 'imagegecko' ),
                    'duplicate' => \__( 'Already selected.', 'imagegecko' ),
                    'remove'    => \__( 'Remove', 'imagegecko' ),
                    'categorySummarySingular' => \__( 'Selected categories currently cover approximately %d published product.', 'imagegecko' ),
                    'categorySummaryPlural'   => \__( 'Selected categories currently cover approximately %d published products.', 'imagegecko' ),
                ],
            ]
        );
    }

    public function ajax_search_categories(): void {
        $this->guard_ajax_request();

        $search = isset( $_GET['q'] ) ? \sanitize_text_field( \wp_unslash( (string) $_GET['q'] ) ) : '';

        if ( '' === $search || strlen( $search ) < 2 ) {
            \wp_send_json_success( [] );
        }

        $terms = \get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'number'     => 20,
                'orderby'    => 'name',
                'order'      => 'ASC',
                'search'     => $search,
            ]
        );

        if ( \is_wp_error( $terms ) ) {
            \wp_send_json_error( [ 'message' => $terms->get_error_message() ], 500 );
        }

        $results = [];

        foreach ( $terms as $term ) {
            $count = (int) $term->count;

            $results[] = [
                'id'    => (int) $term->term_id,
                'value' => $term->name,
                'label' => sprintf(
                    /* translators: 1: category name, 2: product count */
                    \_n( '%1$s — %2$d product', '%1$s — %2$d products', $count, 'imagegecko' ),
                    $term->name,
                    $count
                ),
                'count' => $count,
            ];
        }

        \wp_send_json_success( $results );
    }

    public function ajax_search_products(): void {
        $this->guard_ajax_request();

        $search = isset( $_GET['q'] ) ? \sanitize_text_field( \wp_unslash( (string) $_GET['q'] ) ) : '';

        if ( '' === $search || strlen( $search ) < 2 ) {
            \wp_send_json_success( [] );
        }

        $query = new \WP_Query(
            [
                'post_type'      => 'product',
                'post_status'    => [ 'publish', 'private', 'draft' ],
                'posts_per_page' => 20,
                'orderby'        => 'title',
                'order'          => 'ASC',
                's'              => $search,
            ]
        );

        $results = [];

        if ( ! empty( $query->posts ) ) {
            foreach ( $query->posts as $post ) {
                $product = \function_exists( 'wc_get_product' ) ? \wc_get_product( $post ) : null;

                $label = $product ? $product->get_formatted_name() : \get_the_title( $post );

                $results[] = [
                    'id'    => (int) $post->ID,
                    'value' => $label,
                    'label' => $label,
                ];
            }
        }

        \wp_reset_postdata();

        \wp_send_json_success( $results );
    }

    private function guard_ajax_request(): void {
        $nonce = isset( $_REQUEST['nonce'] ) ? (string) $_REQUEST['nonce'] : '';

        if ( ! \wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Invalid request nonce.', 'imagegecko' ) ], 403 );
        }

        if ( ! \current_user_can( 'manage_woocommerce' ) ) {
            \wp_send_json_error( [ 'message' => \__( 'You do not have permission to perform this action.', 'imagegecko' ) ], 403 );
        }
    }

    public function sanitize_settings( $input ): array {
        $input = is_array( $input ) ? $input : [];

        $sanitized = $this->settings->sanitize_settings( $input );

        if ( isset( $_POST['imagegecko_api_key'] ) ) {
            $api_key = \sanitize_text_field( \wp_unslash( (string) $_POST['imagegecko_api_key'] ) );
            $this->settings->store_api_key( $api_key );
        }

        \add_settings_error( self::MENU_SLUG, 'imagegecko-settings-saved', \__( 'Settings saved.', 'imagegecko' ), 'updated' );

        return $sanitized;
    }

    public function render_settings_page(): void {
        if ( ! \current_user_can( 'manage_woocommerce' ) ) {
            \wp_die( \__( 'You do not have permission to access this page.', 'imagegecko' ) );
        }
        ?>
        <div class="wrap imagegecko-settings">
            <h1><?php \esc_html_e( 'ImageGecko AI Photos', 'imagegecko' ); ?></h1>
            <?php \settings_errors( self::MENU_SLUG ); ?>
            <form method="post" action="options.php">
                <?php
                \settings_fields( self::OPTION_GROUP );
                \do_settings_sections( self::MENU_SLUG );
                \submit_button( \__( 'Save changes', 'imagegecko' ) );
                ?>
            </form>
        </div>
        <?php
    }
}
