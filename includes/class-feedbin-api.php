<?php
/**
 * Feedbin API Integration
 *
 * @package FeedToBlogroll
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
class Feed_To_Blogroll_Feedbin_API {

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
		$options = get_option( 'feed_to_blogroll_options', array() );
		$this->credentials = array(
			'username' => isset( $options['feedbin_username'] ) ? $options['feedbin_username'] : '',
			'password' => isset( $options['feedbin_password'] ) ? $options['feedbin_password'] : '',
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
	 * Make authenticated API request
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param array  $data Request data.
	 * @return array|WP_Error Response data or error.
	 */
	private function make_request( $endpoint, $method = 'GET', $data = array() ) {
		if ( ! $this->has_credentials() ) {
			return new WP_Error( 'no_credentials', __( 'Feedbin credentials not configured', 'feed-to-blogroll' ) );
		}

		$url = $this->api_base . $endpoint;
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->credentials['username'] . ':' . $this->credentials['password'] ),
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'Feed-To-Blogroll/' . FEED_TO_BLOGROLL_VERSION,
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

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Log API response for debugging
		$this->log_api_response( $endpoint, $method, $response_code, $response_body );

		if ( $response_code >= 200 && $response_code < 300 ) {
			$decoded = json_decode( $response_body, true );
			return is_array( $decoded ) ? $decoded : array();
		} else {
			return new WP_Error(
				'api_error',
				sprintf(
					/* translators: %1$s: HTTP status code, %2$s: Response body */
					__( 'Feedbin API error (HTTP %1$s): %2$s', 'feed-to-blogroll' ),
					$response_code,
					$response_body
				)
			);
		}
	}

	/**
	 * Get user subscriptions
	 *
	 * @return array|WP_Error Subscriptions or error.
	 */
	public function get_subscriptions() {
		return $this->make_request( 'subscriptions.json' );
	}

	/**
	 * Get feed details
	 *
	 * @param int $feed_id Feed ID.
	 * @return array|WP_Error Feed details or error.
	 */
	public function get_feed( $feed_id ) {
		return $this->make_request( 'feeds/' . intval( $feed_id ) . '.json' );
	}

	/**
	 * Get all feeds
	 *
	 * @return array|WP_Error Feeds or error.
	 */
	public function get_feeds() {
		return $this->make_request( 'feeds.json' );
	}

	/**
	 * Get starred entries
	 *
	 * @param int $page Page number.
	 * @return array|WP_Error Starred entries or error.
	 */
	public function get_starred_entries( $page = 1 ) {
		return $this->make_request( 'starred_entries.json?page=' . intval( $page ) );
	}

	/**
	 * Test API connection
	 *
	 * @return array|WP_Error Test result or error.
	 */
	public function test_connection() {
		// First test with a simple endpoint
		$result = $this->make_request( 'subscriptions.json' );

		if ( is_wp_error( $result ) ) {
			// Log detailed error for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Feedbin API Test Error: ' . $result->get_error_message() );
			}
			return $result;
		}

		return array(
			'success' => true,
			'message' => __( 'Feedbin API connection successful', 'feed-to-blogroll' ),
			'count'   => count( $result ),
		);
	}

	/**
	 * Get subscription with feed details
	 *
	 * @return array|WP_Error Enhanced subscriptions or error.
	 */
	public function get_subscriptions_with_feeds() {
		$subscriptions = $this->get_subscriptions();
		if ( is_wp_error( $subscriptions ) ) {
			return $subscriptions;
		}

		$feeds = $this->get_feeds();
		if ( is_wp_error( $feeds ) ) {
			return $feeds;
		}

		// Create feeds lookup
		$feeds_lookup = array();
		foreach ( $feeds as $feed ) {
			if ( isset( $feed['id'] ) ) {
				$feeds_lookup[ $feed['id'] ] = $feed;
			}
		}

		// Enhance subscriptions with feed details
		$enhanced_subscriptions = array();
		foreach ( $subscriptions as $subscription ) {
			if ( isset( $subscription['feed_id'] ) && isset( $feeds_lookup[ $subscription['feed_id'] ] ) ) {
				$enhanced_subscriptions[] = array_merge(
					$subscription,
					$feeds_lookup[ $subscription['feed_id'] ]
				);
			}
		}

		return $enhanced_subscriptions;
	}

	/**
	 * Log API responses for debugging
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param int    $response_code HTTP response code.
	 * @param string $response_body Response body.
	 */
	private function log_api_response( $endpoint, $method, $response_code, $response_body ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_entry = sprintf(
				'[%s] %s %s - HTTP %d - %s',
				current_time( 'Y-m-d H:i:s' ),
				$method,
				$endpoint,
				$response_code,
				substr( $response_body, 0, 200 ) . ( strlen( $response_body ) > 200 ? '...' : '' )
			);

			error_log( 'Feedbin API: ' . $log_entry );
		}
	}

	/**
	 * Get API rate limit information
	 *
	 * @return array Rate limit info.
	 */
	public function get_rate_limit_info() {
		$response = wp_remote_head(
			$this->api_base . 'subscriptions.json',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->credentials['username'] . ':' . $this->credentials['password'] ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$headers = wp_remote_retrieve_headers( $response );
		$rate_limit_info = array();

		if ( isset( $headers['X-RateLimit-Limit'] ) ) {
			$rate_limit_info['limit'] = intval( $headers['X-RateLimit-Limit'] );
		}

		if ( isset( $headers['X-RateLimit-Remaining'] ) ) {
			$rate_limit_info['remaining'] = intval( $headers['X-RateLimit-Remaining'] );
		}

		if ( isset( $headers['X-RateLimit-Reset'] ) ) {
			$rate_limit_info['reset'] = intval( $headers['X-RateLimit-Reset'] );
		}

		return $rate_limit_info;
	}

	/**
	 * Check if we're approaching rate limits
	 *
	 * @return bool True if approaching limits.
	 */
	public function is_approaching_rate_limit() {
		$rate_limit_info = $this->get_rate_limit_info();

		if ( empty( $rate_limit_info ) || ! isset( $rate_limit_info['remaining'] ) || ! isset( $rate_limit_info['limit'] ) ) {
			return false;
		}

		$threshold = 0.1; // 10% remaining
		return ( $rate_limit_info['remaining'] / $rate_limit_info['limit'] ) <= $threshold;
	}

	/**
	 * Get API status summary
	 *
	 * @return array API status information.
	 */
	public function get_api_status() {
		$status = array(
			'has_credentials' => $this->has_credentials(),
			'connection_test' => null,
			'rate_limit'      => null,
			'last_check'      => current_time( 'mysql' ),
		);

		if ( $this->has_credentials() ) {
			$connection_test = $this->test_connection();
			$status['connection_test'] = is_wp_error( $connection_test ) ? 'error' : 'success';

			$rate_limit = $this->get_rate_limit_info();
			if ( ! empty( $rate_limit ) ) {
				$status['rate_limit'] = $rate_limit;
			}
		}

		return $status;
	}
}
