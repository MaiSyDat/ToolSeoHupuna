<?php
/**
 * Admin Controller Class
 * Manages admin pages, assets, and AJAX handlers.
 *
 * @package HupunaExternalLinkScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for managing plugin admin interface.
 */
class Hupuna_External_Link_Scanner_Admin {

	/**
	 * Scanner instance.
	 *
	 * @var Hupuna_External_Link_Scanner
	 */
	private $scanner;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->scanner = new Hupuna_External_Link_Scanner();
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_tool_seo_hupuna_scan_batch', array( $this, 'ajax_scan_batch' ) );
	}

	/**
	 * Register Admin Menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Tool SEO Hupuna', 'tool-seo-hupuna' ),
			__( 'Tool SEO', 'tool-seo-hupuna' ),
			'manage_options',
			'tool-seo-hupuna',
			array( $this, 'render_admin_page' ),
			'dashicons-admin-tools',
			30
		);
	}

	/**
	 * Enqueue Assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_tool-seo-hupuna' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tool-seo-hupuna-admin',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TOOL_SEO_HUPUNA_VERSION
		);

		wp_enqueue_script(
			'tool-seo-hupuna-admin',
			TOOL_SEO_HUPUNA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			TOOL_SEO_HUPUNA_VERSION,
			true
		);

		wp_localize_script(
			'tool-seo-hupuna-admin',
			'toolSeoHupuna',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'tool_seo_hupuna_scan_links_nonce' ),
				'postTypes' => $this->scanner->get_scannable_post_types(),
				'strings'   => array(
					'scanning'        => __( 'Scanning...', 'tool-seo-hupuna' ),
					'scanCompleted'   => __( 'Scan Completed!', 'tool-seo-hupuna' ),
					'startScan'       => __( 'Start Scan', 'tool-seo-hupuna' ),
					'initializing'    => __( 'Initializing...', 'tool-seo-hupuna' ),
					'errorEncountered' => __( 'Error encountered.', 'tool-seo-hupuna' ),
					'scanningPostType' => __( 'Scanning Post Type: %s', 'tool-seo-hupuna' ),
					'scanningComments' => __( 'Scanning Comments...', 'tool-seo-hupuna' ),
					'scanningOptions'  => __( 'Scanning Options...', 'tool-seo-hupuna' ),
					'page'             => __( 'Page', 'tool-seo-hupuna' ),
					'error'            => __( 'Error', 'tool-seo-hupuna' ),
					'serverError'      => __( 'Server connection failed: %s', 'tool-seo-hupuna' ),
					'accessDenied'     => __( 'Access denied', 'tool-seo-hupuna' ),
					'noLinksFound'    => __( 'No external links found. Great job!', 'tool-seo-hupuna' ),
					'totalLinksFound' => __( 'Total Links Found:', 'tool-seo-hupuna' ),
					'uniqueUrls'      => __( 'Unique URLs:', 'tool-seo-hupuna' ),
					'groupedByUrl'    => __( 'Grouped by URL', 'tool-seo-hupuna' ),
					'allOccurrences'  => __( 'All Occurrences', 'tool-seo-hupuna' ),
					'currentDomain'   => __( 'Current Domain:', 'tool-seo-hupuna' ),
					'description'     => __( 'Scans posts, pages, comments, and options for external links. System domains (WordPress, WooCommerce, Gravatar) and patterns are automatically ignored.', 'tool-seo-hupuna' ),
					'location'        => __( 'Location:', 'tool-seo-hupuna' ),
					'tag'             => __( 'Tag:', 'tool-seo-hupuna' ),
					'edit'            => __( 'Edit', 'tool-seo-hupuna' ),
					'view'            => __( 'View', 'tool-seo-hupuna' ),
					'prev'            => __( '&laquo; Prev', 'tool-seo-hupuna' ),
					'next'            => __( 'Next &raquo;', 'tool-seo-hupuna' ),
					'of'              => __( 'of', 'tool-seo-hupuna' ),
				),
			)
		);
	}

	/**
	 * AJAX Handler for Batch Scanning.
	 *
	 * @return void
	 */
	public function ajax_scan_batch() {
		// Check capabilities first.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Access denied', 'tool-seo-hupuna' ),
				)
			);
		}

		// Verify nonce.
		check_ajax_referer( 'tool_seo_hupuna_scan_links_nonce', 'nonce' );

		// Prevent timeout.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// Sanitize input.
		$step    = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : '';
		$page    = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$sub_step = isset( $_POST['sub_step'] ) ? sanitize_text_field( wp_unslash( $_POST['sub_step'] ) ) : '';

		$response = array(
			'results' => array(),
			'done'    => false,
		);

		switch ( $step ) {
			case 'post_type':
				if ( ! empty( $sub_step ) ) {
					$scan_data = $this->scanner->scan_post_type_batch( $sub_step, $page, 20 );
					$response['results'] = $scan_data['results'];
					$response['done']    = $scan_data['done'];
				} else {
					$response['done'] = true;
				}
				break;

			case 'comment':
				$scan_data = $this->scanner->scan_comments_batch( $page, 50 );
				$response['results'] = $scan_data['results'];
				$response['done']    = $scan_data['done'];
				break;

			case 'option':
				$scan_data = $this->scanner->scan_options_batch( $page, 100 );
				$response['results'] = $scan_data['results'];
				$response['done']    = $scan_data['done'];
				break;

			default:
				$response['done'] = true;
				break;
		}

		wp_send_json_success( $response );
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
		<div class="wrap">
			<h1><?php echo esc_html__( 'External Link Scanner', 'tool-seo-hupuna' ); ?></h1>

			<div class="card">
				<p>
					<strong><?php echo esc_html__( 'Current Domain:', 'tool-seo-hupuna' ); ?></strong>
					<code><?php echo esc_html( home_url() ); ?></code>
				</p>
				<p class="description">
					<?php echo esc_html__( 'Scans posts, pages, comments, and options for external links. System domains (WordPress, WooCommerce, Gravatar) and patterns are automatically ignored.', 'tool-seo-hupuna' ); ?>
				</p>
			</div>

			<div style="margin: 20px 0;">
				<button type="button" id="tool-seo-hupuna-scan-button" class="button button-primary button-large">
					<span class="dashicons dashicons-search"></span> <?php echo esc_html__( 'Start Scan', 'tool-seo-hupuna' ); ?>
				</button>

				<div id="tool-seo-hupuna-progress-wrap" style="display:none; margin-top: 20px;">
					<div style="background: #f0f0f1; border-radius: 4px; height: 30px; overflow: hidden;">
						<div id="tool-seo-hupuna-progress-fill" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
					</div>
					<div id="tool-seo-hupuna-progress-text" style="margin-top: 10px;"><?php echo esc_html__( 'Initializing...', 'tool-seo-hupuna' ); ?></div>
				</div>
			</div>

			<div id="tool-seo-hupuna-scan-results" style="display: none;">
				<div class="card" style="margin-bottom: 20px;">
					<p>
						<strong><?php echo esc_html__( 'Total Links Found:', 'tool-seo-hupuna' ); ?></strong>
						<span id="total-links">0</span> |
						<strong><?php echo esc_html__( 'Unique URLs:', 'tool-seo-hupuna' ); ?></strong>
						<span id="unique-links">0</span>
					</p>
				</div>

				<div class="nav-tab-wrapper" style="margin-bottom: 20px;">
					<button class="nav-tab nav-tab-active" data-tab="grouped">
						<?php echo esc_html__( 'Grouped by URL', 'tool-seo-hupuna' ); ?>
					</button>
					<button class="nav-tab" data-tab="all">
						<?php echo esc_html__( 'All Occurrences', 'tool-seo-hupuna' ); ?>
					</button>
				</div>

				<div id="tool-seo-hupuna-results-content"></div>
			</div>
		</div>
		<?php
	}
}
