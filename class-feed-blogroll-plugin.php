<?php
/**
 * Main Plugin Class
 *
 * @package FeedBlogroll
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class Feed_Blogroll_Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Feed_Blogroll_Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Feed_Blogroll_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_filter( 'cron_schedules', array( $this, 'register_weekly_cron_schedule' ) );
		add_action( 'plugins_loaded', array( $this, 'migrate_legacy_slug_identifiers' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ), 5 );
		add_action( 'updated_option', array( $this, 'on_updated_feed_blogroll_options' ), 10, 3 );
		add_action( 'init', array( $this, 'init_plugin' ) );
	}

	/**
	 * One-time migration from pre–feed-blogroll option keys, cron hook, and transients.
	 */
	public function migrate_legacy_slug_identifiers() {
		if ( '1' === get_option( 'feed_blogroll_legacy_slug_migration', '' ) ) {
			return;
		}

		$touched = false;

		if ( false !== get_option( 'feed_to_blogroll_options', false ) ) {
			$legacy  = (array) get_option( 'feed_to_blogroll_options', array() );
			$current = get_option( 'feed_blogroll_options', false );
			if ( false === $current ) {
				update_option( 'feed_blogroll_options', $legacy );
			} else {
				update_option( 'feed_blogroll_options', wp_parse_args( (array) $current, $legacy ) );
			}
			delete_option( 'feed_to_blogroll_options' );
			$touched = true;
		}

		$scalar_migrations = array(
			'feed_to_blogroll_plugin_version'  => 'feed_blogroll_plugin_version',
			'feed_to_blogroll_api_last_test'   => 'feed_blogroll_api_last_test',
			'feed_to_blogroll_api_connected'   => 'feed_blogroll_api_connected',
			'feed_to_blogroll_api_last_error'  => 'feed_blogroll_api_last_error',
			'feed_to_blogroll_cache_version'   => 'feed_blogroll_cache_version',
		);
		foreach ( $scalar_migrations as $old_key => $new_key ) {
			if ( false === get_option( $old_key, false ) ) {
				continue;
			}
			if ( false === get_option( $new_key, false ) ) {
				update_option( $new_key, get_option( $old_key ) );
			}
			delete_option( $old_key );
			$touched = true;
		}

		while ( ( $timestamp = wp_next_scheduled( 'feed_to_blogroll_sync_cron' ) ) ) {
			wp_unschedule_event( $timestamp, 'feed_to_blogroll_sync_cron' );
			$touched = true;
		}

		delete_transient( 'feed_to_blogroll_opml' );
		delete_transient( 'feed_to_blogroll_sync_lock' );
		delete_transient( 'feed_to_blogroll_api_cache' );

		update_option( 'feed_blogroll_legacy_slug_migration', '1' );

		if ( $touched ) {
			self::reschedule_sync_cron();
		}
	}

	/**
	 * Register custom cron schedule for weekly sync (not provided by core).
	 *
	 * @param array<string, array<string, int|string>> $schedules Schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public function register_weekly_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'feed-blogroll' ),
			);
		}
		return $schedules;
	}

	/**
	 * After plugin update: reschedule cron, ensure capabilities, store DB version.
	 */
	public function maybe_upgrade() {
		$stored = (string) get_option( 'feed_blogroll_plugin_version', '' );
		if ( version_compare( $stored, FEED_BLOGROLL_VERSION, '>=' ) ) {
			return;
		}

		self::reschedule_sync_cron();
		$this->add_custom_capabilities_once();
		update_option( 'feed_blogroll_plugin_version', FEED_BLOGROLL_VERSION );
	}

	/**
	 * Reschedule sync cron when sync settings change.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 */
	public function on_updated_feed_blogroll_options( $option, $old_value, $value ) {
		if ( 'feed_blogroll_options' !== $option ) {
			return;
		}

		if ( ! is_array( $old_value ) ) {
			$old_value = array();
		}
		if ( ! is_array( $value ) ) {
			return;
		}

		$old_freq = $old_value['sync_frequency'] ?? '';
		$new_freq = $value['sync_frequency'] ?? '';
		$old_auto = ! empty( $old_value['auto_sync'] );
		$new_auto = ! empty( $value['auto_sync'] );

		if ( $old_freq !== $new_freq || $old_auto !== $new_auto ) {
			self::reschedule_sync_cron();
		}
	}

	/**
	 * Clear and re-register the sync cron event from current options.
	 */
	public static function reschedule_sync_cron() {
		wp_clear_scheduled_hook( 'feed_blogroll_sync_cron' );

		$options = get_option( 'feed_blogroll_options', array() );
		if ( empty( $options['auto_sync'] ) ) {
			return;
		}

		$freq = isset( $options['sync_frequency'] ) ? $options['sync_frequency'] : 'daily';

		$schedule = 'daily';
		if ( 'twice_daily' === $freq ) {
			$schedule = 'twicedaily';
		} elseif ( 'weekly' === $freq ) {
			$schedule = 'weekly';
		}

		wp_schedule_event( time(), $schedule, 'feed_blogroll_sync_cron' );
	}

	/**
	 * Textdomain is loaded automatically by WordPress.org (WP ≥ 4.6).
	 */

	/**
	 * Initialize plugin components
	 */
	public function init_plugin() {
		// Load required files
		$this->load_dependencies();

		// Initialize components
		$this->init_components();
	}

	/**
	 * Load plugin dependencies with conditional loading for performance
	 */
	private function load_dependencies() {
		// Core classes always needed
		require_once FEED_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-blogroll-opml.php';
		require_once FEED_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-blogroll-feedbin-api.php';
		require_once FEED_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-blogroll-sync.php';
		require_once FEED_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-blogroll-cpt.php';

		// Admin classes only when needed
		if ( is_admin() ) {
			require_once FEED_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-blogroll-admin.php';
		}

		// Template/shortcodes/REST (REST must register even when is_admin() is true).
		require_once FEED_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-blogroll-template.php';

		// Load repair script only in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			require_once FEED_BLOGROLL_PLUGIN_DIR . 'class-feed-blogroll-repair.php';
		}
	}

	/**
	 * Initialize plugin components
	 */
	private function init_components() {
		// Initialize Custom Post Type
		$cpt = new Feed_Blogroll_CPT();

		// Force registration if not exists
		if ( ! post_type_exists( 'blogroll' ) ) {
			$cpt->force_registration();
		}

		// Initialize Feedbin API
		new Feed_Blogroll_Feedbin_API();

		// Initialize synchronization
		new Feed_Blogroll_Sync();

		// Initialize admin interface only when needed
		if ( is_admin() ) {
			new Feed_Blogroll_Admin();
		}

		new Feed_Blogroll_Template();

		// Initialize block support
		$this->init_block_support();
	}

	/**
	 * Initialize WordPress block support
	 */
	private function init_block_support() {
		// Register block styles
		add_action( 'init', array( $this, 'register_block_styles' ) );

		// Register block using block.json
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * (Removed) Theme support declarations do not belong in a plugin.
	 */

	/**
	 * Register custom block styles
	 */
	public function register_block_styles() {
		// Register blogroll card style
		register_block_style(
			'core/group',
			array(
				'name'         => 'blogroll-card',
				'label'        => __( 'Blogroll Card', 'feed-blogroll' ),
				'inline_style' => '
					.wp-block-group.is-style-blogroll-card {
						background: var(--wp--preset--color--background, #ffffff);
						border: 1px solid var(--wp--preset--color--border, #e1e5e9);
						border-radius: 12px;
						padding: 1.5rem;
						transition: all 0.3s ease;
						box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
					}
					.wp-block-group.is-style-blogroll-card:hover {
						transform: translateY(-4px);
						box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
						border-color: var(--wp--preset--color--primary, #007cba);
					}
				',
			)
		);

		// Register blogroll grid style
		register_block_style(
			'core/group',
			array(
				'name'         => 'blogroll-grid',
				'label'        => __( 'Blogroll Grid', 'feed-blogroll' ),
				'inline_style' => '
					.wp-block-group.is-style-blogroll-grid {
						display: grid;
						gap: 1.5rem;
						grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
					}
					@media (max-width: 768px) {
						.wp-block-group.is-style-blogroll-grid {
							grid-template-columns: 1fr;
						}
					}
				',
			)
		);
	}

	/**
	 * Register block using block.json
	 */
	public function register_block() {
		if ( function_exists( 'register_block_type_from_metadata' ) ) {
			register_block_type_from_metadata(
				FEED_BLOGROLL_PLUGIN_DIR . 'block.json',
				array(
					'render_callback' => array( $this, 'render_blogroll_block' ),
				)
			);
		}
	}

	/**
	 * Render callback for the blogroll block
	 */
	public function render_blogroll_block( $attributes ) {
		// Sanitize and validate attributes
		$category    = isset( $attributes['category'] ) ? sanitize_text_field( $attributes['category'] ) : '';
		$limit       = isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : -1;
		$columns     = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3;
		$show_export = isset( $attributes['showExport'] ) ? (bool) $attributes['showExport'] : true;

		// Validate columns range
		$columns = max( 1, min( 6, $columns ) );

		// Use the existing shortcode logic
		ob_start();
		echo do_shortcode(
			sprintf(
				'[blogroll category="%s" limit="%d" columns="%d" show_export="%s"]',
				esc_attr( $category ),
				esc_attr( $limit ),
				esc_attr( $columns ),
				$show_export ? 'true' : 'false'
			)
		);
		return ob_get_clean();
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Load dependencies first
		$this->load_dependencies();

		// Check if classes are available
		if ( ! class_exists( 'Feed_Blogroll_CPT' ) ) {
			wp_die( esc_html__( 'Feed Blogroll: Required classes not found during activation.', 'feed-blogroll' ) );
		}

		// Create Custom Post Type
		$cpt = new Feed_Blogroll_CPT();
		$cpt->create_post_type();

		// Add custom capabilities to roles once on activation
		$this->add_custom_capabilities_once();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set default options
		$this->set_default_options();

		self::reschedule_sync_cron();

		// Force refresh of post types
		wp_cache_flush();
	}

	/**
	 * Add custom capabilities to roles on activation only.
	 */
	private function add_custom_capabilities_once() {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'edit_blogrolls' );
			$admin_role->add_cap( 'edit_others_blogrolls' );
			$admin_role->add_cap( 'publish_blogrolls' );
			$admin_role->add_cap( 'read_private_blogrolls' );
			$admin_role->add_cap( 'delete_blogrolls' );
			$admin_role->add_cap( 'delete_private_blogrolls' );
			$admin_role->add_cap( 'delete_published_blogrolls' );
			$admin_role->add_cap( 'delete_others_blogrolls' );
			$admin_role->add_cap( 'edit_private_blogrolls' );
			$admin_role->add_cap( 'edit_published_blogrolls' );
		}

		$editor_role = get_role( 'editor' );
		if ( $editor_role ) {
			$editor_role->add_cap( 'edit_blogrolls' );
			$editor_role->add_cap( 'edit_others_blogrolls' );
			$editor_role->add_cap( 'publish_blogrolls' );
			$editor_role->add_cap( 'read_private_blogrolls' );
			$editor_role->add_cap( 'delete_blogrolls' );
			$editor_role->add_cap( 'delete_private_blogrolls' );
			$editor_role->add_cap( 'delete_published_blogrolls' );
			$editor_role->add_cap( 'delete_others_blogrolls' );
			$editor_role->add_cap( 'edit_private_blogrolls' );
			$editor_role->add_cap( 'edit_published_blogrolls' );
		}
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clear scheduled cron job
		wp_clear_scheduled_hook( 'feed_blogroll_sync_cron' );

		// Flush rewrite rules
		flush_rewrite_rules();

		// Optionally remove capabilities on deactivation
		$this->remove_custom_capabilities();
	}

	/**
	 * Set default plugin options
	 */
	private function set_default_options() {
		$default_options = array(
			'feedbin_username' => '',
			'feedbin_password' => '',
			'sync_frequency'   => 'daily',
			'auto_sync'        => true,
			'last_sync'        => '',
			'sync_status'      => 'idle',
			'last_sync_stats'  => array(),
		);

		if ( false === get_option( 'feed_blogroll_options', false ) ) {
			add_option( 'feed_blogroll_options', $default_options, '', 'no' );
			return;
		}

		$existing = get_option( 'feed_blogroll_options', array() );
		update_option( 'feed_blogroll_options', wp_parse_args( (array) $existing, $default_options ) );
	}

	/**
	 * Remove custom capabilities on plugin deactivation.
	 */
	private function remove_custom_capabilities() {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( 'edit_blogrolls' );
			$admin_role->remove_cap( 'edit_others_blogrolls' );
			$admin_role->remove_cap( 'publish_blogrolls' );
			$admin_role->remove_cap( 'read_private_blogrolls' );
			$admin_role->remove_cap( 'delete_blogrolls' );
			$admin_role->remove_cap( 'delete_private_blogrolls' );
			$admin_role->remove_cap( 'delete_published_blogrolls' );
			$admin_role->remove_cap( 'delete_others_blogrolls' );
			$admin_role->remove_cap( 'edit_private_blogrolls' );
			$admin_role->remove_cap( 'edit_published_blogrolls' );
		}

		$editor_role = get_role( 'editor' );
		if ( $editor_role ) {
			$editor_role->remove_cap( 'edit_blogrolls' );
			$editor_role->remove_cap( 'edit_others_blogrolls' );
			$editor_role->remove_cap( 'publish_blogrolls' );
			$editor_role->remove_cap( 'read_private_blogrolls' );
			$editor_role->remove_cap( 'delete_blogrolls' );
			$editor_role->remove_cap( 'delete_private_blogrolls' );
			$editor_role->remove_cap( 'delete_published_blogrolls' );
			$editor_role->remove_cap( 'delete_others_blogrolls' );
			$editor_role->remove_cap( 'edit_private_blogrolls' );
			$editor_role->remove_cap( 'edit_published_blogrolls' );
		}
	}
}

// Initialize plugin
Feed_Blogroll_Plugin::get_instance();
