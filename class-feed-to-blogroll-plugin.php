<?php
/**
 * Main Plugin Class
 *
 * @package FeedToBlogroll
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
class Feed_To_Blogroll_Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Feed_To_Blogroll_Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Feed_To_Blogroll_Plugin
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
		add_action( 'init', array( $this, 'init_plugin' ) );
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
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-to-blogroll-feedbin-api.php';
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-to-blogroll-sync.php';
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-to-blogroll-cpt.php';

		// Admin classes only when needed
		if ( is_admin() ) {
			require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-to-blogroll-admin.php';
		}

		// Frontend classes only when needed
		if ( ! is_admin() || wp_doing_ajax() ) {
			require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-feed-to-blogroll-template.php';
		}

		// Load repair script only in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'class-feed-to-blogroll-repair.php';
		}
	}

	/**
	 * Initialize plugin components
	 */
	private function init_components() {
		// Initialize Custom Post Type
		$cpt = new Feed_To_Blogroll_CPT();

		// Force registration if not exists
		if ( ! post_type_exists( 'blogroll' ) ) {
			$cpt->force_registration();
		}

		// Initialize Feedbin API
		new Feed_To_Blogroll_Feedbin_API();

		// Initialize synchronization
		new Feed_To_Blogroll_Sync();

		// Initialize admin interface only when needed
		if ( is_admin() ) {
			new Feed_To_Blogroll_Admin();
		}

		// Initialize template integration only when needed
		if ( ! is_admin() || wp_doing_ajax() ) {
			new Feed_To_Blogroll_Template();
		}

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
				'label'        => __( 'Blogroll Card', 'feed-to-blogroll' ),
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
				'label'        => __( 'Blogroll Grid', 'feed-to-blogroll' ),
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
				FEED_TO_BLOGROLL_PLUGIN_DIR . 'block.json',
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
		if ( ! class_exists( 'Feed_To_Blogroll_CPT' ) ) {
			wp_die( esc_html__( 'Feed to Blogroll: Required classes not found during activation.', 'feed-to-blogroll' ) );
		}

		// Create Custom Post Type
		$cpt = new Feed_To_Blogroll_CPT();
		$cpt->create_post_type();

		// Add custom capabilities to roles once on activation
		$this->add_custom_capabilities_once();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set default options
		$this->set_default_options();

		// Schedule cron job
		if ( ! wp_next_scheduled( 'feed_to_blogroll_sync_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'feed_to_blogroll_sync_cron' );
		}

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
		wp_clear_scheduled_hook( 'feed_to_blogroll_sync_cron' );

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
			'feedbin_api_key'  => '', // More secure alternative
			'sync_frequency'   => 'daily',
			'auto_sync'        => true,
			'last_sync'        => '',
			'sync_status'      => 'idle',
		);

		update_option( 'feed_to_blogroll_options', $default_options );
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
Feed_To_Blogroll_Plugin::get_instance();
