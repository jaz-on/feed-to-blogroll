<?php
/**
 * Feedbin API Integration
 *
 * @package FeedBlogroll
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feedbin API class
 *
 * @since 1.0.0
 */
class Feed_Blogroll_Feedbin_API {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base = 'https://api.feedbin.com/v2/';

	/**
	 * API credentials
	 *
	 * @var array
	 */
	private $credentials = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->load_credentials();
	}

	/**
	 * Load API credentials from options
	 */
	private function load_credentials() {
		$options = get_option( 'feed_blogroll_options', array() );

		$username = isset( $options['feedbin_username'] ) ? $options['feedbin_username'] : '';
		$password = isset( $options['feedbin_password'] ) ? $options['feedbin_password'] : '';

		if ( defined( 'FEED_BLOGROLL_USERNAME' ) ) {
			$username = (string) constant( 'FEED_BLOGROLL_USERNAME' );
		}
		if ( defined( 'FEED_BLOGROLL_PASSWORD' ) ) {
			$password = (string) constant( 'FEED_BLOGROLL_PASSWORD' );
		}

		$this->credentials = array(
			'username' => $username,
			'password' => $password,
		);
	}

	/**
	 * Check if API credentials are configured
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return ! empty( $this->credentials['username'] ) && ! empty( $this->credentials['password'] );
	}

	/**
	 * Make authenticated API request with improved error handling
	 *
	 * @param string $endpoint API endpoint
	 * @param string $method HTTP method
	 * @param array  $data Request data
	 * @return array|WP_Error Response data or error
	 */
	private function make_request( $endpoint, $method = 'GET', $data = array() ) {
		if ( ! $this->has_credentials() ) {
			return new WP_Error( 'no_credentials', __( 'Feedbin credentials not configured', 'feed-blogroll' ) );
		}

		$url = $this->api_base . $endpoint;
		$args = array(
			'method'  => $method,
			'headers' => array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Authorization' => 'Basic ' . base64_encode( $this->credentials['username'] . ':' . $this->credentials['password'] ),
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'Feed-Blogroll/' . FEED_BLOGROLL_VERSION,
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->handle_api_response( $response, $endpoint );
	}

	/**
	 * Handle API response with improved error handling
	 *
	 * @param array  $response WordPress HTTP response
	 * @param string $endpoint API endpoint that was called
	 * @return array|WP_Error Processed response or error
	 */
	private function handle_api_response( $response, $endpoint ) {
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Debug logging removed for production safety

		// Handle different response codes
		switch ( $response_code ) {
			case 200:
				$decoded = json_decode( $response_body, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					return new WP_Error(
						'json_decode_error',
						/* translators: %s: API endpoint path */
						sprintf( __( 'Failed to decode JSON response from %s', 'feed-blogroll' ), $endpoint ),
						array(
							'endpoint' => $endpoint,
							'response' => $response_body,
						)
					);
				}
				return $decoded;

			case 401:
				return new WP_Error(
					'auth_failed',
					/* translators: no placeholder */
					__( 'Feedbin authentication failed. Please check your username and password.', 'feed-blogroll' ),
					array(
						'endpoint' => $endpoint,
						'code' => $response_code,
					)
				);

			case 403:
				return new WP_Error(
					'forbidden',
					/* translators: no placeholder */
					__( 'Access forbidden. Please check your Feedbin account permissions.', 'feed-blogroll' ),
					array(
						'endpoint' => $endpoint,
						'code' => $response_code,
					)
				);

			case 404:
				return new WP_Error(
					'not_found',
					/* translators: %s: endpoint path */
					sprintf( __( 'API endpoint %s not found', 'feed-blogroll' ), $endpoint ),
					array(
						'endpoint' => $endpoint,
						'code' => $response_code,
					)
				);

			case 429:
				return new WP_Error(
					'rate_limited',
					__( 'API rate limit exceeded. Please try again later.', 'feed-blogroll' ),
					array(
						'endpoint' => $endpoint,
						'code' => $response_code,
					)
				);

			case 500:
			case 502:
			case 503:
			case 504:
				return new WP_Error(
					'server_error',
					/* translators: %d: HTTP response code */
					sprintf( __( 'Feedbin server error (HTTP %d). Please try again later.', 'feed-blogroll' ), $response_code ),
					array(
						'endpoint' => $endpoint,
						'code' => $response_code,
					)
				);

			default:
				return new WP_Error(
					'api_error',
					/* translators: 1: HTTP response code, 2: endpoint path */
					sprintf( __( 'Unexpected API response: HTTP %1$d from %2$s', 'feed-blogroll' ), $response_code, $endpoint ),
					array(
						'endpoint' => $endpoint,
						'code' => $response_code,
						'response' => $response_body,
					)
				);
		}
	}

	/**
	 * Test API connection
	 *
	 * @return string|WP_Error Success message or error
	 */
	public function test_connection() {
		$response = $this->make_request( 'subscriptions.json' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_array( $response ) ) {
			/* translators: %d: number of subscriptions */
			return sprintf( __( 'Connection successful! Found %d subscriptions.', 'feed-blogroll' ), count( $response ) );
		}

		return __( 'Connection successful but unexpected response format.', 'feed-blogroll' );
	}

	/**
	 * Get all feeds from Feedbin
	 *
	 * @return array|WP_Error Array of feeds or error
	 */
	public function get_feeds() {
		$response = $this->make_request( 'subscriptions.json' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Allow disabling tag fetching via constant or filter (performance)
		$fetch_tags = true;
		if ( defined( 'FEED_BLOGROLL_FETCH_TAGS' ) ) {
			$fetch_tags = (bool) constant( 'FEED_BLOGROLL_FETCH_TAGS' );
		}
		/**
		 * Filter whether to fetch tags for subscriptions.
		 *
		 * @param bool $fetch_tags Default true.
		 */
		$fetch_tags = (bool) apply_filters( 'feed_blogroll_fetch_tags', $fetch_tags );

		// Transform subscriptions to feed format
		$feeds = array();
		foreach ( $response as $subscription ) {
			$feed = array(
				'id'          => absint( $subscription['feed_id'] ),
				'title'       => sanitize_text_field( $subscription['title'] ?? '' ),
				'feed_url'    => esc_url_raw( $subscription['feed_url'] ?? '' ),
				'site_url'    => esc_url_raw( $subscription['site_url'] ?? '' ),
				'description' => wp_kses_post( $subscription['description'] ?? '' ),
				'tags'        => array(),
			);

			// Get tags for this subscription (conditionally, cached)
			if ( $fetch_tags ) {
				$tags = $this->get_subscription_tags( $subscription['feed_id'] );
				if ( ! is_wp_error( $tags ) ) {
					$feed['tags'] = array_map( 'sanitize_text_field', $tags );
				}
			}

			$feeds[] = $feed;
		}

		return $feeds;
	}

	/**
	 * Get tags for a specific subscription
	 *
	 * @param int $feed_id Feed ID
	 * @return array|WP_Error Array of tags or error
	 */
	private function get_subscription_tags( $feed_id ) {
		$feed_id = absint( $feed_id );
		if ( ! $feed_id ) {
			return array();
		}

		// Transient cache to avoid per-feed API overhead
		$cache_key = 'ftb_tags_' . $feed_id;
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		$response = $this->make_request( "subscriptions/{$feed_id}/tags.json" );

		if ( is_wp_error( $response ) ) {
			// If tags endpoint fails, return empty array instead of error
			return array();
		}

		if ( is_array( $response ) ) {
			$tags = array_column( $response, 'name' );
			set_transient( $cache_key, $tags, DAY_IN_SECONDS );
			return $tags;
		}

		return array();
	}

	/**
	 * Get API status information
	 *
	 * @return array API status
	 */
	public function get_api_status() {
		$status = array(
			'configured' => $this->has_credentials(),
			'connected'  => false,
			'last_test' => get_option( 'feed_blogroll_api_last_test', '' ),
			'error'      => '',
		);

		if ( ! $status['configured'] ) {
			$status['error'] = __( 'API credentials not configured', 'feed-blogroll' );
			return $status;
		}

		// Test connection if not tested recently
		$last_test = strtotime( $status['last_test'] );
		if ( ( ! $last_test ) || ( time() - $last_test ) > 3600 ) { // Test every hour
			$test_result = $this->test_connection();

			if ( is_wp_error( $test_result ) ) {
				$status['error']     = $test_result->get_error_message();
				$status['connected'] = false;
				update_option( 'feed_blogroll_api_connected', false );
				update_option( 'feed_blogroll_api_last_error', $test_result->get_error_message() );
			} else {
				$status['connected'] = true;
				$status['error']     = '';
				update_option( 'feed_blogroll_api_connected', true );
				update_option( 'feed_blogroll_api_last_error', '' );
			}

			update_option( 'feed_blogroll_api_last_test', current_time( 'mysql' ) );
		} else {
			// Use cached status
			$status['connected'] = get_option( 'feed_blogroll_api_connected', false );
			$status['error'] = get_option( 'feed_blogroll_api_last_error', '' );
		}

		return $status;
	}

	/**
	 * Get subscription count
	 *
	 * @return int|WP_Error Number of subscriptions or error
	 */
	public function get_subscription_count() {
		$response = $this->make_request( 'subscriptions.json' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return is_array( $response ) ? count( $response ) : 0;
	}

	/**
	 * Validate feed URL
	 *
	 * @param string $url Feed URL to validate
	 * @return bool|WP_Error True if valid, error if invalid
	 */
	public function validate_feed_url( $url ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return new WP_Error( 'invalid_url', __( 'Invalid URL format', 'feed-blogroll' ) );
		}

		// Test if URL is accessible
		$response = wp_remote_head( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'url_unreachable', __( 'URL is not accessible', 'feed-blogroll' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			/* translators: %d: HTTP response code */
			return new WP_Error( 'url_error', sprintf( __( 'URL returned HTTP %d', 'feed-blogroll' ), $response_code ) );
		}

		// Check if response looks like a feed
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( $content_type && strpos( $content_type, 'xml' ) === false && strpos( $content_type, 'rss' ) === false ) {
			return new WP_Error( 'not_feed', __( 'URL does not appear to be a valid RSS feed', 'feed-blogroll' ) );
		}

		return true;
	}

	/**
	 * Add new subscription to Feedbin
	 *
	 * @param string $feed_url Feed URL to add
	 * @return array|WP_Error Subscription data or error
	 */
	public function add_subscription( $feed_url ) {
		// Validate feed URL first
		$validation = $this->validate_feed_url( $feed_url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$data = array(
			'feed_url' => esc_url_raw( $feed_url ),
		);

		return $this->make_request( 'subscriptions.json', 'POST', $data );
	}

	/**
	 * Remove subscription from Feedbin
	 *
	 * @param int $subscription_id Subscription ID to remove
	 * @return bool|WP_Error Success status or error
	 */
	public function remove_subscription( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		if ( ! $subscription_id ) {
			return new WP_Error( 'invalid_id', __( 'Invalid subscription ID', 'feed-blogroll' ) );
		}

		$response = $this->make_request( "subscriptions/{$subscription_id}.json", 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}
}
