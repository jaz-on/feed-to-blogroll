<?php
/**
 * Uninstall script for Feed to Blogroll plugin
 *
 * This file is executed when the plugin is deleted from WordPress admin.
 * It cleans up all plugin data including options, custom post types, and files.
 *
 * @package FeedToBlogroll
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Security check
if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

// Clean up options
delete_option( 'feed_to_blogroll_options' );

// Clean up transients
delete_transient( 'feed_to_blogroll_sync_lock' );
delete_transient( 'feed_to_blogroll_api_cache' );

// Clean up scheduled events
wp_clear_scheduled_hook( 'feed_to_blogroll_sync_cron' );

// Clean up custom post types and their data
$post_types = array( 'blogroll' );

foreach ( $post_types as $post_type ) {
	// Get all posts of this type
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	// Delete each post
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

// Clean up taxonomies
$taxonomies = array( 'blogroll_category' );

foreach ( $taxonomies as $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);

	foreach ( $terms as $term ) {
		wp_delete_term( $term->term_id, $taxonomy );
	}
}

// Clean up ACF field groups (if they exist)
if ( function_exists( 'acf_get_field_groups' ) ) {
	$field_groups = acf_get_field_groups();

	foreach ( $field_groups as $field_group ) {
		if ( strpos( $field_group['key'], 'group_blogroll_fields' ) !== false ) {
			acf_delete_field_group( $field_group['ID'] );
		}
	}
}

// Clean up uploaded files (if any)
$upload_dir = wp_upload_dir();
$plugin_upload_dir = $upload_dir['basedir'] . '/feed-to-blogroll/';

if ( is_dir( $plugin_upload_dir ) ) {
	// Remove the entire directory
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

	$filesystem = new WP_Filesystem_Direct( null );
	$filesystem->rmdir( $plugin_upload_dir, true );
}

// Flush rewrite rules
flush_rewrite_rules();
