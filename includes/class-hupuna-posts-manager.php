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
	 * Get current site domain.
	 *
	 * @return string Site domain.
	 */
	private function get_site_domain() {
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
			<div class="card tsh-card" style="margin-bottom: 20px;">
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
				external: '<?php echo esc_js( __( 'EXTERNAL', 'tool-seo-hupuna' ) ); ?>',
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
			var tbody = document.getElementById('tsh-posts-table-body');
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
						var typeClass = link.type === 'product' ? 'product' : link.type === 'product_cat' ? 'category' : link.type === 'external' ? 'external' : 'post';
						var typeLabel = link.type === 'product' ? hupunaPostsManager.strings.product : 
										link.type === 'product_cat' ? hupunaPostsManager.strings.category : 
										link.type === 'external' ? hupunaPostsManager.strings.external : 
										hupunaPostsManager.strings.post;
						var bgColor = link.type === 'product' ? '#e3f2fd' : link.type === 'product_cat' ? '#fff3e0' : link.type === 'external' ? '#ffebee' : '#f3e5f5';
						var badgeColor = link.type === 'product' ? '#1976d2' : link.type === 'product_cat' ? '#f57c00' : link.type === 'external' ? '#d32f2f' : '#7b1fa2';

						return '<div style="margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: ' + bgColor + ';">' +
							'<div style="margin-bottom: 5px; font-size: 11px;">' +
							'<span style="background: ' + badgeColor + '; color: #fff; padding: 2px 8px; border-radius: 3px; font-weight: 600; margin-right: 5px;">' + typeLabel + '</span>' +
							'<span style="color: #666;">' + (link.text || '(No anchor text)') + '</span>' +
							'</div>' +
							'<input type="text" class="tsh-edit-link-url" data-post-id="' + post.id + '" data-link-index="' + index + '" data-old-url="' + escapeHtml(link.url) + '" value="' + escapeHtml(link.url) + '" style="width: 100%; padding: 5px; font-size: 12px; border: 1px solid #ccc;" placeholder="URL" />' +
							'<div style="margin-top: 5px;"><a href="' + escapeHtml(link.url) + '" target="_blank" class="button button-small">' + hupunaPostsManager.strings.viewLink + '</a></div>' +
							'</div>';
					}).join('');

					tr.innerHTML = '<td style="padding:6px;">' + post.id + '</td>' +
						'<td style="padding:6px;"><strong>' + escapeHtml(post.title) + '</strong></td>' +
						'<td style="padding:6px;"><div style="max-height: 300px; overflow-y: auto;">' + linksHtml + '</div></td>' +
						'<td style="padding:6px;">' + post.date + '</td>' +
						'<td style="padding:6px; white-space: nowrap;"><div style="display: flex; gap: 5px; flex-wrap: wrap;">' +
						'<button class="tsh-save-links-btn button button-primary" data-id="' + post.id + '">' + hupunaPostsManager.strings.saveLinks + '</button>' +
						'<a href="' + escapeHtml(post.permalink) + '" target="_blank" class="button button-small">' + hupunaPostsManager.strings.viewPost + '</a>' +
						'<a href="' + escapeHtml(post.edit_link) + '" target="_blank" class="button button-small">' + hupunaPostsManager.strings.edit + '</a>' +
						'<button class="tsh-trash-post-btn button tsh-button-link-delete" data-id="' + post.id + '">' + hupunaPostsManager.strings.delete + '</button>' +
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
			document.querySelectorAll('.tsh-save-links-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var postId = this.dataset.id;
					var linkInputs = document.querySelectorAll('.tsh-edit-link-url[data-post-id="' + postId + '"]');
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

			document.querySelectorAll('.tsh-trash-post-btn').forEach(function(btn) {
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
			var pagination = document.getElementById('tsh-posts-pagination');
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

		document.getElementById('tsh-posts-search-btn').addEventListener('click', function() {
			loadPage(1, document.getElementById('tsh-posts-search-input').value);
		});

		document.getElementById('tsh-posts-clear-search-btn').addEventListener('click', function() {
			document.getElementById('tsh-posts-search-input').value = '';
			loadPage(1, '');
		});

		document.getElementById('tsh-posts-search-input').addEventListener('keydown', function(e) {
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
	 * Optimized with database-level pagination to prevent memory exhaustion.
	 *
	 * @return void
	 */
	public function ajax_get_posts_with_links() {
		check_ajax_referer( 'tool_seo_hupuna_posts_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		$page     = max( 1, intval( isset( $_POST['page'] ) ? $_POST['page'] : 1 ) );
		$search   = trim( sanitize_text_field( isset( $_POST['search'] ) ? $_POST['search'] : '' ) );
		$per_page = 20;

		// Get all public post types.
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$excluded   = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );
		$post_types = array_diff( $post_types, $excluded );

		// Build WP_Query args with database-level pagination.
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page * 3, // Fetch 3x to account for posts without links.
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => false, // We need total count.
		);

		// Use WP_Query search parameter for database-level filtering.
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$posts_with_links = array();
		$max_iterations = 5; // Safety limit to prevent infinite loops.
		$iteration = 0;
		$current_batch_page = $page;

		// Fetch posts in batches until we have enough results with links.
		while ( count( $posts_with_links ) < $per_page && $iteration < $max_iterations ) {
			if ( ! $query->have_posts() ) {
				break; // No more posts available.
			}

			// Process only the current batch (already paginated by WP_Query).
			while ( $query->have_posts() && count( $posts_with_links ) < $per_page ) {
				$query->the_post();
				$post_id = get_the_ID();
				$content = get_the_content();
				$title   = get_the_title();

				// Extract links from this post's content (heavy regex only on paginated results).
				$links = $this->extract_internal_links_from_content( $content, $post_id );

				// Only include posts that have internal links.
				if ( ! empty( $links ) ) {
					// Apply search filter if provided (already filtered by WP_Query 's', but double-check URL matches).
					$should_include = true;
					if ( ! empty( $search ) ) {
						$should_include = false;
						$search_lower = strtolower( $search );

						// Check title match.
						if ( false !== strpos( strtolower( $title ), $search_lower ) ) {
							$should_include = true;
						}

						// Check URL match in links.
						if ( ! $should_include ) {
							foreach ( $links as $link ) {
								$link_url = strtolower( trim( $link['url'] ) );
								if ( false !== strpos( $link_url, $search_lower ) || false !== strpos( $search_lower, $link_url ) ) {
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

			// If we don't have enough results, fetch next batch.
			if ( count( $posts_with_links ) < $per_page ) {
				$current_batch_page++;
				$args['paged'] = $current_batch_page;
				$query = new WP_Query( $args );
				$iteration++;
			} else {
				break; // We have enough results.
			}
		}

		wp_reset_postdata();

		// Calculate total count (approximate for performance).
		// Note: Exact count would require scanning all posts, which defeats the purpose.
		// We use found_posts as an approximation.
		$total = $query->found_posts;

		// Return only the requested page size.
		$paged_posts = array_slice( $posts_with_links, 0, $per_page );

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
	 * Extract internal links (product, category, post) from post content.
	 * This method contains the heavy regex logic and is only called on paginated results.
	 *
	 * @param string $content Post content.
	 * @param int    $post_id Post ID.
	 * @return array Array of link data.
	 */
	private function extract_internal_links_from_content( $content, $post_id ) {
		if ( empty( $content ) ) {
			return array();
		}

		$links      = array();
		$found_urls = array();

		// Pattern 1: Find all <a> links in content.
		preg_match_all( '/<a[^>]+href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $all_matches, PREG_SET_ORDER );

		// Pattern 2: Find plain text URLs (not wrapped in tags) - CRITICAL FIX.
		$text_only = wp_strip_all_tags( $content );
		preg_match_all( '/(https?:\/\/[^\s<>"\'\]\)]+)/i', $text_only, $plain_urls, PREG_SET_ORDER );
		
		// Combine both patterns.
		$all_urls = array();
		foreach ( $all_matches as $match ) {
			$all_urls[] = array( 'url' => trim( $match[1] ), 'text' => strip_tags( $match[2] ), 'is_tag' => true );
		}
		foreach ( $plain_urls as $match ) {
			$url = trim( $match[1], '.,;:!?)' );
			// Only add if not already in anchor tags.
			$exists = false;
			foreach ( $all_urls as $existing ) {
				if ( $existing['url'] === $url ) {
					$exists = true;
					break;
				}
			}
			if ( ! $exists ) {
				$all_urls[] = array( 'url' => $url, 'text' => '', 'is_tag' => false );
			}
		}

		if ( empty( $all_urls ) ) {
			return array();
		}

		foreach ( $all_urls as $url_data ) {
			$url       = trim( $url_data['url'] );
			$link_text = $url_data['is_tag'] ? strip_tags( $url_data['text'] ) : '';

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
				$link_id         = intval( $id_match[1] );
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
									$link_id         = get_the_ID();
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

			// Normalize URL for comparison.
			$normalized_url = $this->normalize_url_for_comparison( $url );
			
			// Add link if it's internal (product, product_cat, post) OR external.
			if ( in_array( $link_type, array( 'product', 'product_cat', 'post' ), true ) && $link_id ) {
				// Internal link.
				$links[] = array(
					'url'  => $normalized_url,
					'text' => trim( $link_text ) ?: $link_name ?: $normalized_url,
					'type' => $link_type,
					'id'   => $link_id,
				);
				$found_urls[] = $normalized_url;
			} elseif ( $this->is_external_url( $normalized_url ) ) {
				// External link - add it too!
				$links[] = array(
					'url'  => $normalized_url,
					'text' => trim( $link_text ) ?: $normalized_url,
					'type' => 'external',
					'id'   => null,
				);
				$found_urls[] = $normalized_url;
			}
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

