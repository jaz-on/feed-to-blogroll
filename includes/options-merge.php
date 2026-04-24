<?php
/**
 * Pure merge helper for saved options (unit-testable).
 *
 * @package FeedBlogroll
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merge sanitized settings keys into the existing option array without dropping runtime keys.
 *
 * @param array $existing         Current option value.
 * @param array $sanitized_subset Keys produced by the settings sanitize callback.
 * @return array
 */
function feed_blogroll_merge_saved_options( array $existing, array $sanitized_subset ) {
	return array_merge( $existing, $sanitized_subset );
}
