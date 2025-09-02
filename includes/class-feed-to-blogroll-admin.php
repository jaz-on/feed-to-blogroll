<?php
/**
 * Blogroll Administration
 *
 * @package FeedToBlogroll
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blogroll administration class
 *
 * @since 1.0.0
 */
class Feed_To_Blogroll_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_feed_to_blogroll_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_feed_to_blogroll_export_opml', array( $this, 'export_opml' ) );
		add_filter( 'plugin_action_links', array( $this, 'add_plugin_action_links' ), 10, 2 );

		// Check CPT registration on admin pages
		add_action( 'admin_notices', array( $this, 'check_cpt_registration' ) );

		// Informative notice when credentials are driven by wp-config constants
		add_action( 'admin_notices', array( $this, 'show_constants_notice' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Feed to Blogroll', 'feed-to-blogroll' ),
			__( 'Blogroll', 'feed-to-blogroll' ),
			'manage_options',
			'feed-to-blogroll',
			array( $this, 'admin_page' ),
			'dashicons-rss',
			30
		);

		// Add submenu pages for blog management
		add_submenu_page(
			'feed-to-blogroll',
			__( 'Dashboard', 'feed-to-blogroll' ),
			__( 'Dashboard', 'feed-to-blogroll' ),
			'manage_options',
			'feed-to-blogroll',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'feed-to-blogroll',
			__( 'All Blogs', 'feed-to-blogroll' ),
			__( 'All Blogs', 'feed-to-blogroll' ),
			'manage_options',
			'edit.php?post_type=blogroll'
		);

		add_submenu_page(
			'feed-to-blogroll',
			__( 'Add New Blog', 'feed-to-blogroll' ),
			__( 'Add New Blog', 'feed-to-blogroll' ),
			'manage_options',
			'post-new.php?post_type=blogroll'
		);

		add_submenu_page(
			'feed-to-blogroll',
			__( 'Categories', 'feed-to-blogroll' ),
			__( 'Categories', 'feed-to-blogroll' ),
			'manage_options',
			'edit-tags.php?taxonomy=blogroll_category&post_type=blogroll'
		);

		// Add diagnostic page only for administrators
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page(
				'feed-to-blogroll',
				__( 'Diagnostics', 'feed-to-blogroll' ),
				__( 'Diagnostics', 'feed-to-blogroll' ),
				'manage_options',
				'feed-to-blogroll-diagnostics',
				array( $this, 'diagnostics_page' )
			);
		}
	}

	/**
	 * Add plugin action links
	 */
	public function add_plugin_action_links( $links, $file ) {
		if ( FEED_TO_BLOGROLL_PLUGIN_BASENAME === $file ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=feed-to-blogroll&tab=settings' ) ),
				esc_html__( 'Settings', 'feed-to-blogroll' )
			);
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * Initialize plugin settings
	 */
	public function init_settings() {
		register_setting(
			'feed_to_blogroll_options',
			'feed_to_blogroll_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => array(),
			)
		);

		// API Settings Section
		add_settings_section(
			'feed_to_blogroll_api_section',
			__( 'Feedbin API Configuration', 'feed-to-blogroll' ),
			array( $this, 'api_section_callback' ),
			'feed_to_blogroll_options'
		);

		// Username field
		add_settings_field(
			'feedbin_username',
			__( 'Feedbin Username', 'feed-to-blogroll' ),
			array( $this, 'username_field_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_api_section'
		);

		// Password field
		add_settings_field(
			'feedbin_password',
			__( 'Feedbin Password', 'feed-to-blogroll' ),
			array( $this, 'password_field_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_api_section'
		);

		// Sync Settings Section
		add_settings_section(
			'feed_to_blogroll_sync_section',
			__( 'Synchronization Settings', 'feed-to-blogroll' ),
			array( $this, 'sync_section_callback' ),
			'feed_to_blogroll_options'
		);

		// Auto sync field
		add_settings_field(
			'auto_sync',
			__( 'Automatic Synchronization', 'feed-to-blogroll' ),
			array( $this, 'auto_sync_field_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_sync_section'
		);

		// Sync frequency field
		add_settings_field(
			'sync_frequency',
			__( 'Sync Frequency', 'feed-to-blogroll' ),
			array( $this, 'sync_frequency_field_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_sync_section'
		);

		// Last sync info field
		add_settings_field(
			'last_sync_info',
			__( 'Last Synchronization', 'feed-to-blogroll' ),
			array( $this, 'last_sync_info_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_sync_section'
		);
	}

	/**
	 * Sanitize and validate options with improved security
	 */
	public function sanitize_options( $input ) {
		$sanitized = array();

		if ( isset( $input['feedbin_username'] ) ) {
			$email = sanitize_email( $input['feedbin_username'] );
			if ( ! is_email( $email ) ) {
				add_settings_error(
					'feed_to_blogroll_options',
					'invalid_email',
					__( 'Please enter a valid email address for Feedbin username.', 'feed-to-blogroll' )
				);
			} else {
				$sanitized['feedbin_username'] = $email;
			}
		}

		if ( isset( $input['feedbin_password'] ) ) {
			$sanitized['feedbin_password'] = sanitize_text_field( $input['feedbin_password'] );
		}

		if ( isset( $input['auto_sync'] ) ) {
			$sanitized['auto_sync'] = (bool) $input['auto_sync'];
		}

		if ( isset( $input['sync_frequency'] ) ) {
			$allowed_frequencies = array( 'twice_daily', 'daily', 'weekly' );
			$sanitized['sync_frequency'] = in_array( $input['sync_frequency'], $allowed_frequencies, true )
				? $input['sync_frequency']
				: 'daily';
		}

		return $sanitized;
	}

	/**
	 * API section callback
	 */
	public function api_section_callback() {
		echo '<p>' . esc_html__( 'Configure your Feedbin API credentials below. These are the same credentials you use to log into Feedbin.com.', 'feed-to-blogroll' ) . '</p>';
	}

	/**
	 * Username field callback
	 */
	public function username_field_callback() {
		$options  = get_option( 'feed_to_blogroll_options', array() );
		$username = isset( $options['feedbin_username'] ) ? $options['feedbin_username'] : '';
		$constant_defined = defined( 'FEED_TO_BLOGROLL_USERNAME' );

		$attr_disabled = $constant_defined ? 'disabled' : '';
		printf(
			'<input type="email" id="feedbin_username" name="feed_to_blogroll_options[feedbin_username]" value="%s" class="regular-text" %s aria-describedby="username-description" />',
			esc_attr( $username ),
			esc_attr( $attr_disabled )
		);
		echo '<p id="username-description" class="description">' . esc_html__( 'Your Feedbin account email address', 'feed-to-blogroll' ) . '</p>';
		if ( $constant_defined ) {
			echo '<p class="description"><em>' . esc_html__( 'This field is locked because FEED_TO_BLOGROLL_USERNAME is defined in wp-config.php.', 'feed-to-blogroll' ) . '</em></p>';
		}
	}

	/**
	 * Password field callback
	 */
	public function password_field_callback() {
		$options  = get_option( 'feed_to_blogroll_options', array() );
		$password = isset( $options['feedbin_password'] ) ? $options['feedbin_password'] : '';
		$constant_defined = defined( 'FEED_TO_BLOGROLL_PASSWORD' );

		$attr_disabled = $constant_defined ? 'disabled' : '';
		printf(
			'<input type="password" id="feedbin_password" name="feed_to_blogroll_options[feedbin_password]" value="%s" class="regular-text" %s aria-describedby="password-description" />',
			esc_attr( $password ),
			esc_attr( $attr_disabled )
		);
		echo '<p id="password-description" class="description">' . esc_html__( 'Your Feedbin account password', 'feed-to-blogroll' ) . '</p>';
		if ( $constant_defined ) {
			echo '<p class="description"><em>' . esc_html__( 'This field is locked because FEED_TO_BLOGROLL_PASSWORD is defined in wp-config.php.', 'feed-to-blogroll' ) . '</em></p>';
		}
	}

	/**
	 * Sync section callback
	 */
	public function sync_section_callback() {
		echo '<p>' . esc_html__( 'Configure how often the plugin should automatically synchronize with your Feedbin account.', 'feed-to-blogroll' ) . '</p>';
	}

	/**
	 * Auto sync field callback
	 */
	public function auto_sync_field_callback() {
		$options   = get_option( 'feed_to_blogroll_options', array() );
		$auto_sync = isset( $options['auto_sync'] ) ? $options['auto_sync'] : true;

		printf(
			'<label><input type="checkbox" id="auto_sync" name="feed_to_blogroll_options[auto_sync]" value="1" %s /> %s</label>',
			checked( 1, $auto_sync, false ),
			esc_html__( 'Enable automatic synchronization', 'feed-to-blogroll' )
		);
		echo '<p class="description">' . esc_html__( 'When enabled, the plugin will automatically sync with Feedbin according to the frequency set below.', 'feed-to-blogroll' ) . '</p>';
	}

	/**
	 * Sync frequency field callback
	 */
	public function sync_frequency_field_callback() {
		$options   = get_option( 'feed_to_blogroll_options', array() );
		$frequency = isset( $options['sync_frequency'] ) ? $options['sync_frequency'] : 'daily';
		$auto_sync = isset( $options['auto_sync'] ) ? $options['auto_sync'] : true;

		$frequencies = array(
			'twice_daily' => __( 'Twice a day (morning & evening)', 'feed-to-blogroll' ),
			'daily'       => __( 'Once a day (morning)', 'feed-to-blogroll' ),
			'weekly'      => __( 'Once a week (Monday morning)', 'feed-to-blogroll' ),
		);

		$disabled = $auto_sync ? '' : 'disabled';
		$class    = $auto_sync ? 'regular-text' : 'regular-text sync-frequency-disabled';

		echo '<select id="sync_frequency" name="feed_to_blogroll_options[sync_frequency]" ' . esc_attr( $disabled ) . ' class="' . esc_attr( $class ) . '">';
		foreach ( $frequencies as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $frequency, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		if ( $auto_sync ) {
			echo '<div class="next-sync-details">';
			echo '<p class="description">';
			echo '<strong>' . esc_html__( 'Next sync:', 'feed-to-blogroll' ) . '</strong> ';
			$next_sync_info = $this->get_next_sync_time( $frequency );
			echo '<span class="next-sync-time ' . esc_attr( $next_sync_info['class'] ) . '">' . esc_html( $next_sync_info['text'] ) . '</span>';
			echo '</p>';
			echo '<p class="sync-schedule-info">';
			echo '<small>' . esc_html( $this->get_schedule_details( $frequency ) ) . '</small>';
			echo '</p>';
			echo '</div>';
		} else {
			echo '<p class="description sync-frequency-disabled">';
			echo '<em>' . esc_html__( 'Enable automatic synchronization above to set frequency', 'feed-to-blogroll' ) . '</em>';
			echo '</p>';
		}
	}

	/**
	 * Enqueue admin scripts with improved accessibility
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'toplevel_page_feed-to-blogroll', 'blogroll_page_feed-to-blogroll-settings', 'blogroll_page_feed-to-blogroll-diagnostics' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'feed-to-blogroll-admin',
			FEED_TO_BLOGROLL_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'common', 'wp-a11y' ),
			FEED_TO_BLOGROLL_VERSION,
			true
		);

		// Activer le système natif WordPress de postboxes
		wp_enqueue_script( 'postbox' );
		wp_add_inline_script(
			'feed-to-blogroll-admin',
			'jQuery(function($){ if ( typeof postboxes !== "undefined" ) { postboxes.add_postbox_toggles("toplevel_page_feed-to-blogroll"); postboxes.add_postbox_toggles("blogroll_page_feed-to-blogroll-settings"); postboxes.add_postbox_toggles("blogroll_page_feed-to-blogroll-diagnostics"); } });'
		);

		wp_localize_script(
			'feed-to-blogroll-admin',
			'feedToBlogrollAdmin',
			array(
				'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'feed_to_blogroll_admin' ),
				'strings' => array(
					'testing'   => __( 'Testing connection...', 'feed-to-blogroll' ),
					'syncing'   => __( 'Synchronizing...', 'feed-to-blogroll' ),
					'exporting' => __( 'Exporting...', 'feed-to-blogroll' ),
					'error'     => __( 'An error occurred', 'feed-to-blogroll' ),
				),
			)
		);

		wp_enqueue_style(
			'feed-to-blogroll-admin',
			FEED_TO_BLOGROLL_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FEED_TO_BLOGROLL_VERSION
		);
	}

	/**
	 * Main admin page with improved accessibility
	 */
	public function admin_page() {
		$sync       = new Feed_To_Blogroll_Sync();
		$stats      = $sync->get_sync_stats();
		$api        = new Feed_To_Blogroll_Feedbin_API();
		$api_status = $api->get_api_status();

		// Get current tab with validation
		$allowed_tabs = array( 'dashboard', 'blogs', 'export', 'settings' );
		$current_tab  = 'dashboard';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tab'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
			if ( in_array( $requested_tab, $allowed_tabs, true ) ) {
				$current_tab = $requested_tab;
			}
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Feed to Blogroll', 'feed-to-blogroll' ); ?></h1>
			
			<hr class="wp-header-end">
			
			<nav class="nav-tab-wrapper wp-clearfix" role="tablist" aria-label="<?php esc_attr_e( 'Blogroll management tabs', 'feed-to-blogroll' ); ?>">
				<a href="?page=feed-to-blogroll&tab=dashboard" 
				   class="nav-tab <?php echo esc_attr( 'dashboard' === $current_tab ? 'nav-tab-active' : '' ); ?>"
				   role="tab"
				   aria-selected="<?php echo esc_attr( 'dashboard' === $current_tab ? 'true' : 'false' ); ?>"
				   aria-controls="tab-dashboard">
					<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
					<?php esc_html_e( 'Dashboard', 'feed-to-blogroll' ); ?>
				</a>
				<a href="?page=feed-to-blogroll&tab=blogs" 
				   class="nav-tab <?php echo esc_attr( 'blogs' === $current_tab ? 'nav-tab-active' : '' ); ?>"
				   role="tab"
				   aria-selected="<?php echo esc_attr( 'blogs' === $current_tab ? 'true' : 'false' ); ?>"
				   aria-controls="tab-blogs">
					<span class="dashicons dashicons-rss" aria-hidden="true"></span>
					<?php esc_html_e( 'Blogs', 'feed-to-blogroll' ); ?>
				</a>
				<a href="?page=feed-to-blogroll&tab=export" 
				   class="nav-tab <?php echo esc_attr( 'export' === $current_tab ? 'nav-tab-active' : '' ); ?>"
				   role="tab"
				   aria-selected="<?php echo esc_attr( 'export' === $current_tab ? 'true' : 'false' ); ?>"
				   aria-controls="tab-export">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Export', 'feed-to-blogroll' ); ?>
				</a>
				<a href="?page=feed-to-blogroll&tab=settings" 
				   class="nav-tab <?php echo esc_attr( 'settings' === $current_tab ? 'nav-tab-active' : '' ); ?>"
				   role="tab"
				   aria-selected="<?php echo esc_attr( 'settings' === $current_tab ? 'true' : 'false' ); ?>"
				   aria-controls="tab-settings">
					<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
					<?php esc_html_e( 'Settings', 'feed-to-blogroll' ); ?>
				</a>
			</nav>

			<div id="tab-dashboard" role="tabpanel" aria-labelledby="dashboard-tab" class="tab-content <?php echo esc_attr( 'dashboard' === $current_tab ? 'active' : '' ); ?>">
				<?php $this->dashboard_tab_content( $stats, $api_status ); ?>
			</div>

			<div id="tab-blogs" role="tabpanel" aria-labelledby="blogs-tab" class="tab-content <?php echo esc_attr( 'blogs' === $current_tab ? 'active' : '' ); ?>">
				<?php $this->blogs_tab_content(); ?>
			</div>

			<div id="tab-export" role="tabpanel" aria-labelledby="export-tab" class="tab-content <?php echo esc_attr( 'export' === $current_tab ? 'active' : '' ); ?>">
				<?php $this->export_tab_content(); ?>
			</div>

			<div id="tab-settings" role="tabpanel" aria-labelledby="settings-tab" class="tab-content <?php echo esc_attr( 'settings' === $current_tab ? 'active' : '' ); ?>">
				<?php $this->settings_tab_content(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Dashboard tab content
	 */
	private function dashboard_tab_content( $stats, $api_status ) {
		?>
		<div class="dashboard-widgets-wrap">
			<div id="dashboard-widgets" class="metabox-holder">
				<div class="postbox-container">
					<div class="meta-box-sortables">
						<div class="postbox">
							<h2 class="hndle ui-sortable-handle">
								<span><?php esc_html_e( 'Synchronization Status', 'feed-to-blogroll' ); ?></span>
							</h2>
							<div class="inside">
								<?php $this->display_sync_status( $stats ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display synchronization status
	 */
	private function display_sync_status( $stats ) {
		$options = get_option( 'feed_to_blogroll_options', array() );
		$last_sync = isset( $options['last_sync'] ) ? $options['last_sync'] : '';
		$sync_status = isset( $options['sync_status'] ) ? $options['sync_status'] : 'idle';

		if ( $last_sync ) {
			$last_sync_time = strtotime( $last_sync );
			$time_ago = human_time_diff( $last_sync_time, time() );

			echo '<div class="sync-status-info">';
			echo '<p><strong>' . esc_html__( 'Last sync:', 'feed-to-blogroll' ) . '</strong> ';
			echo esc_html( date_i18n( 'F j, Y \a\t g:i a', $last_sync_time ) );
			/* translators: %s: time ago */
			echo ' <em>(' . esc_html( sprintf( __( '%s ago', 'feed-to-blogroll' ), $time_ago ) ) . ')</em></p>';

			echo '<p><strong>' . esc_html__( 'Status:', 'feed-to-blogroll' ) . '</strong> ';
			echo '<span class="sync-status-' . esc_attr( $sync_status ) . '">' . esc_html( $this->get_status_label( $sync_status ) ) . '</span></p>';
			echo '</div>';
		} else {
			echo '<p><em>' . esc_html__( 'No synchronization has been performed yet.', 'feed-to-blogroll' ) . '</em></p>';
		}

		// Display sync statistics
		if ( ! empty( $stats ) ) {
			echo '<div class="sync-stats">';
			echo '<h3>' . esc_html__( 'Sync Statistics', 'feed-to-blogroll' ) . '</h3>';
			echo '<ul>';
			if ( isset( $stats['blogs_added'] ) ) {
				echo '<li>' . esc_html__( 'Blogs added:', 'feed-to-blogroll' ) . ' ' . esc_html( $stats['blogs_added'] ) . '</li>';
			}
			if ( isset( $stats['blogs_updated'] ) ) {
				echo '<li>' . esc_html__( 'Blogs updated:', 'feed-to-blogroll' ) . ' ' . esc_html( $stats['blogs_updated'] ) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		// Manual sync button
		echo '<div class="manual-sync-section">';
		echo '<button type="button" id="manual-sync" class="button button-primary" data-nonce="' . esc_attr( wp_create_nonce( 'feed_to_blogroll_admin' ) ) . '">';
		echo esc_html__( 'Manual Sync', 'feed-to-blogroll' );
		echo '</button>';
		echo '<span class="spinner" style="float: none; margin-left: 10px;"></span>';
		echo '</div>';
	}

	/**
	 * Get status label
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'success'   => __( 'Success', 'feed-to-blogroll' ),
			'error'     => __( 'Error', 'feed-to-blogroll' ),
			'running'   => __( 'Running', 'feed-to-blogroll' ),
			'completed' => __( 'Completed', 'feed-to-blogroll' ),
			'idle'      => __( 'Idle', 'feed-to-blogroll' ),
			'unknown'   => __( 'Unknown', 'feed-to-blogroll' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['unknown'];
	}

	/**
	 * Test API connection with improved security
	 */
	public function test_connection() {
		// Vérifier la méthode HTTP
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid request method', 'feed-to-blogroll' ),
					'code'    => 'invalid_method',
					'context' => 'http_validation',
				)
			);
		}

		// Vérifier le nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'feed_to_blogroll_admin' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'feed-to-blogroll' ),
					'code'    => 'nonce_failed',
					'context' => 'security_validation',
				)
			);
		}

		// Vérifier les capacités utilisateur
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions to perform this action.', 'feed-to-blogroll' ),
					'code'    => 'insufficient_permissions',
					'context' => 'capability_check',
				)
			);
		}

		$api = new Feed_To_Blogroll_Feedbin_API();
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html( $result->get_error_message() ),
					'code'    => 'api_error',
					'context' => 'connection_test',
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => esc_html( $result ),
				'code'    => 'success',
				'context' => 'connection_test',
			)
		);
	}

	/**
	 * Export OPML with improved security
	 */
	public function export_opml() {
		// Vérifier la méthode HTTP
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid request method', 'feed-to-blogroll' ),
					'code'    => 'invalid_method',
					'context' => 'http_validation',
				)
			);
		}

		// Vérifier le nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'feed_to_blogroll_admin' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'feed-to-blogroll' ),
					'code'    => 'nonce_failed',
					'context' => 'security_validation',
				)
			);
		}

		// Vérifier les capacités utilisateur
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions to perform this action.', 'feed-to-blogroll' ),
					'code'    => 'insufficient_permissions',
					'context' => 'capability_check',
				)
			);
		}

		// Try cached OPML first
		$cached = get_transient( 'feed_to_blogroll_opml' );
		if ( false !== $cached && is_array( $cached ) && isset( $cached['opml'], $cached['filename'] ) ) {
			wp_send_json_success( $cached );
		}

		$blogs = get_posts(
			array(
				'post_type'              => 'blogroll',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$opml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$opml .= '<opml version="2.0">' . "\n";
		$opml .= '  <head>' . "\n";
		$opml .= '    <title>' . esc_html( get_bloginfo( 'name' ) ) . ' Blogroll</title>' . "\n";
		$opml .= '    <dateCreated>' . gmdate( 'D, d M Y H:i:s' ) . ' GMT</dateCreated>' . "\n";
		$opml .= '  </head>' . "\n";
		$opml .= '  <body>' . "\n";

		foreach ( $blogs as $blog ) {
			$rss_url = get_field( 'rss_url', $blog->ID );
			$site_url = get_field( 'site_url', $blog->ID );

			if ( $rss_url ) {
				$opml .= '    <outline type="rss" text="' . esc_attr( $blog->post_title ) . '" title="' . esc_attr( $blog->post_title ) . '" xmlUrl="' . esc_attr( $rss_url ) . '" htmlUrl="' . esc_attr( $site_url ) . '" />' . "\n";
			}
		}

		$opml .= '  </body>' . "\n";
		$opml .= '</opml>';

		$response = array(
			'opml'     => $opml,
			'filename' => 'blogroll-' . gmdate( 'Y-m-d' ) . '.opml',
		);

		// Cache OPML for 1 hour
		set_transient( 'feed_to_blogroll_opml', $response, HOUR_IN_SECONDS );

		wp_send_json_success( $response );
	}

	/**
	 * Get next sync time based on frequency
	 */
	private function get_next_sync_time( $frequency ) {
		$last_sync = get_option( 'feed_to_blogroll_options' )['last_sync'] ?? '';

		if ( ! $last_sync ) {
			return array(
				'text'  => __( 'Not scheduled yet', 'feed-to-blogroll' ),
				'class' => 'not-scheduled',
			);
		}

		$last_sync_time = strtotime( $last_sync );
		$next_sync_time = $this->calculate_next_sync( $last_sync_time, $frequency );

		if ( time() >= $next_sync_time ) {
			return array(
				'text'  => __( 'Due now', 'feed-to-blogroll' ),
				'class' => 'due-now',
			);
		}

		$time_until = human_time_diff( time(), $next_sync_time );
		return array(
			/* translators: %s: time until next sync */
			'text'  => sprintf( __( 'In %s', 'feed-to-blogroll' ), $time_until ),
			'class' => 'scheduled',
		);
	}

	/**
	 * Calculate next sync time
	 */
	private function calculate_next_sync( $last_sync_time, $frequency ) {
		switch ( $frequency ) {
			case 'twice_daily':
				return $last_sync_time + ( 12 * HOUR_IN_SECONDS );
			case 'daily':
				return $last_sync_time + DAY_IN_SECONDS;
			case 'weekly':
				return $last_sync_time + WEEK_IN_SECONDS;
			default:
				return $last_sync_time + DAY_IN_SECONDS;
		}
	}

	/**
	 * Get schedule details for frequency
	 */
	private function get_schedule_details( $frequency ) {
		switch ( $frequency ) {
			case 'twice_daily':
				return __( 'Schedule: Morning (9:00 AM) and Evening (9:00 PM)', 'feed-to-blogroll' );
			case 'daily':
				return __( 'Schedule: Every morning at 9:00 AM', 'feed-to-blogroll' );
			case 'weekly':
				return __( 'Schedule: Every Monday morning at 9:00 AM', 'feed-to-blogroll' );
			default:
				return __( 'Schedule: Daily at 9:00 AM', 'feed-to-blogroll' );
		}
	}

	/**
	 * Check CPT registration
	 */
	public function check_cpt_registration() {
		if ( ! post_type_exists( 'blogroll' ) ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Feed to Blogroll: Custom Post Type not registered. Please deactivate and reactivate the plugin.', 'feed-to-blogroll' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Show admin notice when credentials constants are defined in wp-config.php
	 */
	public function show_constants_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! defined( 'FEED_TO_BLOGROLL_USERNAME' ) && ! defined( 'FEED_TO_BLOGROLL_PASSWORD' ) ) {
			return;
		}

		// Limit notice to our plugin screens if possible
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && strpos( (string) $screen->id, 'feed-to-blogroll' ) === false ) {
			return;
		}

		$parts = array();
		if ( defined( 'FEED_TO_BLOGROLL_USERNAME' ) ) {
			$parts[] = 'FEED_TO_BLOGROLL_USERNAME';
		}
		if ( defined( 'FEED_TO_BLOGROLL_PASSWORD' ) ) {
			$parts[] = 'FEED_TO_BLOGROLL_PASSWORD';
		}

		$message = sprintf(
			/* translators: %s: list of constants */
			esc_html__( 'Credentials are controlled by %s in wp-config.php. Related settings fields are read-only.', 'feed-to-blogroll' ),
			esc_html( implode( ', ', $parts ) )
		);

		echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Placeholder methods for other tabs
	 */
	private function blogs_tab_content() {
		echo '<p>' . esc_html__( 'Blogs management content will be displayed here.', 'feed-to-blogroll' ) . '</p>';
	}

	private function export_tab_content() {
		echo '<p>' . esc_html__( 'Export functionality will be displayed here.', 'feed-to-blogroll' ) . '</p>';
	}

	private function settings_tab_content() {
		echo '<form method="post" action="options.php">';
		settings_fields( 'feed_to_blogroll_options' );
		do_settings_sections( 'feed_to_blogroll_options' );
		submit_button();
		echo '</form>';
	}
}

