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
        \add_action( 'wp_ajax_imagegecko_save_config', [ $this, 'ajax_save_config' ] );
        \add_action( 'admin_post_imagegecko_save_api_key', [ $this, 'handle_api_key_submission' ] );
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
            'imagegecko_generation',
            \__( 'Generation Defaults', 'imagegecko' ),
            [ $this, 'render_generation_intro' ],
            self::MENU_SLUG
        );

        \add_settings_field(
            'imagegecko_default_prompt',
            \__( 'Default Style Prompt', 'imagegecko' ),
            [ $this, 'render_prompt_field' ],
            self::MENU_SLUG,
            'imagegecko_generation'
        );

        \add_settings_field(
            'imagegecko_selected_categories',
            \__( 'Target Categories', 'imagegecko' ),
            [ $this, 'render_categories_field' ],
            self::MENU_SLUG,
            'imagegecko_generation'
        );

        \add_settings_field(
            'imagegecko_selected_products',
            \__( 'Target Products', 'imagegecko' ),
            [ $this, 'render_products_field' ],
            self::MENU_SLUG,
            'imagegecko_generation'
        );
    }

    public function render_generation_intro(): void {
        echo '<p>' . \esc_html__( 'Define the default styling prompt and which products ImageGecko should enhance when you run the workflow.', 'imagegecko' ) . '</p>';
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
            placeholder="<?php \esc_attr_e( 'Paste the ContentGecko API key from your welcome email', 'imagegecko' ); ?>"
        />
        <p class="description"><?php \esc_html_e( 'You received this key in the email that included your plugin ZIP. Copy it from that email and paste it here. It is stored encrypted in your database.', 'imagegecko' ); ?></p>
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
                'hasApiKey' => '' !== $this->settings->get_api_key(),
                'i18n'    => [
                    'noResults' => \__( 'No matches found.', 'imagegecko' ),
                    'duplicate' => \__( 'Already selected.', 'imagegecko' ),
                    'remove'    => \__( 'Remove', 'imagegecko' ),
                    'categorySummarySingular' => \__( 'Selected categories currently cover approximately %d published product.', 'imagegecko' ),
                    'categorySummaryPlural'   => \__( 'Selected categories currently cover approximately %d published products.', 'imagegecko' ),
                    'startError'              => \__( 'Unable to prepare the generation run. Please try again.', 'imagegecko' ),
                    'processing'              => \__( 'Processing…', 'imagegecko' ),
                    'queued'                  => \__( 'Queued', 'imagegecko' ),
                    'completed'               => \__( 'Completed', 'imagegecko' ),
                    'failed'                  => \__( 'Failed', 'imagegecko' ),
                    'skipped'                 => \__( 'Skipped', 'imagegecko' ),
                    'summaryProgress'         => \__( '%1$d of %2$d products completed', 'imagegecko' ),
                    'summaryFinished'         => \__( 'All done! %d products enhanced.', 'imagegecko' ),
                    'go'                      => \__( 'GO', 'imagegecko' ),
                    'going'                   => \__( 'Working…', 'imagegecko' ),
                    'stepLocked'              => \__( 'Add your API key before running the workflow.', 'imagegecko' ),
                    'idle'                    => \__( 'Idle', 'imagegecko' ),
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

    public function ajax_save_config(): void {
        $this->guard_ajax_request();

        $config_data = isset( $_POST['config'] ) ? (array) $_POST['config'] : [];
        
        $this->logger->info( 'Saving configuration via AJAX.', [ 'config_keys' => array_keys( $config_data ) ] );

        // Sanitize the settings using the same method as the regular form submission
        $sanitized = $this->settings->sanitize_settings( $config_data );
        
        // Save the settings
        $saved = \update_option( Settings::OPTION_KEY, $sanitized );
        
        if ( $saved ) {
            $this->logger->info( 'Configuration saved successfully via AJAX.' );
            \wp_send_json_success( [ 'message' => \__( 'Configuration saved.', 'imagegecko' ) ] );
        } else {
            $this->logger->error( 'Failed to save configuration via AJAX.' );
            \wp_send_json_error( [ 'message' => \__( 'Failed to save configuration.', 'imagegecko' ) ], 500 );
        }
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

        \add_settings_error( self::MENU_SLUG, 'imagegecko-settings-saved', \__( 'Settings saved.', 'imagegecko' ), 'updated' );

        return $sanitized;
    }

    public function handle_api_key_submission(): void {
        if ( ! \current_user_can( 'manage_woocommerce' ) ) {
            \wp_die( \__( 'You do not have permission to perform this action.', 'imagegecko' ) );
        }

        \check_admin_referer( self::NONCE_ACTION, '_imagegecko_nonce' );

        $api_key = isset( $_POST['imagegecko_api_key'] ) ? \sanitize_text_field( \wp_unslash( (string) $_POST['imagegecko_api_key'] ) ) : '';

        $this->settings->store_api_key( $api_key );

        $message = '' === $api_key
            ? \__( 'API key removed. Add a key to continue using ImageGecko.', 'imagegecko' )
            : \__( 'API key saved. You can now configure and run ImageGecko.', 'imagegecko' );

        \add_settings_error( self::MENU_SLUG, 'imagegecko-api-key-saved', $message, 'updated' );
        \set_transient( 'settings_errors', \get_settings_errors(), 30 );

        \wp_safe_redirect( \add_query_arg( [ 'page' => self::MENU_SLUG ], \admin_url( 'admin.php' ) ) );
        exit;
    }

    public function render_settings_page(): void {
        if ( ! \current_user_can( 'manage_woocommerce' ) ) {
            \wp_die( \__( 'You do not have permission to access this page.', 'imagegecko' ) );
        }

        $has_api_key = '' !== $this->settings->get_api_key();
        ?>
        <div class="wrap imagegecko-settings">
            <h1><?php \esc_html_e( 'ImageGecko AI Photos', 'imagegecko' ); ?></h1>
            <?php \settings_errors( self::MENU_SLUG ); ?>
            <div class="imagegecko-steps">
                <section class="imagegecko-step imagegecko-step--api">
                    <h2><?php \esc_html_e( 'Step 1: Connect to ContentGecko', 'imagegecko' ); ?></h2>
                    <p><?php \esc_html_e( 'Paste the API key from your ContentGecko dashboard. This unlocks the rest of the workflow.', 'imagegecko' ); ?></p>
                    <form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
                        <?php \wp_nonce_field( self::NONCE_ACTION, '_imagegecko_nonce' ); ?>
                        <input type="hidden" name="action" value="imagegecko_save_api_key" />
                        <?php $this->render_api_key_field(); ?>
                        <?php \submit_button( \__( 'Save API Key', 'imagegecko' ), 'primary', 'submit', false ); ?>
                        <?php if ( $has_api_key ) : ?>
                            <p class="imagegecko-step__status imagegecko-step__status--connected">
                                <?php \esc_html_e( 'Connected to ContentGecko.', 'imagegecko' ); ?>
                            </p>
                        <?php endif; ?>
                    </form>
                </section>

                <section class="imagegecko-step imagegecko-step--configure<?php echo $has_api_key ? '' : ' imagegecko-step--disabled'; ?>">
                    <h2><?php \esc_html_e( 'Step 2: Choose Products & Styles', 'imagegecko' ); ?></h2>
                    <?php if ( $has_api_key ) : ?>
                        <form method="post" action="options.php" id="imagegecko-config-form">
                            <?php
                            \settings_fields( self::OPTION_GROUP );
                            \do_settings_sections( self::MENU_SLUG );
                            ?>
                        </form>
                        <section class="imagegecko-step imagegecko-step--run">
                            <h2><?php \esc_html_e( 'Step 3: Save & Enhance Products', 'imagegecko' ); ?></h2>
                            <p><?php \esc_html_e( 'When you are ready, click GO. ImageGecko will save your configuration and generate new lifestyle imagery for each product automatically.', 'imagegecko' ); ?></p>
                            <button type="button" class="button button-primary" id="imagegecko-go-button" data-state="idle"><?php \esc_html_e( 'GO', 'imagegecko' ); ?></button>
                            <div id="imagegecko-progress" class="imagegecko-progress" style="display:none;">
                                <p class="imagegecko-progress__summary"></p>
                                <ul class="imagegecko-progress__list"></ul>
                            </div>
                        </section>
                    <?php else : ?>
                        <p class="imagegecko-step__notice notice notice-warning">
                            <?php \esc_html_e( 'Add your API key above to unlock targeting and start enhancing products.', 'imagegecko' ); ?>
                        </p>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }
}
