<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://robertdevore.com
 * @since             1.0.0
 * @package           Barcodes_For_WooCommerce
 *
 * @wordpress-plugin
 *
 * Plugin Name: Barcodes for WooCommerce®
 * Description: Generate and manage barcodes/QR codes for WooCommerce orders and products.
 * Plugin URI:  https://github.com/robertdevore/barcodes-for-woocommerce/
 * Version:     0.0.1
 * Author:      Robert DeVore
 * Author URI:  https://robertdevore.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: barcodes-for-woocommerce
 * Domain Path: /languages
 * Update URI:  https://github.com/robertdevore/barcodes-for-woocommerce/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/robertdevore/barcodes-for-woocommerce/',
    __FILE__,
    'barcodes-for-woocommerce'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

// Set the version number.
define( 'BARCODES_FOR_WOOCOMMERCE_VERSION', '0.0.1' );

/**
 * Main plugin class for building BarcodesForWooCommerce
 */
class BarcodesForWooCommerce {
    /**
     * Constructor to initialize hooks and filters.
     * 
     * @since  1.0.0
     * @return void
     */
    public function __construct() {
        // Hook to add settings menu.
        add_action( 'admin_menu', [ $this, 'register_settings_menu' ] );

        // Register settings.
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Enqueue admin scripts.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Generate barcodes for new orders.
        add_action( 'woocommerce_thankyou', [ $this, 'generate_order_barcode' ] );
        add_action( 'woocommerce_new_order', [ $this, 'generate_order_barcode' ] );

        // Shortcode for displaying barcodes.
        add_shortcode( 'order_barcode', [ $this, 'display_order_barcode' ] );

        // Ajax for barcode lookup.
        add_action( 'wp_ajax_lookup_barcode', [ $this, 'lookup_barcode' ] );
        add_action( 'wp_ajax_nopriv_lookup_barcode', [ $this, 'lookup_barcode' ] );
        add_action( 'wp_ajax_generate_order_barcodes', [ $this, 'generate_order_barcodes' ] );
        add_action( 'wp_ajax_generate_product_barcodes', [ $this, 'generate_product_barcodes' ] );
    }

    /**
     * Registers the settings menu in WooCommerce.
     * 
     * @since  1.0.0
     * @return void
     */
    public function register_settings_menu() {
        add_menu_page(
            esc_html__( 'Barcodes for WooCommerce', 'barcodes-for-woocommerce' ),
            esc_html__( 'Barcodes', 'barcodes-for-woocommerce' ),
            'manage_options',
            'barcodes-for-woocommerce',
            [ $this, 'settings_page' ],
            'dashicons-barcode',
            56
        );
    }

    /**
     * Registers plugin settings.
     * 
     * @since  1.0.0
     * @return void
     */
    public function register_settings() {
        register_setting( 'barcode_settings_group', 'barcode_settings' );
    }

    /**
     * Enqueues admin scripts and styles.
     *
     * @param string $hook The current admin page hook.
     * 
     * @since  1.0.0
     * @return void
     */
    public function enqueue_admin_scripts( $hook ) {
        // Ensure scripts/styles only load on the plugin page.
        if ( strpos( $hook, 'barcodes-for-woocommerce' ) === false ) {
            return;
        }

        // Enqueue WordPress color picker.
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style( 'wp-color-picker' );

        // Enqueue barcode-specific scripts.
        wp_enqueue_script(
            'barcode-scripts',
            plugins_url( 'assets/js/barcode-scripts.js', __FILE__ ),
            [ 'jquery', 'wp-color-picker' ],
            BARCODES_FOR_WOOCOMMERCE_VERSION,
            true
        );

        // Enqueue the advanced settings.
        wp_enqueue_script(
            'advanced-settings',
            plugins_url( 'assets/js/advanced-settings.js', __FILE__ ),
            [ 'jquery' ],
            BARCODES_FOR_WOOCOMMERCE_VERSION,
            true
        );

        // Enqueue QR code library.
        wp_enqueue_script(
            'html5-qrcode',
            plugins_url( 'assets/js/html5-qrcode.min.js', __FILE__ ),
            [],
            BARCODES_FOR_WOOCOMMERCE_VERSION,
            true
        );

        // Enqueue barcode styles.
        wp_enqueue_style(
            'barcode-styles',
            plugins_url( 'assets/css/barcode-styles.css', __FILE__ ),
            [],
            BARCODES_FOR_WOOCOMMERCE_VERSION
        );

        // Localize script for AJAX.
        wp_localize_script( 'advanced-settings', 'barcode_ajax', [
            'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
        ] );
    }

