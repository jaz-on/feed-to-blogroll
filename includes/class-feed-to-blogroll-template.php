<?php
/**
 * Blogroll Template Integration
 *
 * @package FeedToBlogroll
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blogroll template integration class
 *
 * @since 1.0.0
 */
class Feed_To_Blogroll_Template {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'blogroll', array( $this, 'blogroll_shortcode' ) );
		add_shortcode( 'blogroll_grid', array( $this, 'blogroll_grid_shortcode' ) );
		add_filter( 'the_content', array( $this, 'maybe_add_blogroll_content' ) );

		// Register REST API routes
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Register AJAX handlers for frontend export
		add_action( 'wp_ajax_feed_to_blogroll_export_opml', array( $this, 'export_opml_frontend' ) );
		add_action( 'wp_ajax_nopriv_feed_to_blogroll_export_opml', array( $this, 'export_opml_frontend' ) );

		// Invalidate caches when blogroll content changes
		add_action( 'save_post_blogroll', array( $this, 'bust_blogroll_caches' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'bust_blogroll_caches_on_delete' ) );
		// Invalidate on taxonomy changes as well
		add_action( 'created_blogroll_category', array( $this, 'bust_blogroll_caches_on_term_change' ), 10, 3 );
		add_action( 'edited_blogroll_category', array( $this, 'bust_blogroll_caches_on_term_change' ), 10, 3 );
		add_action( 'delete_blogroll_category', array( $this, 'bust_blogroll_caches_on_term_change' ), 10, 3 );
	}

	/**
	 * Enqueue frontend assets only when needed (once per request)
	 */
	public function enqueue_frontend_assets_once() {
		static $enqueued = false;
		if ( $enqueued ) {
			return;
		}
		$enqueued = true;

		wp_enqueue_style(
			'feed-to-blogroll-frontend',
			FEED_TO_BLOGROLL_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			FEED_TO_BLOGROLL_VERSION
		);

		wp_enqueue_script(
			'feed-to-blogroll-frontend',
			FEED_TO_BLOGROLL_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			FEED_TO_BLOGROLL_VERSION,
			true
		);

		wp_localize_script(
			'feed-to-blogroll-frontend',
			'feedToBlogrollFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'feed_to_blogroll_export' ),
				'strings' => array(
					'exporting'   => __( 'Exporting...', 'feed-to-blogroll' ),
					'exported'    => __( 'OPML file downloaded successfully!', 'feed-to-blogroll' ),
					'error'       => __( 'Export failed. Please try again.', 'feed-to-blogroll' ),
					'exportLabel' => __( 'Export OPML', 'feed-to-blogroll' ),
				),
			)
		);
	}

	/**
	 * Main blogroll shortcode with optimized cache
	 *
	 * @param array $atts Shortcode attributes
	 * @return string Shortcode output
	 */
	public function blogroll_shortcode( $atts ) {
		// Ensure assets only when shortcode is used
		$this->enqueue_frontend_assets_once();
		$atts = shortcode_atts(
			array(
				'category'    => '',
				'limit'       => -1,
				'columns'     => 4,
				'show_export' => 'true',
			),
			$atts,
			'blogroll'
		);

		// Sanitize and validate attributes
		$atts['category'] = sanitize_text_field( $atts['category'] );
		$atts['limit'] = absint( $atts['limit'] );
		$atts['columns'] = max( 1, min( 6, absint( $atts['columns'] ) ) );
		$atts['show_export'] = filter_var( $atts['show_export'], FILTER_VALIDATE_BOOLEAN );

		// Generate optimized cache key
		$cache_key = sprintf(
			'blogroll_%s_%d_%d_v%s',
			(string) $atts['category'],
			(int) $atts['limit'],
			(int) $atts['columns'],
			(string) get_option( 'feed_to_blogroll_cache_version', 1 )
		);
		$cached_output = wp_cache_get( $cache_key, 'feed_to_blogroll' );

		if ( false !== $cached_output ) {
			return $cached_output;
		}

		$query_args = array(
			'post_type'              => 'blogroll',
			'post_status'            => 'publish',
			'posts_per_page'         => $atts['limit'],
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true, // Optimize for performance
			'update_post_meta_cache' => true, // Prime post meta cache for bulk reads
			'update_post_term_cache' => false, // We'll load terms separately
		);

		if ( ! empty( $atts['category'] ) ) {
			$term = get_term_by( 'slug', $atts['category'], 'blogroll_category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$post_ids = get_objects_in_term( array( (int) $term->term_id ), 'blogroll_category' );
				if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
					$query_args['post__in'] = array_map( 'intval', $post_ids );
				} else {
					// No posts for this term, shortcut to empty result
					return '<p class="blogroll-empty">' . esc_html__( 'No blogs found.', 'feed-to-blogroll' ) . '</p>';
				}
			}
		}

		$blogs = get_posts( $query_args );

		if ( empty( $blogs ) ) {
			$output = '<p class="blogroll-empty">' . esc_html__( 'No blogs found.', 'feed-to-blogroll' ) . '</p>';
			wp_cache_set( $cache_key, $output, 'feed_to_blogroll', HOUR_IN_SECONDS );
			return $output;
		}

		// Load meta data efficiently
		$blog_data = $this->prepare_blog_data( $blogs );

		ob_start();
		$this->render_blogroll( $blog_data, $atts );
		$output = ob_get_clean();

		// Cache the output
		wp_cache_set( $cache_key, $output, 'feed_to_blogroll', HOUR_IN_SECONDS );

		return $output;
	}

	/**
	 * Grid-specific shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string Shortcode output
	 */
	public function blogroll_grid_shortcode( $atts ) {
		// Ensure assets only when shortcode is used
		$this->enqueue_frontend_assets_once();
		$atts = shortcode_atts(
			array(
				'category' => '',
				'limit'    => 8,
				'columns'  => 3,
			),
			$atts,
			'blogroll_grid'
		);

		// Force grid layout
		$atts['show_export'] = false;
		$atts['columns'] = max( 2, min( 4, absint( $atts['columns'] ) ) );

		return $this->blogroll_shortcode( $atts );
	}

	/**
	 * Prepare blog data efficiently
	 *
	 * @param array $blogs Array of WP_Post objects
	 * @return array Prepared blog data
	 */
	private function prepare_blog_data( $blogs ) {
		$blog_data = array();

		foreach ( $blogs as $blog ) {
			$site_url = get_post_meta( $blog->ID, 'site_url', true );
			$rss_url  = get_post_meta( $blog->ID, 'rss_url', true );
			$blog_data[] = array(
				'id'          => $blog->ID,
				'title'       => $blog->post_title,
				'description' => ( ! empty( $blog->post_excerpt ) ) ? $blog->post_excerpt : wp_trim_words( $blog->post_content, 20 ),
				'site_url'    => ( ! empty( $site_url ) ) ? $site_url : '',
				'rss_url'     => ( ! empty( $rss_url ) ) ? $rss_url : '',
				'categories'  => wp_get_post_terms( $blog->ID, 'blogroll_category', array( 'fields' => 'names' ) ),
			);
		}

		return $blog_data;
	}

	/**
	 * Render blogroll HTML with accessibility improvements
	 *
	 * @param array $blogs Blog data
	 * @param array $atts Shortcode attributes
	 */
	private function render_blogroll( $blogs, $atts ) {
		$container_class = 'feed-to-blogroll-container';
		$grid_class = 'blogroll-grid';
		$columns_class = 'columns-' . $atts['columns'];

		?>
		<div class="<?php echo esc_attr( $container_class ); ?>" role="region" aria-label="<?php esc_attr_e( 'Blogroll', 'feed-to-blogroll' ); ?>">
			<?php if ( $atts['show_export'] ) : ?>
				<?php $export_nonce = wp_create_nonce( 'feed_to_blogroll_export' ); ?>
				<div class="blogroll-export" role="toolbar" aria-label="<?php esc_attr_e( 'Blogroll export options', 'feed-to-blogroll' ); ?>">
					<button
						type="button"
						class="export-opml-button"
						data-nonce="<?php echo esc_attr( $export_nonce ); ?>"
					>
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Export OPML', 'feed-to-blogroll' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<div class="<?php echo esc_attr( $grid_class . ' ' . $columns_class ); ?>" role="list">
				<?php foreach ( $blogs as $blog ) : ?>
					<article class="blog-card" role="listitem">
						<header class="blog-header">
							<h3 class="blog-title">
								<?php if ( ! empty( $blog['site_url'] ) ) : ?>
									<a href="<?php echo esc_url( $blog['site_url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $blog['title'] ); ?>
										<span
											class="external-link-icon"
											aria-label="<?php esc_attr_e( 'Opens in new window', 'feed-to-blogroll' ); ?>"
										>
											<svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
												<path d="M10.5 1.5h-3v1h2.293L5.5 6.793l.707.707L10.5 3.207V5.5h1v-3a1 1 0 0 0-1-1z"/>
												<path d="M9.5 9.5h-7v-7h3.5v-1h-3.5a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1v-3.5h-1v3.5z"/>
											</svg>
										</span>
									</a>
								<?php else : ?>
									<?php echo esc_html( $blog['title'] ); ?>
								<?php endif; ?>
							</h3>
						</header>

						<?php if ( ! empty( $blog['description'] ) ) : ?>
							<div class="blog-description">
								<?php echo wp_kses_post( $blog['description'] ); ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $blog['categories'] ) ) : ?>
							<div class="blog-categories" role="group" aria-label="<?php esc_attr_e( 'Blog categories', 'feed-to-blogroll' ); ?>">
								<?php foreach ( $blog['categories'] as $category ) : ?>
									<span class="blog-category"><?php echo esc_html( $category ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<footer class="blog-actions">
							<?php if ( ! empty( $blog['site_url'] ) ) : ?>
								<a href="<?php echo esc_url( $blog['site_url'] ); ?>" class="blog-link" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Visit Site', 'feed-to-blogroll' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( ! empty( $blog['rss_url'] ) ) : ?>
								<a href="<?php echo esc_url( $blog['rss_url'] ); ?>" class="rss-link" target="_blank" rel="noopener noreferrer">
									<span class="dashicons dashicons-rss" aria-hidden="true"></span>
									<?php esc_html_e( 'RSS Feed', 'feed-to-blogroll' ); ?>
								</a>
							<?php endif; ?>
						</footer>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_frontend_scripts() {
		wp_enqueue_style(
			'feed-to-blogroll-frontend',
			FEED_TO_BLOGROLL_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			FEED_TO_BLOGROLL_VERSION
		);

		wp_enqueue_script(
			'feed-to-blogroll-frontend',
			FEED_TO_BLOGROLL_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			FEED_TO_BLOGROLL_VERSION,
			true
		);

		wp_localize_script(
			'feed-to-blogroll-frontend',
			'feedToBlogrollFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'feed_to_blogroll_export' ),
				'strings' => array(
					'exporting' => __( 'Exporting...', 'feed-to-blogroll' ),
					'exported'  => __( 'OPML file downloaded successfully!', 'feed-to-blogroll' ),
					'error'     => __( 'Export failed. Please try again.', 'feed-to-blogroll' ),
				),
			)
		);
	}

	/**
	 * Maybe add blogroll content to posts
	 *
	 * @param string $content Post content
	 * @return string Modified content
	 */
	public function maybe_add_blogroll_content( $content ) {
		// Only add to single blogroll posts
		if ( is_singular( 'blogroll' ) ) {
			$blog_id = get_the_ID();
			$rss_url = get_field( 'rss_url', $blog_id );
			$site_url = get_field( 'site_url', $blog_id );

			if ( $rss_url || $site_url ) {
				$content .= '<div class="blogroll-links">';
				if ( $site_url ) {
					$content .= sprintf(
						'<p><a href="%s" class="button button-primary" target="_blank" rel="noopener noreferrer">%s</a></p>',
						esc_url( $site_url ),
						esc_html__( 'Visit Website', 'feed-to-blogroll' )
					);
				}
				if ( $rss_url ) {
					$content .= sprintf(
						'<p><a href="%s" class="button button-secondary" target="_blank" rel="noopener noreferrer">%s</a></p>',
						esc_url( $rss_url ),
						esc_html__( 'RSS Feed', 'feed-to-blogroll' )
					);
				}
				$content .= '</div>';
			}
		}

		return $content;
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		register_rest_route(
			'feed-to-blogroll/v1',
			'/blogroll',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_blogroll_rest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'category' => array(
						'validate_callback' => function ( $value ) {
							return is_string( $value ) || is_null( $value ); },
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'    => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0; },
						'sanitize_callback' => 'absint',
						'default'           => 10,
					),
				),
			)
		);
	}

	/**
	 * REST API callback for blogroll
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response object
	 */
	public function get_blogroll_rest( $request ) {
		$category = $request->get_param( 'category' );
		$limit = $request->get_param( 'limit' );

		$query_args = array(
			'post_type'      => 'blogroll',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $category ) ) {
			$term = get_term_by( 'slug', $category, 'blogroll_category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$post_ids = get_objects_in_term( array( (int) $term->term_id ), 'blogroll_category' );
				if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
					$query_args['post__in'] = array_map( 'intval', $post_ids );
				} else {
					return rest_ensure_response( array() );
				}
			}
		}

		$blogs = get_posts( $query_args );
		$blog_data = $this->prepare_blog_data( $blogs );

		$response = rest_ensure_response( $blog_data );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * Frontend OPML export via AJAX
	 */
	public function export_opml_frontend() {
		// Check nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'feed_to_blogroll_export' ) ) {
			wp_send_json_error( __( 'Security check failed', 'feed-to-blogroll' ) );
		}

		// Try cached OPML first
		$cached = get_transient( 'feed_to_blogroll_opml' );
		if ( false !== $cached && is_array( $cached ) && isset( $cached['opml'], $cached['filename'] ) ) {
			wp_send_json_success( $cached );
		}

		$blogs = get_posts(
			array(
				'post_type'      => 'blogroll',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		$opml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$opml .= '<opml version="2.0">' . "\n";
		$opml .= '  <head>' . "\n";
		$opml .= '    <title>' . esc_html( get_bloginfo( 'name' ) ) . ' Blogroll</title>' . "\n";
		$opml .= '    <dateCreated>' . gmdate( 'D, d M Y H:i:s' ) . ' GMT</dateCreated>' . "\n";
		$opml .= '  </head>' . "\n";
		$opml .= '  <body>' . "\n";

		foreach ( $blogs as $blog ) {
			$rss_url = get_field( 'rss_url', $blog->ID );
			$site_url = get_field( 'site_url', $blog->ID );

			if ( $rss_url ) {
				$opml .= '    <outline type="rss" text="' . esc_attr( $blog->post_title ) . '" ';
				$opml .= 'title="' . esc_attr( $blog->post_title ) . '" ';
				$opml .= 'xmlUrl="' . esc_attr( $rss_url ) . '" ';
				$opml .= 'htmlUrl="' . esc_attr( $site_url ) . '" />' . "\n";
			}
		}

		$opml .= '  </body>' . "\n";
		$opml .= '</opml>';

		$response = array(
			'opml'     => $opml,
			'filename' => 'blogroll-' . gmdate( 'Y-m-d' ) . '.opml',
		);

		// Cache OPML for 1 hour
		set_transient( 'feed_to_blogroll_opml', $response, HOUR_IN_SECONDS );

		wp_send_json_success( $response );
	}

	/**
	 * Bust blogroll caches when content changes
	 */
	public function bust_blogroll_caches() {
		$this->increment_cache_version();
		wp_cache_flush_group( 'feed_to_blogroll' );
	}

	/**
	 * Bust caches when blogroll posts are deleted
	 */
	public function bust_blogroll_caches_on_delete( $post_id ) {
		if ( get_post_type( $post_id ) === 'blogroll' ) {
			$this->bust_blogroll_caches();
		}
	}

	/**
	 * Bust caches when taxonomy terms change
	 */
	public function bust_blogroll_caches_on_term_change() {
		$this->bust_blogroll_caches();
	}

	/**
	 * Increment cache version to invalidate all caches
	 */
	private function increment_cache_version() {
		$current_version = get_option( 'feed_to_blogroll_cache_version', 1 );
		update_option( 'feed_to_blogroll_cache_version', $current_version + 1 );
	}
}
