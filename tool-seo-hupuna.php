<?php
/**
 * Plugin Name: Tool SEO Hupuna
 * Plugin URI: https://hupuna.com
 * Description: Comprehensive SEO tools including external link scanner, posts with links manager, and WooCommerce product price manager.
 * Version: 2.1.1
 * Author: MaiSyDat
 * Author URI: https://hupuna.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tool-seo-hupuna
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package ToolSeoHupuna
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current plugin version.
 */
define( 'TOOL_SEO_HUPUNA_VERSION', '2.1.1' );

/**
 * Plugin directory path.
 */
define( 'TOOL_SEO_HUPUNA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'TOOL_SEO_HUPUNA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load core classes.
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-scanner.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-admin.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-posts-manager.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-products-manager.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-robots-manager.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-images-manager.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-llms-manager.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function tool_seo_hupuna_init() {
	$admin = new Hupuna_External_Link_Scanner_Admin();
	$admin->init();

	// Initialize Posts Manager.
	new Hupuna_Posts_Manager();

	// Initialize Products Manager.
	new Hupuna_Products_Manager();

	// Initialize Robots Manager.
	new Hupuna_Robots_Manager();

	// Initialize Images Manager.
	new Hupuna_Images_Manager();

	// Initialize LLMs Manager.
	new Hupuna_Llms_Manager();
}
add_action( 'plugins_loaded', 'tool_seo_hupuna_init' );

/**
 * Load plugin text domain.
 *
 * @return void
 */
function tool_seo_hupuna_load_textdomain() {
	load_plugin_textdomain(
		'tool-seo-hupuna',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'tool_seo_hupuna_load_textdomain' );

/**
 * Flush rewrite rules on plugin activation.
 *
 * @return void
 */
function tool_seo_hupuna_activate() {
	// Flush rewrite rules to register llms.txt route.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'tool_seo_hupuna_activate' );

/**
 * Flush rewrite rules on plugin deactivation.
 *
 * @return void
 */
function tool_seo_hupuna_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tool_seo_hupuna_deactivate' );
