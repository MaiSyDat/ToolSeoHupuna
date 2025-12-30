<?php
/**
 * Robots.txt Manager Class
 * Manages robots.txt content editing from WordPress admin.
 *
 * @package ToolSeoHupuna
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Robots Manager class for managing robots.txt content.
 */
class Hupuna_Robots_Manager {

	/**
	 * Option key for storing robots.txt content.
	 *
	 * @var string
	 */
	private $option_key = 'hupuna_robots_content';

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
		add_action( 'wp_ajax_save_robots_txt', array( $this, 'ajax_save_robots_txt' ) );
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tool-seo-hupuna',
			__( 'Robots.txt Manager', 'tool-seo-hupuna' ),
			__( 'Robots.txt Manager', 'tool-seo-hupuna' ),
			'manage_options',
			'tool-seo-hupuna-robots',
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

		// Get current robots.txt content.
		$robots_content = get_option( $this->option_key, '' );

		// If empty, try to get default WordPress robots.txt.
		if ( empty( $robots_content ) ) {
			$robots_content = $this->get_default_robots_txt();
		}

		$nonce = wp_create_nonce( 'tool_seo_hupuna_robots_nonce' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Robots.txt Manager', 'tool-seo-hupuna' ); ?></h1>
			
			<div class="tsh-panel" style="max-width: 100%; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Edit Robots.txt', 'tool-seo-hupuna' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Edit your site\'s robots.txt content. This will override the default WordPress robots.txt output.', 'tool-seo-hupuna' ); ?>
				</p>
				
				<form id="tsh-robots-form">
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="robots-content"><?php echo esc_html__( 'Robots.txt Content', 'tool-seo-hupuna' ); ?></label>
								</th>
								<td>
									<textarea 
										id="robots-content" 
										name="robots_content" 
										rows="20" 
										style="width: 100%; font-family: monospace; font-size: 13px;"
										placeholder="User-agent: *&#10;Disallow: /wp-admin/&#10;Allow: /wp-admin/admin-ajax.php"
									><?php echo esc_textarea( $robots_content ); ?></textarea>
									<p class="description">
										<?php echo esc_html__( 'Enter the robots.txt content. Each line should be properly formatted according to robots.txt standards.', 'tool-seo-hupuna' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
					
					<p class="submit">
						<button type="button" id="tsh-save-robots-btn" class="button button-primary">
							<?php echo esc_html__( 'Save Changes', 'tool-seo-hupuna' ); ?>
						</button>
						<span id="tsh-robots-message" style="margin-left: 10px;"></span>
					</p>
				</form>
			</div>

			<div class="tsh-panel" style="max-width: 100%; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Preview', 'tool-seo-hupuna' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Preview how your robots.txt will appear:', 'tool-seo-hupuna' ); ?>
				</p>
				<pre id="tsh-robots-preview" style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html( $robots_content ); ?></pre>
			</div>
		</div>

		<script>
		(function($) {
			'use strict';
			
			$(document).ready(function() {
				var $saveBtn = $('#tsh-save-robots-btn');
				var $message = $('#tsh-robots-message');
				var $textarea = $('#robots-content');
				var $preview = $('#tsh-robots-preview');
				
				// Update preview on textarea change.
				$textarea.on('input', function() {
					$preview.text($(this).val());
				});
				
				// Save button click.
				$saveBtn.on('click', function() {
					var content = $textarea.val();
					var originalText = $saveBtn.text();
					
					$saveBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'tool-seo-hupuna' ) ); ?>');
					$message.html('');
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'save_robots_txt',
							nonce: '<?php echo esc_js( $nonce ); ?>',
							robots_content: content
						},
						success: function(response) {
							if (response.success) {
								$message.html('<span style="color: #46b450;">✓ ' + (response.data.message || '<?php echo esc_js( __( 'Robots.txt saved successfully!', 'tool-seo-hupuna' ) ); ?>') + '</span>');
								$preview.text(content);
								setTimeout(function() {
									$message.html('');
								}, 3000);
							} else {
								$message.html('<span style="color: #dc3232;">✗ ' + (response.data.message || '<?php echo esc_js( __( 'Error saving robots.txt', 'tool-seo-hupuna' ) ); ?>') + '</span>');
							}
							$saveBtn.prop('disabled', false).text(originalText);
						},
						error: function() {
							$message.html('<span style="color: #dc3232;">✗ <?php echo esc_js( __( 'Server error. Please try again.', 'tool-seo-hupuna' ) ); ?></span>');
							$saveBtn.prop('disabled', false).text(originalText);
						}
					});
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Get default WordPress robots.txt content.
	 *
	 * @return string Default robots.txt content.
	 */
	private function get_default_robots_txt() {
		$public = get_option( 'blog_public' );
		
		if ( '0' === $public ) {
			return "User-agent: *\nDisallow: /\n";
		}
		
		$output = "User-agent: *\n";
		$output .= "Disallow: /wp-admin/\n";
		$output .= "Allow: /wp-admin/admin-ajax.php\n";
		
		// Add sitemap if available.
		$sitemap_url = get_option( 'hupuna_sitemap_url' );
		if ( empty( $sitemap_url ) ) {
			$sitemap_url = home_url( '/sitemap.xml' );
		}
		$output .= "\nSitemap: " . esc_url( $sitemap_url ) . "\n";
		
		return $output;
	}

	/**
	 * AJAX handler: Save robots.txt content.
	 *
	 * @return void
	 */
	public function ajax_save_robots_txt() {
		check_ajax_referer( 'tool_seo_hupuna_robots_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'tool-seo-hupuna' ) ) );
		}

		$content = isset( $_POST['robots_content'] ) ? wp_unslash( $_POST['robots_content'] ) : '';
		
		// Sanitize: Remove any HTML tags, keep only plain text.
		$content = wp_strip_all_tags( $content );
		
		// Update option.
		update_option( $this->option_key, $content );

		wp_send_json_success( array( 'message' => __( 'Robots.txt saved successfully!', 'tool-seo-hupuna' ) ) );
	}

	/**
	 * Filter robots.txt output.
	 *
	 * @param string $output The robots.txt output.
	 * @param bool   $public Whether the site is public.
	 * @return string Filtered robots.txt output.
	 */
	public function filter_robots_txt( $output, $public ) {
		$custom_content = get_option( $this->option_key, '' );
		
		if ( ! empty( $custom_content ) ) {
			// Return custom content, ensure it's plain text.
			return wp_strip_all_tags( $custom_content ) . "\n";
		}
		
		// Return default output if no custom content.
		return $output;
	}
}

