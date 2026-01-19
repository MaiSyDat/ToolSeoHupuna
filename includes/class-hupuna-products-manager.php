<?php
/**
 * Products Price Manager Class
 * Manages WooCommerce products and variants pricing.
 *
 * @package ToolSeoHupuna
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Products Manager class for managing product prices.
 */
class Hupuna_Products_Manager {

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_get_product_prices', array( $this, 'ajax_get_product_prices' ) );
		add_action( 'wp_ajax_update_product_price', array( $this, 'ajax_update_product_price' ) );
		add_action( 'wp_ajax_update_multiple_prices', array( $this, 'ajax_update_multiple_prices' ) );
		add_action( 'wp_ajax_update_product_name', array( $this, 'ajax_update_product_name' ) );
		add_action( 'wp_ajax_delete_product', array( $this, 'ajax_delete_product' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_submenu_page(
			'tool-seo-hupuna',
			__( 'Product Manager', 'tool-seo-hupuna' ),
			__( 'Product Manager', 'tool-seo-hupuna' ),
			'manage_woocommerce',
			'tool-seo-hupuna-product-prices',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'tool-seo_page_tool-seo-hupuna-product-prices' !== $hook ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style(
			'tool-seo-hupuna-products',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/css/products-manager.css',
			array(),
			TOOL_SEO_HUPUNA_VERSION
		);

		// Enqueue JS
		wp_enqueue_script(
			'tool-seo-hupuna-products',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/js/products-manager.js',
			array( 'jquery' ),
			TOOL_SEO_HUPUNA_VERSION,
			true
		);

		// Localize script data
		wp_localize_script(
			'tool-seo-hupuna-products',
			'hupunaProductsManager',
			array(
				'currentPage' => 1,
				'lastSearch'  => '',
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'tool_seo_hupuna_products_manager_nonce' ),
				'strings'     => array(
					'loading'       => __( 'Loading...', 'tool-seo-hupuna' ),
					'noProducts'    => __( 'No products found.', 'tool-seo-hupuna' ),
					'saving'        => __( 'Saving...', 'tool-seo-hupuna' ),
					'saved'         => __( 'Saved', 'tool-seo-hupuna' ),
					'error'         => __( 'Error!', 'tool-seo-hupuna' ),
					'confirmDelete' => __( 'Are you sure you want to delete this product?', 'tool-seo-hupuna' ),
					'variant'       => __( 'Variant', 'tool-seo-hupuna' ),
					'simple'        => __( 'Simple', 'tool-seo-hupuna' ),
					'save'          => __( 'Save', 'tool-seo-hupuna' ),
					'saveAll'       => __( 'Save All', 'tool-seo-hupuna' ),
					'delete'        => __( 'Delete', 'tool-seo-hupuna' ),
					'view'          => __( 'View', 'tool-seo-hupuna' ),
					'prev'          => __( 'Previous', 'tool-seo-hupuna' ),
					'next'          => __( 'Next', 'tool-seo-hupuna' ),
					'page'          => __( 'Page', 'tool-seo-hupuna' ),
					'of'            => __( 'of', 'tool-seo-hupuna' ),
					'regularPrice'  => __( 'Regular Price', 'tool-seo-hupuna' ),
					'salePrice'     => __( 'Sale Price', 'tool-seo-hupuna' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tool-seo-hupuna' ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_die( esc_html__( 'WooCommerce plugin is required for this feature.', 'tool-seo-hupuna' ) );
		}

		wp_enqueue_script( 'jquery' );
		?>
		<div class="wrap tsh-wrap">
			<h1><?php echo esc_html__( 'Product Manager', 'tool-seo-hupuna' ); ?></h1>
			<div class="tsh-panel" style="margin-bottom: 20px;">
				<p style="margin: 0;">
					<input type="text" id="tsh-products-search-input" class="regular-text" placeholder="<?php echo esc_attr__( 'Search products...', 'tool-seo-hupuna' ); ?>" style="width: calc(100% - 120px); max-width: 800px; margin-right: 10px;" />
					<button id="tsh-products-search-btn" class="button button-primary"><?php echo esc_html__( 'Search', 'tool-seo-hupuna' ); ?></button>
				</p>
			</div>
			<div id="tsh-products-container"></div>
			<div id="tsh-products-pagination" class="mt-3"></div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Get product prices.
	 * Optimized with database-level pagination to prevent memory exhaustion.
	 *
	 * @return void
	 */
	public function ajax_get_product_prices() {
		global $wpdb;
		
		check_ajax_referer( 'tool_seo_hupuna_products_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is required', 'tool-seo-hupuna' ) ) );
		}

		$page     = max( 1, intval( isset( $_POST['page'] ) ? $_POST['page'] : 1 ) );
		$search   = trim( sanitize_text_field( isset( $_POST['search'] ) ? $_POST['search'] : '' ) );
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		// Build SQL query - ONLY query parent products (not variations) to avoid duplicates.
		$where_clauses = array();
		$where_clauses[] = "p.post_type = 'product'"; // Only parent products
		$where_clauses[] = "p.post_status = 'publish'";

		$sql_params = array();

		// Add search filter if provided - search across title, content, SKU, and variation meta.
		if ( ! empty( $search ) ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			
			// Search in: post_title, post_content, SKU meta, variation SKU, or variation attributes.
			$where_clauses[] = "(
				p.post_title LIKE %s 
				OR p.post_content LIKE %s 
				OR EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm 
					WHERE pm.post_id = p.ID 
					AND pm.meta_key = '_sku' 
					AND pm.meta_value LIKE %s
				)
				OR EXISTS (
					SELECT 1 FROM {$wpdb->posts} variations
					LEFT JOIN {$wpdb->postmeta} pm2 ON variations.ID = pm2.post_id
					WHERE variations.post_parent = p.ID
					AND variations.post_type = 'product_variation'
					AND (
						pm2.meta_key = '_sku' AND pm2.meta_value LIKE %s
						OR pm2.meta_key LIKE 'attribute_%' AND pm2.meta_value LIKE %s
					)
				)
			)";
			
			$sql_params[] = $search_like;
			$sql_params[] = $search_like;
			$sql_params[] = $search_like;
			$sql_params[] = $search_like;
			$sql_params[] = $search_like;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Query with SQL_CALC_FOUND_ROWS for accurate pagination.
		$query = $wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS p.ID, p.post_title, p.post_type
			FROM {$wpdb->posts} p
			WHERE $where_sql
			ORDER BY p.ID DESC
			LIMIT %d OFFSET %d",
			array_merge( $sql_params, array( $per_page, $offset ) )
		);

		// Execute query.
		$products = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get total count for pagination.
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		$grouped_products = array();

		if ( ! empty( $products ) ) {
			foreach ( $products as $product_data ) {
				$product_id = (int) $product_data->ID;
				
				// Check if this is a variable product.
				$has_variations = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} 
					WHERE post_parent = %d AND post_type = 'product_variation'",
					$product_id
				) );
				
