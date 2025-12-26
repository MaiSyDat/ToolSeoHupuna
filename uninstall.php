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

// Clear scheduled cron events.
$timestamp = wp_next_scheduled( 'tool_seo_hupuna_check_sitemap' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'tool_seo_hupuna_check_sitemap' );
}

// Note: We don't delete options or data to preserve user settings
// If you want to delete all data on uninstall, uncomment below:
// delete_option( 'tool_seo_hupuna_email_address' );
// delete_option( 'tool_seo_hupuna_sitemap_url' );

