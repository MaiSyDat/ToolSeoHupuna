<?php
/**
 * LLMs.txt Manager Class
 * Manages llms.txt virtual file for LLM crawlers.
 *
 * @package ToolSeoHupuna
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LLMs Manager class for managing llms.txt content.
 */
class Hupuna_Llms_Manager {

	/**
	 * Option key for storing llms.txt settings.
	 *
	 * @var string
	 */
	private $option_key = 'hupuna_llms_settings';

	/**
	 * Query var for rewrite rule.
	 *
	 * @var string
	 */
	private $query_var = 'hupuna_llms_txt';

	/**
	 * Cache key for generated content.
	 *
	 * @var string
	 */
	private $cache_key = 'hupuna_llms_content';

	/**
	 * Cache expiration (uses plugin constant).
	 *
	 * @var int
	 */
	private $cache_expiration;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Use plugin cache expiration constant if available, otherwise default to 1 hour.
		$this->cache_expiration = defined( 'TOOL_SEO_HUPUNA_CACHE_EXPIRATION' ) ? TOOL_SEO_HUPUNA_CACHE_EXPIRATION : HOUR_IN_SECONDS;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_save_llms_txt', array( $this, 'ajax_save_settings' ) );
		add_action( 'admin_init', array( $this, 'check_physical_file' ) );
		