				if ( $has_variations > 0 ) {
					// Variable product - get all variants.
					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						continue;
					}
					
					$variants = $this->get_product_variants_optimized( $product_id, $search );
					
					if ( ! empty( $variants ) ) {
						$grouped_products[] = array(
							'id'        => $product_id,
							'name'      => $product->get_name(),
							'type'      => __( 'Variant', 'tool-seo-hupuna' ),
							'permalink' => get_permalink( $product_id ),
							'variants'  => $variants,
						);
					}
				} else {
					// Simple product - get prices from meta.
					$regular_price = get_post_meta( $product_id, '_regular_price', true );
					$sale_price = get_post_meta( $product_id, '_sale_price', true );
					
					$grouped_products[] = array(
						'id'            => $product_id,
						'name'          => $product_data->post_title,
						'type'          => __( 'Simple', 'tool-seo-hupuna' ),
						'permalink'     => get_permalink( $product_id ),
						'regular_price' => $regular_price,
						'sale_price'    => $sale_price,
					);
				}
			}
		}

		wp_send_json_success(
			array(
				'products' => $grouped_products,
				'total'    => $total,
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
	}

	/**
	 * Get product variants with optimized SQL query.
	 * Shows ALL variants when parent product matches search.
	 *
	 * @param int    $parent_id Parent product ID.
	 * @param string $search    Search term (NOT used for filtering variants).
	 * @return array Array of variant data.
	 */
	private function get_product_variants_optimized( $parent_id, $search = '' ) {
		global $wpdb;
		
		$variants = array();
		
		// Get all variation IDs for this parent using direct SQL.
		$variation_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_parent = %d 
			AND post_type = 'product_variation' 
			AND post_status = 'publish'
			ORDER BY ID ASC",
			$parent_id
		) );
		
		if ( empty( $variation_ids ) ) {
			return array();
		}
		
		// Load ALL variants without filtering - parent already matched search.
		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			
			if ( ! $variation || ! is_a( $variation, 'WC_Product_Variation' ) ) {
				continue;
			}
			
			$variant_name = $this->format_variant_name( $variation );
			
			$variants[] = array(
				'id'            => $variation->get_id(),
				'name'          => $variant_name,
				'regular_price' => $variation->get_regular_price(),
				'sale_price'    => $variation->get_sale_price(),
			);
		}
		
		return $variants;
	}

	/**
	 * Format variant name from attributes.
	 *
	 * @param WC_Product_Variation $variant Variant product object.
	 * @return string Formatted variant name.
	 */
	private function format_variant_name( $variant ) {
		$attributes = $variant->get_attributes();
		if ( empty( $attributes ) ) {
			return '';
		}

		return implode(
			', ',
			array_map(
				function( $value, $key ) {
					return wc_attribute_label( $key ) . ': ' . $value;
				},
				$attributes,
				array_keys( $attributes )
			)
		);
	}

	/**
	 * AJAX handler: Update product price.
	 *
	 * @return void
	 */
	public function ajax_update_product_price() {
		check_ajax_referer( 'tool_seo_hupuna_products_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is required', 'tool-seo-hupuna' ) ) );
		}

		$id            = intval( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		$regular_price = sanitize_text_field( isset( $_POST['regular_price'] ) ? $_POST['regular_price'] : '' );
		$sale_price    = sanitize_text_field( isset( $_POST['sale_price'] ) ? $_POST['sale_price'] : '' );

		$product = wc_get_product( $id );
		if ( $product ) {
			$product->set_regular_price( '' !== $regular_price ? $regular_price : '' );
			$product->set_sale_price( '' !== $sale_price ? $sale_price : '' );
			$product->save();

			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * AJAX handler: Update multiple prices.
	 *
	 * @return void
	 */
	public function ajax_update_multiple_prices() {
		check_ajax_referer( 'tool_seo_hupuna_products_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is required', 'tool-seo-hupuna' ) ) );
		}

		$updates = json_decode( stripslashes( isset( $_POST['updates'] ) ? $_POST['updates'] : '[]' ), true );

		if ( ! is_array( $updates ) ) {
			wp_send_json_error();
		}

		foreach ( $updates as $item ) {
			$id = intval( isset( $item['id'] ) ? $item['id'] : 0 );
			if ( ! $id ) {
				continue;
			}

			$regular_price = isset( $item['regular_price'] ) ? $item['regular_price'] : '';
			$sale_price    = isset( $item['sale_price'] ) ? $item['sale_price'] : '';

			update_post_meta( $id, '_regular_price', $regular_price );
			update_post_meta( $id, '_sale_price', $sale_price );
			update_post_meta( $id, '_price', $sale_price ? $sale_price : $regular_price );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler: Update product name.
	 *
	 * @return void
	 */
	public function ajax_update_product_name() {
		check_ajax_referer( 'tool_seo_hupuna_products_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is required', 'tool-seo-hupuna' ) ) );
		}

		$id   = intval( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		$name = sanitize_text_field( isset( $_POST['name'] ) ? $_POST['name'] : '' );

		if ( $id && $name ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->set_name( $name );
				$product->save();

				wp_send_json_success();
			}
		}

		wp_send_json_error();
	}

	/**
	 * AJAX handler: Delete product.
	 *
	 * @return void
	 */
	public function ajax_delete_product() {
		check_ajax_referer( 'tool_seo_hupuna_products_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'delete_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'tool-seo-hupuna' ) ) );
		}

		$id = intval( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		if ( $id ) {
			$result = wp_delete_post( $id, true );
			if ( $result ) {
				wp_send_json_success( array( 'message' => __( 'Product deleted successfully', 'tool-seo-hupuna' ) ) );
			}
		}

		wp_send_json_error( array( 'message' => __( 'Error deleting product', 'tool-seo-hupuna' ) ) );
	}
}

