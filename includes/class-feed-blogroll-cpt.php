<?php
/**
 * Custom Post Type for Blogroll
 *
 * @package FeedBlogroll
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blogroll Custom Post Type class
 *
 * @since 1.0.0
 */
class Feed_Blogroll_CPT {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'create_post_type' ) );
		add_action( 'init', array( $this, 'create_taxonomies' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );

		// Custom columns
		add_filter( 'manage_blogroll_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_blogroll_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
		add_filter( 'manage_edit-blogroll_sortable_columns', array( $this, 'set_sortable_columns' ) );
	}

	/**
	 * Create the blogroll custom post type
	 */
	public function create_post_type() {
		$labels = array(
			'name'                  => _x( 'Blogroll', 'Post type general name', 'feed-blogroll' ),
			'singular_name'         => _x( 'Blog', 'Post type singular name', 'feed-blogroll' ),
			'menu_name'             => _x( 'Blogroll', 'Admin Menu text', 'feed-blogroll' ),
			'name_admin_bar'        => _x( 'Blog', 'Add New on Toolbar', 'feed-blogroll' ),
			'add_new'               => __( 'Add New', 'feed-blogroll' ),
			'add_new_item'          => __( 'Add New Blog', 'feed-blogroll' ),
			'new_item'              => __( 'New Blog', 'feed-blogroll' ),
			'edit_item'             => __( 'Edit Blog', 'feed-blogroll' ),
			'view_item'             => __( 'View Blog', 'feed-blogroll' ),
			'all_items'             => __( 'All Blogs', 'feed-blogroll' ),
			'search_items'          => __( 'Search Blogs', 'feed-blogroll' ),
			'parent_item_colon'     => __( 'Parent Blogs:', 'feed-blogroll' ),
			'not_found'             => __( 'No blogs found.', 'feed-blogroll' ),
			'not_found_in_trash'    => __( 'No blogs found in Trash.', 'feed-blogroll' ),
			'featured_image'        => _x( 'Blog Featured Image', 'Overrides the "Featured Image" phrase', 'feed-blogroll' ),
			'set_featured_image'    => _x( 'Set featured image', 'Overrides the "Set featured image" phrase', 'feed-blogroll' ),
			'remove_featured_image' => _x( 'Remove featured image', 'Overrides the "Remove featured image" phrase', 'feed-blogroll' ),
			'use_featured_image'    => _x( 'Use as featured image', 'Overrides the "Use as featured image" phrase', 'feed-blogroll' ),
			'archives'              => _x( 'Blog archives', 'The post type archive label', 'feed-blogroll' ),
			'insert_into_item'      => _x( 'Insert into blog', 'Overrides the "Insert into post" phrase', 'feed-blogroll' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this blog', 'Overrides the "Uploaded to this post" phrase', 'feed-blogroll' ),
			'filter_items_list'     => _x( 'Filter blogs list', 'Screen reader text for the filter links', 'feed-blogroll' ),
			'items_list_navigation' => _x( 'Blogs list navigation', 'Screen reader text for the pagination', 'feed-blogroll' ),
			'items_list'            => _x( 'Blogs list', 'Screen reader text for the items list', 'feed-blogroll' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // We'll add it manually
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'blogroll' ),
			'capability_type'    => 'blogroll',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-rss',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'blogroll', $args );
	}

	/**
	 * Create taxonomies for blogroll
	 */
	public function create_taxonomies() {
		// Blogroll Categories
		$category_labels = array(
			'name'              => _x( 'Blog Categories', 'taxonomy general name', 'feed-blogroll' ),
			'singular_name'     => _x( 'Blog Category', 'taxonomy singular name', 'feed-blogroll' ),
			'search_items'      => __( 'Search Categories', 'feed-blogroll' ),
			'all_items'         => __( 'All Categories', 'feed-blogroll' ),
			'parent_item'       => __( 'Parent Category', 'feed-blogroll' ),
			'parent_item_colon' => __( 'Parent Category:', 'feed-blogroll' ),
			'edit_item'         => __( 'Edit Category', 'feed-blogroll' ),
			'update_item'       => __( 'Update Category', 'feed-blogroll' ),
			'add_new_item'      => __( 'Add New Category', 'feed-blogroll' ),
			'new_item_name'     => __( 'New Category Name', 'feed-blogroll' ),
			'menu_name'         => __( 'Categories', 'feed-blogroll' ),
		);

		register_taxonomy(
			'blogroll_category',
			array( 'blogroll' ),
			array(
				'hierarchical'      => true,
				'labels'            => $category_labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'blogroll-category' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Register custom meta fields for blogroll posts
	 */
	public function register_meta_fields() {
		$this->register_custom_meta();
	}

	/**
	 * Register custom meta fields using WordPress native functions
	 */
	private function register_custom_meta() {
		// Register RSS URL meta field
		register_meta(
			'post',
			'rss_url',
			array(
				'object_subtype'    => 'blogroll',
				'type'              => 'string',
				'description'       => __( 'RSS Feed URL for this blog', 'feed-blogroll' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Register site URL meta field
		register_meta(
			'post',
			'site_url',
			array(
				'object_subtype'    => 'blogroll',
				'type'              => 'string',
				'description'       => __( 'Main website URL for this blog', 'feed-blogroll' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Register Feedbin feed ID meta field
		register_meta(
			'post',
			'feed_id',
			array(
				'object_subtype'    => 'blogroll',
				'type'              => 'integer',
				'description'       => __( 'Feedbin internal feed ID', 'feed-blogroll' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Register sync status meta field
		register_meta(
			'post',
			'sync_status',
			array(
				'object_subtype'    => 'blogroll',
				'type'              => 'string',
				'description'       => __( 'Current synchronization status', 'feed-blogroll' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_sync_status' ),
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Register last sync meta field
		register_meta(
			'post',
			'last_sync',
			array(
				'object_subtype'    => 'blogroll',
				'type'              => 'string',
				'description'       => __( 'When this blog was last synchronized', 'feed-blogroll' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Sanitize sync status field
	 *
	 * @param string $value The sync status value
	 * @return string Sanitized sync status
	 */
	public function sanitize_sync_status( $value ) {
		$allowed_statuses = array( 'active', 'inactive', 'error' );
		return in_array( $value, $allowed_statuses, true ) ? $value : 'active';
	}

	/**
	 * Add meta boxes for blogroll posts
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'blogroll_meta',
			__( 'Blogroll Details', 'feed-blogroll' ),
			array( $this, 'meta_box_callback' ),
			'blogroll',
			'normal',
			'high'
		);
	}

	/**
	 * Meta box callback function
	 *
	 * @param WP_Post $post Current post object
	 */
	public function meta_box_callback( $post ) {
		wp_nonce_field( 'blogroll_meta_box', 'blogroll_meta_box_nonce' );

		$rss_url = get_post_meta( $post->ID, 'rss_url', true );
		$site_url = get_post_meta( $post->ID, 'site_url', true );
		$feed_id = get_post_meta( $post->ID, 'feed_id', true );
		$sync_status = get_post_meta( $post->ID, 'sync_status', true );
		$last_sync = get_post_meta( $post->ID, 'last_sync', true );

		if ( empty( $sync_status ) ) {
			$sync_status = 'active';
		}
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="rss_url"><?php esc_html_e( 'RSS Feed URL', 'feed-blogroll' ); ?></label>
				</th>
				<td>
					<input type="url" id="rss_url" name="rss_url" value="<?php echo esc_attr( $rss_url ); ?>" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'Enter the RSS feed URL for this blog', 'feed-blogroll' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="site_url"><?php esc_html_e( 'Website URL', 'feed-blogroll' ); ?></label>
				</th>
				<td>
					<input type="url" id="site_url" name="site_url" value="<?php echo esc_attr( $site_url ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Enter the main website URL for this blog', 'feed-blogroll' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="feed_id"><?php esc_html_e( 'Feedbin Feed ID', 'feed-blogroll' ); ?></label>
				</th>
				<td>
					<input type="number" id="feed_id" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>" class="small-text" readonly />
					<p class="description"><?php esc_html_e( 'Feedbin internal feed ID (auto-populated)', 'feed-blogroll' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sync_status"><?php esc_html_e( 'Sync Status', 'feed-blogroll' ); ?></label>
				</th>
				<td>
					<select id="sync_status" name="sync_status">
						<option value="active" <?php selected( $sync_status, 'active' ); ?>>
							<?php esc_html_e( 'Active', 'feed-blogroll' ); ?>
						</option>
						<option value="inactive" <?php selected( $sync_status, 'inactive' ); ?>>
							<?php esc_html_e( 'Inactive', 'feed-blogroll' ); ?>
						</option>
						<option value="error" <?php selected( $sync_status, 'error' ); ?>>
							<?php esc_html_e( 'Error', 'feed-blogroll' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Current synchronization status', 'feed-blogroll' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="last_sync"><?php esc_html_e( 'Last Sync', 'feed-blogroll' ); ?></label>
				</th>
				<td>
					<input type="text" id="last_sync" name="last_sync" value="<?php echo esc_attr( $last_sync ); ?>" class="regular-text" readonly />
					<p class="description"><?php esc_html_e( 'When this blog was last synchronized', 'feed-blogroll' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save meta box data
	 *
	 * @param int $post_id Post ID
	 */
	public function save_meta_box_data( $post_id ) {
		// Check if nonce is valid
		$nonce = isset( $_POST['blogroll_meta_box_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['blogroll_meta_box_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'blogroll_meta_box' ) ) {
			return;
		}

		// Check if user has permissions to save
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if not an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Save RSS URL
		if ( isset( $_POST['rss_url'] ) ) {
			$rss_url = esc_url_raw( wp_unslash( $_POST['rss_url'] ) );
			if ( ! empty( $rss_url ) ) {
				update_post_meta( $post_id, 'rss_url', $rss_url );
			}
		}

		// Save site URL
		if ( isset( $_POST['site_url'] ) ) {
			$site_url = esc_url_raw( wp_unslash( $_POST['site_url'] ) );
			update_post_meta( $post_id, 'site_url', $site_url );
		}

		// Save sync status
		if ( isset( $_POST['sync_status'] ) ) {
			$sync_status = sanitize_text_field( wp_unslash( $_POST['sync_status'] ) );
			$allowed_statuses = array( 'active', 'inactive', 'error' );
			if ( in_array( $sync_status, $allowed_statuses, true ) ) {
				update_post_meta( $post_id, 'sync_status', $sync_status );
			}
		}
	}

	/**
	 * Set custom columns for the blogroll admin list
	 *
	 * @param array $columns Default columns
	 * @return array Modified columns
	 */
	public function set_custom_columns( $columns ) {
		$new_columns = array();

		// Add checkbox and title
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];

		// Add custom columns
		$new_columns['site_url'] = __( 'Website', 'feed-blogroll' );
		$new_columns['rss_url'] = __( 'RSS Feed', 'feed-blogroll' );
		$new_columns['author'] = __( 'Author', 'feed-blogroll' );
		$new_columns['last_sync'] = __( 'Last Sync', 'feed-blogroll' );
		$new_columns['sync_status'] = __( 'Status', 'feed-blogroll' );

		// Add date
		$new_columns['date'] = $columns['date'];

		return $new_columns;
	}

	/**
	 * Display custom column content
	 *
	 * @param string $column Column name
	 * @param int    $post_id Post ID
	 */
	public function custom_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'site_url':
				$site_url = get_post_meta( $post_id, 'site_url', true );
				if ( ! empty( $site_url ) ) {
					echo '<a href="' . esc_url( $site_url ) . '" target="_blank" rel="noopener noreferrer">';
					echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) );
					echo '</a>';
				} else {
					echo '—';
				}
				break;

			case 'rss_url':
				$rss_url = get_post_meta( $post_id, 'rss_url', true );
				if ( ! empty( $rss_url ) ) {
					echo '<a href="' . esc_url( $rss_url ) . '" target="_blank" rel="noopener noreferrer">';
					echo '<span class="dashicons dashicons-rss" title="' . esc_attr__( 'RSS Feed', 'feed-blogroll' ) . '"></span>';
					echo '</a>';
				} else {
					echo '—';
				}
				break;

			case 'last_sync':
				$last_sync = get_post_meta( $post_id, 'last_sync', true );
				if ( ! empty( $last_sync ) ) {
					echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $last_sync ) ) );
				} else {
					echo '—';
				}
				break;

			case 'sync_status':
				$sync_status = get_post_meta( $post_id, 'sync_status', true );
				if ( empty( $sync_status ) ) {
					$sync_status = 'active';
				}

				$status_labels = array(
					'active'   => __( 'Active', 'feed-blogroll' ),
					'inactive' => __( 'Inactive', 'feed-blogroll' ),
					'error'    => __( 'Error', 'feed-blogroll' ),
				);

				$label = isset( $status_labels[ $sync_status ] ) ? $status_labels[ $sync_status ] : $sync_status;
				echo '<span class="sync-status status-' . esc_attr( $sync_status ) . '">' . esc_html( $label ) . '</span>';
				break;
		}
	}

	/**
	 * Set sortable columns
	 *
	 * @param array $columns Sortable columns
	 * @return array Modified sortable columns
	 */
	public function set_sortable_columns( $columns ) {
		$columns['last_sync'] = 'last_sync';
		$columns['sync_status'] = 'sync_status';
		return $columns;
	}

	/**
	 * Force registration of the custom post type
	 */
	public function force_registration() {
		$this->create_post_type();
		$this->create_taxonomies();
		$this->register_custom_meta();
		flush_rewrite_rules();
	}

	/**
	 * Get blogroll statistics
	 *
	 * @return array Blogroll stats
	 */
	public function get_blogroll_stats() {
		$stats = array(
			'total_blogs'    => 0,
			'active_blogs'   => 0,
			'inactive_blogs' => 0,
			'error_blogs'    => 0,
		);

		$total_blogs = wp_count_posts( 'blogroll' );
		$stats['total_blogs'] = $total_blogs->publish ?? 0;

		// Count by sync status
		$active_blogs = get_posts(
			array(
				'post_type'      => 'blogroll',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => 'sync_status',
						'value' => 'active',
					),
				),
			)
		);
		$stats['active_blogs'] = count( $active_blogs );

		$inactive_blogs = get_posts(
			array(
				'post_type'      => 'blogroll',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => 'sync_status',
						'value' => 'inactive',
					),
				),
			)
		);
		$stats['inactive_blogs'] = count( $inactive_blogs );

		$error_blogs = get_posts(
			array(
				'post_type'      => 'blogroll',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => 'sync_status',
						'value' => 'error',
					),
				),
			)
		);
		$stats['error_blogs'] = count( $error_blogs );

		return $stats;
	}
}
