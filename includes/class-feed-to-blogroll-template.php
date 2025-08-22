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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
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
	 * Main blogroll shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function blogroll_shortcode( $atts ) {
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

		// Generate cache key based on attributes + cache version
		$cache_version = (int) get_option( 'feed_to_blogroll_cache_version', 1 );
		$cache_key     = 'blogroll_shortcode_v' . $cache_version . '_' . md5( wp_json_encode( $atts ) );
		$cached_output = wp_cache_get( $cache_key, 'feed_to_blogroll' );

		if ( false !== $cached_output ) {
			return $cached_output;
		}

		$query_args = array(
			'post_type'              => 'blogroll',
			'post_status'            => 'publish',
			'posts_per_page'         => intval( $atts['limit'] ),
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true, // Optimize for performance
			'update_post_meta_cache' => false, // We'll load meta separately
			'update_post_term_cache' => false, // We'll load terms separately
		);

		if ( ! empty( $atts['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'blogroll_category',
					'field'    => 'slug',
					'terms'    => $atts['category'],
				),
			);
		}

		$blogs = get_posts( $query_args );

		if ( empty( $blogs ) ) {
			return '<p>' . esc_html__( 'No blogs found.', 'feed-to-blogroll' ) . '</p>';
		}

		ob_start();
		?>
		<div class="feed-to-blogroll-container">
			<?php if ( 'true' === $atts['show_export'] ) : ?>
				<div class="blogroll-export">
					<button type="button" class="export-opml-button" data-nonce="<?php echo esc_attr( wp_create_nonce( 'feed_to_blogroll_frontend' ) ); ?>" aria-label="<?php esc_attr_e( 'Export blogroll as OPML file', 'feed-to-blogroll' ); ?>">
						<svg class="export-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false">
							<path d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1z"/>
						</svg>
						<?php esc_html_e( 'Export OPML', 'feed-to-blogroll' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<div class="blogroll-grid columns-<?php echo esc_attr( $atts['columns'] ); ?>">
				<?php foreach ( $blogs as $blog ) : ?>
					<?php $this->render_blog_card( $blog ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		$output = ob_get_clean();

		// Cache the output for 1 hour
		wp_cache_set( $cache_key, $output, 'feed_to_blogroll', HOUR_IN_SECONDS );

		return $output;
	}

	/**
	 * Bust caches when blogroll post is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether this is an existing post being updated or not.
	 */
	public function bust_blogroll_caches( $post_id, $post, $update = false ) {
		// Only for our CPT
		if ( 'blogroll' !== $post->post_type ) {
			return;
		}

		// Prevent unused parameter warning
		unset( $update );

		// Increment cache version to invalidate all shortcode caches
		$version = (int) get_option( 'feed_to_blogroll_cache_version', 1 );
		update_option( 'feed_to_blogroll_cache_version', $version + 1 );

		// Clear OPML cache
		wp_cache_delete( 'feed_to_blogroll_opml', 'feed_to_blogroll' );
	}

	/**
	 * Bust caches when any post is deleted (check for blogroll type).
	 *
	 * @param int $post_id Post ID.
	 */
	public function bust_blogroll_caches_on_delete( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'blogroll' === $post->post_type ) {
			$version = (int) get_option( 'feed_to_blogroll_cache_version', 1 );
			update_option( 'feed_to_blogroll_cache_version', $version + 1 );
			wp_cache_delete( 'feed_to_blogroll_opml', 'feed_to_blogroll' );
		}
	}

	/**
	 * Blogroll grid shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function blogroll_grid_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'limit'    => 12,
				'columns'  => 4,
			),
			$atts,
			'blogroll_grid'
		);

		return $this->blogroll_shortcode( $atts );
	}

	/**
	 * Render a single blog card
	 *
	 * @param WP_Post $blog Blog post object.
	 */
	private function render_blog_card( $blog ) {
		// Get all meta fields in one query to avoid multiple database calls
		$meta_fields = get_fields( $blog->ID );
		$site_url    = $meta_fields['site_url'] ?? '';
		$rss_url     = $meta_fields['rss_url'] ?? '';
		$author      = $meta_fields['author'] ?? '';

		// Get categories efficiently
		$categories = wp_get_object_terms( $blog->ID, 'blogroll_category', array( 'fields' => 'names' ) );

		// Prepare structured data
		$structured_data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'WebSite',
			'name'        => $blog->post_title,
			'url'         => $site_url,
			'description' => $blog->post_excerpt,
			'author'      => array(
				'@type' => 'Person',
				'name'  => $author ? $author : 'Unknown Author',
			),
			'publisher'   => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
		);

		// Add RSS feed information
		if ( $rss_url ) {
			$structured_data['rss'] = $rss_url;
		}

		// Add category information
		if ( $categories && ! is_wp_error( $categories ) ) {
			$structured_data['keywords'] = $categories;
		}
		?>
		<article class="blog-card h-entry" id="blog-<?php echo esc_attr( $blog->ID ); ?>" itemscope itemtype="https://schema.org/WebSite" role="article" aria-labelledby="blog-title-<?php echo esc_attr( $blog->ID ); ?>">
			<!-- Structured data -->
			<script type="application/ld+json">
				<?php echo wp_json_encode( $structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
			</script>
			
			<div class="blog-card-content e-content">
				<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
					<div class="blog-category" itemprop="keywords">
						<?php echo esc_html( is_array( $categories ) ? ( $categories[0] ?? '' ) : '' ); ?>
					</div>
				<?php endif; ?>

				<h3 class="blog-title p-name" id="blog-title-<?php echo esc_attr( $blog->ID ); ?>">
					<a href="<?php echo esc_url( $site_url ); ?>" target="_blank" rel="noopener noreferrer" itemprop="url" aria-describedby="blog-description-<?php echo esc_attr( $blog->ID ); ?>">
						<span itemprop="name"><?php echo esc_html( $blog->post_title ); ?></span>
					</a>
				</h3>

				<?php if ( $blog->post_excerpt ) : ?>
					<div class="blog-description p-summary" id="blog-description-<?php echo esc_attr( $blog->ID ); ?>" itemprop="description">
						<?php echo wp_kses_post( $blog->post_excerpt ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $author ) : ?>
					<div class="blog-author p-author" itemprop="author" itemscope itemtype="https://schema.org/Person">
						<span itemprop="name"><?php echo esc_html( $author ); ?></span>
					</div>
				<?php endif; ?>

				<?php /* translators: %s: blog title */ ?>
				<div class="blog-actions" role="group" aria-label="<?php echo esc_attr( sprintf( __( 'Actions for %s', 'feed-to-blogroll' ), $blog->post_title ) ); ?>">
					<?php if ( $site_url ) : ?>
						<?php /* translators: %s: blog title */ ?>
						<a href="<?php echo esc_url( $site_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary u-url" itemprop="url" aria-label="<?php echo esc_attr( sprintf( __( 'Visit %s website', 'feed-to-blogroll' ), $blog->post_title ) ); ?>">
							<?php esc_html_e( 'Visit Site', 'feed-to-blogroll' ); ?>
						</a>
					<?php endif; ?>

					<?php if ( $rss_url ) : ?>
						<?php /* translators: %s: blog title */ ?>
						<a href="<?php echo esc_url( $rss_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" aria-label="<?php echo esc_attr( sprintf( __( 'Subscribe to RSS feed for %s', 'feed-to-blogroll' ), $blog->post_title ) ); ?>">
							<svg class="rss-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false">
								<path d="M2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2zm1.5 2.5c5.523 0 10 4.477 10 10a1 1 0 1 1-2 0 8 8 0 0 0-8-8 1 1 0 0 1 0-2zm0 4a6 6 0 0 1 6 6 1 1 0 1 1-2 0 4 4 0 0 0-4-4 1 1 0 0 1 0-2zm.5 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
							</svg>
							<span><?php esc_html_e( 'RSS', 'feed-to-blogroll' ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Maybe add blogroll content to posts/pages
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function maybe_add_blogroll_content( $content ) {
		global $post;

		// Check if this is a blogroll post type
		if ( 'blogroll' === get_post_type() ) {
			$site_url = get_field( 'site_url', $post->ID );
			$rss_url  = get_field( 'rss_url', $post->ID );
			$author   = get_field( 'author', $post->ID );

			$blogroll_info = '<div class="blogroll-meta">';

			if ( $site_url ) {
				$blogroll_info .= sprintf(
					'<p><strong>%s:</strong> <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
					esc_html__( 'Website', 'feed-to-blogroll' ),
					esc_url( $site_url ),
					esc_url( $site_url )
				);
			}

			if ( $rss_url ) {
				$blogroll_info .= sprintf(
					'<p><strong>%s:</strong> <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
					esc_html__( 'RSS Feed', 'feed-to-blogroll' ),
					esc_url( $rss_url ),
					esc_html__( 'Subscribe to RSS', 'feed-to-blogroll' )
				);
			}

			if ( $author ) {
				$blogroll_info .= sprintf(
					'<p><strong>%s:</strong> %s</p>',
					esc_html__( 'Author', 'feed-to-blogroll' ),
					esc_html( $author )
				);
			}

			$blogroll_info .= '</div>';

			$content = $blogroll_info . $content;
		}

		return $content;
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
				'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'feed_to_blogroll_frontend' ),
			)
		);
	}

	/**
	 * Get blogroll data for REST API
	 *
	 * @param WP_REST_Request $request Request parameters.
	 * @return WP_REST_Response Response object.
	 */
	public function get_blogroll_rest( $request ) {
		$category = sanitize_text_field( $request->get_param( 'category' ) );
		$limit    = intval( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 12 );

		// Cache REST response for a short period and invalidate via version bump
		$version   = (int) get_option( 'feed_to_blogroll_cache_version', 1 );
		$cache_key = 'rest_blogroll_v' . $version . '_' . md5( wp_json_encode( array( $category, $limit ) ) );
		$cached    = wp_cache_get( $cache_key, 'feed_to_blogroll' );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$query_args = array(
			'post_type'      => 'blogroll',
			'post_status'    => 'publish',
			'posts_per_page' => intval( $limit ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $category ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'blogroll_category',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$blogs         = get_posts( $query_args );
		$blogroll_data = array();

		foreach ( $blogs as $blog ) {
			$blogroll_data[] = array(
				'id'          => $blog->ID,
				'title'       => $blog->post_title,
				'description' => $blog->post_excerpt,
				'site_url'    => get_field( 'site_url', $blog->ID ),
				'rss_url'     => get_field( 'rss_url', $blog->ID ),
				'author'      => get_field( 'author', $blog->ID ),
				'categories'  => wp_list_pluck( get_the_terms( $blog->ID, 'blogroll_category' ), 'name' ),
			);
		}

		wp_cache_set( $cache_key, $blogroll_data, 'feed_to_blogroll', MINUTE_IN_SECONDS * 10 );
		return rest_ensure_response( $blogroll_data );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		register_rest_route(
			'feed-to-blogroll/v1',
			'/blogroll',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_blogroll_rest' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'category' => array(
							'type'        => 'string',
							'description' => __( 'Category slug to filter blogs', 'feed-to-blogroll' ),
							'required'    => false,
						),
						'limit'    => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of blogs to return', 'feed-to-blogroll' ),
							'required'    => false,
							'default'     => 12,
						),
					),
				),
			)
		);
	}

	/**
	 * Get blogroll statistics
	 *
	 * @return array Statistics.
	 */
	public function get_blogroll_stats() {
		$stats = array(
			'total_blogs'      => wp_count_posts( 'blogroll' )->publish,
			'total_categories' => wp_count_terms( 'blogroll_category' ),
			'last_sync'        => get_option( 'feed_to_blogroll_options' )['last_sync'] ?? '',
		);

		return $stats;
	}

	/**
	 * Export OPML for frontend (public access)
	 */
	public function export_opml_frontend() {
		// Check nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'feed_to_blogroll_frontend' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed', 'feed-to-blogroll' ) );
		}

		// Generate OPML content
		$cache_key = 'feed_to_blogroll_opml';
		$opml      = wp_cache_get( $cache_key, 'feed_to_blogroll' );
		if ( false === $opml ) {
			$opml = $this->generate_opml();
			wp_cache_set( $cache_key, $opml, 'feed_to_blogroll', HOUR_IN_SECONDS );
		}

		wp_send_json_success(
			array(
				'opml'     => $opml,
				'filename' => 'blogroll-' . gmdate( 'Y-m-d' ) . '.opml',
			)
		);
	}

	/**
	 * Generate OPML export
	 *
	 * @return string OPML content.
	 */
	public function generate_opml() {
		$blogs = get_posts(
			array(
				'post_type'              => 'blogroll',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'title',
				'order'                  => 'ASC',
			)
		);

		$opml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$opml .= '<opml version="2.0">' . "\n";
		$opml .= '  <head>' . "\n";
		$opml .= '    <title>' . esc_html( get_bloginfo( 'name' ) ) . ' Blogroll</title>' . "\n";
		$opml .= '    <dateCreated>' . gmdate( 'D, d M Y H:i:s' ) . ' GMT</dateCreated>' . "\n";
		$opml .= '  </head>' . "\n";
		$opml .= '  <body>' . "\n";

		foreach ( $blogs as $blog ) {
			$rss_url  = get_field( 'rss_url', $blog->ID );
			$site_url = get_field( 'site_url', $blog->ID );

			if ( $rss_url ) {
				$opml .= '    <outline type="rss" text="' . esc_attr( $blog->post_title ) . '" title="' . esc_attr( $blog->post_title ) . '" xmlUrl="' . esc_attr( $rss_url ) . '" htmlUrl="' . esc_attr( $site_url ) . '" />' . "\n";
			}
		}

		$opml .= '  </body>' . "\n";
		$opml .= '</opml>';

		return $opml;
	}
}
