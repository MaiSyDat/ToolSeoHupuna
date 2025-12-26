<?php
/**
 * Posts with Links Manager Class
 * Manages posts/pages with internal links (product, product_cat, post).
 * Extended to support all post types.
 *
 * @package HupunaExternalLinkScanner
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
			__( 'Posts with Links', 'tool-seo-hupuna' ),
			__( 'Posts with Links', 'tool-seo-hupuna' ),
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
		<div class="wrap">
			<h1><?php echo esc_html__( 'Posts with Links', 'tool-seo-hupuna' ); ?></h1>
			<div class="card" style="margin-bottom: 20px;">
				<p style="margin: 0;">
					<input type="text" id="search-input" class="regular-text" placeholder="<?php echo esc_attr__( 'Paste URL to find posts containing this link...', 'tool-seo-hupuna' ); ?>" style="width: calc(100% - 200px); max-width: 800px; margin-right: 10px;" />
					<button id="search-btn" class="button button-primary"><?php echo esc_html__( 'Search', 'tool-seo-hupuna' ); ?></button>
					<button id="clear-search-btn" class="button"><?php echo esc_html__( 'Clear Search', 'tool-seo-hupuna' ); ?></button>
				</p>
			</div>
			<table id="posts-table" class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 60px;"><?php echo esc_html__( 'ID', 'tool-seo-hupuna' ); ?></th>
						<th style="width: 200px;"><?php echo esc_html__( 'Title', 'tool-seo-hupuna' ); ?></th>
						<th><?php echo esc_html__( 'Links in Content', 'tool-seo-hupuna' ); ?></th>
						<th style="width: 120px;"><?php echo esc_html__( 'Date', 'tool-seo-hupuna' ); ?></th>
						<th style="width: 200px;"><?php echo esc_html__( 'Actions', 'tool-seo-hupuna' ); ?></th>
					</tr>
				</thead>
				<tbody id="posts-table-body">
					<tr>
						<td colspan="5"><?php echo esc_html__( 'Loading...', 'tool-seo-hupuna' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div id="pagination" class="mt-3"></div>
		</div>

		<script>
		var hupunaPostsManager = {
			currentPage: 1,
			lastSearch: '',
			ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			nonce: '<?php echo esc_js( wp_create_nonce( 'tool_seo_hupuna_posts_manager_nonce' ) ); ?>',
			strings: {
				loading: '<?php echo esc_js( __( 'Loading...', 'tool-seo-hupuna' ) ); ?>',
				noPosts: '<?php echo esc_js( __( 'No posts found.', 'tool-seo-hupuna' ) ); ?>',
				saving: '<?php echo esc_js( __( 'Saving...', 'tool-seo-hupuna' ) ); ?>',
				saved: '<?php echo esc_js( __( 'Saved', 'tool-seo-hupuna' ) ); ?>',
				error: '<?php echo esc_js( __( 'Error!', 'tool-seo-hupuna' ) ); ?>',
				confirmDelete: '<?php echo esc_js( __( 'Are you sure you want to delete this post?', 'tool-seo-hupuna' ) ); ?>',
				product: '<?php echo esc_js( __( 'PRODUCT', 'tool-seo-hupuna' ) ); ?>',
				category: '<?php echo esc_js( __( 'CATEGORY', 'tool-seo-hupuna' ) ); ?>',
				post: '<?php echo esc_js( __( 'POST', 'tool-seo-hupuna' ) ); ?>',
				viewLink: '<?php echo esc_js( __( 'View Link', 'tool-seo-hupuna' ) ); ?>',
				saveLinks: '<?php echo esc_js( __( 'Save Links', 'tool-seo-hupuna' ) ); ?>',
				viewPost: '<?php echo esc_js( __( 'View Post', 'tool-seo-hupuna' ) ); ?>',
				edit: '<?php echo esc_js( __( 'Edit', 'tool-seo-hupuna' ) ); ?>',
				delete: '<?php echo esc_js( __( 'Delete', 'tool-seo-hupuna' ) ); ?>',
				prev: '<?php echo esc_js( __( 'Previous', 'tool-seo-hupuna' ) ); ?>',
				next: '<?php echo esc_js( __( 'Next', 'tool-seo-hupuna' ) ); ?>',
				page: '<?php echo esc_js( __( 'Page', 'tool-seo-hupuna' ) ); ?>',
				of: '<?php echo esc_js( __( 'of', 'tool-seo-hupuna' ) ); ?>'
			}
		};

		function loadPage(page, search) {
			hupunaPostsManager.lastSearch = search;
			var tbody = document.getElementById('posts-table-body');
			tbody.innerHTML = '<tr><td colspan="5">' + hupunaPostsManager.strings.loading + '</td></tr>';

			var formData = new FormData();
			formData.append('action', 'get_posts_with_links');
			formData.append('nonce', hupunaPostsManager.nonce);
			formData.append('page', page);
			formData.append('search', search);

			fetch(hupunaPostsManager.ajaxUrl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				tbody.innerHTML = '';
				if (!data.success || data.data.posts.length === 0) {
					tbody.innerHTML = '<tr><td colspan="5">' + hupunaPostsManager.strings.noPosts + '</td></tr>';
					renderPagination(page, 0);
					return;
				}

				data.data.posts.forEach(function(post) {
					var tr = document.createElement('tr');
					var linksHtml = post.links.map(function(link, index) {
						var typeClass = link.type === 'product' ? 'product' : link.type === 'product_cat' ? 'category' : 'post';
						var typeLabel = link.type === 'product' ? hupunaPostsManager.strings.product : 
										link.type === 'product_cat' ? hupunaPostsManager.strings.category : 
										hupunaPostsManager.strings.post;
						var bgColor = link.type === 'product' ? '#e3f2fd' : link.type === 'product_cat' ? '#fff3e0' : '#f3e5f5';
						var badgeColor = link.type === 'product' ? '#1976d2' : link.type === 'product_cat' ? '#f57c00' : '#7b1fa2';

						return '<div style="margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: ' + bgColor + ';">' +
							'<div style="margin-bottom: 5px; font-size: 11px;">' +
							'<span style="background: ' + badgeColor + '; color: #fff; padding: 2px 8px; border-radius: 3px; font-weight: 600; margin-right: 5px;">' + typeLabel + '</span>' +
							'<span style="color: #666;">' + (link.text || '(no text)') + '</span>' +
							'</div>' +
							'<input type="text" class="edit-link-url" data-post-id="' + post.id + '" data-link-index="' + index + '" data-old-url="' + escapeHtml(link.url) + '" value="' + escapeHtml(link.url) + '" style="width: 100%; padding: 5px; font-size: 12px; border: 1px solid #ccc;" placeholder="URL" />' +
							'<div style="margin-top: 5px;"><a href="' + escapeHtml(link.url) + '" target="_blank" class="button button-small">' + hupunaPostsManager.strings.viewLink + '</a></div>' +
							'</div>';
					}).join('');

					tr.innerHTML = '<td style="padding:6px;">' + post.id + '</td>' +
						'<td style="padding:6px;"><strong>' + escapeHtml(post.title) + '</strong></td>' +
						'<td style="padding:6px;"><div style="max-height: 300px; overflow-y: auto;">' + linksHtml + '</div></td>' +
						'<td style="padding:6px;">' + post.date + '</td>' +
						'<td style="padding:6px; white-space: nowrap;"><div style="display: flex; gap: 5px; flex-wrap: wrap;">' +
						'<button class="save-links-btn button button-primary" data-id="' + post.id + '">' + hupunaPostsManager.strings.saveLinks + '</button>' +
						'<a href="' + escapeHtml(post.permalink) + '" target="_blank" class="button button-small">' + hupunaPostsManager.strings.viewPost + '</a>' +
						'<a href="' + escapeHtml(post.edit_link) + '" target="_blank" class="button button-small">' + hupunaPostsManager.strings.edit + '</a>' +
						'<button class="trash-post-btn button button-link-delete" data-id="' + post.id + '">' + hupunaPostsManager.strings.delete + '</button>' +
						'</div></td>';
					tbody.appendChild(tr);
				});

				attachEventHandlers();
				var totalPages = Math.ceil(data.data.total / data.data.per_page);
				renderPagination(page, totalPages);
				hupunaPostsManager.currentPage = page;
			})
			.catch(err => {
				tbody.innerHTML = '<tr><td colspan="5">Error: ' + err.message + '</td></tr>';
			});
		}

		function attachEventHandlers() {
			document.querySelectorAll('.save-links-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var postId = this.dataset.id;
					var linkInputs = document.querySelectorAll('.edit-link-url[data-post-id="' + postId + '"]');
					var links = [];
					linkInputs.forEach(function(input) {
						links.push({
							old_url: input.dataset.oldUrl,
							new_url: input.value.trim()
						});
					});

					var originalText = this.textContent;
					this.disabled = true;
					this.textContent = hupunaPostsManager.strings.saving;

					var formData = new FormData();
					formData.append('action', 'update_post_links');
					formData.append('nonce', hupunaPostsManager.nonce);
					formData.append('id', postId);
					formData.append('links', JSON.stringify(links));

					fetch(hupunaPostsManager.ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(r => r.json())
					.then(data => {
						this.textContent = data.success ? '✓ ' + hupunaPostsManager.strings.saved : hupunaPostsManager.strings.error;
						if (data.success) {
							linkInputs.forEach(function(input) {
								input.dataset.oldUrl = input.value.trim();
							});
							setTimeout(function() {
								loadPage(hupunaPostsManager.currentPage, hupunaPostsManager.lastSearch);
							}, 1000);
						}
						setTimeout(function() {
							this.textContent = originalText;
							this.disabled = false;
						}.bind(this), 2000);
					})
					.catch(() => {
						this.textContent = hupunaPostsManager.strings.error;
						setTimeout(function() {
							this.textContent = originalText;
							this.disabled = false;
						}.bind(this), 2000);
					});
				});
			});

			document.querySelectorAll('.trash-post-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (!confirm(hupunaPostsManager.strings.confirmDelete)) return;
					var id = this.dataset.id;

					var formData = new FormData();
					formData.append('action', 'trash_post');
					formData.append('nonce', hupunaPostsManager.nonce);
					formData.append('id', id);

					fetch(hupunaPostsManager.ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(r => r.json())
					.then(data => {
						if (data.success) {
							alert(data.data.message || 'Post deleted');
							loadPage(hupunaPostsManager.currentPage, hupunaPostsManager.lastSearch);
						} else {
							alert(data.data.message || 'Error deleting post');
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
				btn.onclick = function() { loadPage(pageNum, hupunaPostsManager.lastSearch); };
				return btn;
			}

			if (page > 1) pagination.appendChild(createButton(hupunaPostsManager.strings.prev, page - 1));
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
			if (page < totalPages) pagination.appendChild(createButton(hupunaPostsManager.strings.next, page + 1));
		}

		function escapeHtml(text) {
			var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
			return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
		}

		document.getElementById('search-btn').addEventListener('click', function() {
			loadPage(1, document.getElementById('search-input').value);
		});

		document.getElementById('clear-search-btn').addEventListener('click', function() {
			document.getElementById('search-input').value = '';
			loadPage(1, '');
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
	 * AJAX handler: Get posts with links.
	 *
	 * @return void
	 */
	public function ajax_get_posts_with_links() {
		check_ajax_referer( 'tool_seo_hupuna_posts_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		$page    = max( 1, intval( isset( $_POST['page'] ) ? $_POST['page'] : 1 ) );
		$search  = trim( sanitize_text_field( isset( $_POST['search'] ) ? $_POST['search'] : '' ) );
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		// Get all public post types (extended from original code).
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$excluded   = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );
		$post_types = array_diff( $post_types, $excluded );

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$posts_with_links = array();
		$query            = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id  = get_the_ID();
				$content  = get_the_content();
				$title    = get_the_title();
				$post_type = get_post_type( $post_id );

				$links      = array();
				$found_urls = array();

				// Find all <a> links in content.
				preg_match_all( '/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $all_matches, PREG_SET_ORDER );

				if ( ! empty( $all_matches ) ) {
					foreach ( $all_matches as $match ) {
						$url      = trim( $match[1] );
						$link_text = strip_tags( $match[2] );

						// Skip if already processed.
						if ( in_array( $url, $found_urls, true ) ) {
							continue;
						}

						// Skip mailto, tel, javascript, #.
						if ( preg_match( '/^(mailto:|tel:|javascript:|#)/i', $url ) ) {
							continue;
						}

						$link_type = 'other';
						$link_id   = null;
						$link_name = '';

						// Check URL contains post ID (?p=).
						if ( preg_match( '/(?:p=)(\d+)/', $url, $id_match ) ) {
							$link_id   = intval( $id_match[1] );
							$linked_post_type = get_post_type( $link_id );

							if ( $linked_post_type === 'product' && function_exists( 'wc_get_product' ) ) {
								$product = wc_get_product( $link_id );
								if ( $product ) {
									$link_type = 'product';
									$link_name = $product->get_name();
								}
							} elseif ( $linked_post_type === 'post' ) {
								$link_type = 'post';
								$link_name = get_the_title( $link_id );
							}
						} else {
							// Parse URL to determine link type.
							$parsed_url = wp_parse_url( $url );
							$path       = isset( $parsed_url['path'] ) ? trim( $parsed_url['path'], '/' ) : '';

							if ( empty( $path ) ) {
								continue;
							}

							$path_parts = explode( '/', $path );

							// Check category/tag.
							$term = null;
							foreach ( $path_parts as $part ) {
								if ( ! empty( $part ) ) {
									$term = get_term_by( 'slug', $part, 'product_cat' );
									if ( $term ) {
										$link_type = 'product_cat';
										$link_id   = $term->term_id;
										$link_name = $term->name;
										break;
									}

									$term = get_term_by( 'slug', $part, 'product_tag' );
									if ( $term ) {
										$link_type = 'product_cat';
										$link_id   = $term->term_id;
										$link_name = $term->name;
										break;
									}
								}
							}

							// If not category, try product or post.
							if ( $link_type === 'other' ) {
								$slug = end( $path_parts );

								if ( $slug && ! empty( $slug ) ) {
									$post_obj = get_page_by_path( $path, OBJECT, 'product' );
									if ( ! $post_obj && $slug ) {
										$post_obj = get_page_by_path( $slug, OBJECT, 'product' );
									}

									if ( $post_obj ) {
										$link_id = $post_obj->ID;
										if ( get_post_type( $link_id ) === 'product' && function_exists( 'wc_get_product' ) ) {
											$product = wc_get_product( $link_id );
											if ( $product ) {
												$link_type = 'product';
												$link_name = $product->get_name();
											}
										}
									} else {
										$post_obj = get_page_by_path( $path, OBJECT, 'post' );
										if ( ! $post_obj && $slug ) {
											$post_obj = get_page_by_path( $slug, OBJECT, 'post' );
										}

										if ( $post_obj ) {
											$link_id = $post_obj->ID;
											if ( get_post_type( $link_id ) === 'post' ) {
												$link_type = 'post';
												$link_name = get_the_title( $link_id );
											}
										} else {
											$query_link = new WP_Query(
												array(
													'name'           => $slug,
													'post_type'      => array( 'product', 'post' ),
													'posts_per_page' => 1,
												)
											);
											if ( $query_link->have_posts() ) {
												$query_link->the_post();
												$link_id = get_the_ID();
												$linked_post_type = get_post_type( $link_id );

												if ( $linked_post_type === 'product' && function_exists( 'wc_get_product' ) ) {
													$product = wc_get_product( $link_id );
													if ( $product ) {
														$link_type = 'product';
														$link_name = $product->get_name();
													}
												} elseif ( $linked_post_type === 'post' ) {
													$link_type = 'post';
													$link_name = get_the_title( $link_id );
												}
												wp_reset_postdata();
											}
										}
									}
								}
							}
						}

						// Add link if product, product_cat, or post.
						if ( in_array( $link_type, array( 'product', 'product_cat', 'post' ), true ) && $link_id ) {
							$links[] = array(
								'url'  => $url,
								'text' => trim( $link_text ) ?: $link_name ?: $url,
								'type' => $link_type,
								'id'   => $link_id,
							);
							$found_urls[] = $url;
						}
					}
				}

				// Only include posts with links.
				if ( ! empty( $links ) ) {
					$should_include = false;

					if ( ! $search ) {
						$should_include = true;
					} else {
						if ( false !== strpos( strtolower( $title ), strtolower( $search ) ) ) {
							$should_include = true;
						}

						if ( ! $should_include ) {
							$search_normalized = trim( $search );
							foreach ( $links as $link ) {
								$link_url_normalized = trim( $link['url'] );
								if ( false !== strpos( $link_url_normalized, $search_normalized ) ||
									false !== strpos( $search_normalized, $link_url_normalized ) ) {
									$should_include = true;
									break;
								}
							}
						}
					}

					if ( $should_include ) {
						$posts_with_links[] = array(
							'id'        => $post_id,
							'title'     => $title,
							'permalink' => get_permalink( $post_id ),
							'edit_link' => get_edit_post_link( $post_id, '' ),
							'date'      => get_the_date( 'd/m/Y' ),
							'links'     => $links,
						);
					}
				}
			}
			wp_reset_postdata();
		}

		$total       = count( $posts_with_links );
		$paged_posts = array_slice( $posts_with_links, $offset, $per_page );

		wp_send_json_success(
			array(
				'posts'    => $paged_posts,
				'total'    => $total,
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
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

