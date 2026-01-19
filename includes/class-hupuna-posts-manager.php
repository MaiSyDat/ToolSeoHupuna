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
					'saving'        => __( 'Saving...', 'tool-seo-hupuna' ),
					'saved'         => __( 'Saved', 'tool-seo-hupuna' ),
					'error'         => __( 'Error!', 'tool-seo-hupuna' ),
					'confirmDelete' => __( 'Are you sure you want to delete this post?', 'tool-seo-hupuna' ),
					'product'       => __( 'PRODUCT', 'tool-seo-hupuna' ),
					'category'      => __( 'CATEGORY', 'tool-seo-hupuna' ),
					'post'          => __( 'POST', 'tool-seo-hupuna' ),
					'external'      => __( 'EXTERNAL', 'tool-seo-hupuna' ),
					'viewLink'      => __( 'View Link', 'tool-seo-hupuna' ),
					'saveLinks'     => __( 'Save Links', 'tool-seo-hupuna' ),
					'viewPost'      => __( 'View Post', 'tool-seo-hupuna' ),
					'edit'          => __( 'Edit', 'tool-seo-hupuna' ),
					'delete'        => __( 'Delete', 'tool-seo-hupuna' ),
					'prev'          => __( 'Previous', 'tool-seo-hupuna' ),
					'next'          => __( 'Next', 'tool-seo-hupuna' ),
					'page'          => __( 'Page', 'tool-seo-hupuna' ),
					'of'            => __( 'of', 'tool-seo-hupuna' ),
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
		// Use cached site domain (shared with scanner).
		$cached_domain = get_transient( 'tool_seo_hupuna_site_domain' );
		
		if ( false !== $cached_domain ) {
			return $cached_domain;
		}

		$url    = home_url();
		$parsed = wp_parse_url( $url );
		$host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
		
		// Handle localhost with port or subdirectory.
		if ( empty( $host ) || 'localhost' === $host ) {
			$host = parse_url( $url, PHP_URL_HOST );
			if ( empty( $host ) ) {
				if ( preg_match( '#https?://([^/]+)#', $url, $matches ) ) {
					$host = $matches[1];
				}
			}
		}
		
		// Cache for 24 hours.
		set_transient( 'tool_seo_hupuna_site_domain', $host, DAY_IN_SECONDS );
		
		return $host;
	}

	/**
	 * Check if URL is external.
	 *
	 * @param string $url URL to check.
	 * @return bool True if external, false otherwise.
	 */
	private function is_external_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['host'] ) ) {
			return false;
		}

		$url_host = strtolower( trim( $parsed['host'] ) );
		$url_host = preg_replace( '#^www\.#', '', $url_host );
		
		$site_host = strtolower( trim( $this->site_domain ) );
		$site_host = preg_replace( '#^www\.#', '', $site_host );
		
		// Handle localhost cases.
		if ( 'localhost' === $site_host || strpos( $site_host, 'localhost' ) !== false ) {
			if ( 'localhost' === $url_host || strpos( $url_host, 'localhost' ) !== false ) {
				$site_parsed = wp_parse_url( home_url() );
				$site_path = isset( $site_parsed['path'] ) ? $site_parsed['path'] : '';
				$url_path = isset( $parsed['path'] ) ? $parsed['path'] : '';
				
				if ( ! empty( $site_path ) && 0 === strpos( $url_path, rtrim( $site_path, '/' ) ) ) {
					return false;
				}
				
				$site_full = $site_host . ( isset( $site_parsed['port'] ) ? ':' . $site_parsed['port'] : '' );
				$url_full = $url_host . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
				
				return $url_full !== $site_full;
			}
		}

		return $url_host !== $site_host;
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
		add_action( 'wp_ajax_update_post_links', array( $this, 'ajax_update_post_links' ) );
		add_action( 'wp_ajax_trash_post', array( $this, 'ajax_trash_post' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tool-seo-hupuna',
			__( 'Post Link Manager', 'tool-seo-hupuna' ),
			__( 'Post Link Manager', 'tool-seo-hupuna' ),
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
			<h1><?php echo esc_html__( 'Post Link Manager', 'tool-seo-hupuna' ); ?></h1>
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
						<th style="width: 200px;"><?php echo esc_html__( 'Actions', 'tool-seo-hupuna' ); ?></th>
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

		// Get all public post types (cached).
		$post_types = get_transient( 'tool_seo_hupuna_public_post_types' );
		if ( false === $post_types ) {
			$post_types = get_post_types( array( 'public' => true ), 'names' );
			$excluded   = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );
			$post_types = array_diff( $post_types, $excluded );
			set_transient( 'tool_seo_hupuna_public_post_types', $post_types, TOOL_SEO_HUPUNA_CACHE_EXPIRATION );
		}

		// Prepare post types for SQL IN clause.
		$post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// Build base SQL query - filter posts containing HTML anchor tags.
		$where_clauses = array();
		$where_clauses[] = "post_type IN ($post_types_placeholders)";
		$where_clauses[] = "post_status = 'publish'";
		$where_clauses[] = "post_content LIKE %s"; // Filter posts with <a tags.

		$sql_params = array_values( $post_types );
		$sql_params[] = '%<a %'; // Posts must contain anchor tags.

		// Add search filter if provided.
		if ( ! empty( $search ) ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = "(post_title LIKE %s OR post_content LIKE %s)";
			$sql_params[] = $search_like;
			$sql_params[] = $search_like;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Query with SQL_CALC_FOUND_ROWS for accurate pagination.
		$query = $wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS ID, post_title, post_content, post_date 
			FROM {$wpdb->posts} 
			WHERE $where_sql 
			ORDER BY post_date DESC 
			LIMIT %d OFFSET %d",
			array_merge( $sql_params, array( $per_page, $offset ) )
		);

		// Execute query.
		$posts = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get total count for pagination.
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		$posts_with_links = array();

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				// Extract links from post content.
				$links = $this->extract_internal_links_from_content( $post->post_content, $post->ID );

				// Only include posts that actually have links after extraction.
				if ( ! empty( $links ) ) {
					// If search is provided, Filter by URL/anchor text match.
					$should_include = true;
					if ( ! empty( $search ) ) {
						$should_include = false;
						$search_lower = strtolower( $search );

						// Check if title matches (already filtered by SQL, but double-check).
						if ( false !== strpos( strtolower( $post->post_title ), $search_lower ) ) {
							$should_include = true;
						}

						// Check if any link URL or anchor text matches.
						if ( ! $should_include ) {
							foreach ( $links as $link ) {
								$link_url = strtolower( trim( $link['url'] ) );
								$link_text = strtolower( trim( $link['text'] ) );
								
								if ( false !== strpos( $link_url, $search_lower ) || 
									 false !== strpos( $link_text, $search_lower ) ) {
									$should_include = true;
									break;
								}
							}
						}
					}

					if ( $should_include ) {
						$posts_with_links[] = array(
							'id'        => (int) $post->ID,
							'title'     => $post->post_title,
							'permalink' => get_permalink( $post->ID ),
							'edit_link' => get_edit_post_link( $post->ID, '' ),
							'date'      => mysql2date( 'd/m/Y', $post->post_date ),
							'links'     => $links,
						);
					}
				}
			}
		}

		wp_send_json_success(
			array(
				'posts'    => $posts_with_links,
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

		// Bước 1: Quét tất cả thẻ <a> bất kể cấu trúc lồng nhau
		// Regex này bắt URL và Anchor Text chính xác hơn
		preg_match_all('/<a[^>]+href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $all_matches, PREG_SET_ORDER);

		foreach ($all_matches as $url_data) {
			$url       = trim($url_data[1]);
			$link_text = strip_tags($url_data[2]); // Lấy text kể cả khi nằm trong <strong><em>

			// Chuẩn hóa URL
			$normalized_url = $this->normalize_url_for_comparison($url);
			if (empty($normalized_url) || in_array($normalized_url, $found_urls, true)) {
				continue;
			}

			// Bỏ qua các giao thức không cần thiết
			if (preg_match('/^(mailto:|tel:|javascript:|#)/i', $normalized_url)) {
				continue;
			}

			$link_type = 'other';
			$link_id   = null;
			$link_name = '';

			// Bước 2: Nhận diện loại link (Giữ nguyên logic cũ nhưng thêm fallback)
			if (preg_match('/(?:p=)(\d+)/', $normalized_url, $id_match)) {
				$link_id = intval($id_match[1]);
				$linked_post_type = get_post_type($link_id);
				if ($linked_post_type === 'product') $link_type = 'product';
				elseif ($linked_post_type === 'post') $link_type = 'post';
			} else {
				// Thử tìm qua slug/path
				$parsed_url = wp_parse_url($normalized_url);
				$path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';

				if (! empty($path)) {
					// Kiểm tra xem có phải sản phẩm/bài viết/danh mục không
					$post_obj = get_page_by_path($path, OBJECT, array('post', 'product', 'page'));
					if ($post_obj) {
						$link_id = $post_obj->ID;
						$link_type = get_post_type($link_id);
					} else {
						// Kiểm tra danh mục sản phẩm
						$path_parts = explode('/', $path);
						$slug = end($path_parts);
						$term = get_term_by('slug', $slug, 'product_cat');
						if ($term) {
							$link_id = $term->term_id;
							$link_type = 'product_cat';
						}
					}
				}
			}

			// Bước 3: FIX QUAN TRỌNG - Nếu không xác định được ID nhưng vẫn là nội bộ hoặc link cần quản lý
			// Chúng ta vẫn cho hiện thị dưới dạng 'post' hoặc 'external' thay vì bỏ qua hoàn toàn
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

		return $links;
	}

	/**
	 * Normalize URL for comparison (handle relative paths).
	 *
	 * @param string $url URL to normalize.
	 * @return string Normalized URL.
	 */
	private function normalize_url_for_comparison( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		// Skip non-http protocols.
		if ( 0 === strpos( $url, '#' ) || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) || 0 === strpos( $url, 'javascript:' ) || 0 === strpos( $url, 'data:' ) ) {
			return '';
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
	 * AJAX handler: Update post links.
	 *
	 * @return void
	 */
	public function ajax_update_post_links() {
		check_ajax_referer( 'tool_seo_hupuna_posts_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		$id    = intval( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		$links = json_decode( stripslashes( isset( $_POST['links'] ) ? $_POST['links'] : '[]' ), true );

		if ( ! $id || ! is_array( $links ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data', 'tool-seo-hupuna' ) ) );
		}

		$post = get_post( $id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found', 'tool-seo-hupuna' ) ) );
		}

		$content = $post->post_content;

		foreach ( $links as $link ) {
			$old_url = esc_url_raw( $link['old_url'] );
			$new_url = esc_url_raw( $link['new_url'] );
			if ( $old_url && $new_url && $old_url !== $new_url ) {
				$old_url_escaped = preg_quote( $old_url, '/' );
				$content         = preg_replace(
					'/href=["\']' . $old_url_escaped . '["\']/i',
					'href="' . esc_attr( $new_url ) . '"',
					$content
				);
			}
		}

		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => $content,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Links updated successfully', 'tool-seo-hupuna' ) ) );
	}

	/**
	 * AJAX handler: Trash post.
	 *
	 * @return void
	 */
	public function ajax_trash_post() {
		check_ajax_referer( 'tool_seo_hupuna_posts_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete posts', 'tool-seo-hupuna' ) ) );
		}

		$id = intval( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		if ( $id ) {
			$result = wp_trash_post( $id );
			if ( $result ) {
				wp_send_json_success( array( 'message' => __( 'Post deleted', 'tool-seo-hupuna' ) ) );
			}
		}

		wp_send_json_error( array( 'message' => __( 'Error deleting post', 'tool-seo-hupuna' ) ) );
	}
}

