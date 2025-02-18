<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

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
