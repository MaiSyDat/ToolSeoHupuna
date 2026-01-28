<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ToolSeoHupuna
 * @since 2.1.1
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clear plugin transients.
delete_transient( 'hupuna_site_domain' );
delete_transient( 'tool_seo_hupuna_site_domain' );
delete_transient( 'tool_seo_hupuna_post_types' );
delete_transient( 'tool_seo_hupuna_public_post_types' );

