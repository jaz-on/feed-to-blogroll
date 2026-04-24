<?php
/**
 * Plugin Name: Feed Blogroll
 * Plugin URI: https://github.com/jaz-on/feed-blogroll
 * Description: Synchronizes your blogroll from the Feedbin API into WordPress custom post types with no external Composer dependencies.
 * Version: 1.2.0
 * Requires at least: 6.1
 * Tested up to: 6.9
 * Requires PHP: 8.2
 * Author: Jason Rouet
 * Author URI: https://jasonrouet.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: feed-blogroll
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/jaz-on/feed-blogroll
 * Primary Branch: main
 *
 * @package FeedBlogroll
 * @since 1.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'FEED_BLOGROLL_VERSION', '1.2.0' );
define( 'FEED_BLOGROLL_PLUGIN_FILE', __FILE__ );
define( 'FEED_BLOGROLL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEED_BLOGROLL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FEED_BLOGROLL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FEED_BLOGROLL_GITHUB_URL', 'https://github.com/jaz-on/feed-blogroll' );
define( 'FEED_BLOGROLL_KOFI_URL', 'https://ko-fi.com/jasonrouet' );

require_once FEED_BLOGROLL_PLUGIN_DIR . 'includes/options-merge.php';

// Load the main plugin class
require_once FEED_BLOGROLL_PLUGIN_DIR . 'class-feed-blogroll-plugin.php';

// Register activation/deactivation hooks from main plugin file
register_activation_hook(
	__FILE__,
	function () {
		Feed_Blogroll_Plugin::get_instance()->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		Feed_Blogroll_Plugin::get_instance()->deactivate();
	}
);
