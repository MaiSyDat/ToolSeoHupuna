<?php
/**
 * Main Scanner Class
 * Handles logic for extracting, normalizing, and verifying external links.
 *
 * @package ToolSeoHupuna
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
	 * Handles localhost and subdirectory installations.
	 * Uses caching to avoid repeated calculations.
	 *
	 * @return string Site domain.
	 */
	private function get_site_domain() {
		// Use transient cache for site domain (rarely changes).
		$cached_domain = get_transient( 'tool_seo_hupuna_site_domain' );
		
		if ( false !== $cached_domain ) {
			return $cached_domain;
		}

		$url    = home_url();
		$parsed = wp_parse_url( $url );
		$host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
		
		// Handle localhost with port or subdirectory.
		if ( empty( $host ) || 'localhost' === $host ) {
			// For localhost, extract from full URL.
			$host = parse_url( $url, PHP_URL_HOST );
			if ( empty( $host ) ) {
				// Fallback: try to extract from URL string.
				if ( preg_match( '#https?://([^/]+)#', $url, $matches ) ) {
					$host = $matches[1];
				}
			}
		}
		
		// Cache for 24 hours (site domain rarely changes).
		set_transient( 'tool_seo_hupuna_site_domain', $host, DAY_IN_SECONDS );
		
		return $host;
	}

	/**
	 * Check if URL is external and not whitelisted.
	 * Improved to handle localhost and subdirectory installations correctly.
	 *
	 * @param string $url URL to check.
	 * @return bool True if external, false otherwise.
	 */
	public function is_external_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		// Normalize URL first to handle relative URLs.
		$url = $this->normalize_url( $url );
		if ( ! $url ) {
			return false;
		}

		// Parse the URL properly.
		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['host'] ) ) {
			return false;
		}

		$url_host = strtolower( trim( $parsed['host'] ) );
		$url_host = preg_replace( '#^www\.#', '', $url_host );
		
		// Get site domain and normalize.
		$site_host = strtolower( trim( $this->site_domain ) );
		$site_host = preg_replace( '#^www\.#', '', $site_host );
		
		// Handle localhost cases - check if both are localhost.
		if ( 'localhost' === $site_host || strpos( $site_host, 'localhost' ) !== false ) {
			// For localhost, check if URL is also localhost.
			if ( 'localhost' === $url_host || strpos( $url_host, 'localhost' ) !== false ) {
				// Both are localhost, check if same path/port.
				$site_parsed = wp_parse_url( home_url() );
				$site_path = isset( $site_parsed['path'] ) ? $site_parsed['path'] : '';
				$url_path = isset( $parsed['path'] ) ? $parsed['path'] : '';
				
				// If URL path starts with site path, it's internal.
				if ( ! empty( $site_path ) && 0 === strpos( $url_path, rtrim( $site_path, '/' ) ) ) {
					return false;
				}
				
				// Different localhost paths = external.
				// Compare full host including port if present.
				$site_full = $site_host . ( isset( $site_parsed['port'] ) ? ':' . $site_parsed['port'] : '' );
				$url_full = $url_host . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
				
				return $url_full !== $site_full;
			}
		}

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
		 * @since 2.1.1
		 * @param array $whitelist Array of domain strings to whitelist.
		 */
		$whitelist = apply_filters( 'tool_seo_hupuna_whitelist', $whitelist );

		foreach ( $whitelist as $white ) {
			if ( false !== strpos( $url_host, strtolower( $white ) ) ) {
				return false;
			}
		}

		// Compare domains (case-insensitive).
		return $url_host !== $site_host;
	}

	/**
	 * Extract links from content string using Regex.
	 * Includes safety audit for bad keywords.
	 * Detects links in anchor tags, image tags, iframe tags, and plain text URLs.
	 *
	 * @param string $content Content to scan.
	 * @return array Array of found links with safety data.
	 */
	private function extract_links( $content ) {
		if ( empty( $content ) || ( false === strpos( $content, 'http' ) && false === strpos( $content, '<' ) ) ) {
			return array();
		}

		// PERFORMANCE: Limit content length to prevent regex timeout on extremely long posts.
		// Allow filtering for custom limits.
		$max_length = apply_filters( 'tool_seo_hupuna_scan_content_limit', 50000 );
		
		if ( strlen( $content ) > $max_length ) {
			// Truncate content for scanning and log a warning.
			$content = substr( $content, 0, $max_length );
			
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Tool SEO Hupuna: Content truncated to %d characters for link scanning to prevent timeout.', $max_length ) );
		}

		$links = array();
		$found_urls = array(); // Track URLs to avoid duplicates.

		// Pattern 1: Anchor tags (improved regex to catch all variations including blockquote).
		// Match <a> tags with href attribute, handling any attribute order.
		preg_match_all( '/<a[^>]+href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$url = $this->normalize_url( $match[1] );
			if ( $url && $this->is_external_url( $url ) && ! in_array( $url, $found_urls, true ) ) {
				$link_text = wp_strip_all_tags( $match[2] );
				$safety = $this->analyze_link_safety( $url, $link_text );
				
				$links[] = array(
					'url'       => $url,
					'text'      => $link_text,
					'tag'       => 'a',
					'attribute' => 'href',
					'is_safe'   => $safety['is_safe'],
					'risk_type' => $safety['risk_type'],
				);
				$found_urls[] = $url;
			}
		}

		// Pattern 2: Image tags.
		preg_match_all( '/<img[^>]*\s+src=["\']([^"\']+)["\'][^>]*>/is', $content, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$url = $this->normalize_url( $match[1] );
			if ( $url && $this->is_external_url( $url ) && ! in_array( $url, $found_urls, true ) ) {
				$safety = $this->analyze_link_safety( $url, '' );
				
				$links[] = array(
					'url'       => $url,
					'text'      => '',
					'tag'       => 'img',
					'attribute' => 'src',
					'is_safe'   => $safety['is_safe'],
					'risk_type' => $safety['risk_type'],
				);
				$found_urls[] = $url;
			}
		}

		// Pattern 3: Iframe tags (for embedded content).
		preg_match_all( '/<iframe[^>]*\s+src=["\']([^"\']+)["\'][^>]*>/is', $content, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$url = $this->normalize_url( $match[1] );
			if ( $url && $this->is_external_url( $url ) && ! in_array( $url, $found_urls, true ) ) {
				$safety = $this->analyze_link_safety( $url, '' );
				
				$links[] = array(
					'url'       => $url,
					'text'      => '',
					'tag'       => 'iframe',
					'attribute' => 'src',
					'is_safe'   => $safety['is_safe'],
					'risk_type' => $safety['risk_type'],
				);
				$found_urls[] = $url;
			}
		}

		// Pattern 4: Plain text URLs (not wrapped in HTML tags) - CRITICAL FIX.
		// Extract plain text content (without HTML tags) to find standalone URLs.
		// This ensures we catch URLs like "https://hupunsaa.com/" that are not in <a> tags.
		$text_only = wp_strip_all_tags( $content );
		// Find all URLs in plain text (not already captured in HTML attributes).
		preg_match_all( '/(https?:\/\/[^\s<>"\'\]\)]+)/i', $text_only, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			// Clean URL: remove trailing punctuation that might be part of sentence.
			$url = trim( $match[1], '.,;:!?)' );
			$url = $this->normalize_url( $url );
			if ( $url && $this->is_external_url( $url ) && ! in_array( $url, $found_urls, true ) ) {
				$safety = $this->analyze_link_safety( $url, '' );
				
				$links[] = array(
					'url'       => $url,
					'text'      => '',
					'tag'       => 'text',
					'attribute' => 'plain',
					'is_safe'   => $safety['is_safe'],
					'risk_type' => $safety['risk_type'],
				);
				$found_urls[] = $url;
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

		// Optimized query args for performance.
		$args = array(
			'post_type'              => $post_type,
			'post_status'            => 'any',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'fields'                 => 'ids', // Only get IDs, not full post objects.
			'no_found_rows'          => true, // Skip SQL_CALC_FOUND_ROWS for performance.
			'update_post_term_cache' => false, // Don't cache terms.
			'update_post_meta_cache' => false, // Don't cache meta (we'll fetch what we need).
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return array(
				'results' => array(),
				'done'    => true,
			);
		}

		// Batch fetch post data to reduce database queries.
		foreach ( $posts as $post_id ) {
			// Use get_post with specific fields to reduce memory usage.
			$post = get_post( $post_id, ARRAY_A );
			if ( ! $post || empty( $post['post_content'] ) ) {
				continue;
			}

			// Scan Content.
			$links = $this->extract_links( $post['post_content'] );
			foreach ( $links as $link ) {
				$results[] = $this->format_result( $link, $post_type, $post_id, $post['post_title'], __( 'Content', 'tool-seo-hupuna' ) );
			}

			// Scan Excerpt.
			if ( ! empty( $post['post_excerpt'] ) ) {
				$links = $this->extract_links( $post['post_excerpt'] );
				foreach ( $links as $link ) {
					$results[] = $this->format_result( $link, $post_type, $post_id, $post['post_title'], __( 'Excerpt', 'tool-seo-hupuna' ) );
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
					'title'      => sprintf( __( 'Comment #%d', 'tool-seo-hupuna' ), $comment->comment_ID ),
					'url'        => $link['url'],
					'link_text'  => $link['text'],
					'tag'        => $link['tag'],
					'attribute'  => $link['attribute'],
					'location'   => __( 'Content', 'tool-seo-hupuna' ),
					'edit_url'   => get_edit_comment_link( $comment->comment_ID ),
					'view_url'   => get_comment_link( $comment->comment_ID ),
					'is_safe'    => isset( $link['is_safe'] ) ? $link['is_safe'] : true,
					'risk_type'  => isset( $link['risk_type'] ) ? $link['risk_type'] : '',
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
						'title'     => sprintf( __( 'Option: %s', 'tool-seo-hupuna' ), $option->option_name ),
						'url'       => $link['url'],
						'link_text' => $link['text'],
						'tag'       => $link['tag'],
						'attribute' => $link['attribute'],
						'location'  => __( 'Value', 'tool-seo-hupuna' ),
						'edit_url'  => admin_url( 'options.php' ),
						'view_url'  => home_url(),
						'is_safe'    => isset( $link['is_safe'] ) ? $link['is_safe'] : true,
						'risk_type'  => isset( $link['risk_type'] ) ? $link['risk_type'] : '',
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
	 * Includes safety audit data.
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
			'is_safe'   => isset( $link['is_safe'] ) ? $link['is_safe'] : true,
			'risk_type' => isset( $link['risk_type'] ) ? $link['risk_type'] : '',
		);
	}

	/**
	 * Get blacklisted keywords for safety audit.
	 * Categories: Gambling, Adult, Spam, Malware, Pharmaceuticals.
	 *
	 * @since 2.1.1
	 * @return array Array of blacklisted keywords with their risk categories.
	 */
	private function get_bad_keywords() {
		$blacklist = array(
			// Gambling/Spam keywords.
			'casino'     => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			'bet'        => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			'poker'      => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			'gambling'   => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			'lottery'    => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			'slot'       => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			'jackpot'    => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			'pokerstars' => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			'bet365'     => __( 'Potential Gambling/Spam', 'tool-seo-hupuna' ),
			
			// Adult content keywords.
			'adult'      => __( 'Adult Content', 'tool-seo-hupuna' ),
			'porn'       => __( 'Adult Content', 'tool-seo-hupuna' ),
			'xxx'        => __( 'Adult Content', 'tool-seo-hupuna' ),
			'sex'        => __( 'Adult Content', 'tool-seo-hupuna' ),
			'escort'     => __( 'Adult Content', 'tool-seo-hupuna' ),
			'camgirl'    => __( 'Adult Content', 'tool-seo-hupuna' ),
			
			// Spam/Pharmaceutical keywords.
			'cialis'     => __( 'Spam/Pharmaceutical', 'tool-seo-hupuna' ),
			'viagra'     => __( 'Spam/Pharmaceutical', 'tool-seo-hupuna' ),
			'pharmacy'   => __( 'Spam/Pharmaceutical', 'tool-seo-hupuna' ),
			'prescription' => __( 'Spam/Pharmaceutical', 'tool-seo-hupuna' ),
			'buy-now'    => __( 'Suspicious Pattern', 'tool-seo-hupuna' ),
			'cheap'      => __( 'Suspicious Pattern', 'tool-seo-hupuna' ),
			'discount'   => __( 'Suspicious Pattern', 'tool-seo-hupuna' ),
			
			// Crypto/Scam keywords.
			'crypto'     => __( 'Cryptocurrency/Scam', 'tool-seo-hupuna' ),
			'bitcoin'    => __( 'Cryptocurrency/Scam', 'tool-seo-hupuna' ),
			'investment' => __( 'Cryptocurrency/Scam', 'tool-seo-hupuna' ),
			'forex'      => __( 'Cryptocurrency/Scam', 'tool-seo-hupuna' ),
			'binary'     => __( 'Cryptocurrency/Scam', 'tool-seo-hupuna' ),
			
			// Suspicious TLDs/Patterns.
			'.xyz'       => __( 'Suspicious Pattern', 'tool-seo-hupuna' ),
			'.top'       => __( 'Suspicious Pattern', 'tool-seo-hupuna' ),
			'.click'     => __( 'Suspicious Pattern', 'tool-seo-hupuna' ),
			'.download'  => __( 'Suspicious Pattern', 'tool-seo-hupuna' ),
			'.stream'    => __( 'Suspicious Pattern', 'tool-seo-hupuna' ),
			
			// Malware/Hacker keywords.
			'hack'       => __( 'Malware/Hacker', 'tool-seo-hupuna' ),
			'crack'      => __( 'Malware/Hacker', 'tool-seo-hupuna' ),
			'keygen'     => __( 'Malware/Hacker', 'tool-seo-hupuna' ),
			'warez'      => __( 'Malware/Hacker', 'tool-seo-hupuna' ),
			'torrent'   => __( 'Malware/Hacker', 'tool-seo-hupuna' ),
			'injection' => __( 'Malware/Hacker', 'tool-seo-hupuna' ),
		);

		/**
		 * Filter the blacklist of keywords for safety audit.
		 * 
		 * @since 2.1.1
		 * @param array $blacklist Array of keywords as keys and risk types as values.
		 */
		$blacklist = apply_filters( 'tool_seo_hupuna_blacklist_keywords', $blacklist );

		return $blacklist;
	}

	/**
	 * Analyze link safety by checking URL and anchor text against blacklist.
	 * Uses case-insensitive string matching (no HTTP requests).
	 *
	 * @since 2.1.1
	 * @param string $url  URL to analyze.
	 * @param string $text Anchor text to analyze.
	 * @return array Array with 'is_safe' (bool) and 'risk_type' (string).
	 */
	private function analyze_link_safety( $url, $text ) {
		$blacklist = $this->get_bad_keywords();
		$url_lower  = strtolower( $url );
		$text_lower = strtolower( $text );

		// Check both URL and anchor text for blacklisted keywords.
		foreach ( $blacklist as $keyword => $risk_type ) {
			$keyword_lower = strtolower( $keyword );
			
			// Check if keyword appears in URL or anchor text.
			if ( false !== strpos( $url_lower, $keyword_lower ) || 
				( ! empty( $text_lower ) && false !== strpos( $text_lower, $keyword_lower ) ) ) {
				return array(
					'is_safe'   => false,
					'risk_type' => $risk_type,
				);
			}
		}

		// Link is safe if no blacklisted keywords found.
		return array(
			'is_safe'   => true,
			'risk_type' => '',
		);
	}
}
