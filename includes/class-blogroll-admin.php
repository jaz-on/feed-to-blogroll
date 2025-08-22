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
		if ( plugin_basename( FEED_TO_BLOGROLL_PLUGIN_DIR . 'feed-to-blogroll.php' ) === $file ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=feed-to-blogroll&tab=settings' ) ),
				__( 'Settings', 'feed-to-blogroll' )
			);
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * Initialize settings
	 */
	public function init_settings() {
		register_setting(
			'feed_to_blogroll_options',
			'feed_to_blogroll_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default' => array(
					'feedbin_username' => '',
					'feedbin_password' => '',
					'sync_frequency' => 'daily',
					'auto_sync' => true,
					'last_sync' => '',
					'sync_status' => 'idle',
				),
			)
		);

		// API Configuration Section
		add_settings_section(
			'feed_to_blogroll_api_section',
			__( 'Feedbin API Configuration', 'feed-to-blogroll' ),
			array( $this, 'api_section_callback' ),
			'feed_to_blogroll_options'
		);

		add_settings_field(
			'feedbin_username',
			__( 'Feedbin Username', 'feed-to-blogroll' ),
			array( $this, 'username_field_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_api_section'
		);

		add_settings_field(
			'feedbin_password',
			__( 'Feedbin Password', 'feed-to-blogroll' ),
			array( $this, 'password_field_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_api_section'
		);

		// Synchronization Section
		add_settings_section(
			'feed_to_blogroll_sync_section',
			__( 'Synchronization Settings', 'feed-to-blogroll' ),
			array( $this, 'sync_section_callback' ),
			'feed_to_blogroll_options'
		);

		add_settings_field(
			'auto_sync',
			__( 'Automatic Synchronization', 'feed-to-blogroll' ),
			array( $this, 'auto_sync_field_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_sync_section'
		);

		add_settings_field(
			'sync_frequency',
			__( 'Sync Frequency', 'feed-to-blogroll' ),
			array( $this, 'sync_frequency_field_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_sync_section'
		);

		add_settings_field(
			'last_sync_info',
			__( 'Last Synchronization', 'feed-to-blogroll' ),
			array( $this, 'last_sync_info_callback' ),
			'feed_to_blogroll_options',
			'feed_to_blogroll_sync_section'
		);
	}

	/**
	 * Sanitize options
	 *
	 * @param array $input Input options.
	 * @return array Sanitized options.
	 */
	public function sanitize_options( $input ) {
		$sanitized = array();

		if ( isset( $input['feedbin_username'] ) ) {
			$sanitized['feedbin_username'] = sanitize_email( $input['feedbin_username'] );
		}

		if ( isset( $input['feedbin_password'] ) ) {
			$sanitized['feedbin_password'] = sanitize_text_field( $input['feedbin_password'] );
		}

		if ( isset( $input['auto_sync'] ) ) {
			$sanitized['auto_sync'] = (bool) $input['auto_sync'];
		}

		if ( isset( $input['sync_frequency'] ) ) {
			$sanitized['sync_frequency'] = sanitize_text_field( $input['sync_frequency'] );
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
		$options = get_option( 'feed_to_blogroll_options', array() );
		$username = isset( $options['feedbin_username'] ) ? $options['feedbin_username'] : '';

		printf(
			'<input type="email" id="feedbin_username" name="feed_to_blogroll_options[feedbin_username]" value="%s" class="regular-text" required />',
			esc_attr( $username )
		);
		echo '<p class="description">' . esc_html__( 'Your Feedbin account email address', 'feed-to-blogroll' ) . '</p>';
	}

	/**
	 * Password field callback
	 */
	public function password_field_callback() {
		$options = get_option( 'feed_to_blogroll_options', array() );
		$password = isset( $options['feedbin_password'] ) ? $options['feedbin_password'] : '';

		printf(
			'<input type="password" id="feedbin_password" name="feed_to_blogroll_options[feedbin_password]" value="%s" class="regular-text" required />',
			esc_attr( $password )
		);
		echo '<p class="description">' . esc_html__( 'Your Feedbin account password', 'feed-to-blogroll' ) . '</p>';
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
		$options = get_option( 'feed_to_blogroll_options', array() );
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
		$options = get_option( 'feed_to_blogroll_options', array() );
		$frequency = isset( $options['sync_frequency'] ) ? $options['sync_frequency'] : 'daily';
		$auto_sync = isset( $options['auto_sync'] ) ? $options['auto_sync'] : true;

		$frequencies = array(
			'twice_daily' => __( 'Twice a day (morning & evening)', 'feed-to-blogroll' ),
			'daily'       => __( 'Once a day (morning)', 'feed-to-blogroll' ),
			'weekly'      => __( 'Once a week (Monday morning)', 'feed-to-blogroll' ),
		);

		$disabled = $auto_sync ? '' : 'disabled';
		$class = $auto_sync ? 'regular-text' : 'regular-text sync-frequency-disabled';

		echo '<select id="sync_frequency" name="feed_to_blogroll_options[sync_frequency]" ' . $disabled . ' class="' . $class . '">';
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
			echo '<small>' . $this->get_schedule_details( $frequency ) . '</small>';
			echo '</p>';
			echo '</div>';
		} else {
			echo '<p class="description sync-frequency-disabled">';
			echo '<em>' . esc_html__( 'Enable automatic synchronization above to set frequency', 'feed-to-blogroll' ) . '</em>';
			echo '</p>';
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page.
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
					'testing'     => __( 'Testing connection...', 'feed-to-blogroll' ),
					'syncing'     => __( 'Synchronizing...', 'feed-to-blogroll' ),
					'exporting'   => __( 'Exporting...', 'feed-to-blogroll' ),
					'error'       => __( 'An error occurred', 'feed-to-blogroll' ),
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
	 * Main admin page
	 */
	public function admin_page() {
		$sync = new Feed_To_Blogroll_Sync();
		$stats = $sync->get_sync_stats();
		$api = new Feed_To_Blogroll_Feedbin_API();
		$api_status = $api->get_api_status();

		// Get current tab
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Feed to Blogroll', 'feed-to-blogroll' ); ?></h1>
			
			<hr class="wp-header-end">
			
			<nav class="nav-tab-wrapper wp-clearfix">
				<a href="?page=feed-to-blogroll&tab=dashboard" class="nav-tab <?php echo esc_attr( $current_tab === 'dashboard' ? 'nav-tab-active' : '' ); ?>">
					<span class="dashicons dashicons-dashboard"></span>
					<?php esc_html_e( 'Dashboard', 'feed-to-blogroll' ); ?>
				</a>
				<a href="?page=feed-to-blogroll&tab=blogs" class="nav-tab <?php echo esc_attr( $current_tab === 'blogs' ? 'nav-tab-active' : '' ); ?>">
					<span class="dashicons dashicons-rss"></span>
					<?php esc_html_e( 'Blogs', 'feed-to-blogroll' ); ?>
				</a>
				<a href="?page=feed-to-blogroll&tab=export" class="nav-tab <?php echo esc_attr( $current_tab === 'export' ? 'nav-tab-active' : '' ); ?>">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export', 'feed-to-blogroll' ); ?>
				</a>
				<a href="?page=feed-to-blogroll&tab=settings" class="nav-tab <?php echo esc_attr( $current_tab === 'settings' ? 'nav-tab-active' : '' ); ?>">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Settings', 'feed-to-blogroll' ); ?>
				</a>
			</nav>
			
			<?php
			switch ( $current_tab ) {
				case 'dashboard':
					$this->render_dashboard_tab( $stats, $api_status );
					break;
				case 'blogs':
					$this->render_blogs_tab();
					break;
				case 'export':
					$this->render_export_tab();
					break;
				case 'settings':
					$this->render_settings_tab();
					break;
				default:
					$this->render_dashboard_tab( $stats, $api_status );
					break;
			}
			?>
			
			<div id="feed-to-blogroll-messages"></div>
		</div>
		<?php
	}

	/**
	 * Render dashboard tab
	 *
	 * @param array $stats Statistics data.
	 * @param array $api_status API status data.
	 */
	private function render_dashboard_tab( $stats, $api_status ) {
		?>
		<div class="metabox-holder">
			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e( 'Statistics', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Active Blogs', 'feed-to-blogroll' ); ?></th>
								<td>
									<strong><?php echo esc_html( $stats['total_blogs'] ); ?></strong>
									<p class="description"><?php esc_html_e( 'Number of blogs currently published', 'feed-to-blogroll' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Inactive Blogs', 'feed-to-blogroll' ); ?></th>
								<td>
									<strong><?php echo esc_html( $stats['inactive_blogs'] ); ?></strong>
									<p class="description"><?php esc_html_e( 'Number of blogs marked as inactive', 'feed-to-blogroll' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last Sync', 'feed-to-blogroll' ); ?></th>
								<td>
									<strong><?php echo esc_html( $stats['last_sync'] ? date_i18n( 'F j, Y \a\t g:i a', strtotime( $stats['last_sync'] ) ) : '—' ); ?></strong>
									<p class="description"><?php esc_html_e( 'Date and time of the last synchronization', 'feed-to-blogroll' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Quick Actions', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<p>
						<button type="button" class="button button-primary" id="manual-sync">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Manual Sync', 'feed-to-blogroll' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="test-connection">
							<span class="dashicons dashicons-admin-network"></span>
							<?php esc_html_e( 'Test Connection', 'feed-to-blogroll' ); ?>
						</button>
						<a href="?page=feed-to-blogroll&tab=export" class="button button-secondary">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export OPML', 'feed-to-blogroll' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=blogroll' ) ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-list-view"></span>
							<?php esc_html_e( 'Manage Blogs', 'feed-to-blogroll' ); ?>
						</a>
					</p>
					<div id="sync-progress" style="display: none;">
						<div class="notice notice-info">
							<p>
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Synchronization in progress...', 'feed-to-blogroll' ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>

			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-admin-network"></span>
					<?php esc_html_e( 'System Status', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'API Connection', 'feed-to-blogroll' ); ?></th>
								<td>
									<?php
									$connection_status = $api_status['connection_test'] ?? 'unknown';
									$status_class = $this->get_status_class( $connection_status );
									$status_label = $this->get_status_label( $connection_status );
									?>
									<span class="dashicons dashicons-<?php echo esc_attr( $status_class['icon'] ); ?>"></span>
									<span class="status-<?php echo esc_attr( $connection_status ); ?>">
										<?php echo esc_html( $status_label ); ?>
									</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Sync Status', 'feed-to-blogroll' ); ?></th>
								<td>
									<?php
									$sync_status = $stats['sync_status'];
									$status_class = $this->get_status_class( $sync_status );
									$status_label = $this->get_status_label( $sync_status );
									?>
									<span class="dashicons dashicons-<?php echo esc_attr( $status_class['icon'] ); ?>"></span>
									<span class="status-<?php echo esc_attr( $sync_status ); ?>">
										<?php echo esc_html( $status_label ); ?>
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render blogs tab
	 */
	private function render_blogs_tab() {
		?>
		<div class="metabox-holder">
			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-rss"></span>
					<?php esc_html_e( 'Manage Blogs', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<p>
						<?php esc_html_e( 'Manage your blogroll entries. You can edit, delete, or change the status of individual blogs.', 'feed-to-blogroll' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=blogroll' ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-list-view"></span>
							<?php esc_html_e( 'View All Blogs', 'feed-to-blogroll' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=blogroll' ) ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Add New Blog', 'feed-to-blogroll' ); ?>
						</a>
					</p>
				</div>
			</div>

			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-category"></span>
					<?php esc_html_e( 'Categories', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<p>
						<?php esc_html_e( 'Organize your blogs into categories for better management and display.', 'feed-to-blogroll' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=blogroll_category&post_type=blogroll' ) ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-category"></span>
							<?php esc_html_e( 'Manage Categories', 'feed-to-blogroll' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render export tab
	 */
	private function render_export_tab() {
		?>
		<div class="metabox-holder">
			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export OPML', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<p>
						<?php esc_html_e( 'Export your blogroll as an OPML file. This file can be imported into other RSS readers or used as a backup.', 'feed-to-blogroll' ); ?>
					</p>
					<p>
						<button type="button" class="button button-primary" id="export-opml">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export OPML File', 'feed-to-blogroll' ); ?>
						</button>
					</p>
					<div class="notice notice-info">
						<p>
							<strong><?php esc_html_e( 'Note:', 'feed-to-blogroll' ); ?></strong>
							<?php esc_html_e( 'The OPML file will include all active blogs in your blogroll. Only blogs with valid RSS URLs will be included in the export.', 'feed-to-blogroll' ); ?>
						</p>
					</div>
				</div>
			</div>

			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'About OPML', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<p>
						<?php esc_html_e( 'OPML (Outline Processor Markup Language) is a standard format for exchanging lists of RSS feeds between different RSS readers and aggregators.', 'feed-to-blogroll' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'You can use the exported OPML file to:', 'feed-to-blogroll' ); ?>
					</p>
					<ul class="ul-disc">
						<li><?php esc_html_e( 'Backup your blogroll', 'feed-to-blogroll' ); ?></li>
						<li><?php esc_html_e( 'Import feeds into other RSS readers', 'feed-to-blogroll' ); ?></li>
						<li><?php esc_html_e( 'Share your blogroll with others', 'feed-to-blogroll' ); ?></li>
						<li><?php esc_html_e( 'Migrate to a different RSS reader', 'feed-to-blogroll' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab
	 */
	private function render_settings_tab() {
		?>
		<div class="metabox-holder">
			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'Getting Started', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<div class="feed-to-blogroll-setup-instructions">
						<h3><?php esc_html_e( 'Getting Started with Feed to Blogroll', 'feed-to-blogroll' ); ?></h3>
						<p><?php esc_html_e( 'Follow these steps to set up automatic synchronization with your Feedbin account:', 'feed-to-blogroll' ); ?></p>
						
						<ol>
							<li>
								<strong><?php esc_html_e( 'Get your Feedbin credentials:', 'feed-to-blogroll' ); ?></strong>
								<?php esc_html_e( 'Go to', 'feed-to-blogroll' ); ?>
								<a href="https://feedbin.com" target="_blank" rel="noopener noreferrer">Feedbin.com</a>
								<?php esc_html_e( 'and sign in to your account. If you don\'t have an account yet,', 'feed-to-blogroll' ); ?>
								<a href="https://feedbin.com/signup" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'sign up for free', 'feed-to-blogroll' ); ?></a>.
							</li>
							<li>
								<strong><?php esc_html_e( 'Find your API credentials:', 'feed-to-blogroll' ); ?></strong>
								<?php esc_html_e( 'Feedbin uses your regular login credentials (email and password) for API access. No special API keys needed!', 'feed-to-blogroll' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Enter credentials below:', 'feed-to-blogroll' ); ?></strong>
								<?php esc_html_e( 'Use your Feedbin email and password in the fields below. These are the same credentials you use to log into Feedbin.com.', 'feed-to-blogroll' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Test connection:', 'feed-to-blogroll' ); ?></strong>
								<?php esc_html_e( 'Go to the Dashboard tab and click "Test Connection" to verify your setup.', 'feed-to-blogroll' ); ?>
							</li>
						</ol>
						
						<div class="notice notice-warning">
							<p>
								<strong><?php esc_html_e( 'Important Note:', 'feed-to-blogroll' ); ?></strong>
								<?php esc_html_e( 'Feedbin does not provide separate API keys. You must use your regular login credentials (email + password) for API access.', 'feed-to-blogroll' ); ?>
								<br>
								<?php esc_html_e( 'This is a limitation of Feedbin\'s API design, not this plugin. Your credentials are stored securely and are only used to authenticate with the Feedbin API. They are never shared or displayed.', 'feed-to-blogroll' ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>

			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Feedbin API Configuration', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'feed_to_blogroll_options' );
						do_settings_sections( 'feed_to_blogroll_options' );
						submit_button( __( 'Save Settings', 'feed-to-blogroll' ) );
						?>
					</form>
				</div>
			</div>

			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Next Steps After Configuration', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<ol>
						<li><?php esc_html_e( 'Save your settings using the button above', 'feed-to-blogroll' ); ?></li>
						<li><?php esc_html_e( 'Go to the Dashboard tab to test your connection', 'feed-to-blogroll' ); ?></li>
						<li><?php esc_html_e( 'Click "Test Connection" to verify your Feedbin credentials', 'feed-to-blogroll' ); ?></li>
						<li><?php esc_html_e( 'Use "Manual Sync" to import your first blogs', 'feed-to-blogroll' ); ?></li>
						<li><?php esc_html_e( 'Add the shortcode [blogroll] to any page to display your blogroll', 'feed-to-blogroll' ); ?></li>
					</ol>
				</div>
			</div>

			<div class="postbox">
				<h2 class="hndle ui-sortable-handle">
					<span class="dashicons dashicons-sos"></span>
					<?php esc_html_e( 'Need Help?', 'feed-to-blogroll' ); ?>
				</h2>
				<div class="inside">
					<div class="feed-to-blogroll-help-links">
						<p>
							<strong><?php esc_html_e( 'For Feedbin support:', 'feed-to-blogroll' ); ?></strong>
							<a href="https://feedbin.com/help" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Help Center', 'feed-to-blogroll' ); ?></a> |
							<a href="https://feedbin.com/blog" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Blog', 'feed-to-blogroll' ); ?></a> |
							<a href="https://github.com/feedbin/feedbin-api" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'API Documentation', 'feed-to-blogroll' ); ?></a>
						</p>
						<p>
							<strong><?php esc_html_e( 'For plugin support:', 'feed-to-blogroll' ); ?></strong>
							<a href="https://github.com/jaz-on/feed-to-blogroll" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub Repository', 'feed-to-blogroll' ); ?></a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get status class information
	 *
	 * @param string $status Status value.
	 * @return array Status class information.
	 */
	private function get_status_class( $status ) {
		$classes = array(
			'success' => array(
				'icon' => 'yes-alt',
				'color' => '#28a745',
			),
			'error' => array(
				'icon' => 'dismiss',
				'color' => '#dc3545',
			),
			'running' => array(
				'icon' => 'update',
				'color' => '#ffc107',
			),
			'completed' => array(
				'icon' => 'yes-alt',
				'color' => '#17a2b8',
			),
			'idle' => array(
				'icon' => 'clock',
				'color' => '#6c757d',
			),
			'unknown' => array(
				'icon' => 'minus',
				'color' => '#6c757d',
			),
		);

		return isset( $classes[ $status ] ) ? $classes[ $status ] : $classes['unknown'];
	}

	/**
	 * Get status label
	 *
	 * @param string $status Status value.
	 * @return string Status label.
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'success' => __( 'Success', 'feed-to-blogroll' ),
			'error'   => __( 'Error', 'feed-to-blogroll' ),
			'running' => __( 'Running', 'feed-to-blogroll' ),
			'completed' => __( 'Completed', 'feed-to-blogroll' ),
			'idle'    => __( 'Idle', 'feed-to-blogroll' ),
			'unknown' => __( 'Unknown', 'feed-to-blogroll' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['unknown'];
	}

	/**
	 * Test API connection
	 */
	public function test_connection() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( esc_html__( 'Missing nonce', 'feed-to-blogroll' ) );
		}
		check_ajax_referer( 'feed_to_blogroll_admin', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'feed-to-blogroll' ) );
		}

		$api = new Feed_To_Blogroll_Feedbin_API();
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( esc_html( $result->get_error_message() ) );
		}

		wp_send_json_success( esc_html( $result ) );
	}

	/**
	 * Export OPML
	 */
	public function export_opml() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( __( 'Missing nonce', 'feed-to-blogroll' ) );
		}
		check_ajax_referer( 'feed_to_blogroll_admin', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'feed-to-blogroll' ) );
		}

		$blogs = get_posts(
			array(
				'post_type'      => 'blogroll',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
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

		wp_send_json_success(
			array(
				'opml' => $opml,
				'filename' => 'blogroll-' . date( 'Y-m-d' ) . '.opml',
			)
		);
	}

	/**
	 * Last sync info field callback
	 */
	public function last_sync_info_callback() {
		$options = get_option( 'feed_to_blogroll_options', array() );
		$last_sync = isset( $options['last_sync'] ) ? $options['last_sync'] : '';
		$sync_status = isset( $options['sync_status'] ) ? $options['sync_status'] : 'idle';

		if ( $last_sync ) {
			$last_sync_time = strtotime( $last_sync );
			$time_ago = human_time_diff( $last_sync_time, current_time( 'timestamp' ) );

			echo '<div class="last-sync-info">';
			echo '<p><strong>' . esc_html__( 'Last sync:', 'feed-to-blogroll' ) . '</strong> ';
			echo esc_html( date_i18n( 'F j, Y \a\t g:i a', $last_sync_time ) );
			echo ' <em>(' . esc_html( sprintf( __( '%s ago', 'feed-to-blogroll' ), $time_ago ) ) . ')</em></p>';

			echo '<p><strong>' . esc_html__( 'Status:', 'feed-to-blogroll' ) . '</strong> ';
			echo '<span class="sync-status-' . esc_attr( $sync_status ) . '">' . esc_html( $this->get_status_label( $sync_status ) ) . '</span></p>';
			echo '</div>';
		} else {
			echo '<p><em>' . esc_html__( 'No synchronization has been performed yet.', 'feed-to-blogroll' ) . '</em></p>';
		}
	}



	/**
	 * Get next sync time based on frequency
	 *
	 * @param string $frequency Sync frequency.
	 * @return array Array with 'text' and 'class' keys.
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

		if ( $next_sync_time <= current_time( 'timestamp' ) ) {
			return array(
				'text'  => __( 'Due now', 'feed-to-blogroll' ),
				'class' => 'due-now',
			);
		}

		$time_until = human_time_diff( current_time( 'timestamp' ), $next_sync_time );
		return array(
			'text'  => sprintf( __( 'In %s', 'feed-to-blogroll' ), $time_until ),
			'class' => 'scheduled',
		);
	}

	/**
	 * Calculate next sync time
	 *
	 * @param int    $last_sync_time Last sync timestamp.
	 * @param string $frequency Sync frequency.
	 * @return int Next sync timestamp.
	 */
	private function calculate_next_sync( $last_sync_time, $frequency ) {
		switch ( $frequency ) {
			case 'twice_daily':
				// Next sync in 12 hours
				return $last_sync_time + ( 12 * HOUR_IN_SECONDS );
			case 'daily':
				// Next sync in 24 hours
				return $last_sync_time + DAY_IN_SECONDS;
			case 'weekly':
				// Next sync in 7 days
				return $last_sync_time + WEEK_IN_SECONDS;
			default:
				return $last_sync_time + DAY_IN_SECONDS;
		}
	}

	/**
	 * Get schedule details for frequency
	 *
	 * @param string $frequency Sync frequency.
	 * @return string Schedule details.
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
	 * Diagnostics page for troubleshooting
	 */
	public function diagnostics_page() {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Feed to Blogroll Diagnostics', 'feed-to-blogroll' ); ?></h1>
			
			<hr class="wp-header-end">
			
			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Diagnostics Information', 'feed-to-blogroll' ); ?></strong>
					<?php esc_html_e( 'This page provides detailed information about your system configuration and plugin status to help troubleshoot any issues.', 'feed-to-blogroll' ); ?>
				</p>
			</div>
			
			<div class="metabox-holder">
				<div class="postbox">
					<h2 class="hndle ui-sortable-handle">
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'System Status', 'feed-to-blogroll' ); ?>
					</h2>
					<div class="inside">
						<?php $this->display_system_status(); ?>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle ui-sortable-handle">
						<span class="dashicons dashicons-admin-network"></span>
						<?php esc_html_e( 'API Connection Test', 'feed-to-blogroll' ); ?>
					</h2>
					<div class="inside">
						<?php $this->display_api_test(); ?>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle ui-sortable-handle">
						<span class="dashicons dashicons-database"></span>
						<?php esc_html_e( 'Database Status', 'feed-to-blogroll' ); ?>
					</h2>
					<div class="inside">
						<?php $this->display_database_status(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display system status
	 */
	private function display_system_status() {
		$options = get_option( 'feed_to_blogroll_options', array() );

		echo '<table class="form-table" role="presentation">';
		echo '<tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Plugin Version:', 'feed-to-blogroll' ) . '</th><td>' . esc_html( FEED_TO_BLOGROLL_VERSION ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'WordPress Version:', 'feed-to-blogroll' ) . '</th><td>' . esc_html( get_bloginfo( 'version' ) ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'PHP Version:', 'feed-to-blogroll' ) . '</th><td>' . esc_html( PHP_VERSION ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'ACF Pro Active:', 'feed-to-blogroll' ) . '</th><td>' . ( class_exists( 'ACF' ) ? '✅' : '❌' ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Blogroll CPT Registered:', 'feed-to-blogroll' ) . '</th><td>' . ( post_type_exists( 'blogroll' ) ? '✅' : '❌' ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Feedbin Credentials Set:', 'feed-to-blogroll' ) . '</th><td>' . ( ! empty( $options['feedbin_username'] ) && ! empty( $options['feedbin_password'] ) ? '✅' : '❌' ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Display API connection test
	 */
	private function display_api_test() {
		echo '<p><button type="button" class="button button-primary" id="diagnostic-api-test">' . esc_html__( 'Test API Connection', 'feed-to-blogroll' ) . '</button></p>';
		echo '<div id="api-test-result"></div>';

		?>
		<script>
		jQuery(document).ready(function($) {
			$('#diagnostic-api-test').on('click', function() {
				var button = $(this);
				button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'feed-to-blogroll' ); ?>');
				
				$.post(ajaxurl, {
					action: 'feed_to_blogroll_test_connection',
					nonce: '<?php echo esc_js( wp_create_nonce( 'feed_to_blogroll_admin' ) ); ?>'
				}, function(response) {
					button.prop('disabled', false).text('<?php esc_html_e( 'Test API Connection', 'feed-to-blogroll' ); ?>');
					
					if (response.success) {
						$('#api-test-result').html('<div class="notice notice-success"><p>✅ ' + response.data.message + ' (' + response.data.count + ' subscriptions)</p></div>');
					} else {
						$('#api-test-result').html('<div class="notice notice-error"><p>❌ ' + response.data + '</p></div>');
					}
				}).fail(function() {
					button.prop('disabled', false).text('<?php esc_html_e( 'Test API Connection', 'feed-to-blogroll' ); ?>');
					$('#api-test-result').html('<div class="notice notice-error"><p>❌ <?php esc_html_e( 'Network error occurred', 'feed-to-blogroll' ); ?></p></div>');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Display database status
	 */
	private function display_database_status() {
		$blogroll_count = wp_count_posts( 'blogroll' );
		$categories_count = wp_count_terms( 'blogroll_category' );
		$options = get_option( 'feed_to_blogroll_options', array() );

		echo '<table class="form-table" role="presentation">';
		echo '<tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Published Blogs:', 'feed-to-blogroll' ) . '</th><td>' . esc_html( $blogroll_count->publish ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Draft Blogs:', 'feed-to-blogroll' ) . '</th><td>' . esc_html( $blogroll_count->draft ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Categories:', 'feed-to-blogroll' ) . '</th><td>' . esc_html( $categories_count ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Last Sync:', 'feed-to-blogroll' ) . '</th><td>' . esc_html( isset( $options['last_sync'] ) ? date_i18n( 'F j, Y \a\t g:i a', strtotime( $options['last_sync'] ) ) : '—' ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Sync Status:', 'feed-to-blogroll' ) . '</th><td>' . esc_html( isset( $options['sync_status'] ) ? $this->get_status_label( $options['sync_status'] ) : '—' ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Check CPT registration and show notice if needed
	 */
	public function check_cpt_registration() {
		// Only show on our plugin pages
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'toplevel_page_feed-to-blogroll', 'blogroll_page_feed-to-blogroll-settings', 'blogroll_page_feed-to-blogroll-diagnostics', 'blogroll_page_feed-to-blogroll-repair' ), true ) ) {
			return;
		}

		// Check if CPT is registered
		if ( ! post_type_exists( 'blogroll' ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Feed to Blogroll Error:', 'feed-to-blogroll' ); ?></strong>
					<?php esc_html_e( 'The blogroll Custom Post Type is not registered. This will prevent synchronization from working.', 'feed-to-blogroll' ); ?>
				</p>
				<p>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=feed-to-blogroll-repair' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Repair Now', 'feed-to-blogroll' ); ?>
					</a>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=feed-to-blogroll&action=force_repair' ), 'force_repair_cpt' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Force Repair', 'feed-to-blogroll' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}
}

