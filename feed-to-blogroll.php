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
 * Network: false
 *
 * @package FeedToBlogroll
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the main plugin class
require_once plugin_dir_path( __FILE__ ) . 'class-feed-to-blogroll-plugin.php';
