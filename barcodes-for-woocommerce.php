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

require 'vendor/plugin-update-checker/plugin-update-checker.php';
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
define( 'BARCODES_ROOT_FILE', __FILE__ );

// Check if Composer's autoloader is already registered globally.
if ( ! class_exists( 'RobertDevore\WPComCheck\WPComPluginHandler' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use RobertDevore\WPComCheck\WPComPluginHandler;

new WPComPluginHandler( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' );

// Add the required plugin files.
require 'classes/BarcodesForWooCommerce.php';
require 'includes/display-barcodes.php';
require 'includes/generate-barcodes.php';

/**
 * Load plugin text domain for translations
 * 
 * @since  1.0.1
 * @return void
 */
function barcodes_wc_load_textdomain() {
    load_plugin_textdomain( 
        'barcodes-for-woocommerce', 
        false, 
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'barcodes_wc_load_textdomain' );

// Include the class.
new BarcodesForWooCommerce();
