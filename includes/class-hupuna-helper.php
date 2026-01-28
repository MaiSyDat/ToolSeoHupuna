<?php
/**
 * Helper Class
 * Provides common utility methods for the plugin.
 *
 * @package ToolSeoHupuna
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hupuna Helper Class.
 */
class Hupuna_Helper {

	/**
	 * Get current site domain for comparison.
	 *
	 * @return string Site domain.
	 */
	public static function get_site_domain() {
		$domain = get_transient( 'hupuna_site_domain' );
		if ( false === $domain ) {
			$url    = home_url();
			$parsed = wp_parse_url( $url );
			$domain = isset( $parsed['host'] ) ? $parsed['host'] : '';
			set_transient( 'hupuna_site_domain', $domain, DAY_IN_SECONDS );
		}
		return $domain;
	}

	/**
	 * Check if a URL is external.
	 *
	 * @param string $url URL to check.
	 * @return bool True if external, false otherwise.
	 */
	public static function is_external_url( $url ) {
		if ( empty( $url ) || strpos( $url, '#' ) === 0 || strpos( $url, 'mailto:' ) === 0 || strpos( $url, 'tel:' ) === 0 || strpos( $url, 'javascript:' ) === 0 ) {
			return false;
		}

		$site_domain = self::get_site_domain();
		$parsed_url  = wp_parse_url( $url );

		// If no host, it's relative (internal).
		if ( ! isset( $parsed_url['host'] ) ) {
			return false;
		}

		$host = $parsed_url['host'];

		// Check if host matches site domain or is a subdomain.
		if ( $host === $site_domain || strpos( $host, '.' . $site_domain ) !== false ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize URL for comparison.
	 *
	 * @param string $url URL to normalize.
	 * @return string Normalized URL.
	 */
	public static function normalize_url( $url ) {
		$url = trim( $url );
		if ( empty( $url ) ) {
			return '';
		}

		// Remove fragment and query strings for safer comparison if needed, 
		// but usually we just want a clean string.
		$url = strtok( $url, '#' );
		
		return $url;
	}
}
