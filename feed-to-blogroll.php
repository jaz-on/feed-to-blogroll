<?php
/**
 * Plugin Name: Feed to Blogroll
 * Plugin URI: https://github.com/jaz-on/feed-to-blogroll
 * Description: Automatic blogroll synchronization with Feedbin API, integrated with Distributed theme.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 6.5
 * Requires PHP: 8.2
 * Author: Jason Rouet
 * Author URI: https://jasonrouet.local
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: feed-to-blogroll
 * Domain Path: /languages
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

// Load the main plugin class
require_once FEED_TO_BLOGROLL_PLUGIN_DIR . 'class-feed-to-blogroll-plugin.php';

// Register activation/deactivation hooks from main plugin file
register_activation_hook(
	__FILE__,
	function () {
		Feed_To_Blogroll_Plugin::get_instance()->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		Feed_To_Blogroll_Plugin::get_instance()->deactivate();
	}
);
