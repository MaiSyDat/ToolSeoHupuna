<?php
/**
 * Products Price Manager Class
 * Manages WooCommerce products and variants pricing.
 *
 * @package HupunaExternalLinkScanner
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
			__( 'Product Price Manager', 'tool-seo-hupuna' ),
			__( 'Product Prices', 'tool-seo-hupuna' ),
			'manage_woocommerce',
			'tool-seo-hupuna-product-prices',
			array( $this, 'render_admin_page' )
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
		<div class="wrap">
			<h1><?php echo esc_html__( 'Product Price Manager', 'tool-seo-hupuna' ); ?></h1>
			<div class="card" style="margin-bottom: 20px;">
				<p style="margin: 0;">
					<input type="text" id="search-input" class="regular-text" placeholder="<?php echo esc_attr__( 'Search products...', 'tool-seo-hupuna' ); ?>" style="width: calc(100% - 120px); max-width: 800px; margin-right: 10px;" />
					<button id="search-btn" class="button button-primary"><?php echo esc_html__( 'Search', 'tool-seo-hupuna' ); ?></button>
				</p>
			</div>
			<table id="price-table" class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Name', 'tool-seo-hupuna' ); ?></th>
						<th><?php echo esc_html__( 'Variant', 'tool-seo-hupuna' ); ?></th>
						<th><?php echo esc_html__( 'Type', 'tool-seo-hupuna' ); ?></th>
						<th><?php echo esc_html__( 'Regular Price', 'tool-seo-hupuna' ); ?></th>
						<th><?php echo esc_html__( 'Sale Price', 'tool-seo-hupuna' ); ?></th>
						<th style="width: 200px;"><?php echo esc_html__( 'Actions', 'tool-seo-hupuna' ); ?></th>
						<th style="width: 100px;"><?php echo esc_html__( 'View', 'tool-seo-hupuna' ); ?></th>
					</tr>
				</thead>
				<tbody id="price-table-body">
					<tr>
						<td colspan="7"><?php echo esc_html__( 'Loading...', 'tool-seo-hupuna' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div id="pagination" class="mt-3"></div>
		</div>

		<script>
		var hupunaProductsManager = {
			currentPage: 1,
			lastSearch: '',
			ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			nonce: '<?php echo esc_js( wp_create_nonce( 'tool_seo_hupuna_products_manager_nonce' ) ); ?>',
			strings: {
				loading: '<?php echo esc_js( __( 'Loading...', 'tool-seo-hupuna' ) ); ?>',
				noProducts: '<?php echo esc_js( __( 'No products found.', 'tool-seo-hupuna' ) ); ?>',
				saving: '<?php echo esc_js( __( 'Saving...', 'tool-seo-hupuna' ) ); ?>',
				saved: '<?php echo esc_js( __( 'Saved', 'tool-seo-hupuna' ) ); ?>',
				error: '<?php echo esc_js( __( 'Error!', 'tool-seo-hupuna' ) ); ?>',
				confirmDelete: '<?php echo esc_js( __( 'Are you sure you want to delete this product?', 'tool-seo-hupuna' ) ); ?>',
				variant: '<?php echo esc_js( __( 'Variant', 'tool-seo-hupuna' ) ); ?>',
				simple: '<?php echo esc_js( __( 'Simple', 'tool-seo-hupuna' ) ); ?>',
				save: '<?php echo esc_js( __( 'Save', 'tool-seo-hupuna' ) ); ?>',
				saveAll: '<?php echo esc_js( __( 'Save All', 'tool-seo-hupuna' ) ); ?>',
				delete: '<?php echo esc_js( __( 'Delete', 'tool-seo-hupuna' ) ); ?>',
				view: '<?php echo esc_js( __( 'View', 'tool-seo-hupuna' ) ); ?>',
				prev: '<?php echo esc_js( __( 'Previous', 'tool-seo-hupuna' ) ); ?>',
				next: '<?php echo esc_js( __( 'Next', 'tool-seo-hupuna' ) ); ?>',
				page: '<?php echo esc_js( __( 'Page', 'tool-seo-hupuna' ) ); ?>',
				of: '<?php echo esc_js( __( 'of', 'tool-seo-hupuna' ) ); ?>',
				regularPrice: '<?php echo esc_js( __( 'Regular Price', 'tool-seo-hupuna' ) ); ?>',
				salePrice: '<?php echo esc_js( __( 'Sale Price', 'tool-seo-hupuna' ) ); ?>'
			}
		};

		function loadPage(page, search) {
			hupunaProductsManager.lastSearch = search;
			var tbody = document.getElementById('price-table-body');
			tbody.innerHTML = '<tr><td colspan="7">' + hupunaProductsManager.strings.loading + '</td></tr>';

			var formData = new FormData();
			formData.append('action', 'get_product_prices');
			formData.append('nonce', hupunaProductsManager.nonce);
			formData.append('page', page);
			formData.append('search', search);

			fetch(hupunaProductsManager.ajaxUrl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				tbody.innerHTML = '';
				if (!data.success || data.data.products.length === 0) {
					tbody.innerHTML = '<tr><td colspan="7">' + hupunaProductsManager.strings.noProducts + '</td></tr>';
					renderPagination(page, 0);
					return;
				}

				data.data.products.forEach(function(p) {
					if (p.type === hupunaProductsManager.strings.variant) {
						p.variants.forEach(function(v, i) {
							var tr = document.createElement('tr');
							tr.innerHTML = '<td>' + (i === 0 ? '<textarea class="edit-name" data-id="' + p.id + '" style="width: 100%; height: 100px;">' + escapeHtml(p.name) + '</textarea>' : '') + '</td>' +
								'<td style="padding:6px;">' + escapeHtml(v.name) + '</td>' +
								'<td style="padding:6px;">' + escapeHtml(p.type) + '</td>' +
								'<td style="padding:6px;"><input type="number" value="' + (v.regular_price || '') + '" data-id="' + v.id + '" data-type="regular" placeholder="' + hupunaProductsManager.strings.regularPrice + '" style="width:100px;"/></td>' +
								'<td style="padding:6px;"><input type="number" value="' + (v.sale_price || '') + '" data-id="' + v.id + '" data-type="sale" placeholder="' + hupunaProductsManager.strings.salePrice + '" style="width:100px;"/></td>' +
								'<td style="padding:6px; white-space: nowrap;"><div style="display: flex; gap: 5px; flex-wrap: wrap;">' + (i === 0 ? '<button class="delete-btn button button-link-delete" data-id="' + p.id + '">' + hupunaProductsManager.strings.delete + '</button><button class="save-all-btn button button-primary" data-id="' + p.id + '">' + hupunaProductsManager.strings.saveAll + '</button>' : '<button class="save-btn button button-primary" data-id="' + v.id + '">' + hupunaProductsManager.strings.save + '</button>') + '</div></td>' +
								'<td style="padding:6px; width: 100px; text-align: center;">' + (i === 0 ? '<a href="' + escapeHtml(p.permalink || '/?p=' + p.id) + '" target="_blank" class="button button-small">' + hupunaProductsManager.strings.view + '</a>' : '') + '</td>';
							tbody.appendChild(tr);
						});
					} else {
						var tr = document.createElement('tr');
						tr.innerHTML = '<td><textarea class="edit-name" data-id="' + p.id + '" style="width: 100%; height: 100px;">' + escapeHtml(p.name) + '</textarea></td>' +
							'<td style="padding:6px;">-</td>' +
							'<td style="padding:6px;">' + escapeHtml(p.type) + '</td>' +
							'<td style="padding:6px;"><input type="number" value="' + (p.regular_price || '') + '" data-id="' + p.id + '" data-type="regular" placeholder="' + hupunaProductsManager.strings.regularPrice + '" style="width:100px;" /></td>' +
							'<td style="padding:6px;"><input type="number" value="' + (p.sale_price || '') + '" data-id="' + p.id + '" data-type="sale" placeholder="' + hupunaProductsManager.strings.salePrice + '" style="width:100px;" /></td>' +
							'<td style="padding:6px; white-space: nowrap;"><div style="display: flex; gap: 5px; flex-wrap: wrap;"><button class="delete-btn button button-link-delete" data-id="' + p.id + '">' + hupunaProductsManager.strings.delete + '</button><button class="save-btn button button-primary" data-id="' + p.id + '">' + hupunaProductsManager.strings.save + '</button></div></td>' +
							'<td style="padding:6px; width: 100px; text-align: center;"><a href="' + escapeHtml(p.permalink || '/?p=' + p.id) + '" target="_blank" class="button button-small">' + hupunaProductsManager.strings.view + '</a></td>';
						tbody.appendChild(tr);
					}
				});

				attachEventHandlers();
				var totalPages = Math.ceil(data.data.total / data.data.per_page);
				renderPagination(page, totalPages);
				hupunaProductsManager.currentPage = page;
			})
			.catch(err => {
				tbody.innerHTML = '<tr><td colspan="7">Error: ' + err.message + '</td></tr>';
			});
		}

		function attachEventHandlers() {
			// Save single price
			document.querySelectorAll('.save-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var productId = this.dataset.id;
					var row = this.closest('tr');
					var regularInput = row.querySelector('input[data-type="regular"]');
					var saleInput = row.querySelector('input[data-type="sale"]');

					if (!regularInput) return;

					var regular_price = regularInput.value || '';
					var sale_price = saleInput ? saleInput.value || '' : '';

					var originalText = this.textContent;
					this.disabled = true;
					this.textContent = hupunaProductsManager.strings.saving;

					var formData = new FormData();
					formData.append('action', 'update_product_price');
					formData.append('nonce', hupunaProductsManager.nonce);
					formData.append('id', productId);
					formData.append('regular_price', regular_price);
					formData.append('sale_price', sale_price);

					fetch(hupunaProductsManager.ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(r => r.json())
					.then(data => {
						this.textContent = data.success ? '✓ ' + hupunaProductsManager.strings.saved : hupunaProductsManager.strings.error;
						setTimeout(function() {
							this.textContent = originalText;
							this.disabled = false;
						}.bind(this), 2000);
					})
					.catch(() => {
						this.textContent = hupunaProductsManager.strings.error;
						setTimeout(function() {
							this.textContent = originalText;
							this.disabled = false;
						}.bind(this), 2000);
					});
				});
			});

			// Save all variants
			document.querySelectorAll('.save-all-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var productId = this.dataset.id;
					var regularInputs = document.querySelectorAll('input[data-type="regular"]');
					var saleInputs = document.querySelectorAll('input[data-type="sale"]');
					var updates = [];

					regularInputs.forEach(function(input, i) {
						var id = input.dataset.id;
						var regular_price = input.value || '';
						var sale_price = saleInputs[i] ? saleInputs[i].value || '' : '';

						if (id) {
							updates.push({
								id: id,
								regular_price: regular_price,
								sale_price: sale_price
							});
						}
					});

					var originalText = this.textContent;
					this.disabled = true;
					this.textContent = hupunaProductsManager.strings.saving;

					var formData = new FormData();
					formData.append('action', 'update_multiple_prices');
					formData.append('nonce', hupunaProductsManager.nonce);
					formData.append('updates', JSON.stringify(updates));

					fetch(hupunaProductsManager.ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(r => r.json())
					.then(data => {
						this.textContent = data.success ? '✓ ' + hupunaProductsManager.strings.saved : hupunaProductsManager.strings.error;
						setTimeout(function() {
							this.textContent = originalText;
							this.disabled = false;
						}.bind(this), 2000);
					})
					.catch(() => {
						this.textContent = hupunaProductsManager.strings.error;
						setTimeout(function() {
							this.textContent = originalText;
							this.disabled = false;
						}.bind(this), 2000);
					});
				});
			});

			// Update product name
			document.querySelectorAll('.edit-name').forEach(function(textarea) {
				textarea.addEventListener('blur', function() {
					var id = this.dataset.id;
					var name = this.value;

					var formData = new FormData();
					formData.append('action', 'update_product_name');
					formData.append('nonce', hupunaProductsManager.nonce);
					formData.append('id', id);
					formData.append('name', name);

					fetch(hupunaProductsManager.ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(r => r.json())
					.then(data => {
						if (!data.success) {
							alert('❌ ' + (data.data && data.data.message ? data.data.message : 'Error updating product name'));
						}
					})
					.catch(() => {
						alert('❌ Error updating product name');
					});
				});
			});

			// Delete product
			document.querySelectorAll('.delete-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (!confirm(hupunaProductsManager.strings.confirmDelete)) return;

					var id = this.dataset.id;
					var row = this.closest('tr');

					var formData = new FormData();
					formData.append('action', 'delete_product');
					formData.append('nonce', hupunaProductsManager.nonce);
					formData.append('id', id);

					fetch(hupunaProductsManager.ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(r => r.json())
					.then(data => {
						if (data.success) {
							alert(data.data && data.data.message ? data.data.message : 'Product deleted');
							loadPage(hupunaProductsManager.currentPage, hupunaProductsManager.lastSearch);
						} else {
							alert(data.data && data.data.message ? data.data.message : 'Error deleting product');
						}
					})
					.catch(err => {
						alert('Error: ' + err.message);
					});
				});
			});
		}

		function renderPagination(page, totalPages) {
			var pagination = document.getElementById('pagination');
			pagination.innerHTML = '';
			if (totalPages <= 1) return;

			function createButton(label, pageNum, disabled) {
				var btn = document.createElement('button');
				btn.textContent = label;
				btn.className = 'button';
				btn.style.margin = '0 2px';
				if (label === page.toString()) {
					btn.className += ' button-primary';
					btn.disabled = true;
				}
				btn.disabled = disabled || false;
				btn.onclick = function() { loadPage(pageNum, hupunaProductsManager.lastSearch); };
				return btn;
			}

			if (page > 1) pagination.appendChild(createButton(hupunaProductsManager.strings.prev, page - 1));
			for (var i = 1; i <= totalPages; i++) {
				if (i === 1 || i === totalPages || Math.abs(i - page) <= 1) {
					pagination.appendChild(createButton(i, i, i === page));
				} else if ((i === 2 && page > 3) || (i === totalPages - 1 && page < totalPages - 2)) {
					var dots = document.createElement('span');
					dots.textContent = '...';
					dots.style.margin = '0 5px';
					pagination.appendChild(dots);
				}
			}
			if (page < totalPages) pagination.appendChild(createButton(hupunaProductsManager.strings.next, page + 1));
		}

		function escapeHtml(text) {
			var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
			return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
		}

		document.getElementById('search-btn').addEventListener('click', function() {
			loadPage(1, document.getElementById('search-input').value);
		});

		document.getElementById('search-input').addEventListener('keydown', function(e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				loadPage(1, this.value);
			}
		});

		loadPage(1, '');
		</script>
		<?php
	}

	/**
	 * AJAX handler: Get product prices.
	 *
	 * @return void
	 */
	public function ajax_get_product_prices() {
		check_ajax_referer( 'tool_seo_hupuna_products_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is required', 'tool-seo-hupuna' ) ) );
		}

		$page     = max( 1, intval( isset( $_POST['page'] ) ? $_POST['page'] : 1 ) );
		$search   = strtolower( sanitize_text_field( isset( $_POST['search'] ) ? $_POST['search'] : '' ) );
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$args = array(
			'status'  => 'publish',
			'limit'   => -1,
			'type'    => array( 'simple', 'variable' ),
			'orderby' => 'ID',
			'order'   => 'DESC',
		);

		$grouped_products = array();

		foreach ( wc_get_products( $args ) as $product ) {
			if ( $product->is_type( 'variable' ) ) {
				$variants = array();

				foreach ( $product->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( ! $child ) {
						continue;
					}

					$variant_name = implode(
						', ',
						array_map(
							function( $value, $key ) {
								return wc_attribute_label( $key ) . ': ' . $value;
							},
							$child->get_attributes(),
							array_keys( $child->get_attributes() )
						)
					);

					if ( ! $search || false !== strpos( strtolower( $variant_name ), $search ) || false !== strpos( strtolower( $product->get_name() ), $search ) ) {
						$variants[] = array(
							'id'            => $child->get_id(),
							'name'          => $variant_name,
							'regular_price' => $child->get_regular_price(),
							'sale_price'    => $child->get_sale_price(),
						);
					}
				}

				if ( ! empty( $variants ) ) {
					$grouped_products[] = array(
						'id'        => $product->get_id(),
						'name'      => $product->get_name(),
						'type'      => __( 'Variant', 'tool-seo-hupuna' ),
						'permalink' => get_permalink( $product->get_id() ),
						'variants'  => $variants,
					);
				}
			} else {
				if ( ! $search || false !== strpos( strtolower( $product->get_name() ), $search ) ) {
					$grouped_products[] = array(
						'id'            => $product->get_id(),
						'name'          => $product->get_name(),
						'type'          => __( 'Simple', 'tool-seo-hupuna' ),
						'permalink'     => get_permalink( $product->get_id() ),
						'regular_price' => $product->get_regular_price(),
						'sale_price'    => $product->get_sale_price(),
					);
				}
			}
		}

		$total         = count( $grouped_products );
		$paged_products = array_slice( $grouped_products, $offset, $per_page );

		wp_send_json_success(
			array(
				'products' => $paged_products,
				'total'    => $total,
				'per_page' => $per_page,
				'page'     => $page,
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

		if ( ! current_user_can( 'administrator' ) ) {
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

