<?php
/**
 * Main Scanner Class
 * Handles logic for extracting, normalizing, and verifying external links.
 *
 * @package HupunaExternalLinkScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scanner class for extracting and processing external links.
 */
class Hupuna_External_Link_Scanner {

	/**
	 * Site domain.
	 *
	 * @var string
	 */
	private $site_domain;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->site_domain = $this->get_site_domain();
	}

	/**
	 * Get current site domain.
	 *
	 * @return string Site domain.
	 */
	private function get_site_domain() {
		$url    = home_url();
		$parsed = wp_parse_url( $url );
		return isset( $parsed['host'] ) ? $parsed['host'] : '';
	}

	/**
	 * Check if URL is external and not whitelisted.
	 *
	 * @param string $url URL to check.
	 * @return bool True if external, false otherwise.
	 */
	public function is_external_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		// Remove protocol and www.
		$url_clean = preg_replace( '#^https?://#', '', $url );
		$url_clean = preg_replace( '#^www\.#', '', $url_clean );

		$parsed = wp_parse_url( 'http://' . $url_clean );
		if ( ! isset( $parsed['host'] ) ) {
			return false;
		}

		$url_domain  = preg_replace( '#^www\.#', '', $parsed['host'] );
		$site_domain = preg_replace( '#^www\.#', '', $this->site_domain );

		// Whitelist system domains - allow filtering.
		$whitelist = array(
			'wordpress.org',
			'woocommerce.com',
			'gravatar.com',
			'wp.com',
			's0.wp.com',
			's1.wp.com',
			's2.wp.com',
			'secure.gravatar.com',
			'w.org',
		);

		/**
		 * Filter the whitelist of domains to exclude from external link scanning.
		 *
		 * @param array $whitelist Array of domain strings to whitelist.
		 */
		$whitelist = apply_filters( 'hupuna_els_whitelist', $whitelist );

		foreach ( $whitelist as $white ) {
			if ( false !== strpos( $url_domain, $white ) ) {
				return false;
			}
		}

		return strtolower( $url_domain ) !== strtolower( $site_domain );
	}

	/**
	 * Extract links from content string using Regex.
	 *
	 * @param string $content Content to scan.
	 * @return array Array of found links.
	 */
	private function extract_links( $content ) {
		if ( empty( $content ) || ( false === strpos( $content, 'http' ) && false === strpos( $content, '<' ) ) ) {
			return array();
		}

		$links = array();

		// Pattern 1: Anchor tags.
		preg_match_all( '/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$url = $this->normalize_url( $match[1] );
			if ( $url && $this->is_external_url( $url ) ) {
				$links[] = array(
					'url'       => $url,
					'text'      => wp_strip_all_tags( $match[2] ),
					'tag'       => 'a',
					'attribute' => 'href',
				);
			}
		}

		// Pattern 2: Image tags.
		preg_match_all( '/<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/is', $content, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$url = $this->normalize_url( $match[1] );
			if ( $url && $this->is_external_url( $url ) ) {
				$links[] = array(
					'url'       => $url,
					'text'      => '',
					'tag'       => 'img',
					'attribute' => 'src',
				);
			}
		}

		return $links;
	}

	/**
	 * Normalize URL (handle relative paths).
	 *
	 * @param string $url URL to normalize.
	 * @return string|false Normalized URL or false on failure.
	 */
	private function normalize_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		// Skip non-http protocols.
		if ( 0 === strpos( $url, '#' ) || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) || 0 === strpos( $url, 'javascript:' ) || 0 === strpos( $url, 'data:' ) ) {
			return false;
		}

		// Handle relative URLs.
		if ( 0 !== strpos( $url, 'http' ) && 0 !== strpos( $url, '//' ) ) {
			if ( 0 === strpos( $url, '/' ) ) {
				$url = home_url( $url );
			}
		} elseif ( 0 === strpos( $url, '//' ) ) {
			$url = 'http:' . $url;
		}

		return $url;
	}

	/**
	 * Get list of public post types to scan.
	 *
	 * @return array Array of post type names.
	 */
	public function get_scannable_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$excluded    = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );
		return array_diff( $post_types, $excluded );
	}

	/**
	 * Batch Scan: Post Types.
	 *
	 * @param string $post_type Post type to scan.
	 * @param int    $page      Page number.
	 * @param int    $per_page  Items per page.
	 * @return array Scan results.
	 */
	public function scan_post_type_batch( $post_type, $page = 1, $per_page = 20 ) {
		$results = array();

		$args = array(
			'post_type'              => $post_type,
			'post_status'            => 'any',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return array(
				'results' => array(),
				'done'    => true,
			);
		}

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			// Scan Content.
			$links = $this->extract_links( $post->post_content );
			foreach ( $links as $link ) {
				$results[] = $this->format_result( $link, $post_type, $post_id, $post->post_title, __( 'Content', 'hupuna-external-link-scanner' ) );
			}

			// Scan Excerpt.
			if ( ! empty( $post->post_excerpt ) ) {
				$links = $this->extract_links( $post->post_excerpt );
				foreach ( $links as $link ) {
					$results[] = $this->format_result( $link, $post_type, $post_id, $post->post_title, __( 'Excerpt', 'hupuna-external-link-scanner' ) );
				}
			}
		}

		return array(
			'results' => $results,
			'done'    => count( $posts ) < $per_page,
		);
	}

	/**
	 * Batch Scan: Comments.
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Items per page.
	 * @return array Scan results.
	 */
	public function scan_comments_batch( $page = 1, $per_page = 50 ) {
		$results = array();
		$offset  = ( $page - 1 ) * $per_page;

		$comments = get_comments(
			array(
				'number' => $per_page,
				'offset' => $offset,
				'status' => 'all',
			)
		);

		if ( empty( $comments ) ) {
			return array(
				'results' => array(),
				'done'    => true,
			);
		}

		foreach ( $comments as $comment ) {
			$links = $this->extract_links( $comment->comment_content );
			foreach ( $links as $link ) {
				$results[] = array(
					'type'       => 'comment',
					'id'         => $comment->comment_ID,
					'title'      => sprintf( __( 'Comment #%d', 'hupuna-external-link-scanner' ), $comment->comment_ID ),
					'url'        => $link['url'],
					'link_text'  => $link['text'],
					'tag'        => $link['tag'],
					'attribute'  => $link['attribute'],
					'location'   => __( 'Content', 'hupuna-external-link-scanner' ),
					'edit_url'   => get_edit_comment_link( $comment->comment_ID ),
					'view_url'   => get_comment_link( $comment->comment_ID ),
				);
			}
		}

		return array(
			'results' => $results,
			'done'    => count( $comments ) < $per_page,
		);
	}

	/**
	 * Batch Scan: Options (Optimized SQL).
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Items per page.
	 * @return array Scan results.
	 */
	public function scan_options_batch( $page = 1, $per_page = 100 ) {
		global $wpdb;
		$offset  = ( $page - 1 ) * $per_page;
		$results = array();

		// Direct SQL to filter out junk options - using prepared statements for safety.
		// Note: Using esc_like for LIKE patterns and proper escaping.
		$transient_pattern = '%' . $wpdb->esc_like( 'transient' ) . '%';
		$cron_pattern      = $wpdb->esc_like( 'cron' );
		$ptk_pattern       = $wpdb->esc_like( 'ptk_patterns' );
		$woo_pattern        = $wpdb->esc_like( 'woocommerce_' ) . '%';
		$http_pattern       = '%' . $wpdb->esc_like( 'http' ) . '%';
		$html_pattern       = '%' . $wpdb->esc_like( '<' ) . '%';

		$query = $wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} 
			WHERE option_name NOT LIKE %s 
			AND option_name NOT LIKE %s
			AND option_name NOT LIKE %s
			AND option_name NOT LIKE %s
			AND (option_value LIKE %s OR option_value LIKE %s)
			LIMIT %d OFFSET %d",
			$transient_pattern,
			$cron_pattern,
			$ptk_pattern,
			$woo_pattern,
			$http_pattern,
			$html_pattern,
			$per_page,
			$offset
		);

		$options = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $options ) ) {
			return array(
				'results' => array(),
				'done'    => true,
			);
		}

		foreach ( $options as $option ) {
			if ( is_string( $option->option_value ) ) {
				$links = $this->extract_links( $option->option_value );
				foreach ( $links as $link ) {
					$results[] = array(
						'type'      => 'option',
						'id'        => $option->option_name,
						'title'     => sprintf( __( 'Option: %s', 'hupuna-external-link-scanner' ), $option->option_name ),
						'url'       => $link['url'],
						'link_text' => $link['text'],
						'tag'       => $link['tag'],
						'attribute' => $link['attribute'],
						'location'  => __( 'Value', 'hupuna-external-link-scanner' ),
						'edit_url'  => admin_url( 'options.php' ),
						'view_url'  => home_url(),
					);
				}
			}
		}

		return array(
			'results' => $results,
			'done'    => count( $options ) < $per_page,
		);
	}

	/**
	 * Format result helper.
	 *
	 * @param array  $link     Link data.
	 * @param string $type     Result type.
	 * @param int    $id       Item ID.
	 * @param string $title    Item title.
	 * @param string $location Location within item.
	 * @return array Formatted result array.
	 */
	private function format_result( $link, $type, $id, $title, $location ) {
		return array(
			'type'      => $type,
			'id'        => $id,
			'title'     => $title,
			'url'       => $link['url'],
			'link_text' => isset( $link['text'] ) ? $link['text'] : '',
			'tag'       => isset( $link['tag'] ) ? $link['tag'] : 'a',
			'attribute' => isset( $link['attribute'] ) ? $link['attribute'] : 'href',
			'location'  => $location,
			'edit_url'  => get_edit_post_link( $id, 'raw' ),
			'view_url'  => get_permalink( $id ),
		);
	}
}
