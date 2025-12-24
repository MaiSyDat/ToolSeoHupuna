<?php
/**
 * Plugin Name: Hupuna External Link Scanner
 * Plugin URI: https://wordpress.org/plugins/hupuna-external-link-scanner
 * Description: Scans the entire website content for external links. Optimized for large databases with batch processing.
 * Version: 2.0.0
 * Author: MaiSyDat
 * Author URI: https://hupuna.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hupuna-external-link-scanner
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package HupunaExternalLinkScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current plugin version.
 */
define( 'HUPUNA_ELS_VERSION', '2.0.0' );

/**
 * Plugin directory path.
 */
define( 'HUPUNA_ELS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'HUPUNA_ELS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load core classes.
require_once HUPUNA_ELS_PLUGIN_DIR . 'includes/class-hupuna-scanner.php';
require_once HUPUNA_ELS_PLUGIN_DIR . 'includes/class-hupuna-admin.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function hupuna_els_init() {
	$admin = new Hupuna_External_Link_Scanner_Admin();
	$admin->init();
}
add_action( 'plugins_loaded', 'hupuna_els_init' );

/**
 * Load plugin text domain.
 *
 * @return void
 */
function hupuna_els_load_textdomain() {
	load_plugin_textdomain(
		'hupuna-external-link-scanner',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'hupuna_els_load_textdomain' );
