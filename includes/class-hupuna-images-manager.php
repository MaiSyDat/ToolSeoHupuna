<?php
/**
 * Unused Images Manager Class
 * Scans and manages unused images in the Media Library.
 *
 * @package ToolSeoHupuna
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Images Manager class for detecting and deleting unused images.
 */
class Hupuna_Images_Manager {

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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_tool_seo_hupuna_scan_unused_images', array( $this, 'ajax_scan_unused_images' ) );
		add_action( 'wp_ajax_tool_seo_hupuna_delete_image', array( $this, 'ajax_delete_image' ) );
		add_action( 'wp_ajax_tool_seo_hupuna_bulk_delete_images', array( $this, 'ajax_bulk_delete_images' ) );
	}

	/**
	 * Register Admin Menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tool-seo-hupuna',
			__( 'Unused Image Scanner', 'tool-seo-hupuna' ),
			__( 'Unused Images', 'tool-seo-hupuna' ),
			'manage_options',
			'tool-seo-hupuna-images',
			array( $this, 'render_admin_page' ),
			1 // Position: 1 = right after External Links (position 0)
		);
	}

	/**
	 * Enqueue Assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check if we're on the images page by checking GET parameter (more reliable).
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'tool-seo-hupuna-images' !== $page ) {
			return;
		}

		wp_enqueue_style(
			'tool-seo-hupuna-admin',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TOOL_SEO_HUPUNA_VERSION
		);

		wp_enqueue_script( 'jquery' ); // Ensure jQuery is loaded.
		wp_enqueue_script(
			'tool-seo-hupuna-images',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/js/admin-images.js',
			array( 'jquery' ),
			TOOL_SEO_HUPUNA_VERSION,
			true
		);

		wp_localize_script(
			'tool-seo-hupuna-images',
			'toolSeoHupunaImages',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tool_seo_hupuna_images_nonce' ),
				'strings' => array(
					'scanning'         => __( 'Scanning...', 'tool-seo-hupuna' ),
					'scanCompleted'    => __( 'Scan Completed!', 'tool-seo-hupuna' ),
					'startScan'        => __( 'Start Scan', 'tool-seo-hupuna' ),
					'initializing'     => __( 'Initializing...', 'tool-seo-hupuna' ),
					'errorEncountered' => __( 'Error encountered.', 'tool-seo-hupuna' ),
					'scanningImages'   => __( 'Scanning images...', 'tool-seo-hupuna' ),
					'page'             => __( 'Page', 'tool-seo-hupuna' ),
					'error'            => __( 'Error', 'tool-seo-hupuna' ),
					'serverError'      => __( 'Server connection failed: %s', 'tool-seo-hupuna' ),
					'accessDenied'     => __( 'Access denied', 'tool-seo-hupuna' ),
					'noImagesFound'    => __( 'No unused images found. Great job!', 'tool-seo-hupuna' ),
					'totalImagesFound' => __( 'Total Unused Images:', 'tool-seo-hupuna' ),
					'delete'           => __( 'Delete', 'tool-seo-hupuna' ),
					'deleteSelected'   => __( 'Delete Selected', 'tool-seo-hupuna' ),
					'selectAll'        => __( 'Select All', 'tool-seo-hupuna' ),
					'confirmDelete'    => __( 'Are you sure you want to delete this image? This action cannot be undone.', 'tool-seo-hupuna' ),
					'confirmBulkDelete' => __( 'Are you sure you want to delete the selected images? This action cannot be undone.', 'tool-seo-hupuna' ),
					'deleting'         => __( 'Deleting...', 'tool-seo-hupuna' ),
					'deleted'          => __( 'Deleted successfully', 'tool-seo-hupuna' ),
					'deleteError'      => __( 'Error deleting image', 'tool-seo-hupuna' ),
				),
			)
		);
	}

	/**
	 * AJAX Handler: Scan unused images (batch processing).
	 *
	 * @return void
	 */
	public function ajax_scan_unused_images() {
		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Access denied', 'tool-seo-hupuna' ),
				)
			);
		}

		// Verify nonce.
		check_ajax_referer( 'tool_seo_hupuna_images_nonce', 'nonce' );

		// Prevent timeout.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// Sanitize input.
		$page     = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = 20; // Process 20 images per batch.

		$scan_data = $this->scan_images_batch( $page, $per_page );

		wp_send_json_success( $scan_data );
	}

	/**
	 * Batch scan images for unused detection.
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Images per page.
	 * @return array Scan results.
	 */
	private function scan_images_batch( $page = 1, $per_page = 20 ) {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;
		$results = array();

		// Get all image attachments (batch).
		// WordPress attachments typically have post_status = 'inherit' (not 'publish').
		// Exclude trashed attachments only.
		$query = $wpdb->prepare(
			"SELECT ID, post_title, post_date, guid, post_status
			FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_mime_type LIKE 'image/%%'
			AND post_status != 'trash'
			ORDER BY ID ASC
			LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		$images = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $images ) ) {
			// Return stats even when no images found.
			return array(
				'results' => array(),
				'done'    => true,
				'stats'   => array(
					'scanned' => 0,
					'unused'  => 0,
					'total_in_db' => $this->get_total_images_count(),
				),
			);
		}

		$scanned_count = 0;
		$unused_count = 0;

		foreach ( $images as $image ) {
			$image_id = intval( $image->ID );
			$scanned_count++;
			
			// Get image file path for accurate filename detection.
			$file_path = get_attached_file( $image_id );
			if ( ! $file_path ) {
				continue; // Skip if file path not found.
			}
			
			// Check if image is unused.
			$is_used = $this->is_image_used( $image_id, $file_path );
			
			if ( ! $is_used ) {
				$unused_count++;
				// Get image metadata.
				$file_url  = wp_get_attachment_url( $image_id );
				$filename  = basename( $file_path );

				$results[] = array(
					'id'         => $image_id,
					'title'      => ! empty( $image->post_title ) ? $image->post_title : $filename,
					'filename'   => $filename,
					'url'        => $file_url,
					'date'       => $image->post_date,
					'thumbnail'  => wp_get_attachment_image_url( $image_id, 'thumbnail' ),
				);
			}
		}

		return array(
			'results' => $results,
			'done'    => count( $images ) < $per_page,
			'stats'   => array(
				'scanned' => $scanned_count,
				'unused'  => $unused_count,
				'total_in_db' => $this->get_total_images_count(),
			),
		);
	}

	/**
	 * Get total count of images in database.
	 *
	 * @return int Total image count.
	 */
	private function get_total_images_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(*) 
			FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_mime_type LIKE 'image/%%'
			AND post_status != 'trash'"
		);

		return intval( $count );
	}

	/**
	 * Check if image is used anywhere on the site.
	 * CRITICAL: Deep scan logic with 100% safety check.
	 *
	 * @param int    $image_id Image attachment ID.
	 * @param string $file_path Image file path.
	 * @return bool True if image is used, false if unused.
	 */
	private function is_image_used( $image_id, $file_path ) {
		global $wpdb;

		// Extract filename from file path.
		$filename = basename( $file_path );
		// Get base filename (without size suffix, e.g., "image-150x150.jpg" -> "image.jpg").
		$base_filename = $this->get_base_filename( $filename );

		// Check 0: WooCommerce Placeholder Image.
		// WooCommerce stores placeholder image ID in option 'woocommerce_placeholder_image'.
		$woocommerce_placeholder = get_option( 'woocommerce_placeholder_image', 0 );
		if ( ! empty( $woocommerce_placeholder ) && intval( $woocommerce_placeholder ) === $image_id ) {
			return true;
		}

		// Check 1: Featured Image (_thumbnail_id).
		$featured_check = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} 
				WHERE meta_key = '_thumbnail_id' 
				AND meta_value = %d",
				$image_id
			)
		);
		if ( $featured_check > 0 ) {
			return true;
		}

		// Check 2a: WooCommerce Product Gallery (_product_image_gallery) - comma-separated IDs.
		// This is a common case, so check it separately with optimized query.
		$id_in_gallery = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} 
				WHERE meta_key = '_product_image_gallery' 
				AND (
					meta_value = %d 
					OR meta_value LIKE %s 
					OR meta_value LIKE %s 
					OR meta_value LIKE %s
				)",
				$image_id,
				$image_id . ',%',      // Start: "123,456"
				'%,' . $image_id . ',%', // Middle: "456,123,789"
				'%,' . $image_id        // End: "456,123"
			)
		);
		if ( $id_in_gallery > 0 ) {
			return true;
		}

		// Check 2b: Image ID in postmeta (many plugins/themes store image IDs).
		// IMPORTANT: Only check in specific meta keys that commonly store image IDs.
		// This prevents false positives from matching image IDs in unrelated data.
		$common_image_meta_keys = array(
			'_thumbnail_id',           // Already checked in Check 1, but include for completeness
			'_product_image',           // WooCommerce product image
			'_wp_attachment_id',        // WordPress attachment ID
			'image',                    // Generic image field
			'image_id',                 // Generic image ID field
			'featured_image',           // Featured image
			'header_image',            // Header image
			'logo',                     // Logo
			'custom_logo',              // Custom logo
		);
		
		// Escape and prepare meta keys for IN clause.
		$escaped_keys = array_map( 'esc_sql', $common_image_meta_keys );
		$keys_placeholders = "'" . implode( "','", $escaped_keys ) . "'";
		
		// Check exact match in specific meta keys.
		$id_in_postmeta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} 
				WHERE meta_key IN ($keys_placeholders)
				AND meta_value = %d",
				$image_id
			)
		);
		if ( $id_in_postmeta > 0 ) {
			return true;
		}
		
		// Check 2c: Image ID in serialized/JSON data (for page builders like Elementor, WPBakery).
		$id_in_serialized = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} 
				WHERE (meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s)
				AND (meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)",
				'%elementor%', '%_elementor%', '%wpbakery%', '%vc_%', '%_wpb_%',
				'%"' . $image_id . '"%',  // JSON format: "123"
				'%:' . $image_id . ';%',  // Serialized format: :123;
				'%:' . $image_id . '}%'   // Serialized format: :123}
			)
		);
		if ( $id_in_serialized > 0 ) {
			return true;
		}

		// Prepare filename patterns for searching (both full and base filename).
		$filename_escaped = $wpdb->esc_like( $filename );
		$base_filename_escaped = $wpdb->esc_like( $base_filename );

		// Check 3: Filename in post_content (Standard WP Content).
		// Search for both full filename and base filename.
		$filename_in_content = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} 
				WHERE (post_content LIKE %s OR post_content LIKE %s)
				AND post_status != 'trash'",
				'%' . $filename_escaped . '%',
				'%' . $base_filename_escaped . '%'
			)
		);
		if ( $filename_in_content > 0 ) {
			return true;
		}

		// Check 4: Filename in postmeta (CRITICAL for Page Builders like Elementor, WPBakery, ACF).
		// IMPORTANT: Exclude attachment metadata to avoid false positives.
		// Also check _product_image_gallery for filenames (some themes/plugins store filenames instead of IDs).
		$filename_in_postmeta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} 
				WHERE meta_key NOT LIKE %s
				AND meta_key NOT LIKE %s
				AND (meta_value LIKE %s OR meta_value LIKE %s)",
				'%_wp_attachment%',
				'%_wp_attached_file%',
				'%' . $filename_escaped . '%',
				'%' . $base_filename_escaped . '%'
			)
		);
		if ( $filename_in_postmeta > 0 ) {
			return true;
		}

		// Check 5a: Image ID in wp_options (for theme_mods like Flatsome logos).
		// Flatsome stores logo as attachment ID in theme_mods (serialized PHP array).
		// WordPress theme_mods are stored as serialized arrays: a:1:{s:9:"site_logo";i:227;}
		// We need to check for both serialized format and also check if the ID appears after "site_logo" or "site_logo_dark" keys.
		$id_in_options = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} 
				WHERE option_name LIKE %s
				AND (
					option_value LIKE %s 
					OR option_value LIKE %s 
					OR option_value LIKE %s
					OR option_value LIKE %s
					OR option_value LIKE %s
					OR option_value LIKE %s
					OR option_value LIKE %s
				)",
				'%theme_mods%',
				'%i:' . $image_id . ';%',                    // Serialized integer: i:227;
				'%:"' . $image_id . '"%',                   // JSON string: "227"
				'%:' . $image_id . ';%',                   // Serialized in array: :227;
				'%:' . $image_id . '}%',                   // End of array: :227}
				'%:' . $image_id . ',%',                   // In array: :227,
				'%site_logo";i:' . $image_id . ';%',      // site_logo";i:227;
				'%site_logo_dark";i:' . $image_id . ';%'  // site_logo_dark";i:228;
			)
		);
		if ( $id_in_options > 0 ) {
			return true;
		}

		// Check 5b: Filename in wp_options (CRITICAL for Theme Options, Flatsome UX blocks, Logos, Favicons, Widgets).
		// IMPORTANT: Exclude transient and cache options to avoid false positives.
		$filename_in_options = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} 
				WHERE option_name NOT LIKE %s
				AND option_name NOT LIKE %s
				AND option_name NOT LIKE %s
				AND option_name NOT LIKE %s
				AND (option_value LIKE %s OR option_value LIKE %s)",
				'%_transient%',
				'%_cache%',
				'%cron%',
				'%theme_mods%',  // Already checked above
				'%' . $filename_escaped . '%',
				'%' . $base_filename_escaped . '%'
			)
		);
		if ( $filename_in_options > 0 ) {
			return true;
		}

		// Check 6: Filename in wp_termmeta (Category/Taxonomy images).
		$filename_in_termmeta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->termmeta} 
				WHERE meta_value LIKE %s OR meta_value LIKE %s",
				'%' . $filename_escaped . '%',
				'%' . $base_filename_escaped . '%'
			)
		);
		if ( $filename_in_termmeta > 0 ) {
			return true;
		}

		// Image is unused if all checks pass.
		return false;
	}

	/**
	 * Get base filename without size suffix.
	 * Handles WordPress image sizes like "image-150x150.jpg" -> "image.jpg".
	 *
	 * @param string $filename Full filename.
	 * @return string Base filename.
	 */
	private function get_base_filename( $filename ) {
		// Remove size suffix pattern: -WIDTHxHEIGHT (e.g., -150x150, -300x200).
		$base = preg_replace( '/-(\d+)x(\d+)\./', '.', $filename );
		
		// Also handle scaled images: -scaled.
		$base = str_replace( '-scaled.', '.', $base );
		
		return $base;
	}

	/**
	 * AJAX Handler: Delete single image.
	 *
	 * @return void
	 */
	public function ajax_delete_image() {
		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Access denied', 'tool-seo-hupuna' ),
				)
			);
		}

		// Verify nonce.
		check_ajax_referer( 'tool_seo_hupuna_images_nonce', 'nonce' );

		// Sanitize input.
		$image_id = isset( $_POST['image_id'] ) ? intval( $_POST['image_id'] ) : 0;

		if ( $image_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid image ID', 'tool-seo-hupuna' ),
				)
			);
		}

		// Delete attachment (force delete, removes file and database entry).
		$deleted = wp_delete_attachment( $image_id, true );

		if ( $deleted ) {
			wp_send_json_success(
				array(
					'message' => __( 'Image deleted successfully', 'tool-seo-hupuna' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to delete image', 'tool-seo-hupuna' ),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Bulk delete images.
	 *
	 * @return void
	 */
	public function ajax_bulk_delete_images() {
		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Access denied', 'tool-seo-hupuna' ),
				)
			);
		}

		// Verify nonce.
		check_ajax_referer( 'tool_seo_hupuna_images_nonce', 'nonce' );

		// Sanitize input.
		$image_ids = isset( $_POST['image_ids'] ) ? array_map( 'intval', $_POST['image_ids'] ) : array();

		if ( empty( $image_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No images selected', 'tool-seo-hupuna' ),
				)
			);
		}

		$deleted_count = 0;
		$failed_count  = 0;

		foreach ( $image_ids as $image_id ) {
			if ( $image_id > 0 ) {
				$deleted = wp_delete_attachment( $image_id, true );
				if ( $deleted ) {
					$deleted_count++;
				} else {
					$failed_count++;
				}
			}
		}

		wp_send_json_success(
			array(
				'message'      => sprintf(
					// translators: %1$d: deleted count, %2$d: failed count.
					__( 'Deleted: %1$d, Failed: %2$d', 'tool-seo-hupuna' ),
					$deleted_count,
					$failed_count
				),
				'deleted'      => $deleted_count,
				'failed'       => $failed_count,
			)
		);
	}

	/**
	 * Render Admin Interface.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tool-seo-hupuna' ) );
		}

		?>
		<div class="wrap tsh-wrap">
			<h1><?php echo esc_html__( 'Unused Image Scanner', 'tool-seo-hupuna' ); ?></h1>

			<div class="card tsh-card">
				<p class="description">
					<?php echo esc_html__( 'Scans the Media Library for images that are not used anywhere on your site. Images are checked in posts, pages, post meta, options, and term meta to ensure 100% accuracy.', 'tool-seo-hupuna' ); ?>
				</p>
			</div>

			<div style="margin: 20px 0;">
				<button type="button" id="tool-seo-hupuna-images-scan-button" class="button button-primary button-large">
					<span class="dashicons dashicons-search"></span> <?php echo esc_html__( 'Start Scan', 'tool-seo-hupuna' ); ?>
				</button>

				<div id="tool-seo-hupuna-images-progress-wrap" class="tsh-progress-bar" style="display:none; margin-top: 20px;">
					<div style="background: #f0f0f1; border-radius: 4px; height: 30px; overflow: hidden;">
						<div id="tool-seo-hupuna-images-progress-fill" class="tsh-progress-fill" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
					</div>
					<div id="tool-seo-hupuna-images-progress-text" style="margin-top: 10px;"><?php echo esc_html__( 'Initializing...', 'tool-seo-hupuna' ); ?></div>
				</div>
			</div>

			<div id="tool-seo-hupuna-images-scan-results" class="tsh-scan-results" style="display: none;">
				<div class="card tsh-card" style="margin-bottom: 20px;">
					<p>
						<strong><?php echo esc_html__( 'Total Unused Images:', 'tool-seo-hupuna' ); ?></strong>
						<span id="total-unused-images">0</span>
					</p>
				</div>

				<div style="margin-bottom: 20px;">
					<button type="button" id="tool-seo-hupuna-images-bulk-delete" class="button button-link-delete" style="display: none;">
						<?php echo esc_html__( 'Delete Selected', 'tool-seo-hupuna' ); ?>
					</button>
				</div>

				<div id="tool-seo-hupuna-images-results-content" class="tsh-results-content"></div>
			</div>
		</div>
		<?php
	}
}