    /**
     * Generates product barcodes in batches.
     * 
     * @since  1.0.0
     * @return void
     */
    public function generate_product_barcodes() {
        $batch_size = 10;
        $offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

        // Fetch products without barcodes.
        $products = get_posts( [
            'post_type'      => 'product',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'meta_query'     => [
                [
                    'key'     => '_product_barcode',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        $total_products     = $this->get_total_products_without_barcodes();
        $processed_products = $offset + count( $products );
        $remaining_products = $total_products - $processed_products;

        // Generate barcodes for each product in the batch.
        foreach ( $products as $product ) {
            $barcode = $this->create_barcode( $product->ID );
            update_post_meta( $product->ID, '_product_barcode', $barcode );
        }

        // If no more products to process, return a message.
        if ( empty( $products ) ) {
            wp_send_json_success( [
                'updated'   => 0,
                'remaining' => 0,
                'message'   => $offset === 0
                    ? esc_html__( 'No new products need barcodes.', 'barcodes-for-woocommerce' )
                    : esc_html__( 'All products processed.', 'barcodes-for-woocommerce' ),
            ] );
        }

        // Return progress details.
        wp_send_json_success( [
            'updated'   => count( $products ),
            'remaining' => $remaining_products,
            'message'   => '',
        ] );
    }

    /**
     * Helper function to get the total number of products without barcodes.
     *
     * @since  1.0.0
     * @return int The count of products without barcodes.
     */
    private function get_total_products_without_barcodes() {
        $query = new WP_Query( [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_product_barcode',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        return $query->found_posts;
    }

    /**
     * Creates a unique barcode.
     *
     * @param int $data The data (usually an ID) to generate the barcode from.
     * 
     * @since  1.0.0
     * @return string The generated barcode.
     */
    private function create_barcode( $data ) {
        return md5( $data . time() );
    }

    /**
     * Displays a barcode for an order.
     *
     * @param array $atts The shortcode attributes.
     * 
     * @since  1.0.0
     * @return string The HTML output for the barcode.
     */
    public function display_order_barcode( $atts ) {
        $atts = shortcode_atts( [ 'order_id' => null ], $atts );

        if ( ! $atts['order_id'] ) {
            return '<p>' . esc_html__( 'No order ID provided.', 'barcodes-for-woocommerce' ) . '</p>';
        }

        $barcode  = get_post_meta( $atts['order_id'], '_barcode', true );
        $settings = get_option( 'barcode_settings', [ 'color' => '#000000' ] );
        $color    = str_replace( '#', '', $settings['color'] ); // Strip '#' for QR code URL

        if ( $barcode ) {
            return sprintf(
                '<img src="%s" alt="%s">',
                esc_url( "https://api.qrserver.com/v1/create-qr-code/?data={$barcode}&color={$color}" ),
                esc_attr__( 'Order Barcode', 'barcodes-for-woocommerce' )
            );
        }

        return '<p>' . esc_html__( 'No barcode found.', 'barcodes-for-woocommerce' ) . '</p>';
    }

    public function generate_order_barcodes() {
        $batch_size = 10;
        $offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
    
        // Fetch orders without barcodes.
        $query = new WC_Order_Query( [
            'limit'      => $batch_size,
            'offset'     => $offset,
            'meta_query' => [
                [
                    'key'     => '_barcode',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );
    
        $orders = $query->get_orders();
    
        if ( empty( $orders ) ) {
            // If this is the first batch and no orders are found, return a message.
            if ( $offset === 0 ) {
                wp_send_json_success( [
                    'updated'   => 0,
                    'remaining' => 0,
                    'message'   => esc_html__( 'All orders already have barcodes.', 'barcodes-for-woocommerce' ),
                ] );
            }
    
            // If later batches, return an "All processed" message.
            wp_send_json_success( [
                'updated'   => 0,
                'remaining' => 0,
                'message'   => esc_html__( 'All orders processed.', 'barcodes-for-woocommerce' ),
            ] );
        }
    
        // Generate barcodes for each order in the batch.
        foreach ( $orders as $order ) {
            $barcode = $this->create_barcode( $order->get_id() );
            update_post_meta( $order->get_id(), '_barcode', $barcode );
        }
    
        // Count remaining orders without barcodes.
        $remaining_orders = $this->get_total_orders_without_barcodes() - $offset - count( $orders );
    
        wp_send_json_success( [
            'updated'   => count( $orders ),
            'remaining' => max( $remaining_orders, 0 ),
            'message'   => '',
        ] );
    }    

    public function lookup_barcode() {
        // Check if the barcode is provided.
        if ( ! isset( $_POST['barcode'] ) || empty( $_POST['barcode'] ) ) {
            wp_send_json_error( __( 'No barcode provided.', 'barcodes-for-woocommerce' ) );
        }
    
        // Sanitize the barcode input.
        $barcode = sanitize_text_field( $_POST['barcode'] );
    
        // Get the order ID by barcode.
        $order_id = $this->get_order_by_barcode( $barcode );
    
        if ( $order_id ) {
            $order          = wc_get_order( $order_id );
            $customer_email = $order->get_billing_email();
            $customer_name  = $order->get_formatted_billing_full_name();
            $order_status   = $order->get_status();
            $status_color   = $this->get_order_status_color( $order_status );
    
            $order_data = [
                'order_id'         => $order->get_id(),
                'order_link'       => admin_url( "post.php?post={$order->get_id()}&action=edit" ),
                'order_date'       => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
                'order_status'     => ucfirst( $order_status ),
                'status_color'     => $status_color,
                'customer_name'    => $customer_name,
                'customer_link'    => admin_url( "edit.php?s={$customer_email}&post_type=shop_order" ),
                'customer_phone'   => $order->get_billing_phone(),
                'customer_email'   => $customer_email,
                'customer_address' => $order->get_formatted_billing_address(),
            ];
    
            wp_send_json_success( $order_data );
        } else {
            wp_send_json_error( __( 'Order not found.', 'barcodes-for-woocommerce' ) );
        }
    }    

    /**
     * Outputs the settings page.
     * 
     * @since  1.0.0
     * @return void
     */
    public function settings_page() {
        $tabs = [
            'general'  => esc_html__( 'General Settings', 'barcodes-for-woocommerce' ),
            'scan'     => esc_html__( 'Scan Barcodes', 'barcodes-for-woocommerce' ),
            'advanced' => esc_html__( 'Advanced', 'barcodes-for-woocommerce' ),
        ];
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Barcodes for WooCommerce', 'barcodes-for-woocommerce' ); ?>
                <?php
                echo sprintf(
                    '<a id="barcodes-support-btn" href="%1$s" target="_blank" class="button button-alt" style="margin-left: 10px;">
                        <span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> %2$s
                    </a>
                    <a id="barcodes-docs-btn" href="%3$s" target="_blank" class="button button-alt" style="margin-left: 5px;">
                        <span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span> %4$s
                    </a>',
                    esc_url( 'https://robertdevore.com/contact/' ),
                    esc_html__( 'Support', 'barcodes-for-woocommerce' ),
                    esc_url( 'https://robertdevore.com/articles/barcodes-for-woocommerce/' ),
                    esc_html__( 'Documentation', 'barcodes-for-woocommerce' )
                );
                ?>
            </h1>
            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $tab => $label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $tab, admin_url( 'admin.php?page=barcodes-for-woocommerce' ) ) ); ?>" 
                    class="nav-tab <?php echo esc_attr( $current_tab === $tab ? 'nav-tab-active' : '' ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            <?php if ( 'general' === $current_tab ) : ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'barcode_settings_group' );
                    do_settings_sections( 'barcode_settings_group' );
                    $settings = get_option( 'barcode_settings', [
                        'enabled' => 0,
                        'color'   => '#000000',
                        'type'    => 'qr_code',
                    ] );
                    ?>
                    <h2><?php esc_html_e( 'General Settings', 'barcodes-for-woocommerce' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enabled"><?php esc_html_e( 'Enable Barcodes', 'barcodes-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="enabled" name="barcode_settings[enabled]"
                                    value="1" <?php checked( $settings['enabled'], 1 ); ?>>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="type"><?php esc_html_e( 'Barcode Type', 'barcodes-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <select id="type" name="barcode_settings[type]">
                                    <option value="qr_code" <?php selected( $settings['type'], 'qr_code' ); ?>>
                                        <?php esc_html_e( 'QR Code', 'barcodes-for-woocommerce' ); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="color"><?php esc_html_e( 'Barcode Color', 'barcodes-for-woocommerce' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="color" name="barcode_settings[color]"
                                    value="<?php echo esc_attr( $settings['color'] ); ?>" class="color-field">
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            <?php elseif ( 'scan' === $current_tab ) : ?>
                <h2><?php esc_html_e( 'Scan Barcodes', 'barcodes-for-woocommerce' ); ?></h2>
                <p><?php esc_html_e( 'Use this page to scan barcodes and manage orders.', 'barcodes-for-woocommerce' ); ?></p>
                <input type="text" id="barcode-input" placeholder="<?php esc_attr_e( 'Search barcodes', 'barcodes-for-woocommerce' ); ?>">
                <button id="scan-barcode" class="button button-primary"><?php esc_html_e( 'Search', 'barcodes-for-woocommerce' ); ?></button>
                <div id="camera-scanner" style="margin-top: 20px;">
                    <h3><?php esc_html_e( 'Or use your camera to scan', 'barcodes-for-woocommerce' ); ?></h3>
                    <div id="reader" style="width: 300px; margin-bottom: 20px;"></div>
                    <button id="toggle-scanner" class="button button-primary"><?php esc_html_e( 'Start Scanner', 'barcodes-for-woocommerce' ); ?></button>
                </div>
                <div id="scan-results" style="margin-top: 20px;"></div>
            <?php elseif ( 'advanced' === $current_tab ) : ?>
                <div id="advanced-settings">
                    <h2><?php esc_html_e( 'Advanced Settings', 'barcodes-for-woocommerce' ); ?></h2>
                    <p><?php esc_html_e( 'Generate barcodes for existing orders and products.', 'barcodes-for-woocommerce' ); ?></p>
                    <div>
                        <h3><?php esc_html_e( 'Orders', 'barcodes-for-woocommerce' ); ?></h3>
                        <button id="generate-order-barcodes" class="button button-primary"><?php esc_html_e( 'Generate Barcodes for Orders', 'barcodes-for-woocommerce' ); ?></button>
                        <div id="order-progress" style="display:none;">
                            <progress id="order-progress-bar" value="0" max="100"></progress>
                            <span id="order-progress-text"></span>
                        </div>
                    </div>
                    <div>
                        <h3><?php esc_html_e( 'Products', 'barcodes-for-woocommerce' ); ?></h3>
                        <button id="generate-product-barcodes" class="button button-primary"><?php esc_html_e( 'Generate Barcodes for Products', 'barcodes-for-woocommerce' ); ?></button>
                        <div id="product-progress" style="display:none;">
                            <progress id="product-progress-bar" value="0" max="100"></progress>
                            <span id="product-progress-text"></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get WooCommerce order status color based on the status.
     *
     * @param string $status The WooCommerce order status.
     * 
     * @since  1.0.0
     * @return string The corresponding color for the status.
     */
    private function get_order_status_color( $status ) {
        $status_colors = [
            'pending'    => 'orange',
            'processing' => 'blue',
            'on-hold'    => 'yellow',
            'completed'  => 'green',
            'cancelled'  => 'red',
            'refunded'   => 'purple',
            'failed'     => 'gray',
        ];

        return isset( $status_colors[ $status ] ) ? $status_colors[ $status ] : 'black';
    }

    /**
     * Get order ID by barcode.
     *
     * @param string $barcode The barcode to search for.
     * 
     * @since  1.0.0
     * @return int|null The order ID if found, otherwise null.
     */
    private function get_order_by_barcode( $barcode ) {
        global $wpdb;

        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id 
                 FROM $wpdb->postmeta 
                 WHERE meta_key = '_barcode' 
                 AND meta_value = %s",
                $barcode
            )
        );

        return $order_id ? absint( $order_id ) : null;
    }

    /**
     * Generate a barcode for an order.
     *
     * @param int $order_id The ID of the order.
     * 
     * @since  1.0.0
     * @return void
     */
    public function generate_order_barcode( $order_id ) {
        if ( metadata_exists( 'post', $order_id, '_barcode' ) ) {
            return; // Skip if barcode already exists
        }

        $barcode = $this->create_barcode( $order_id );
        update_post_meta( $order_id, '_barcode', sanitize_text_field( $barcode ) );
    }

    /**
     * Get the total number of orders without barcodes.
     *
     * @since  1.0.0
     * @return int The count of orders without barcodes.
     */
    private function get_total_orders_without_barcodes() {
        $query = new WC_Order_Query( [
            'limit'      => -1,
            'meta_query' => [
                [
                    'key'     => '_barcode',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        return count( $query->get_orders() );
    }

}

new BarcodesForWooCommerce();

/**
 * Display the order barcode in the admin order details.
 *
 * @param WC_Order $order The WooCommerce order object.
 * 
 * @since  1.0.0
 * @return void
 */
function display_order_barcode_in_admin( $order ) {
    $barcode  = get_post_meta( $order->get_id(), '_barcode', true );
    $settings = get_option( 'barcode_settings', [ 'color' => '#000000' ] );
    $color    = str_replace( '#', '', $settings['color'] );

    if ( $barcode ) {
        echo '<p class="form-field form-field-wide wc-order-barcode"><strong>' . esc_html__( 'Order Barcode:', 'barcodes-for-woocommerce' ) . '</strong></p>';
        echo "<img src='" . esc_url( "https://api.qrserver.com/v1/create-qr-code/?data={$barcode}&color={$color}" ) . "' alt='" . esc_attr__( 'Order Barcode', 'barcodes-for-woocommerce' ) . "' />";
        echo '<p class="form-field form-field-wide wc-order-barcode"><strong>' . esc_html__( 'Barcode Text:', 'barcodes-for-woocommerce' ) . '</strong> <span style="font-family: monospace;">' . esc_html( $barcode ) . '</span></p>';
    }
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'display_order_barcode_in_admin' );

/**
 * Display the order barcode on the order details page.
 *
 * @param WC_Order $order The WooCommerce order object.
 * 
 * @since  1.0.0
 * @return void
 */
function display_order_barcode_in_order_details( $order ) {
    $barcode  = get_post_meta( $order->get_id(), '_barcode', true );
    $settings = get_option( 'barcode_settings', [ 'color' => '#000000' ] );
    $color    = str_replace( '#', '', $settings['color'] );

    if ( $barcode ) {
        echo '<p><strong>' . esc_html__( 'Order Barcode:', 'barcodes-for-woocommerce' ) . '</strong></p>';
        echo "<img src='" . esc_url( "https://api.qrserver.com/v1/create-qr-code/?data={$barcode}&color={$color}" ) . "' alt='" . esc_attr__( 'Order Barcode', 'barcodes-for-woocommerce' ) . "' />";
    }
}
add_action( 'woocommerce_order_details_after_order_table', 'display_order_barcode_in_order_details' );

/**
 * Display the product barcode (QR code) on the single product summary.
 * Links the QR code to the product's URL and positions it below the Add to Cart button.
 * 
 * @since  1.0.0
 * @return void
 */
function display_product_barcode_below_add_to_cart() {
    global $post;

    $barcode  = get_post_meta( $post->ID, '_product_barcode', true );
    $settings = get_option( 'barcode_settings', [ 'color' => '#000000' ] );
    $color    = str_replace( '#', '', $settings['color'] );
    $product_url = get_permalink( $post->ID );

    if ( $barcode ) {
        echo '<div class="product-qr-code" style="margin-top: 15px; text-align: center;">';
        echo '<p><strong>' . esc_html__( 'Scan to share this product:', 'barcodes-for-woocommerce' ) . '</strong></p>';
        echo sprintf(
            '<a href="%s" target="_blank"><img src="%s" alt="%s" style="max-width: 150px;"></a>',
            esc_url( $product_url ),
            esc_url( "https://api.qrserver.com/v1/create-qr-code/?data={$product_url}&color={$color}" ),
            esc_attr__( 'Product QR Code', 'barcodes-for-woocommerce' )
        );
        echo '</div>';
    }
}
add_action( 'woocommerce_after_add_to_cart_button', 'display_product_barcode_below_add_to_cart' );

/**
 * Add the product barcode to the product data meta box.
 * 
 * @since  1.0.0
 * @return void
 */
function add_product_barcode_meta_box() {
    global $post;

    $barcode  = get_post_meta( $post->ID, '_product_barcode', true );
    $settings = get_option( 'barcode_settings', [ 'color' => '#000000' ] );
    $color    = str_replace( '#', '', $settings['color'] );

    echo '<div class="options_group">';
    echo '<p><strong>' . esc_html__( 'Product Barcode:', 'barcodes-for-woocommerce' ) . '</strong></p>';
    if ( $barcode ) {
        echo "<img src='" . esc_url( "https://api.qrserver.com/v1/create-qr-code/?data={$barcode}&color={$color}" ) . "' alt='" . esc_attr__( 'Product Barcode', 'barcodes-for-woocommerce' ) . "' />";
    }
    echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'add_product_barcode_meta_box' );

/**
 * Add the order barcode (as a QR code) to the customer email.
 *
 * @param WC_Order $order The WooCommerce order object.
 * @param bool     $sent_to_admin Whether the email is sent to admin.
 * @param bool     $plain_text Whether the email is in plain text format.
 * @param WC_Email $email The WooCommerce email object.
 *
 * @since 1.0.0
 */
function add_barcode_to_email( $order, $sent_to_admin, $plain_text ) {
    // Only add the barcode to customer emails, not admin emails.
    if ( $sent_to_admin ) {
        return;
    }

    $barcode  = get_post_meta( $order->get_id(), '_barcode', true );
    $settings = get_option( 'barcode_settings', [ 'color' => '#000000' ] );
    $color    = str_replace( '#', '', $settings['color'] );

    if ( $barcode ) {
        if ( $plain_text ) {
            // Add barcode as plain text for plain text emails.
            echo esc_html__( 'Order Barcode:', 'barcodes-for-woocommerce' ) . ' ' . esc_html( $barcode ) . "\n";
        } else {
            // Add barcode as an image for HTML emails.
            echo '<h3>' . esc_html__( 'Order Barcode', 'barcodes-for-woocommerce' ) . '</h3>';
            echo "<img src='" . esc_url( "https://api.qrserver.com/v1/create-qr-code/?data={$barcode}&color={$color}" ) . "' alt='" . esc_attr__( 'Order Barcode', 'barcodes-for-woocommerce' ) . "' style='margin-bottom: 20px;'/>";
        }
    }
}
add_action( 'woocommerce_email_after_order_table', 'add_barcode_to_email', 10, 3 );

/**
 * Generate a product barcode when saving a product.
 *
 * @param int $post_id The ID of the post being saved.
 * 
 * @since  1.0.0
 * @return void
 */
function generate_product_barcode_on_save( $post_id ) {
    if ( get_post_type( $post_id ) === 'product' && ! metadata_exists( 'post', $post_id, '_product_barcode' ) ) {
        $barcode = md5( $post_id . time() );
        update_post_meta( $post_id, '_product_barcode', sanitize_text_field( $barcode ) );
    }
}
add_action( 'save_post', 'generate_product_barcode_on_save' );

/**
 * Helper function to handle WordPress.com environment checks.
 *
 * @param string $plugin_slug     The plugin slug.
 * @param string $learn_more_link The link to more information.
 * 
 * @since  0.0.2
 * @return bool
 */
function wp_com_plugin_check( $plugin_slug, $learn_more_link ) {
    // Check if the site is hosted on WordPress.com.
    if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
        // Ensure the deactivate_plugins function is available.
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Deactivate the plugin if in the admin area.
        if ( is_admin() ) {
            deactivate_plugins( $plugin_slug );

            // Add a deactivation notice for later display.
            add_option( 'wpcom_deactivation_notice', $learn_more_link );

            // Prevent further execution.
            return true;
        }
    }

    return false;
}

/**
 * Auto-deactivate the plugin if running in an unsupported environment.
 *
 * @since  0.0.2
 * @return void
 */
function wpcom_auto_deactivation() {
    if ( wp_com_plugin_check( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' ) ) {
        return; // Stop execution if deactivated.
    }
}
add_action( 'plugins_loaded', 'wpcom_auto_deactivation' );

/**
 * Display an admin notice if the plugin was deactivated due to hosting restrictions.
 *
 * @since  0.0.2
 * @return void
 */
function wpcom_admin_notice() {
    $notice_link = get_option( 'wpcom_deactivation_notice' );
    if ( $notice_link ) {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        __( 'My Plugin has been deactivated because it cannot be used on WordPress.com-hosted websites. %s', 'barcodes-for-woocommerce' ),
                        '<a href="' . esc_url( $notice_link ) . '" target="_blank" rel="noopener">' . __( 'Learn more', 'barcodes-for-woocommerce' ) . '</a>'
                    )
                );
                ?>
            </p>
        </div>
        <?php
        delete_option( 'wpcom_deactivation_notice' );
    }
}
add_action( 'admin_notices', 'wpcom_admin_notice' );

/**
 * Prevent plugin activation on WordPress.com-hosted sites.
 *
 * @since  0.0.2
 * @return void
 */
function wpcom_activation_check() {
    if ( wp_com_plugin_check( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' ) ) {
        // Display an error message and stop activation.
        wp_die(
            wp_kses_post(
                sprintf(
                    '<h1>%s</h1><p>%s</p><p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
                    __( 'Plugin Activation Blocked', 'barcodes-for-woocommerce' ),
                    __( 'This plugin cannot be activated on WordPress.com-hosted websites. It is restricted due to concerns about WordPress.com policies impacting the community.', 'barcodes-for-woocommerce' ),
                    esc_url( 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' ),
                    __( 'Learn more', 'barcodes-for-woocommerce' )
                )
            ),
            esc_html__( 'Plugin Activation Blocked', 'barcodes-for-woocommerce' ),
            [ 'back_link' => true ]
        );
    }
}
register_activation_hook( __FILE__, 'wpcom_activation_check' );

/**
 * Add a deactivation flag when the plugin is deactivated.
 *
 * @since  0.0.2
 * @return void
 */
function wpcom_deactivation_flag() {
    add_option( 'wpcom_deactivation_notice', 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' );
}
register_deactivation_hook( __FILE__, 'wpcom_deactivation_flag' );
