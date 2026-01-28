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
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_tool_seo_hupuna_scan_batch', array( $this, 'ajax_scan_batch' ) );
	}

	/**
	 * Add Admin Menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Tool SEO Hupuna', 'tool-seo-hupuna' ),
			__( 'Tool SEO', 'tool-seo-hupuna' ),
			'manage_options',
			'tool-seo-hupuna',
			array( $this, 'render_admin_page' ),
			'dashicons-admin-tools',
			30
		);
		
		add_submenu_page(
			'tool-seo-hupuna',
			__( 'External Link Scanner', 'tool-seo-hupuna' ),
			__( 'External Links', 'tool-seo-hupuna' ),
			'manage_options',
			'tool-seo-hupuna',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue Admin Assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_tool-seo-hupuna' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tool-seo-hupuna-admin',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TOOL_SEO_HUPUNA_VERSION
		);

		wp_enqueue_script(
			'tool-seo-hupuna-admin',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			TOOL_SEO_HUPUNA_VERSION,
			true
		);

		$post_types = get_transient( 'tool_seo_hupuna_post_types' );
		if ( false === $post_types ) {
			$post_types = $this->get_scannable_post_types();
			set_transient( 'tool_seo_hupuna_post_types', $post_types, TOOL_SEO_HUPUNA_CACHE_EXPIRATION );
		}

		wp_localize_script(
			'tool-seo-hupuna-admin',
			'toolSeoHupuna',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'tool_seo_hupuna_scan_links_nonce' ),
				'postTypes' => $post_types,
				'strings'   => array(
					'scanning'        => __( 'Scanning...', 'tool-seo-hupuna' ),
					'scanCompleted'   => __( 'Scan Completed!', 'tool-seo-hupuna' ),
					'startScan'       => __( 'Start Scan', 'tool-seo-hupuna' ),
					'initializing'    => __( 'Initializing...', 'tool-seo-hupuna' ),
					'errorEncountered' => __( 'Error encountered.', 'tool-seo-hupuna' ),
					'scanningPostType' => __( 'Scanning Post Type: %s', 'tool-seo-hupuna' ),
					'scanningComments' => __( 'Scanning Comments...', 'tool-seo-hupuna' ),
					'scanningOptions'  => __( 'Scanning Options...', 'tool-seo-hupuna' ),
					'page'             => __( 'Page', 'tool-seo-hupuna' ),
					'error'            => __( 'Error', 'tool-seo-hupuna' ),
					'serverError'      => __( 'Server connection failed: %s', 'tool-seo-hupuna' ),
					'accessDenied'     => __( 'Access denied', 'tool-seo-hupuna' ),
					'noLinksFound'    => __( 'No external links found. Great job!', 'tool-seo-hupuna' ),
					'totalLinksFound' => __( 'Total Links Found:', 'tool-seo-hupuna' ),
					'uniqueUrls'      => __( 'Unique URLs:', 'tool-seo-hupuna' ),
					'groupedByUrl'    => __( 'Grouped by URL', 'tool-seo-hupuna' ),
					'allOccurrences'  => __( 'All Occurrences', 'tool-seo-hupuna' ),
					'currentDomain'   => __( 'Current Domain:', 'tool-seo-hupuna' ),
					'description'     => __( 'Scans posts, pages, comments, and options for external links. System domains (WordPress, WooCommerce, Gravatar) and patterns are automatically ignored.', 'tool-seo-hupuna' ),
					'location'        => __( 'Location:', 'tool-seo-hupuna' ),
					'tag'             => __( 'Tag:', 'tool-seo-hupuna' ),
					'edit'            => __( 'Edit', 'tool-seo-hupuna' ),
					'view'            => __( 'View', 'tool-seo-hupuna' ),
					'prev'            => __( '&laquo; Prev', 'tool-seo-hupuna' ),
					'next'            => __( 'Next &raquo;', 'tool-seo-hupuna' ),
					'of'              => __( 'of', 'tool-seo-hupuna' ),
					'occurrence'      => __( 'occurrence', 'tool-seo-hupuna' ),
					'occurrences'     => __( 'occurrences', 'tool-seo-hupuna' ),
					'unsafe'          => __( 'Unsafe', 'tool-seo-hupuna' ),
					'type'            => __( 'Type', 'tool-seo-hupuna' ),
					'title'           => __( 'Title', 'tool-seo-hupuna' ),
					'locationHeader'  => __( 'Location', 'tool-seo-hupuna' ),
					'tag'             => __( 'Tag', 'tool-seo-hupuna' ),
					'actions'         => __( 'Actions', 'tool-seo-hupuna' ),
					'comment'         => __( 'Comment', 'tool-seo-hupuna' ),
					'option'          => __( 'Option', 'tool-seo-hupuna' ),
				),
			)
		);

		// Add dynamic post type labels for localization.
		$pt_labels = array();
		foreach ( $post_types as $pt ) {
			$pt_obj = get_post_type_object( $pt );
			if ( $pt_obj ) {
				$pt_labels[ $pt ] = $pt_obj->labels->singular_name;
			}
		}

		wp_localize_script(
			'tool-seo-hupuna-admin',
			'toolSeoHupunaPostTypes',
			$pt_labels
		);
	}

	/**
	 * AJAX Handler: Batch Scan.
	 *
	 * @return void
	 */
	public function ajax_scan_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		check_ajax_referer( 'tool_seo_hupuna_scan_links_nonce', 'nonce' );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'memory_limit', '256M' );
		}

		$step     = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
		$sub_step = isset( $_POST['sub_step'] ) ? sanitize_text_field( wp_unslash( $_POST['sub_step'] ) ) : '';

		$response = array( 'results' => array(), 'done' => false );

		try {
			switch ( $step ) {
				case 'post_type':
					if ( ! empty( $sub_step ) ) {
						$scan_data = $this->scan_post_type_batch( $sub_step, $page, 20 );
						$response['results'] = $scan_data['results'];
						$response['done']    = $scan_data['done'];
					} else {
						$response['done'] = true;
					}
					break;
				case 'comment':
					$scan_data = $this->scan_comments_batch( $page, 50 );
					$response['results'] = $scan_data['results'];
					$response['done']    = $scan_data['done'];
					break;
				case 'option':
					$scan_data = $this->scan_options_batch( $page, 100 );
					$response['results'] = $scan_data['results'];
					$response['done']    = $scan_data['done'];
					break;
				default:
					$response['done'] = true;
					break;
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Error!', 'tool-seo-hupuna' ) ) );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Render Admin Page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'tool-seo-hupuna' ) );
		}
		?>
		<div class="wrap tsh-wrap">
			<h1><?php echo esc_html__( 'External Link Scanner', 'tool-seo-hupuna' ); ?></h1>
			<div class="tsh-panel">
				<p>
					<strong><?php echo esc_html__( 'Current Domain:', 'tool-seo-hupuna' ); ?></strong>
					<code><?php echo esc_html( home_url() ); ?></code>
				</p>
				<p class="description">
					<?php echo esc_html__( 'Scans posts, pages, comments, and options for external links. System domains (WordPress, WooCommerce, Gravatar) and patterns are automatically ignored.', 'tool-seo-hupuna' ); ?>
				</p>
			</div>
			<div style="margin: 20px 0;">
				<button type="button" id="tool-seo-hupuna-scan-button" class="button button-primary button-large">
					<span class="dashicons dashicons-search"></span> <?php echo esc_html__( 'Start Scan', 'tool-seo-hupuna' ); ?>
				</button>
				<div id="tool-seo-hupuna-progress-wrap" class="tsh-progress-bar" style="display:none; margin-top: 20px;">
					<div style="background: #f0f0f1; border-radius: 4px; height: 30px; overflow: hidden;">
						<div id="tool-seo-hupuna-progress-fill" class="tsh-progress-fill" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
					</div>
					<div id="tool-seo-hupuna-progress-text" style="margin-top: 10px;"><?php echo esc_html__( 'Initializing...', 'tool-seo-hupuna' ); ?></div>
				</div>
			</div>
			<div id="tool-seo-hupuna-scan-results" class="tsh-scan-results" style="display: none;">
				<div class="tsh-panel" style="margin-bottom: 20px;">
					<p>
						<strong><?php echo esc_html__( 'Total Links Found:', 'tool-seo-hupuna' ); ?></strong>
						<span id="total-links">0</span> |
						<strong><?php echo esc_html__( 'Unique URLs:', 'tool-seo-hupuna' ); ?></strong>
						<span id="unique-links">0</span>
					</p>
				</div>
				<div class="nav-tab-wrapper tsh-results-tabs" style="margin-bottom: 20px;">
					<button class="nav-tab nav-tab-active tsh-tab" data-tab="grouped">
						<?php echo esc_html__( 'Grouped by URL', 'tool-seo-hupuna' ); ?>
					</button>
					<button class="nav-tab tsh-tab" data-tab="all">
						<?php echo esc_html__( 'All Occurrences', 'tool-seo-hupuna' ); ?>
					</button>
				</div>
				<div id="tool-seo-hupuna-results-content" class="tsh-results-content"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get current site domain.
	 * Handles localhost and subdirectory installations.
	 * Uses caching to avoid repeated calculations.
	 *
	 * @return string Site domain.
	 */
	public function get_site_domain() {
		return Hupuna_Helper::get_site_domain();
	}

	/**
	 * Check if URL is external and not whitelisted.
	 * Improved to handle localhost and subdirectory installations correctly.
	 *
	 * @param string $url URL to check.
	 * @return bool True if external, false otherwise.
	 */
	public function is_external_url( $url ) {
		return Hupuna_Helper::is_external_url( $url );
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

	public function normalize_url( $url ) {
		$url = Hupuna_Helper::normalize_url( $url );
		if ( ! $url ) {
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
