<?php
/**
 * Posts with Links Manager Class
 * Manages posts/pages with links (internal: product, product_cat, post AND external links).
 * Extended to support all post types.
 *
 * @package ToolSeoHupuna
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Posts Manager class for managing posts with links.
 */
class Hupuna_Posts_Manager {

	/**
	 * Site domain for external link detection.
	 *
	 * @var string
	 */
	private $site_domain;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->site_domain = $this->get_site_domain();
		$this->init_hooks();
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		wp_enqueue_style(
			'tool-seo-hupuna-admin',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TOOL_SEO_HUPUNA_VERSION
		);

		// Enqueue JS
		wp_enqueue_script(
			'tool-seo-hupuna-posts',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/js/posts-manager.js',
			array( 'jquery' ),
			TOOL_SEO_HUPUNA_VERSION,
			true
		);

		// Localize script data
		wp_localize_script(
			'tool-seo-hupuna-posts',
			'hupunaPostsManager',
			array(
				'currentPage' => 1,
				'lastSearch'  => '',
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'tool_seo_hupuna_posts_manager_nonce' ),
				'strings'     => array(
					'loading'       => __( 'Loading...', 'tool-seo-hupuna' ),
					'noPosts'       => __( 'No posts found.', 'tool-seo-hupuna' ),
					'product'       => __( 'PRODUCT', 'tool-seo-hupuna' ),
					'category'      => __( 'CATEGORY', 'tool-seo-hupuna' ),
					'post'          => __( 'POST', 'tool-seo-hupuna' ),
					'external'      => __( 'EXTERNAL', 'tool-seo-hupuna' ),
					'prev'          => __( 'Previous', 'tool-seo-hupuna' ),
					'next'          => __( 'Next', 'tool-seo-hupuna' ),
					'page'          => __( 'Page', 'tool-seo-hupuna' ),
					'of'            => __( 'of', 'tool-seo-hupuna' ),
					'noAnchor'      => __( '(No anchor text)', 'tool-seo-hupuna' ),
					'url'           => __( 'URL', 'tool-seo-hupuna' ),
					'excerpt'       => __( 'Excerpt', 'tool-seo-hupuna' ),
					'productCategory' => __( 'Product Category', 'tool-seo-hupuna' ),
					'newsCategory'  => __( 'Category', 'tool-seo-hupuna' ),
					'product_singular' => __( 'Product', 'tool-seo-hupuna' ),
					'view'          => __( 'View', 'tool-seo-hupuna' ),
				),
			)
		);
	}

	/**
	 * Get current site domain.
	 * Uses cached value to avoid repeated calculations.
	 *
	 * @return string Site domain.
	 */
	private function get_site_domain() {
		return Hupuna_Helper::get_site_domain();
	}

	/**
	 * Check if URL is external.
	 *
	 * @param string $url URL to check.
	 * @return bool True if external, false otherwise.
	 */
	private function is_external_url( $url ) {
		return Hupuna_Helper::is_external_url( $url );
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_get_posts_with_links', array( $this, 'ajax_get_posts_with_links' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tool-seo-hupuna',
			__( 'Links in Posts', 'tool-seo-hupuna' ),
			__( 'Links in Posts', 'tool-seo-hupuna' ),
			'manage_options',
			'tool-seo-hupuna-posts-with-links',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tool-seo-hupuna' ) );
		}

		wp_enqueue_script( 'jquery' );
		?>
		<div class="wrap tsh-wrap">
			<h1><?php echo esc_html__( 'Links in Posts', 'tool-seo-hupuna' ); ?></h1>
			<div class="tsh-panel" style="margin-bottom: 20px;">
				<p style="margin: 0;">
					<input type="text" id="tsh-posts-search-input" class="regular-text" placeholder="<?php echo esc_attr__( 'Paste URL to find posts containing this link...', 'tool-seo-hupuna' ); ?>" style="width: calc(100% - 200px); max-width: 800px; margin-right: 10px;" />
					<button id="tsh-posts-search-btn" class="button button-primary"><?php echo esc_html__( 'Search', 'tool-seo-hupuna' ); ?></button>
					<button id="tsh-posts-clear-search-btn" class="button"><?php echo esc_html__( 'Clear Search', 'tool-seo-hupuna' ); ?></button>
				</p>
			</div>
			<table id="tsh-posts-table" class="wp-list-table widefat fixed striped tsh-table">
				<thead>
					<tr>
						<th style="width: 60px;"><?php echo esc_html__( 'ID', 'tool-seo-hupuna' ); ?></th>
						<th style="width: 200px;"><?php echo esc_html__( 'Title', 'tool-seo-hupuna' ); ?></th>
						<th><?php echo esc_html__( 'Links in Content', 'tool-seo-hupuna' ); ?></th>
						<th style="width: 120px;"><?php echo esc_html__( 'Date', 'tool-seo-hupuna' ); ?></th>
						<th style="width: 100px;"><?php echo esc_html__( 'Actions', 'tool-seo-hupuna' ); ?></th>
					</tr>
				</thead>
				<tbody id="tsh-posts-table-body">
					<tr>
						<td colspan="5"><?php echo esc_html__( 'Loading...', 'tool-seo-hupuna' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div id="tsh-posts-pagination" class="mt-3"></div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Get posts with links.
	 * Optimized with direct SQL queries for maximum performance.
	 *
	 * @return void
	 */
	public function ajax_get_posts_with_links() {
		global $wpdb;
		
		check_ajax_referer( 'tool_seo_hupuna_posts_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		$page     = max( 1, intval( isset( $_POST['page'] ) ? $_POST['page'] : 1 ) );
		$search   = trim( sanitize_text_field( isset( $_POST['search'] ) ? $_POST['search'] : '' ) );
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		// 1. Get Posts (including Products)
		$post_types = get_transient( 'tool_seo_hupuna_public_post_types' );
		if ( false === $post_types ) {
			$post_types = get_post_types( array( 'public' => true ), 'names' );
			$excluded   = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );
			$post_types = array_diff( $post_types, $excluded );
			set_transient( 'tool_seo_hupuna_public_post_types', $post_types, TOOL_SEO_HUPUNA_CACHE_EXPIRATION );
		}

		$post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$where_clauses = array();
		$where_clauses[] = "post_type IN ($post_types_placeholders)";
		$where_clauses[] = "post_status = 'publish'";
		$where_clauses[] = "(post_content LIKE %s OR post_excerpt LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s)"; 

		$sql_params = array_values( $post_types );
		$sql_params[] = '%<a%';
		$sql_params[] = '%<a%';
		$sql_params[] = '%http%';
		$sql_params[] = '%http%';

		if ( ! empty( $search ) ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = "(post_title LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s)";
			$sql_params[] = $search_like;
			$sql_params[] = $search_like;
			$sql_params[] = $search_like;
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$posts_query = $wpdb->prepare(
			"SELECT 'post' as item_source, ID, post_title as title, post_content as content, post_excerpt as excerpt_content, post_date as item_date, post_type as sub_type
			FROM {$wpdb->posts} 
			WHERE $where_sql",
			$sql_params
		);

		// 2. Get Terms (Categories/Product Categories)
		$taxonomies = array( 'category', 'product_cat' );
		$tax_placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
		$term_where = array();
		$term_where[] = "tt.taxonomy IN ($tax_placeholders)";
		$term_where[] = "(tt.description LIKE %s OR tt.description LIKE %s)";
		
		$term_params = array_values( $taxonomies );
		$term_params[] = '%<a%';
		$term_params[] = '%http%';

		if ( ! empty( $search ) ) {
			$term_where[] = "(t.name LIKE %s OR tt.description LIKE %s)";
			$term_params[] = $search_like;
			$term_params[] = $search_like;
		}

		$term_where_sql = implode( ' AND ', $term_where );
		$terms_query = $wpdb->prepare(
			"SELECT 'term' as item_source, t.term_id as ID, t.name as title, tt.description as content, '' as excerpt_content, '0000-00-00 00:00:00' as item_date, tt.taxonomy as sub_type
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE $term_where_sql",
			$term_params
		);

		// Combine with UNION
		$combined_query = "($posts_query) UNION ($terms_query) ORDER BY item_date DESC LIMIT %d OFFSET %d";
		$final_query = $wpdb->prepare( $combined_query, array( $per_page, $offset ) );
		
		// Get total for UNION is tricky, we'll get full results count from another query or just use SQL_CALC_FOUND_ROWS on the UNION
		$results = $wpdb->get_results( "SELECT SQL_CALC_FOUND_ROWS * FROM ($posts_query UNION $terms_query) as combined ORDER BY item_date DESC LIMIT $offset, $per_page" );
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		$items_with_links = array();

		if ( ! empty( $results ) ) {
			foreach ( $results as $item ) {
				// Extract links from both content and excerpt (if applicable)
				$content_links = $this->extract_internal_links_from_content( $item->content, $item->ID );
				$excerpt_links = !empty($item->excerpt_content) ? $this->extract_internal_links_from_content( $item->excerpt_content, $item->ID ) : array();
				
				// Tag source for UI
				foreach($excerpt_links as &$el) {
					$el['in_excerpt'] = true;
				}

				$links = array_merge($content_links, $excerpt_links);

				if ( ! empty( $links ) ) {
					$items_with_links[] = array(
						'id'          => (int) $item->ID,
						'source'      => $item->item_source, // 'post' or 'term'
						'sub_type'    => $item->sub_type,
						'title'       => $item->title,
						'permalink'   => ($item->item_source === 'post') ? get_permalink( $item->ID ) : get_term_link( (int)$item->ID, $item->sub_type ),
						'edit_link'   => ($item->item_source === 'post') ? get_edit_post_link( $item->ID, '' ) : get_edit_term_link( $item->ID, $item->sub_type ),
						'date'        => ($item->item_date !== '0000-00-00 00:00:00') ? mysql2date( 'd/m/Y', $item->item_date ) : '-',
						'links'       => $links,
					);
				}
			}
		}

		wp_send_json_success(
			array(
				'posts'    => $items_with_links,
				'total'    => $total,
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
	}

	/**
	 * Extract internal links (product, category, post) from post content.
	 * This method contains the heavy regex logic and is only called on paginated results.
	 *
	 * @param string $content Post content.
	 * @param int    $post_id Post ID.
	 * @return array Array of link data.
	 */
	private function extract_internal_links_from_content($content, $post_id)
	{
		if (empty($content)) {
			return array();
		}

		$links      = array();
		$found_urls = array();

		// Step 1: Extract formal <a> tags.
		preg_match_all('/<a[^>]+href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $a_matches, PREG_SET_ORDER);

		foreach ($a_matches as $url_data) {
			$url       = trim($url_data[1]);
			$link_text = strip_tags($url_data[2]);
			
			$this->process_detected_link($url, $link_text, $links, $found_urls);
		}

		// Step 2: Extract plain URLs (unlinked).
		// We use a regex for URLs that are NOT preceded by href=" or href='
		// and NOT inside an <a> tag (though checking inside is harder, usually unlinked URLs are just floating).
		$url_pattern = '/(?<!href=["\'])(?<!href=)(?<!["\'])(https?:\/\/[^\s<"\']+)/i';
		preg_match_all($url_pattern, $content, $plain_matches);

		if (!empty($plain_matches[1])) {
			foreach ($plain_matches[1] as $url) {
				$url = trim($url, '.,;!?'); // Clean trailing punctuation
				$this->process_detected_link($url, '', $links, $found_urls);
			}
		}

		return $links;
	}

	/**
	 * Process a detected link (either formal anchor or plain URL).
	 *
	 * @param string $url URL to process.
	 * @param string $link_text Anchor text.
	 * @param array  $links Reference to links array.
	 * @param array  $found_urls Reference to found URLs array.
	 */
	private function process_detected_link($url, $link_text, &$links, &$found_urls) {
		$normalized_url = $this->normalize_url_for_comparison($url);
		
		if (empty($normalized_url) || in_array($normalized_url, $found_urls, true)) {
			return;
		}

		if (preg_match('/^(mailto:|tel:|javascript:|#)/i', $normalized_url)) {
			return;
		}

		$link_type = 'other';
		$link_id   = null;

		// Identify link type
		$link_id_found = url_to_postid($normalized_url);
		if ($link_id_found) {
			$link_id = $link_id_found;
			$linked_post_type = get_post_type($link_id);
			if ($linked_post_type === 'product') $link_type = 'product';
			elseif ($linked_post_type === 'post') $link_type = 'post';
		} else {
			// Deep path matching for subdirectories and custom prefixes
			$parsed_url = wp_parse_url($normalized_url);
			$path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';

			if (! empty($path)) {
				// 1. Strip subdirectory if current WP is in one (e.g. /tiktok/)
				$home_path = trim(wp_parse_url(home_url(), PHP_URL_PATH), '/');
				if ($home_path && strpos($path, $home_path) === 0) {
					$path = trim(substr($path, strlen($home_path)), '/');
				}

				// 2. Try raw path first
				$post_obj = get_page_by_path($path, OBJECT, array('post', 'product', 'page'));
				
				// 3. Try stripping common prefixes if not found
				if (!$post_obj) {
					$clean_slug = preg_replace('/^(product|show-post|blog|san-pham|bai-viet)\//', '', $path);
					if ($clean_slug !== $path) {
						$post_obj = get_page_by_path($clean_slug, OBJECT, array('post', 'product', 'page'));
					}
				}

				if ($post_obj) {
					$link_id = $post_obj->ID;
					$link_type = get_post_type($link_id);
				} else {
					// Check for product categories or other terms
					$path_parts = explode('/', $path);
					$slug = end($path_parts);
					$term = get_term_by('slug', $slug, 'product_cat');
					if (!$term) {
						$term = get_term_by('slug', $slug, 'category');
					}
					
					if ($term) {
						$link_id = $term->term_id;
						$link_type = ($term->taxonomy === 'product_cat') ? 'product_cat' : 'category';
					}
				}
			}
		}

		if (! $this->is_external_url($normalized_url)) {
			$links[] = array(
				'url'  => $normalized_url,
				'text' => trim($link_text) ?: $normalized_url,
				'type' => ($link_type !== 'other') ? $link_type : 'post',
				'id'   => $link_id,
			);
		} else {
			$links[] = array(
				'url'  => $normalized_url,
				'text' => trim($link_text) ?: $normalized_url,
				'type' => 'external',
				'id'   => null,
			);
		}
		$found_urls[] = $normalized_url;
	}


	/**
	 * Normalize URL for comparison (handle relative paths).
	 *
	 * @param string $url URL to normalize.
	 * @return string Normalized URL.
	 */
	private function normalize_url_for_comparison( $url ) {
		return Hupuna_Helper::normalize_url( $url );
	}
}