		// Flush rewrite rules on activation or when settings are saved.
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ) );
	}

	/**
	 * Add rewrite rule for llms.txt.
	 *
	 * @return void
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . $this->query_var . '=1', 'top' );
		add_rewrite_tag( '%' . $this->query_var . '%', '([^&]+)' );
	}

	/**
	 * Serve llms.txt content.
	 *
	 * @return void
	 */
	public function serve_llms_txt() {
		$llms_request = get_query_var( $this->query_var );
		
		if ( ! $llms_request ) {
			return;
		}

		// Try to get cached content.
		$content = get_transient( $this->cache_key );
		
		if ( false === $content ) {
			// Get settings.
			$settings = get_option( $this->option_key, array() );
			
			// Generate content.
			$content = $this->generate_llms_content( $settings );
			
			// Cache content for 1 hour.
			set_transient( $this->cache_key, $content, $this->cache_expiration );
		}
		
		// Security: Ensure content is plain text only (strip any potential HTML/scripts).
		$content = wp_strip_all_tags( $content );
		
		// Set strict headers for security.
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );
		
		// Output content.
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already sanitized above
		exit;
	}

	/**
	 * Generate llms.txt content from settings.
	 *
	 * @param array $settings Settings array.
	 * @return string Generated content.
	 */
	private function generate_llms_content( $settings ) {
		$content = '';
		
		// Site Title (H1).
		$site_title = ! empty( $settings['site_title'] ) ? $settings['site_title'] : get_bloginfo( 'name' );
		if ( ! empty( $site_title ) ) {
			$content .= '# ' . $site_title . "\n\n";
		}
		
		// Introduction (Blockquote).
		$intro = ! empty( $settings['introduction'] ) ? trim( $settings['introduction'] ) : '';
		if ( ! empty( $intro ) ) {
			$content .= $this->format_blockquote( $intro ) . "\n";
		}
		
		// Auto-fetched Posts/Pages by Post Type (in order).
		$enabled_post_types = ! empty( $settings['enabled_post_types'] ) && is_array( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array();
		$section_order = ! empty( $settings['section_order'] ) && is_array( $settings['section_order'] ) ? $settings['section_order'] : array();
		$posts_limit = ! empty( $settings['posts_limit'] ) ? intval( $settings['posts_limit'] ) : 50;
		
		// Merge order with enabled types, remove duplicates while preserving order.
		$ordered_types = array_unique( array_merge( $section_order, $enabled_post_types ) );
		
		// Cache post type objects to avoid repeated lookups.
		$post_type_objects = array();
		
		foreach ( $ordered_types as $post_type_name ) {
			if ( ! in_array( $post_type_name, $enabled_post_types, true ) ) {
				continue;
			}
			
			// Use cached object if available.
			if ( ! isset( $post_type_objects[ $post_type_name ] ) ) {
				$post_type_obj = get_post_type_object( $post_type_name );
				if ( ! $post_type_obj ) {
					continue;
				}
				$post_type_objects[ $post_type_name ] = $post_type_obj;
			} else {
				$post_type_obj = $post_type_objects[ $post_type_name ];
			}
			
			// Get posts for this post type - only fetch needed fields.
			$posts = get_posts( array(
				'post_type'      => $post_type_name,
				'post_status'    => 'publish',
				'posts_per_page' => $posts_limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids', // Only get IDs for better performance.
			) );
			
			if ( ! empty( $posts ) ) {
				$section_title = $post_type_obj->label;
				$content .= '## ' . $section_title . "\n\n";
				
				foreach ( $posts as $post_id ) {
					$title = get_the_title( $post_id );
					$url = get_permalink( $post_id );
					
					if ( empty( $title ) || empty( $url ) ) {
						continue;
					}
					
					// Get excerpt efficiently - only fetch if needed.
					$post = get_post( $post_id, ARRAY_A );
					$excerpt = '';
					if ( ! empty( $post['post_excerpt'] ) ) {
						$excerpt = wp_strip_all_tags( $post['post_excerpt'] );
					} elseif ( ! empty( $post['post_content'] ) ) {
						$excerpt = wp_trim_words( wp_strip_all_tags( $post['post_content'] ), 30, '...' );
					}
					
					$content .= '- [' . $title . '](' . $url . ')';
					if ( ! empty( $excerpt ) ) {
						$content .= ': ' . $excerpt;
					}
					$content .= "\n";
				}
				
				$content .= "\n";
			}
		}
		
		// Custom Manual Links Section.
		$links = ! empty( $settings['links'] ) && is_array( $settings['links'] ) ? $settings['links'] : array();
		if ( ! empty( $links ) ) {
			$content .= "## " . __( 'Main Links', 'tool-seo-hupuna' ) . "\n\n";
			
			foreach ( $links as $link ) {
				if ( empty( $link['title'] ) || empty( $link['url'] ) ) {
					continue;
				}
				
				$title = $link['title'];
				$url = $link['url'];
				$description = ! empty( $link['description'] ) ? $link['description'] : '';
				
				$content .= '- [' . $title . '](' . $url . ')';
				if ( ! empty( $description ) ) {
					$content .= ': ' . trim( $description );
				}
				$content .= "\n";
			}
			
			$content .= "\n";
		}
		
		// Footer/Extra Content.
		$footer = ! empty( $settings['footer'] ) ? trim( $settings['footer'] ) : '';
		if ( ! empty( $footer ) ) {
			$content .= "## " . __( 'Optional Output Section', 'tool-seo-hupuna' ) . "\n\n";
			$content .= $footer . "\n";
		}
		
		return $content;
	}

	/**
	 * Format text as blockquote (each line with > prefix).
	 *
	 * @param string $text Text to format.
	 * @return string Formatted blockquote text.
	 */
	private function format_blockquote( $text ) {
		$lines = explode( "\n", trim( $text ) );
		$formatted = '';
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) ) {
				$formatted .= '> ' . $line . "\n";
			}
		}
		return $formatted;
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tool-seo-hupuna',
			__( 'LLMs.txt Manager', 'tool-seo-hupuna' ),
			__( 'LLMs.txt Manager', 'tool-seo-hupuna' ),
			'manage_options',
			'tool-seo-hupuna-llms',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check by page parameter (more reliable than hook name).
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'tool-seo-hupuna-llms' !== $page ) {
			return;
		}

		// Enqueue admin CSS with high priority to override WordPress defaults.
		wp_enqueue_style(
			'tool-seo-hupuna-admin',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TOOL_SEO_HUPUNA_VERSION,
			'all'
		);

		// Enqueue custom JS with proper dependencies.
		wp_enqueue_script(
			'tool-seo-hupuna-llms',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/js/admin-llms.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			TOOL_SEO_HUPUNA_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'tool-seo-hupuna-llms',
			'toolSeoHupunaLlms',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tool_seo_hupuna_llms_nonce' ),
				'strings' => array(
					'saving'     => __( 'Saving...', 'tool-seo-hupuna' ),
					'saved'      => __( 'Settings saved successfully!', 'tool-seo-hupuna' ),
					'error'      => __( 'Error saving settings.', 'tool-seo-hupuna' ),
					'deleteRow'  => __( 'Delete', 'tool-seo-hupuna' ),
					'addRow'     => __( 'Add Link', 'tool-seo-hupuna' ),
					'previewUrl' => home_url( 'llms.txt' ),
				),
			)
		);
	}

	/**
	 * AJAX handler: Save settings.
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Access denied', 'tool-seo-hupuna' ),
				)
			);
		}

		// Verify nonce.
		check_ajax_referer( 'tool_seo_hupuna_llms_nonce', 'nonce' );

		// Sanitize and prepare settings.
		$settings = array(
			'site_title'        => isset( $_POST['site_title'] ) ? sanitize_text_field( wp_unslash( $_POST['site_title'] ) ) : '',
			'introduction'      => isset( $_POST['introduction'] ) ? sanitize_textarea_field( wp_unslash( $_POST['introduction'] ) ) : '',
			'footer'            => isset( $_POST['footer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['footer'] ) ) : '',
			'links'             => array(),
			'posts_limit'       => isset( $_POST['posts_limit'] ) ? intval( $_POST['posts_limit'] ) : 50,
		);
		
		// Handle enabled_post_types - can be array from form or JSON from AJAX.
		if ( isset( $_POST['enabled_post_types'] ) ) {
			if ( is_array( $_POST['enabled_post_types'] ) ) {
				$settings['enabled_post_types'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_post_types'] ) );
			} else {
				// Try to decode as JSON.
				$decoded = json_decode( wp_unslash( $_POST['enabled_post_types'] ), true );
				$settings['enabled_post_types'] = is_array( $decoded ) ? array_map( 'sanitize_text_field', $decoded ) : array();
			}
		} else {
			$settings['enabled_post_types'] = array();
		}
		
		// Handle section_order - can be array from form or JSON from AJAX.
		if ( isset( $_POST['section_order'] ) ) {
			if ( is_array( $_POST['section_order'] ) ) {
				$settings['section_order'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['section_order'] ) );
			} else {
				// Try to decode as JSON.
				$decoded = json_decode( wp_unslash( $_POST['section_order'] ), true );
				$settings['section_order'] = is_array( $decoded ) ? array_map( 'sanitize_text_field', $decoded ) : array();
			}
		} else {
			$settings['section_order'] = array();
		}

		// Process links array.
		if ( isset( $_POST['links'] ) && is_array( $_POST['links'] ) ) {
			foreach ( $_POST['links'] as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}

				$title = isset( $link['title'] ) ? sanitize_text_field( wp_unslash( $link['title'] ) ) : '';
				$url = isset( $link['url'] ) ? esc_url_raw( wp_unslash( $link['url'] ) ) : '';
				$description = isset( $link['description'] ) ? sanitize_text_field( wp_unslash( $link['description'] ) ) : '';

				// Only add if title and URL are provided.
				if ( ! empty( $title ) && ! empty( $url ) ) {
					$settings['links'][] = array(
						'title'       => $title,
						'url'         => $url,
						'description' => $description,
					);
				}
			}
		}

		update_option( $this->option_key, $settings );

		// Clear cache when settings are saved.
		delete_transient( $this->cache_key );

		// Flush rewrite rules only if needed.
		flush_rewrite_rules( false );
		
		wp_send_json_success(
			array(
				'message' => __( 'Settings saved successfully!', 'tool-seo-hupuna' ),
			)
		);
	}

	/**
	 * Maybe flush rewrite rules.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules() {
		$flushed = get_option( 'hupuna_llms_rewrite_flushed', false );
		if ( ! $flushed ) {
			flush_rewrite_rules( false );
			update_option( 'hupuna_llms_rewrite_flushed', true );
		}
	}

	/**
	 * Check if physical llms.txt file exists and display warning.
	 *
	 * @return void
	 */
	public function check_physical_file() {
		$physical_file = ABSPATH . 'llms.txt';
		
		// Check if physical file exists.
		if ( file_exists( $physical_file ) ) {
			add_action( 'admin_notices', array( $this, 'display_physical_file_warning' ) );
		}
	}

	/**
	 * Display admin notice about physical llms.txt file.
	 *
	 * @return void
	 */
	public function display_physical_file_warning() {
		$physical_file = ABSPATH . 'llms.txt';
		
		// Only show to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php echo esc_html__( 'Tool SEO Hupuna Warning:', 'tool-seo-hupuna' ); ?></strong>
				<?php
				printf(
					/* translators: %s: file path */
					esc_html__( 'A physical llms.txt file exists at %s. This file will override the virtual llms.txt generated by this plugin. Please delete the physical file for the plugin to work correctly.', 'tool-seo-hupuna' ),
					'<code>' . esc_html( $physical_file ) . '</code>'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tool-seo-hupuna-llms&delete_physical_llms=1' ), 'delete_physical_llms', 'llms_nonce' ) ); ?>" class="button button-primary">
					<?php echo esc_html__( 'Delete Physical File', 'tool-seo-hupuna' ); ?>
				</a>
			</p>
		</div>
		<?php
		
		// Handle file deletion if requested.
		if ( isset( $_GET['delete_physical_llms'] ) && isset( $_GET['llms_nonce'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['llms_nonce'] ) ), 'delete_physical_llms' ) ) {
				if ( file_exists( $physical_file ) && is_writable( $physical_file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					if ( unlink( $physical_file ) ) {
						?>
						<div class="notice notice-success is-dismissible">
							<p><?php echo esc_html__( 'Physical llms.txt file deleted successfully!', 'tool-seo-hupuna' ); ?></p>
						</div>
						<?php
					} else {
						?>
						<div class="notice notice-error is-dismissible">
							<p><?php echo esc_html__( 'Failed to delete physical llms.txt file. Please delete it manually.', 'tool-seo-hupuna' ); ?></p>
						</div>
						<?php
					}
				}
			}
		}
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

		// Get current settings.
		$settings = get_option( $this->option_key, array() );
		$site_title = ! empty( $settings['site_title'] ) ? $settings['site_title'] : get_bloginfo( 'name' );
		$introduction = ! empty( $settings['introduction'] ) ? $settings['introduction'] : '';
		$footer = ! empty( $settings['footer'] ) ? $settings['footer'] : '';
		$links = ! empty( $settings['links'] ) && is_array( $settings['links'] ) ? $settings['links'] : array();
		
		// Auto-fetch settings.
		$enabled_post_types = ! empty( $settings['enabled_post_types'] ) && is_array( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array();
		$section_order = ! empty( $settings['section_order'] ) && is_array( $settings['section_order'] ) ? $settings['section_order'] : array();
		$posts_limit = ! empty( $settings['posts_limit'] ) ? intval( $settings['posts_limit'] ) : 50;
		
		// Get available post types.
		$available_post_types = get_post_types( array( 'public' => true ), 'objects' );
		$excluded_types = array( 'attachment', 'revision', 'nav_menu_item' );
		$available_post_types = array_filter( $available_post_types, function( $post_type ) use ( $excluded_types ) {
			return ! in_array( $post_type->name, $excluded_types, true );
		} );

		?>
		<div class="wrap tsh-wrap">
			<h1><?php echo esc_html__( 'LLMs.txt Manager', 'tool-seo-hupuna' ); ?></h1>

			<div class="tsh-panel" style="max-width: 100%; margin-top: 20px;">
				<p class="description">
					<?php echo esc_html__( 'Your llms.txt file:', 'tool-seo-hupuna' ); ?>
					<strong><a href="<?php echo esc_url( home_url( 'llms.txt' ) ); ?>" target="_blank"><?php echo esc_url( home_url( 'llms.txt' ) ); ?></a></strong>
				</p>
			</div>

			<form id="tsh-llms-form" method="post">
				<?php wp_nonce_field( 'tool_seo_hupuna_llms_nonce', 'nonce' ); ?>

				<!-- Section 1: Header Info -->
				<div class="tsh-panel" style="margin-top: 20px;">
					<h2><?php echo esc_html__( 'Header Information', 'tool-seo-hupuna' ); ?></h2>
					
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="site-title"><?php echo esc_html__( 'Site Title (H1)', 'tool-seo-hupuna' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										id="site-title" 
										name="site_title" 
										value="<?php echo esc_attr( $site_title ); ?>" 
										class="regular-text"
										placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
									>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="introduction"><?php echo esc_html__( 'Introduction / Summary', 'tool-seo-hupuna' ); ?></label>
								</th>
								<td>
									<textarea 
										id="introduction" 
										name="introduction" 
										rows="5" 
										class="large-text"
										placeholder="<?php echo esc_attr__( 'Enter a brief introduction about your site...', 'tool-seo-hupuna' ); ?>"
									><?php echo esc_textarea( $introduction ); ?></textarea>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Section 2: Auto-fetch Posts/Pages -->
				<div class="tsh-panel" style="margin-top: 20px;">
					<h2><?php echo esc_html__( 'Auto-fetch Content', 'tool-seo-hupuna' ); ?></h2>
					
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label><?php echo esc_html__( 'Select Post Types', 'tool-seo-hupuna' ); ?></label>
								</th>
								<td>
									<fieldset>
										<?php foreach ( $available_post_types as $post_type ) : ?>
											<label style="display: block; margin-bottom: 8px;">
												<input 
													type="checkbox" 
													name="enabled_post_types[]" 
													value="<?php echo esc_attr( $post_type->name ); ?>"
													<?php checked( in_array( $post_type->name, $enabled_post_types, true ) ); ?>
												>
												<?php echo esc_html( $post_type->label ); ?> (<?php echo esc_html( $post_type->name ); ?>)
											</label>
										<?php endforeach; ?>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="posts-limit"><?php echo esc_html__( 'Posts Limit', 'tool-seo-hupuna' ); ?></label>
								</th>
								<td>
									<input 
										type="number" 
										id="posts-limit" 
										name="posts_limit" 
										value="<?php echo esc_attr( $posts_limit ); ?>" 
										min="1" 
										max="500"
										class="small-text"
									>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label><?php echo esc_html__( 'Section Order', 'tool-seo-hupuna' ); ?></label>
								</th>
								<td>
									<ul id="section-order-sortable" style="list-style: none; padding: 0; margin: 10px 0;">
										<?php 
										// First, render all available post types (for JavaScript to use as template).
										// Then show only enabled ones, maintaining order from section_order.
										$ordered_sections = array_unique( array_merge( $section_order, $enabled_post_types ) );
										
										// Render enabled post types in order.
										foreach ( $ordered_sections as $post_type_name ) :
											if ( ! isset( $available_post_types[ $post_type_name ] ) ) {
												continue;
											}
											if ( ! in_array( $post_type_name, $enabled_post_types, true ) ) {
												continue; // Only show enabled ones.
											}
											$post_type_obj = $available_post_types[ $post_type_name ];
										?>
											<li class="section-order-item" data-post-type="<?php echo esc_attr( $post_type_name ); ?>" style="background: #f6f7f7; padding: 10px; margin: 5px 0; border: 1px solid #ddd; cursor: move;">
												<span class="dashicons dashicons-move" style="color: #999; vertical-align: middle;"></span>
												<input type="hidden" name="section_order[]" value="<?php echo esc_attr( $post_type_name ); ?>">
												<strong><?php echo esc_html( $post_type_obj->label ); ?></strong> (<?php echo esc_html( $post_type_name ); ?>)
											</li>
										<?php endforeach; ?>
									</ul>
									<div id="section-order-templates" style="display: none;">
										<?php 
										// Create hidden templates for all post types (for JavaScript to clone).
										foreach ( $available_post_types as $post_type_name => $post_type_obj ) :
										?>
											<li class="section-order-item-template" data-post-type="<?php echo esc_attr( $post_type_name ); ?>" style="background: #f6f7f7; padding: 10px; margin: 5px 0; border: 1px solid #ddd; cursor: move;">
												<span class="dashicons dashicons-move" style="color: #999; vertical-align: middle;"></span>
												<input type="hidden" name="section_order[]" value="<?php echo esc_attr( $post_type_name ); ?>">
												<strong><?php echo esc_html( $post_type_obj->label ); ?></strong> (<?php echo esc_html( $post_type_name ); ?>)
											</li>
										<?php endforeach; ?>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Section 3: Custom Link Builder (Sortable) -->
				<div class="tsh-panel" style="margin-top: 20px;">
					<h2><?php echo esc_html__( 'Custom Links (Manual)', 'tool-seo-hupuna' ); ?></h2>

					<div id="llms-links-container">
						<table class="wp-list-table widefat fixed striped" id="llms-links-table">
							<thead>
								<tr>
									<th style="width: 30px;" class="sort-handle-column"></th>
									<th style="width: 25%;"><?php echo esc_html__( 'Title', 'tool-seo-hupuna' ); ?></th>
									<th style="width: 35%;"><?php echo esc_html__( 'URL', 'tool-seo-hupuna' ); ?></th>
									<th style="width: 35%;"><?php echo esc_html__( 'Description', 'tool-seo-hupuna' ); ?></th>
									<th style="width: 80px;"><?php echo esc_html__( 'Actions', 'tool-seo-hupuna' ); ?></th>
								</tr>
							</thead>
							<tbody id="llms-links-tbody">
								<?php if ( ! empty( $links ) ) : ?>
									<?php foreach ( $links as $index => $link ) : ?>
										<tr class="llms-link-row" data-index="<?php echo esc_attr( $index ); ?>">
											<td class="sort-handle">
												<span class="dashicons dashicons-move" style="cursor: move; color: #999;"></span>
											</td>
											<td>
												<input 
													type="text" 
													name="links[<?php echo esc_attr( $index ); ?>][title]" 
													value="<?php echo esc_attr( $link['title'] ); ?>" 
													class="regular-text" 
													placeholder="<?php echo esc_attr__( 'Link Title', 'tool-seo-hupuna' ); ?>"
													required
												>
											</td>
											<td>
												<input 
													type="url" 
													name="links[<?php echo esc_attr( $index ); ?>][url]" 
													value="<?php echo esc_attr( $link['url'] ); ?>" 
													class="regular-text" 
													placeholder="https://example.com/page"
													required
												>
											</td>
											<td>
												<input 
													type="text" 
													name="links[<?php echo esc_attr( $index ); ?>][description]" 
													value="<?php echo esc_attr( isset( $link['description'] ) ? $link['description'] : '' ); ?>" 
													class="regular-text" 
													placeholder="<?php echo esc_attr__( 'Optional description', 'tool-seo-hupuna' ); ?>"
												>
											</td>
											<td>
												<button type="button" class="button button-small button-link-delete delete-link-row">
													<?php echo esc_html__( 'Delete', 'tool-seo-hupuna' ); ?>
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>

						<p style="margin-top: 15px;">
							<button type="button" id="add-link-row" class="button">
								<span class="dashicons dashicons-plus-alt"></span> <?php echo esc_html__( 'Add Link', 'tool-seo-hupuna' ); ?>
							</button>
						</p>
					</div>
				</div>

				<!-- Section 4: Footer/Extra -->
				<div class="tsh-panel" style="margin-top: 20px;">
					<h2><?php echo esc_html__( 'Footer / Additional Content', 'tool-seo-hupuna' ); ?></h2>
					
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="footer"><?php echo esc_html__( 'Additional Content (Markdown)', 'tool-seo-hupuna' ); ?></label>
								</th>
								<td>
									<textarea 
										id="footer" 
										name="footer" 
										rows="10" 
										class="large-text code"
										placeholder="<?php echo esc_attr__( 'Enter any additional Markdown content to append at the bottom of llms.txt...', 'tool-seo-hupuna' ); ?>"
									><?php echo esc_textarea( $footer ); ?></textarea>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Submit Button -->
				<p class="submit" style="margin-top: 20px;">
					<button type="submit" id="save-llms-settings" class="button button-primary button-large">
						<?php echo esc_html__( 'Save Settings', 'tool-seo-hupuna' ); ?>
					</button>
					<a href="<?php echo esc_url( home_url( 'llms.txt' ) ); ?>" target="_blank" class="button button-large" style="margin-left: 10px;">
						<?php echo esc_html__( 'Preview llms.txt', 'tool-seo-hupuna' ); ?>
					</a>
				</p>
			</form>

			<div id="llms-save-message" style="display: none; margin-top: 20px;"></div>
		</div>
		<?php
	}
}

