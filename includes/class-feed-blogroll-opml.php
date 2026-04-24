<?php
/**
 * OPML export builder (shared by admin AJAX, REST, and caches).
 *
 * @package FeedBlogroll
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OPML export helper.
 */
class Feed_Blogroll_OPML {

	/**
	 * Transient key for cached export payload.
	 */
	public const TRANSIENT_KEY = 'feed_blogroll_opml';

	/**
	 * Get OPML payload array: opml string + filename. Uses transient cache when allowed.
	 *
	 * @param bool $use_cache Whether to read/write the transient cache.
	 * @return array{opml: string, filename: string}
	 */
	public static function get_export_payload( $use_cache = true ) {
		if ( $use_cache ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached && is_array( $cached ) && isset( $cached['opml'], $cached['filename'] ) ) {
				return $cached;
			}
		}

		$blogs = get_posts(
			array(
				'post_type'              => 'blogroll',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$response = array(
			'opml'     => self::build_opml_string( $blogs ),
			'filename' => 'blogroll-' . gmdate( 'Y-m-d' ) . '.opml',
		);

		set_transient( self::TRANSIENT_KEY, $response, HOUR_IN_SECONDS );

		return $response;
	}

	/**
	 * Build OPML XML from blogroll posts.
	 *
	 * @param array<int, WP_Post> $blogs Post objects.
	 * @return string
	 */
	public static function build_opml_string( $blogs ) {
		$opml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$opml .= '<opml version="2.0">' . "\n";
		$opml .= '  <head>' . "\n";
		$opml .= '    <title>' . esc_html( get_bloginfo( 'name' ) ) . ' Blogroll</title>' . "\n";
		$opml .= '    <dateCreated>' . gmdate( 'D, d M Y H:i:s' ) . ' GMT</dateCreated>' . "\n";
		$opml .= '  </head>' . "\n";
		$opml .= '  <body>' . "\n";

		foreach ( $blogs as $blog ) {
			$rss_url  = get_post_meta( $blog->ID, 'rss_url', true );
			$site_url = get_post_meta( $blog->ID, 'site_url', true );
			if ( $rss_url ) {
				$opml .= '    <outline type="rss" text="' . esc_attr( $blog->post_title ) . '" title="' . esc_attr( $blog->post_title ) . '" xmlUrl="' . esc_attr( $rss_url ) . '" htmlUrl="' . esc_attr( $site_url ) . '" />' . "\n";
			}
		}

		$opml .= '  </body>' . "\n";
		$opml .= '</opml>';

		return $opml;
	}

	/**
	 * Invalidate cached OPML.
	 */
	public static function bust_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
