<?php
/**
 * Plugin Name: Tool SEO Hupuna
 * Plugin URI: https://hupuna.com
 * Description: Comprehensive SEO tools for WordPress, including external link scanner, posts manager, WooCommerce product manager, and keyword search.
 * Version: 2.3.0
 * Author: MaiSyDat
 * Author URI: https://github.com/MaiSyDat
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
define( 'TOOL_SEO_HUPUNA_VERSION', '2.3.0' );

/**
 * Plugin directory path.
 */
define( 'TOOL_SEO_HUPUNA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'TOOL_SEO_HUPUNA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Cache group for plugin transients.
 */
define( 'TOOL_SEO_HUPUNA_CACHE_GROUP', 'tool_seo_hupuna' );

/**
 * Cache expiration time (1 hour).
 */
define( 'TOOL_SEO_HUPUNA_CACHE_EXPIRATION', HOUR_IN_SECONDS );

// Load core classes.
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-helper.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-scanner.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-posts-manager.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-products-manager.php';
require_once TOOL_SEO_HUPUNA_PLUGIN_DIR . 'includes/class-hupuna-keyword-search.php';

/**
 * Initialize the plugin.
 * Uses singleton pattern for better performance and memory management.
 *
 * @return void
 */
function tool_seo_hupuna_init() {
	// Check minimum requirements.
	if ( ! tool_seo_hupuna_check_requirements() ) {
		return;
	}

	// Initialize features.
	new Hupuna_External_Link_Scanner();

	// Initialize feature managers (lazy loading - only when needed).
	// Posts Manager.
	new Hupuna_Posts_Manager();

	// Products Manager (only if WooCommerce is active).
	if ( class_exists( 'WooCommerce' ) ) {
		new Hupuna_Products_Manager();
	}

	// Keyword Search Manager.
	new Hupuna_Keyword_Search();

	/**
	 * Fires after plugin initialization.
	 * Allows other plugins to hook into plugin initialization.
	 *
	 * @since 2.1.1
	 */
	do_action( 'tool_seo_hupuna_init' );
}
add_action( 'plugins_loaded', 'tool_seo_hupuna_init', 10 );

/**
 * Check plugin requirements.
 *
 * @return bool True if requirements are met, false otherwise.
 */
function tool_seo_hupuna_check_requirements() {
	// Check PHP version.
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		add_action( 'admin_notices', 'tool_seo_hupuna_php_version_notice' );
		return false;
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
		add_action( 'admin_notices', 'tool_seo_hupuna_wp_version_notice' );
		return false;
	}

	return true;
}

/**
 * Display PHP version notice.
 *
 * @return void
 */
function tool_seo_hupuna_php_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: PHP version */
				esc_html__( 'Tool SEO Hupuna requires PHP version 7.4 or higher. You are running PHP %s.', 'tool-seo-hupuna' ),
				esc_html( PHP_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display WordPress version notice.
 *
 * @return void
 */
function tool_seo_hupuna_wp_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: WordPress version */
				esc_html__( 'Tool SEO Hupuna requires WordPress version 5.8 or higher. You are running WordPress %s.', 'tool-seo-hupuna' ),
				esc_html( get_bloginfo( 'version' ) )
			);
			?>
		</p>
	</div>
	<?php
}

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
	// Flush rewrite rules.
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
	
	// Clear plugin transients on deactivation.
	tool_seo_hupuna_clear_cache();
}
register_deactivation_hook( __FILE__, 'tool_seo_hupuna_deactivate' );

/**
 * Clear plugin cache/transients.
 * Useful for debugging or when data needs to be refreshed.
 *
 * @return void
 */
function tool_seo_hupuna_clear_cache() {
	// Clear site domain cache.
	delete_transient( 'hupuna_site_domain' );
	delete_transient( 'tool_seo_hupuna_site_domain' );
	
	// Clear post types cache.
	delete_transient( 'tool_seo_hupuna_post_types' );
	delete_transient( 'tool_seo_hupuna_public_post_types' );
	
	/**
	 * Fires after plugin cache is cleared.
	 * Allows other code to clear additional caches.
	 *
	 * @since 2.1.1
	 */
	do_action( 'tool_seo_hupuna_cache_cleared' );
}
