<?php
/**
 * Plugin Name: Feed to Blogroll
 * Plugin URI: https://github.com/jasonrouet/feed-to-blogroll
 * Description: Automatic blogroll synchronization with Feedbin API, integrated with Distributed theme.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.2
 * Author: Jason Rouet
 * Author URI: https://jasonrouet.local
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: feed-to-blogroll
 * Domain Path: /languages
 * Network: false
 *
 * @package FeedToBlogroll
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'FEED_TO_BLOGROLL_VERSION', '1.0.0' );
define( 'FEED_TO_BLOGROLL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEED_TO_BLOGROLL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FEED_TO_BLOGROLL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

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
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_plugin' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'feed-to-blogroll',
			false,
			dirname( FEED_TO_BLOGROLL_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components
	 */
	public function init_plugin() {
		// Check if ACF Pro is active
		if ( ! class_exists( 'ACF' ) ) {
			add_action( 'admin_notices', array( $this, 'acf_missing_notice' ) );
			return;
		}

		// Load required files
		$this->load_dependencies();

		// Initialize components
		$this->init_components();
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-feedbin-api.php';
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-blogroll-sync.php';
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-blogroll-cpt.php';
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-blogroll-admin.php';
		require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'includes/class-blogroll-template.php';

		// Load repair script in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'repair-cpt.php';
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

		// Initialize admin interface
		if ( is_admin() ) {
			new Feed_To_Blogroll_Admin();
		}

		// Initialize template integration
		new Feed_To_Blogroll_Template();

		// Initialize block support
		$this->init_block_support();
	}

	/**
	 * Initialize WordPress block support
	 */
	private function init_block_support() {
		// Add theme support for block styles
		add_action( 'after_setup_theme', array( $this, 'add_block_support' ) );

		// Register block styles
		add_action( 'init', array( $this, 'register_block_styles' ) );

		// Register block using block.json
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Add theme support for blocks
	 */
	public function add_block_support() {
		// Add support for block styles
		add_theme_support( 'wp-block-styles' );

		// Add support for editor styles
		add_theme_support( 'editor-styles' );

		// Add support for responsive embeds
		add_theme_support( 'responsive-embeds' );

		// Add support for custom line height
		add_theme_support( 'custom-line-height' );

		// Add support for custom spacing
		add_theme_support( 'custom-spacing' );
	}

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
		$category = isset( $attributes['category'] ) ? $attributes['category'] : '';
		$limit = isset( $attributes['limit'] ) ? $attributes['limit'] : -1;
		$columns = isset( $attributes['columns'] ) ? $attributes['columns'] : 3;
		$show_export = isset( $attributes['showExport'] ) ? $attributes['showExport'] : true;

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
			wp_die( 'Feed to Blogroll: Required classes not found during activation.' );
		}

		// Create Custom Post Type
		$cpt = new Feed_To_Blogroll_CPT();
		$cpt->create_post_type();

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
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clear scheduled cron job
		wp_clear_scheduled_hook( 'feed_to_blogroll_sync_cron' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options
	 */
	private function set_default_options() {
		$default_options = array(
			'feedbin_username' => '',
			'feedbin_password' => '',
			'sync_frequency' => 'daily',
			'auto_sync' => true,
			'last_sync' => '',
			'sync_status' => 'idle',
		);

		update_option( 'feed_to_blogroll_options', $default_options );
	}

	/**
	 * Display notice if ACF Pro is missing
	 */
	public function acf_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: ACF Pro plugin name */
					esc_html__( 'Feed to Blogroll requires %s to be installed and activated.', 'feed-to-blogroll' ),
					'<strong>Advanced Custom Fields Pro</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}
}

// Initialize plugin
Feed_To_Blogroll_Plugin::get_instance();
