<?php
/**
 * Uninstall script for Feed Blogroll plugin
 *
 * This file is executed when the plugin is deleted from WordPress admin.
 * It cleans up all plugin data including options, custom post types, and files.
 *
 * @package FeedBlogroll
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clean up options
delete_option( 'feed_blogroll_options' );
delete_option( 'feed_blogroll_plugin_version' );
delete_option( 'feed_blogroll_api_last_test' );
delete_option( 'feed_blogroll_api_connected' );
delete_option( 'feed_blogroll_api_last_error' );
delete_option( 'feed_blogroll_cache_version' );
delete_option( 'feed_blogroll_legacy_slug_migration' );

// Legacy keys (pre–feed-blogroll rename).
delete_option( 'feed_to_blogroll_options' );
delete_option( 'feed_to_blogroll_plugin_version' );
delete_option( 'feed_to_blogroll_api_last_test' );
delete_option( 'feed_to_blogroll_api_connected' );
delete_option( 'feed_to_blogroll_api_last_error' );
delete_option( 'feed_to_blogroll_cache_version' );

// Clean up transients
delete_transient( 'feed_blogroll_sync_lock' );
delete_transient( 'feed_blogroll_api_cache' );
delete_transient( 'feed_blogroll_opml' );
delete_transient( 'feed_to_blogroll_opml' );
delete_transient( 'feed_to_blogroll_sync_lock' );
delete_transient( 'feed_to_blogroll_api_cache' );

// Clean up scheduled events
wp_clear_scheduled_hook( 'feed_blogroll_sync_cron' );
wp_clear_scheduled_hook( 'feed_to_blogroll_sync_cron' );

// Clean up custom post types and their data
$post_types = array( 'blogroll' );

foreach ( $post_types as $plugin_post_type ) {
	$plugin_posts = get_posts(
		array(
			'post_type'      => $plugin_post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $plugin_posts as $plugin_post_id ) {
		wp_delete_post( $plugin_post_id, true );
	}
}

// Clean up taxonomies
$taxonomies = array( 'blogroll_category' );

foreach ( $taxonomies as $plugin_taxonomy ) {
	$plugin_terms = get_terms(
		array(
			'taxonomy'   => $plugin_taxonomy,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $plugin_terms ) || empty( $plugin_terms ) ) {
		continue;
	}

	foreach ( $plugin_terms as $plugin_term ) {
		wp_delete_term( $plugin_term->term_id, $plugin_taxonomy );
	}
}

// Meta fields are automatically removed with posts

// Clean up uploaded files (if any)
$upload_dir        = wp_upload_dir();
$plugin_upload_dirs = array(
	$upload_dir['basedir'] . '/feed-blogroll/',
	$upload_dir['basedir'] . '/feed-to-blogroll/',
);

if ( array_filter( $plugin_upload_dirs, 'is_dir' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
	$filesystem = new WP_Filesystem_Direct( null );
	foreach ( $plugin_upload_dirs as $plugin_upload_dir ) {
		if ( is_dir( $plugin_upload_dir ) ) {
			$filesystem->rmdir( $plugin_upload_dir, true );
		}
	}
}

// Flush rewrite rules
flush_rewrite_rules();
