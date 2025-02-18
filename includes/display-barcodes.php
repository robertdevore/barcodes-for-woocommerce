<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

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

    $barcode     = get_post_meta( $post->ID, '_product_barcode', true );
    $settings    = get_option( 'barcode_settings', [ 'color' => '#000000' ] );
    $color       = str_replace( '#', '', $settings['color'] );
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
