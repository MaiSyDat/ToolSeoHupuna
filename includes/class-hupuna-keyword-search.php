<?php
/**
 * Keyword Search Class
 * Manages keyword search functionality across products, posts, and categories.
 *
 * @package ToolSeoHupuna
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keyword Search class for managing search interface and AJAX.
 */
class Hupuna_Keyword_Search {

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
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_hupuna_keyword_search', array( $this, 'ajax_keyword_search' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_submenu() {
		add_submenu_page(
			'tool-seo-hupuna',
			__( 'Keyword Search', 'tool-seo-hupuna' ),
			__( 'Keyword Search', 'tool-seo-hupuna' ),
			'manage_options',
			'tool-seo-hupuna-keyword-search',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'tool-seo-hupuna-keyword-search' ) ) {
			return;
		}

		wp_enqueue_style(
			'tool-seo-hupuna-admin',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TOOL_SEO_HUPUNA_VERSION
		);

		wp_enqueue_script(
			'hupuna-keyword-search',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/js/keyword-search.js',
			array( 'jquery' ),
			TOOL_SEO_HUPUNA_VERSION,
			true
		);

		wp_localize_script(
			'hupuna-keyword-search',
			'hupunaKeywordSearch',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hupuna_keyword_search_nonce' ),
				'strings' => array(
					'searching' => __( 'Searching...', 'tool-seo-hupuna' ),
					'noResults' => __( 'No results found.', 'tool-seo-hupuna' ),
					'error'     => __( 'An error occurred.', 'tool-seo-hupuna' ),
					'edit'      => __( 'Edit', 'tool-seo-hupuna' ),
					'view'      => __( 'View', 'tool-seo-hupuna' ),
				),
			)
		);
	}

	/**
	 * Render Admin Page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		?>
		<div class="wrap tsh-wrap">
			<h1><?php echo esc_html__( 'Keyword Search', 'tool-seo-hupuna' ); ?></h1>
			
			<div class="tsh-panel" style="margin-bottom: 20px;">
				<div style="display: flex; gap: 10px; align-items: center;">
					<input type="text" id="hupuna-search-input" class="regular-text" placeholder="<?php echo esc_attr__( 'Enter keyword to search...', 'tool-seo-hupuna' ); ?>" />
					<button type="button" id="hupuna-search-btn" class="button button-primary">
						<span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
						<?php echo esc_html__( 'Start Search', 'tool-seo-hupuna' ); ?>
					</button>
				</div>
				<p class="description">
					<?php echo esc_html__( 'Search in Products (WooCommerce), News (Posts), and Category descriptions.', 'tool-seo-hupuna' ); ?>
				</p>
			</div>

			<div id="hupuna-search-results" class="tsh-scan-results" style="display: none;">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 150px;"><?php echo esc_html__( 'Content Type', 'tool-seo-hupuna' ); ?></th>
							<th><?php echo esc_html__( 'Title / Name', 'tool-seo-hupuna' ); ?></th>
							<th><?php echo esc_html__( 'Excerpt', 'tool-seo-hupuna' ); ?></th>
							<th style="width: 100px;"><?php echo esc_html__( 'Actions', 'tool-seo-hupuna' ); ?></th>
						</tr>
					</thead>
					<tbody id="hupuna-search-results-body">
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX Keyword Search.
	 *
	 * @return void
	 */
	public function ajax_keyword_search() {
		check_ajax_referer( 'hupuna_keyword_search_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		global $wpdb;

		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

		if ( empty( $keyword ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a keyword.', 'tool-seo-hupuna' ) ) );
		}

		$search_pattern = '%' . $wpdb->esc_like( $keyword ) . '%';
		$results        = array();

		// 1. Search in Posts & Products.
		// For 'post' (News): search in both title and content.
		// For 'product' (Product): search specifically in post_content (description).
		$posts_query = $wpdb->prepare(
			"SELECT ID, post_title, post_content, post_type 
			FROM {$wpdb->posts} 
			WHERE (
				(post_type = 'post' AND (post_title LIKE %s OR post_content LIKE %s)) OR 
				(post_type = 'product' AND post_content LIKE %s)
			)
			AND post_status = 'publish'
			ORDER BY post_date DESC
			LIMIT 100",
			$search_pattern,
			$search_pattern,
			$search_pattern
		);

		$posts_results = $wpdb->get_results( $posts_query );

		if ( ! empty( $posts_results ) ) {
			foreach ( $posts_results as $post ) {
				$type_label = ( 'product' === $post->post_type ) ? __( 'Product', 'tool-seo-hupuna' ) : __( 'News', 'tool-seo-hupuna' );
				
				$results[] = array(
					'type'      => $type_label,
					'title'     => $post->post_title,
					'excerpt'   => $this->get_content_excerpt( $post->post_content, $keyword ),
					'edit_link' => get_edit_post_link( $post->ID ),
					'view_link' => get_permalink( $post->ID ),
				);
			}
		}

		// 2. Search in Categories & Product Categories description
		$tax_query = $wpdb->prepare(
			"SELECT t.term_id, t.name, tt.description, tt.taxonomy 
			FROM {$wpdb->terms} t 
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
			WHERE tt.description LIKE %s 
			AND tt.taxonomy IN ('category', 'product_cat')
			LIMIT 50",
			$search_pattern
		);

		$tax_results = $wpdb->get_results( $tax_query );

		if ( ! empty( $tax_results ) ) {
			foreach ( $tax_results as $term ) {
				$type_label = ( 'product_cat' === $term->taxonomy ) ? __( 'Product Category', 'tool-seo-hupuna' ) : __( 'News Category', 'tool-seo-hupuna' );
				
				$results[] = array(
					'type'      => $type_label,
					'title'     => $term->name,
					'excerpt'   => $this->get_content_excerpt( $term->description, $keyword ),
					'edit_link' => get_edit_term_link( $term->term_id, $term->taxonomy ),
					'view_link' => get_term_link( $term->term_id ),
				);
			}
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Get content excerpt with keyword highlighting.
	 *
	 * @param string $content Full content.
	 * @param string $keyword Keyword to highlight.
	 * @return string Excerpt.
	 */
	private function get_content_excerpt( $content, $keyword ) {
		$content = wp_strip_all_tags( $content );
		$keyword_pos = mb_stripos( $content, $keyword );

		if ( false === $keyword_pos ) {
			return mb_substr( $content, 0, 150 ) . '...';
		}

		$start = max( 0, $keyword_pos - 75 );
		$length = 150;
		
		$excerpt = mb_substr( $content, $start, $length );
		
		// Highlight keyword
		$excerpt = preg_replace( '/' . preg_quote( $keyword, '/' ) . '/iu', '<mark>$0</mark>', $excerpt );

		return ( $start > 0 ? '...' : '' ) . $excerpt . ( mb_strlen( $content ) > $start + $length ? '...' : '' );
	}
}
