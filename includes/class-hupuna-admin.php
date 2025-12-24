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
		add_action( 'wp_ajax_hupuna_scan_batch', array( $this, 'ajax_scan_batch' ) );
	}

	/**
	 * Register Admin Menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Hupuna Link Scanner', 'hupuna-external-link-scanner' ),
			__( 'Link Scanner', 'hupuna-external-link-scanner' ),
			'manage_options',
			'hupuna-scan-links',
			array( $this, 'render_admin_page' ),
			'dashicons-admin-links',
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
		if ( 'toplevel_page_hupuna-scan-links' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'hupuna-els-admin',
			HUPUNA_ELS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			HUPUNA_ELS_VERSION
		);

		wp_enqueue_script(
			'hupuna-els-admin',
			HUPUNA_ELS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			HUPUNA_ELS_VERSION,
			true
		);

		wp_localize_script(
			'hupuna-els-admin',
			'hupunaEls',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'hupuna_scan_links_nonce' ),
				'postTypes' => $this->scanner->get_scannable_post_types(),
				'strings'   => array(
					'scanning'        => __( 'Scanning...', 'hupuna-external-link-scanner' ),
					'scanCompleted'   => __( 'Scan Completed!', 'hupuna-external-link-scanner' ),
					'startScan'       => __( 'Start Scan', 'hupuna-external-link-scanner' ),
					'initializing'    => __( 'Initializing...', 'hupuna-external-link-scanner' ),
					'errorEncountered' => __( 'Error encountered.', 'hupuna-external-link-scanner' ),
					'scanningPostType' => __( 'Scanning Post Type: %s', 'hupuna-external-link-scanner' ),
					'scanningComments' => __( 'Scanning Comments...', 'hupuna-external-link-scanner' ),
					'scanningOptions'  => __( 'Scanning Options...', 'hupuna-external-link-scanner' ),
					'page'             => __( 'Page', 'hupuna-external-link-scanner' ),
					'error'            => __( 'Error', 'hupuna-external-link-scanner' ),
					'serverError'      => __( 'Server connection failed: %s', 'hupuna-external-link-scanner' ),
					'accessDenied'     => __( 'Access denied', 'hupuna-external-link-scanner' ),
					'noLinksFound'    => __( 'No external links found. Great job!', 'hupuna-external-link-scanner' ),
					'totalLinksFound' => __( 'Total Links Found:', 'hupuna-external-link-scanner' ),
					'uniqueUrls'      => __( 'Unique URLs:', 'hupuna-external-link-scanner' ),
					'groupedByUrl'    => __( 'Grouped by URL', 'hupuna-external-link-scanner' ),
					'allOccurrences'  => __( 'All Occurrences', 'hupuna-external-link-scanner' ),
					'currentDomain'   => __( 'Current Domain:', 'hupuna-external-link-scanner' ),
					'description'     => __( 'Scans posts, pages, comments, and options for external links. System domains (WordPress, WooCommerce, Gravatar) and patterns are automatically ignored.', 'hupuna-external-link-scanner' ),
					'location'        => __( 'Location:', 'hupuna-external-link-scanner' ),
					'tag'             => __( 'Tag:', 'hupuna-external-link-scanner' ),
					'edit'            => __( 'Edit', 'hupuna-external-link-scanner' ),
					'view'            => __( 'View', 'hupuna-external-link-scanner' ),
					'prev'            => __( '&laquo; Prev', 'hupuna-external-link-scanner' ),
					'next'            => __( 'Next &raquo;', 'hupuna-external-link-scanner' ),
					'of'              => __( 'of', 'hupuna-external-link-scanner' ),
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
					'message' => __( 'Access denied', 'hupuna-external-link-scanner' ),
				)
			);
		}

		// Verify nonce.
		check_ajax_referer( 'hupuna_scan_links_nonce', 'nonce' );

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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hupuna-external-link-scanner' ) );
		}

		?>
		<div class="wrap hupuna-els-wrap">
			<h1><?php echo esc_html__( 'Hupuna External Link Scanner', 'hupuna-external-link-scanner' ); ?></h1>

			<div class="hupuna-els-header">
				<p>
					<strong><?php echo esc_html__( 'Current Domain:', 'hupuna-external-link-scanner' ); ?></strong>
					<code><?php echo esc_html( home_url() ); ?></code>
				</p>
				<p class="description">
					<?php echo esc_html__( 'Scans posts, pages, comments, and options for external links. System domains (WordPress, WooCommerce, Gravatar) and patterns are automatically ignored.', 'hupuna-external-link-scanner' ); ?>
				</p>
			</div>

			<div class="hupuna-els-actions">
				<button type="button" id="hupuna-scan-button" class="button button-primary button-large">
					<span class="dashicons dashicons-search"></span> <?php echo esc_html__( 'Start Scan', 'hupuna-external-link-scanner' ); ?>
				</button>

				<div id="hupuna-progress-wrap" style="display:none;">
					<div class="hupuna-progress-bar">
						<div class="hupuna-progress-fill" style="width: 0%"></div>
					</div>
					<div id="hupuna-progress-text"><?php echo esc_html__( 'Initializing...', 'hupuna-external-link-scanner' ); ?></div>
				</div>
			</div>

			<div id="hupuna-scan-results" class="hupuna-scan-results" style="display: none;">
				<div class="hupuna-results-summary">
					<p>
						<strong><?php echo esc_html__( 'Total Links Found:', 'hupuna-external-link-scanner' ); ?></strong>
						<span id="total-links">0</span> |
						<strong><?php echo esc_html__( 'Unique URLs:', 'hupuna-external-link-scanner' ); ?></strong>
						<span id="unique-links">0</span>
					</p>
				</div>

				<div class="hupuna-results-tabs">
					<button class="tab-button active" data-tab="grouped">
						<?php echo esc_html__( 'Grouped by URL', 'hupuna-external-link-scanner' ); ?>
					</button>
					<button class="tab-button" data-tab="all">
						<?php echo esc_html__( 'All Occurrences', 'hupuna-external-link-scanner' ); ?>
					</button>
				</div>

				<div id="hupuna-results-content" class="hupuna-results-content"></div>
			</div>
		</div>
		<?php
	}
}
