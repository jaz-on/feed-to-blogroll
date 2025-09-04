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
	 * Perform manual synchronization via AJAX with improved security
	 */
	public function manual_sync() {
		// Check HTTP method
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid request method', 'feed-to-blogroll' ),
					'code'    => 'invalid_method',
					'context' => 'http_validation',
				)
			);
		}

		// Check nonce presence BEFORE any usage
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'feed_to_blogroll_admin' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'feed-to-blogroll' ),
					'code'    => 'nonce_failed',
					'context' => 'security_validation',
				)
			);
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions to perform this action.', 'feed-to-blogroll' ),
					'code'    => 'insufficient_permissions',
					'context' => 'capability_check',
				)
			);
		}

		$result = $this->sync_blogroll();

		if ( $result['success'] ) {
			// Invalidate OPML cache
			wp_cache_delete( 'feed_to_blogroll_opml', 'feed_to_blogroll' );
			wp_send_json_success( $result );
		} else {
			wp_send_json_error(
				array(
					'message' => $result['message'],
					'code'    => 'sync_failed',
					'context' => 'manual_sync',
				)
			);
		}
	}

	/**
	 * Main synchronization method
	 *
	 * @return array Sync result
	 */
	public function sync_blogroll() {
		$start_time = microtime( true );
		$result = array(
			'success'           => false,
			'message'           => '',
			'blogs_added'       => 0,
			'blogs_updated'     => 0,
			'blogs_deactivated' => 0,
			'errors'            => array(),
			'duration'          => 0,
		);

		try {
			// Check if API credentials are configured
			if ( ! $this->api->has_credentials() ) {
				$result['message'] = __( 'Feedbin API credentials not configured', 'feed-to-blogroll' );
				return $result;
			}

			// Get feeds from Feedbin
			$feeds = $this->api->get_feeds();
			if ( is_wp_error( $feeds ) ) {
				$result['message'] = $feeds->get_error_message();
				$result['errors'][] = $feeds->get_error_message();
				return $result;
			}

			// Process each feed
			foreach ( $feeds as $feed ) {
				$feed_result = $this->process_feed( $feed );
				if ( $feed_result['success'] ) {
					if ( 'added' === $feed_result['action'] ) { // Yoda
						$result['blogs_added']++;
					} elseif ( 'updated' === $feed_result['action'] ) { // Yoda
						$result['blogs_updated']++;
					}
				} else {
					$result['errors'][] = $feed_result['error'];
				}
			}

			// Deactivate blogs that are no longer in Feedbin
			$deactivated_count = $this->deactivate_removed_blogs( $feeds );
			$result['blogs_deactivated'] = $deactivated_count;

			// Update sync status
			$options = get_option( 'feed_to_blogroll_options', array() );
			$options['last_sync'] = current_time( 'mysql' );
			$options['sync_status'] = 'success';
			update_option( 'feed_to_blogroll_options', $options );

			$result['success'] = true;
			$result['message'] = sprintf(
				/* translators: 1: blogs added, 2: blogs updated, 3: blogs deactivated */
				__( 'Synchronization completed successfully. %1$d blogs added, %2$d updated, %3$d deactivated.', 'feed-to-blogroll' ),
				$result['blogs_added'],
				$result['blogs_updated'],
				$result['blogs_deactivated']
			);

		} catch ( Exception $e ) {
			$result['message'] = $e->getMessage();
			$result['errors'][] = $e->getMessage();

			// Update sync status to error
			$options = get_option( 'feed_to_blogroll_options', array() );
			$options['sync_status'] = 'error';
			update_option( 'feed_to_blogroll_options', $options );
		}

		$result['duration'] = microtime( true ) - $start_time;
		return $result;
	}

	/**
	 * Process individual feed
	 *
	 * @param array $feed Feed data from API
	 * @return array Processing result
	 */
	private function process_feed( $feed ) {
		$result = array(
			'success' => false,
			'action'  => '',
			'error'   => '',
		);

		try {
			// Check if blog already exists
			$existing_blog = $this->find_existing_blog( $feed['feed_url'] );

			if ( $existing_blog ) {
				// Update existing blog
				$updated = $this->update_blog( $existing_blog->ID, $feed );
				if ( $updated ) {
					$result['success'] = true;
					$result['action'] = 'updated';
				} else {
					/* translators: %s: blog title */
					$result['error'] = sprintf( __( 'Failed to update blog: %s', 'feed-to-blogroll' ), $feed['title'] );
				}
			} else {
				// Create new blog
				$blog_id = $this->create_blog( $feed );
				if ( $blog_id ) {
					$result['success'] = true;
					$result['action'] = 'added';
				} else {
					/* translators: %s: blog title */
					$result['error'] = sprintf( __( 'Failed to create blog: %s', 'feed-to-blogroll' ), $feed['title'] );
				}
			}
		} catch ( Exception $e ) {
			$result['error'] = $e->getMessage();
		}

		return $result;
	}

	/**
	 * Find existing blog by RSS URL
	 *
	 * @param string $rss_url RSS URL to search for
	 * @return WP_Post|false Blog post or false if not found
	 */
	private function find_existing_blog( $rss_url ) {
		$args = array(
			'post_type'              => 'blogroll',
			'post_status'            => array( 'publish', 'draft' ),
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		// Faster ID pre-resolution via direct postmeta query
		global $wpdb;
		$rss_url_escaped = esc_url_raw( $rss_url );
		$cache_key = 'ftb_rss_to_postid_' . md5( (string) $rss_url_escaped );
		$post_id = wp_cache_get( $cache_key, 'feed_to_blogroll' );
		if ( false === $post_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional optimized lookup with object cache layer above
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", 'rss_url', $rss_url_escaped ) );
			wp_cache_set( $cache_key, $post_id, 'feed_to_blogroll', 600 );
		}
		if ( $post_id > 0 ) {
			$args['p'] = $post_id;
		}

		$query = new WP_Query( $args );
		if ( ! empty( $query->posts ) ) {
			return get_post( $query->posts[0] );
		}
		return false;
	}

	/**
	 * Create new blog post
	 *
	 * @param array $feed Feed data
	 * @return int|false Blog post ID or false on failure
	 */
	private function create_blog( $feed ) {
		$post_data = array(
			'post_title'   => sanitize_text_field( $feed['title'] ),
			'post_content' => wp_kses_post( $feed['description'] ?? '' ),
			'post_status'  => 'publish',
			'post_type'    => 'blogroll',
		);

		$blog_id = wp_insert_post( $post_data );

		if ( $blog_id && ! is_wp_error( $blog_id ) ) {
			// Set meta fields
			update_post_meta( $blog_id, 'rss_url', esc_url_raw( $feed['feed_url'] ) );
			update_post_meta( $blog_id, 'site_url', esc_url_raw( $feed['site_url'] ?? '' ) );
			update_post_meta( $blog_id, 'feed_id', absint( $feed['id'] ) );

			// Set categories if available
			if ( ! empty( $feed['tags'] ) ) {
				$this->set_blog_categories( $blog_id, $feed['tags'] );
			}

			return $blog_id;
		}

		return false;
	}

	/**
	 * Update existing blog post
	 *
	 * @param int   $blog_id Blog post ID
	 * @param array $feed    Feed data
	 * @return bool Success status
	 */
	private function update_blog( $blog_id, $feed ) {
		$post_data = array(
			'ID'           => $blog_id,
			'post_title'   => sanitize_text_field( $feed['title'] ),
			'post_content' => wp_kses_post( $feed['description'] ?? '' ),
		);

		$updated = wp_update_post( $post_data );

		if ( $updated && ! is_wp_error( $updated ) ) {
			// Update meta fields
			update_post_meta( $blog_id, 'site_url', esc_url_raw( $feed['site_url'] ?? '' ) );
			update_post_meta( $blog_id, 'feed_id', absint( $feed['id'] ) );

			// Update categories if available
			if ( ! empty( $feed['tags'] ) ) {
				$this->set_blog_categories( $blog_id, $feed['tags'] );
			}

			return true;
		}

		return false;
	}

	/**
	 * Set blog categories from tags
	 *
	 * @param int   $blog_id Blog post ID
	 * @param array $tags    Tags from feed
	 */
	private function set_blog_categories( $blog_id, $tags ) {
		$category_ids = array();

		foreach ( $tags as $tag ) {
			$tag_name = sanitize_text_field( $tag );
			$term = term_exists( $tag_name, 'blogroll_category' );

			if ( ! $term ) {
				$term = wp_insert_term( $tag_name, 'blogroll_category' );
			}

			if ( ! is_wp_error( $term ) ) {
				$category_ids[] = $term['term_id'];
			}
		}

		if ( ! empty( $category_ids ) ) {
			wp_set_object_terms( $blog_id, $category_ids, 'blogroll_category' );
		}
	}

	/**
	 * Deactivate blogs that are no longer in Feedbin
	 *
	 * @param array $feeds Current feeds from API
	 * @return int Number of deactivated blogs
	 */
	private function deactivate_removed_blogs( $feeds ) {
		$feed_urls = array_column( $feeds, 'feed_url' );
		$deactivated_count = 0;

		$paged = 1;
		do {
			$batch = get_posts(
				array(
					'post_type'              => 'blogroll',
					'post_status'            => 'publish',
					'posts_per_page'         => 500,
					'paged'                  => $paged,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $post_id ) {
				$rss_url = get_post_meta( $post_id, 'rss_url', true );
				if ( $rss_url && ! in_array( $rss_url, $feed_urls, true ) ) {
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						)
					);
					$deactivated_count++;
				}
			}

			$paged++;
			$batch_count = is_array( $batch ) ? count( $batch ) : 0;
		} while ( 500 === $batch_count ); // Yoda

		return $deactivated_count;
	}

	/**
	 * Get synchronization statistics
	 *
	 * @return array Sync statistics
	 */
	public function get_sync_stats() {
		$counts = wp_count_posts( 'blogroll' );
		$options = get_option( 'feed_to_blogroll_options', array() );

		return array(
			'total_published' => isset( $counts->publish ) ? (int) $counts->publish : 0,
			'total_draft'     => isset( $counts->draft ) ? (int) $counts->draft : 0,
			'last_sync'       => isset( $options['last_sync'] ) ? $options['last_sync'] : '',
			'sync_status'     => isset( $options['sync_status'] ) ? $options['sync_status'] : 'idle',
		);
	}
}
