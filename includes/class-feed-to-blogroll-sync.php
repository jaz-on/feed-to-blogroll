<?php
/**
 * Blogroll Synchronization
 *
 * @package FeedToBlogroll
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blogroll synchronization class
 *
 * @since 1.0.0
 */
class Feed_To_Blogroll_Sync {

	/**
	 * Feedbin API instance
	 *
	 * @var Feed_To_Blogroll_Feedbin_API
	 */
	private $api;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api = new Feed_To_Blogroll_Feedbin_API();

		// Hook into WordPress cron
		add_action( 'feed_to_blogroll_sync_cron', array( $this, 'scheduled_sync' ) );

		// Add manual sync action (admin only)
		add_action( 'wp_ajax_feed_to_blogroll_manual_sync', array( $this, 'manual_sync' ) );
	}

	/**
	 * Perform scheduled synchronization
	 */
	public function scheduled_sync() {
		// Check if auto-sync is enabled
		$options = get_option( 'feed_to_blogroll_options', array() );
		if ( empty( $options['auto_sync'] ) ) {
			return;
		}

		// Check if we should sync (avoid too frequent syncs)
		$last_sync = isset( $options['last_sync'] ) ? $options['last_sync'] : '';
		if ( $last_sync && ( time() - strtotime( $last_sync ) ) < 3600 ) { // 1 hour minimum
			return;
		}

		$this->sync_blogroll();
	}

	/**
	 * Perform manual synchronization via AJAX
	 */
	public function manual_sync() {
		// Check nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'feed_to_blogroll_admin' ) ) {
			wp_send_json_error( __( 'Security check failed', 'feed-to-blogroll' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'feed-to-blogroll' ) );
		}

		$result = $this->sync_blogroll();

		if ( $result['success'] ) {
			// Invalidate OPML cache
			wp_cache_delete( 'feed_to_blogroll_opml', 'feed_to_blogroll' );
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Main synchronization method
	 *
	 * @return array Sync result
	 */
	public function sync_blogroll() {
		$start_time = microtime( true );
		$result     = array(
			'success'           => false,
			'message'           => '',
			'blogs_added'       => 0,
			'blogs_updated'     => 0,
			'blogs_deactivated' => 0,
			'errors'            => array(),
			'duration'          => 0,
		);

		try {
			// Update sync status
			$this->update_sync_status( 'running' );

			// Get subscriptions from Feedbin
			$subscriptions = $this->api->get_subscriptions_with_feeds();
			if ( is_wp_error( $subscriptions ) ) {
				throw new Exception( $subscriptions->get_error_message() );
			}

			// Get existing blogs
			$existing_blogs = $this->get_existing_blogs();

			// Process each subscription
			foreach ( $subscriptions as $subscription ) {
				$this->process_subscription( $subscription, $existing_blogs, $result );
			}

			// Deactivate blogs no longer in Feedbin
			$this->deactivate_removed_blogs( $subscriptions, $existing_blogs, $result );

			// Update sync status
			$this->update_sync_status( 'completed' );
			$this->update_last_sync();

			$result['success'] = true;
			$result['message'] = sprintf(
				/* translators: %1$d: blogs added, %2$d: blogs updated, %3$d: blogs deactivated */
				__( 'Synchronization completed: %1$d added, %2$d updated, %3$d deactivated', 'feed-to-blogroll' ),
				$result['blogs_added'],
				$result['blogs_updated'],
				$result['blogs_deactivated']
			);

		} catch ( Exception $e ) {
			$result['message']  = $e->getMessage();
			$result['errors'][] = $e->getMessage();
			$this->update_sync_status( 'error' );
		}

		$result['duration'] = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		// Log the result
		$this->log_sync_result( $result );

		return $result;
	}

	/**
	 * Process a single subscription
	 *
	 * @param array $subscription Subscription data.
	 * @param array $existing_blogs Existing blogs.
	 * @param array $result Sync result reference.
	 */
	private function process_subscription( $subscription, $existing_blogs, &$result ) {
		$feed_id = $subscription['feed_id'];

		if ( isset( $existing_blogs[ $feed_id ] ) ) {
			// Update existing blog
			$this->update_blog( $existing_blogs[ $feed_id ], $subscription );
			++$result['blogs_updated'];
		} else {
			// Create new blog
			$this->create_blog( $subscription );
			++$result['blogs_added'];
		}
	}

	/**
	 * Create a new blog post
	 *
	 * @param array $subscription Subscription data.
	 * @return int|WP_Error Post ID or error.
	 */
	private function create_blog( $subscription ) {
		$post_data = array(
			'post_title'   => sanitize_text_field( $subscription['title'] ),
			'post_content' => wp_kses_post( $subscription['description'] ?? '' ),
			'post_excerpt' => sanitize_textarea_field( $subscription['description'] ?? '' ),
			'post_status'  => 'publish',
			'post_type'    => 'blogroll',
		);

		$post_id = wp_insert_post( $post_data );

		if ( ! is_wp_error( $post_id ) ) {
			// Set ACF fields
			update_field( 'site_url', esc_url_raw( $subscription['site_url'] ?? '' ), $post_id );
			update_field( 'rss_url', esc_url_raw( $subscription['feed_url'] ?? '' ), $post_id );
			update_field( 'author', sanitize_text_field( $subscription['author'] ?? '' ), $post_id );
			update_field( 'feedbin_id', intval( $subscription['feed_id'] ), $post_id );
			update_field( 'last_sync', current_time( 'mysql' ), $post_id );
			update_field( 'sync_status', 'active', $post_id );

			// Set category if available
			if ( ! empty( $subscription['category'] ) ) {
				wp_set_object_terms( $post_id, $subscription['category'], 'blogroll_category' );
			}
		}

		return $post_id;
	}

	/**
	 * Update an existing blog post
	 *
	 * @param array $existing_blog Existing blog data.
	 * @param array $subscription Subscription data.
	 * @return int|WP_Error Post ID or error.
	 */
	private function update_blog( $existing_blog, $subscription ) {
		$post_data = array(
			'ID'           => $existing_blog['ID'],
			'post_title'   => sanitize_text_field( $subscription['title'] ),
			'post_content' => wp_kses_post( $subscription['description'] ?? '' ),
			'post_excerpt' => sanitize_textarea_field( $subscription['description'] ?? '' ),
		);

		$post_id = wp_update_post( $post_data );

		if ( ! is_wp_error( $post_id ) ) {
			// Update ACF fields
			update_field( 'site_url', esc_url_raw( $subscription['site_url'] ?? '' ), $post_id );
			update_field( 'rss_url', esc_url_raw( $subscription['feed_url'] ?? '' ), $post_id );
			update_field( 'author', sanitize_text_field( $subscription['author'] ?? '' ), $post_id );
			update_field( 'last_sync', current_time( 'mysql' ), $post_id );
			update_field( 'sync_status', 'active', $post_id );

			// Update category if available
			if ( ! empty( $subscription['category'] ) ) {
				wp_set_object_terms( $post_id, $subscription['category'], 'blogroll_category' );
			}
		}

		return $post_id;
	}

	/**
	 * Deactivate blogs no longer in Feedbin
	 *
	 * @param array $subscriptions Current subscriptions.
	 * @param array $existing_blogs Existing blogs.
	 * @param array $result Sync result reference.
	 */
	private function deactivate_removed_blogs( $subscriptions, $existing_blogs, &$result ) {
		$current_feed_ids = array_column( $subscriptions, 'feed_id' );

		foreach ( $existing_blogs as $feed_id => $blog ) {
			if ( ! in_array( $feed_id, $current_feed_ids, true ) ) {
				// Deactivate blog (set to draft instead of deleting)
				wp_update_post(
					array(
						'ID'          => $blog['ID'],
						'post_status' => 'draft',
					)
				);

				update_field( 'sync_status', 'inactive', $blog['ID'] );
				++$result['blogs_deactivated'];
			}
		}
	}

	/**
	 * Get existing blogs indexed by Feedbin ID
	 *
	 * @return array Existing blogs.
	 */
	private function get_existing_blogs() {
		$blogs = get_posts(
			array(
				'post_type'      => 'blogroll',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => 'feedbin_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$indexed_blogs = array();
		foreach ( $blogs as $blog ) {
			$feedbin_id = get_field( 'feedbin_id', $blog->ID );
			if ( $feedbin_id ) {
				$indexed_blogs[ $feedbin_id ] = array(
					'ID'    => $blog->ID,
					'title' => $blog->post_title,
				);
			}
		}

		return $indexed_blogs;
	}

	/**
	 * Update synchronization status
	 *
	 * @param string $status Status to set.
	 */
	private function update_sync_status( $status ) {
		$options                = get_option( 'feed_to_blogroll_options', array() );
		$options['sync_status'] = $status;
		update_option( 'feed_to_blogroll_options', $options );
	}

	/**
	 * Update last synchronization time
	 */
	private function update_last_sync() {
		$options              = get_option( 'feed_to_blogroll_options', array() );
		$options['last_sync'] = current_time( 'mysql' );
		update_option( 'feed_to_blogroll_options', $options );
	}

	/**
	 * Log synchronization result
	 *
	 * @param array $result Sync result.
	 */
	private function log_sync_result( $result ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_entry = sprintf(
				'[%s] Blogroll sync: %s (Duration: %sms, Added: %d, Updated: %d, Deactivated: %d)',
				current_time( 'Y-m-d H:i:s' ),
				$result['success'] ? 'SUCCESS' : 'FAILED',
				$result['duration'],
				$result['blogs_added'],
				$result['blogs_updated'],
				$result['blogs_deactivated']
			);

			if ( ! empty( $result['errors'] ) ) {
				$log_entry .= ' - Errors: ' . implode( ', ', $result['errors'] );
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Feed to Blogroll: ' . $log_entry );
		}
	}

	/**
	 * Get synchronization statistics
	 *
	 * @return array Statistics.
	 */
	public function get_sync_stats() {
		$options = get_option( 'feed_to_blogroll_options', array() );

		$stats = array(
			'last_sync'      => isset( $options['last_sync'] ) ? $options['last_sync'] : '',
			'sync_status'    => isset( $options['sync_status'] ) ? $options['sync_status'] : 'idle',
			'total_blogs'    => wp_count_posts( 'blogroll' )->publish,
			'inactive_blogs' => wp_count_posts( 'blogroll' )->draft,
		);

		return $stats;
	}
}
