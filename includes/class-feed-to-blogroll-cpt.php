<?php
/**
 * Custom Post Type for Blogroll
 *
 * @package FeedToBlogroll
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
class Feed_To_Blogroll_CPT {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook to init for proper WordPress integration
		add_action( 'init', array( $this, 'create_post_type' ) );
		add_action( 'init', array( $this, 'create_taxonomies' ) );
		add_action( 'acf/init', array( $this, 'create_acf_fields' ) );
		add_filter( 'manage_blogroll_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_blogroll_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
		add_filter( 'manage_edit-blogroll_sortable_columns', array( $this, 'set_sortable_columns' ) );
		
		// Add custom capabilities
		add_action( 'admin_init', array( $this, 'add_custom_capabilities' ) );
		
		// Add meta box for additional information
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );
	}

	/**
	 * Create the Custom Post Type
	 */
	public function create_post_type() {
		$labels = array(
			'name'                  => _x( 'Blogroll', 'Post type general name', 'feed-to-blogroll' ),
			'singular_name'         => _x( 'Blog', 'Post type singular name', 'feed-to-blogroll' ),
			'menu_name'             => _x( 'Blogroll', 'Admin Menu text', 'feed-to-blogroll' ),
			'name_admin_bar'        => _x( 'Blog', 'Add New on Toolbar', 'feed-to-blogroll' ),
			'add_new'               => __( 'Add Blog', 'feed-to-blogroll' ),
			'add_new_item'          => __( 'Add New Blog', 'feed-to-blogroll' ),
			'new_item'              => __( 'New Blog', 'feed-to-blogroll' ),
			'edit_item'             => __( 'Edit Blog', 'feed-to-blogroll' ),
			'view_item'             => __( 'View Blog', 'feed-to-blogroll' ),
			'all_items'             => __( 'All Blogs', 'feed-to-blogroll' ),
			'search_items'          => __( 'Search Blogs', 'feed-to-blogroll' ),
			'parent_item_colon'     => __( 'Parent Blogs:', 'feed-to-blogroll' ),
			'not_found'             => __( 'No blogs found.', 'feed-to-blogroll' ),
			'not_found_in_trash'    => __( 'No blogs found in Trash.', 'feed-to-blogroll' ),
			'featured_image'        => _x( 'Blog Image', 'Overrides the "Featured Image" phrase', 'feed-to-blogroll' ),
			'set_featured_image'    => _x( 'Set blog image', 'Overrides the "Set featured image" phrase', 'feed-to-blogroll' ),
			'remove_featured_image' => _x( 'Remove blog image', 'Overrides the "Remove featured image" phrase', 'feed-to-blogroll' ),
			'use_featured_image'    => _x( 'Use as blog image', 'Overrides the "Use as featured image" phrase', 'feed-to-blogroll' ),
			'archives'              => _x( 'Blog archives', 'The post type archive label', 'feed-to-blogroll' ),
			'insert_into_item'      => _x( 'Insert into blog', 'Overrides the "Insert into post" phrase', 'feed-to-blogroll' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this blog', 'Overrides the "Uploaded to this post" phrase', 'feed-to-blogroll' ),
			'filter_items_list'     => __( 'Filter blogs list', 'Screen reader text for the filter links', 'feed-to-blogroll' ),
			'items_list_navigation' => __( 'Blogs list navigation', 'Screen reader text for the pagination', 'feed-to-blogroll' ),
			'items_list'            => __( 'Blogs list', 'Screen reader text for the items list', 'feed-to-blogroll' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // Don't show in main menu, we have our own
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'blogroll' ),
			'capability_type'    => array( 'blogroll', 'blogrolls' ),
			'map_meta_cap'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-rss',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'show_in_rest'       => true,
			'rest_base'          => 'blogroll',
			'show_in_graphql'    => true,
		);

		register_post_type( 'blogroll', $args );
	}

	/**
	 * Create taxonomies for the blogroll
	 */
	public function create_taxonomies() {
		// Category taxonomy
		$category_labels = array(
			'name'              => _x( 'Blog Categories', 'taxonomy general name', 'feed-to-blogroll' ),
			'singular_name'     => _x( 'Blog Category', 'taxonomy singular name', 'feed-to-blogroll' ),
			'search_items'      => __( 'Search Categories', 'feed-to-blogroll' ),
			'all_items'         => __( 'All Categories', 'feed-to-blogroll' ),
			'parent_item'       => __( 'Parent Category', 'feed-to-blogroll' ),
			'parent_item_colon' => __( 'Parent Category:', 'feed-to-blogroll' ),
			'edit_item'         => __( 'Edit Category', 'feed-to-blogroll' ),
			'update_item'       => __( 'Update Category', 'feed-to-blogroll' ),
			'add_new_item'      => __( 'Add New Category', 'feed-to-blogroll' ),
			'new_item_name'     => __( 'New Category Name', 'feed-to-blogroll' ),
			'menu_name'         => __( 'Categories', 'feed-to-blogroll' ),
		);

		$category_args = array(
			'hierarchical'      => true,
			'labels'            => $category_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'blogroll-category' ),
			'show_in_rest'      => true,
			'rest_base'         => 'blogroll-category',
			'show_in_graphql'   => true,
		);

		register_taxonomy( 'blogroll_category', array( 'blogroll' ), $category_args );

		// Tag taxonomy for additional organization
		$tag_labels = array(
			'name'              => _x( 'Blog Tags', 'taxonomy general name', 'feed-to-blogroll' ),
			'singular_name'     => _x( 'Blog Tag', 'taxonomy singular name', 'feed-to-blogroll' ),
			'search_items'      => __( 'Search Tags', 'feed-to-blogroll' ),
			'all_items'         => __( 'All Tags', 'feed-to-blogroll' ),
			'edit_item'         => __( 'Edit Tag', 'feed-to-blogroll' ),
			'update_item'       => __( 'Update Tag', 'feed-to-blogroll' ),
			'add_new_item'      => __( 'Add New Tag', 'feed-to-blogroll' ),
			'new_item_name'     => __( 'New Tag Name', 'feed-to-blogroll' ),
			'menu_name'         => __( 'Tags', 'feed-to-blogroll' ),
		);

		$tag_args = array(
			'hierarchical'      => false,
			'labels'            => $tag_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'blogroll-tag' ),
			'show_in_rest'      => true,
			'rest_base'         => 'blogroll-tag',
			'show_in_graphql'   => true,
		);

		register_taxonomy( 'blogroll_tag', array( 'blogroll' ), $tag_args );
	}

	/**
	 * Create ACF fields for the blogroll
	 */
	public function create_acf_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_blogroll_fields',
				'title'                 => __( 'Blogroll Information', 'feed-to-blogroll' ),
				'fields'                => array(
					array(
						'key'               => 'field_rss_url',
						'label'             => __( 'RSS Feed URL', 'feed-to-blogroll' ),
						'name'              => 'rss_url',
						'type'              => 'url',
						'instructions'      => __( 'Enter the RSS feed URL for this blog', 'feed-to-blogroll' ),
						'required'          => 1,
						'placeholder'       => 'https://example.com/feed/',
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
					),
					array(
						'key'               => 'field_site_url',
						'label'             => __( 'Website URL', 'feed-to-blogroll' ),
						'name'              => 'site_url',
						'type'              => 'url',
						'instructions'      => __( 'Enter the main website URL for this blog', 'feed-to-blogroll' ),
						'required'          => 0,
						'placeholder'       => 'https://example.com/',
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
					),
					array(
						'key'               => 'field_feed_id',
						'label'             => __( 'Feedbin Feed ID', 'feed-to-blogroll' ),
						'name'              => 'feed_id',
						'type'              => 'number',
						'instructions'      => __( 'Feedbin internal feed ID (auto-populated)', 'feed-to-blogroll' ),
						'required'          => 0,
						'readonly'          => 1,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
					),
					array(
						'key'               => 'field_last_sync',
						'label'             => __( 'Last Sync', 'feed-to-blogroll' ),
						'name'              => 'last_sync',
						'type'              => 'date_time_picker',
						'instructions'      => __( 'When this blog was last synchronized', 'feed-to-blogroll' ),
						'required'          => 0,
						'readonly'          => 1,
						'display_format'    => 'F j, Y g:i a',
						'return_format'     => 'Y-m-d H:i:s',
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
					),
					array(
						'key'               => 'field_sync_status',
						'label'             => __( 'Sync Status', 'feed-to-blogroll' ),
						'name'              => 'sync_status',
						'type'              => 'select',
						'instructions'      => __( 'Current synchronization status', 'feed-to-blogroll' ),
						'required'          => 0,
						'choices'           => array(
							'active'   => __( 'Active', 'feed-to-blogroll' ),
							'inactive' => __( 'Inactive', 'feed-to-blogroll' ),
							'error'    => __( 'Error', 'feed-to-blogroll' ),
						),
						'default_value'     => 'active',
						'allow_null'        => 0,
						'multiple'          => 0,
						'ui'                => 0,
						'return_format'     => 'value',
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'blogroll',
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen'        => '',
				'active'                => true,
				'description'           => '',
				'show_in_rest'          => 1,
			)
		);
	}

	/**
	 * Set custom columns for the blogroll admin list
	 *
	 * @param array $columns Default columns
	 * @return array Modified columns
	 */
	public function set_custom_columns( $columns ) {
		$new_columns = array();
		
		// Insert custom columns after title
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['rss_url'] = __( 'RSS Feed', 'feed-to-blogroll' );
				$new_columns['site_url'] = __( 'Website', 'feed-to-blogroll' );
				$new_columns['sync_status'] = __( 'Sync Status', 'feed-to-blogroll' );
				$new_columns['last_sync'] = __( 'Last Sync', 'feed-to-blogroll' );
			}
		}

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
			case 'rss_url':
				$rss_url = get_field( 'rss_url', $post_id );
				if ( $rss_url ) {
					printf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $rss_url ),
						esc_html__( 'View RSS', 'feed-to-blogroll' )
					);
				} else {
					echo '<span class="no-rss">' . esc_html__( 'No RSS URL', 'feed-to-blogroll' ) . '</span>';
				}
				break;

			case 'site_url':
				$site_url = get_field( 'site_url', $post_id );
				if ( $site_url ) {
					printf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $site_url ),
						esc_html__( 'Visit Site', 'feed-to-blogroll' )
					);
				} else {
					echo '<span class="no-site">' . esc_html__( 'No website', 'feed-to-blogroll' ) . '</span>';
				}
				break;

			case 'sync_status':
				$sync_status = get_field( 'sync_status', $post_id ) ?: 'active';
				$status_labels = array(
					'active'   => __( 'Active', 'feed-to-blogroll' ),
					'inactive' => __( 'Inactive', 'feed-to-blogroll' ),
					'error'    => __( 'Error', 'feed-to-blogroll' ),
				);
				$status_class = 'status-' . $sync_status;
				printf(
					'<span class="sync-status %s">%s</span>',
					esc_attr( $status_class ),
					esc_html( $status_labels[ $sync_status ] ?? $sync_status )
				);
				break;

			case 'last_sync':
				$last_sync = get_field( 'last_sync', $post_id );
				if ( $last_sync ) {
					echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $last_sync ) ) );
				} else {
					echo '<span class="never-synced">' . esc_html__( 'Never', 'feed-to-blogroll' ) . '</span>';
				}
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
		$columns['sync_status'] = 'sync_status';
		$columns['last_sync'] = 'last_sync';
		return $columns;
	}

	/**
	 * Add custom capabilities for blogroll management
	 */
	public function add_custom_capabilities() {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'edit_blogrolls' );
			$admin_role->add_cap( 'edit_others_blogrolls' );
			$admin_role->add_cap( 'publish_blogrolls' );
			$admin_role->add_cap( 'read_private_blogrolls' );
			$admin_role->add_cap( 'delete_blogrolls' );
			$admin_role->add_cap( 'delete_private_blogrolls' );
			$admin_role->add_cap( 'delete_published_blogrolls' );
			$admin_role->add_cap( 'delete_others_blogrolls' );
			$admin_role->add_cap( 'edit_private_blogrolls' );
			$admin_role->add_cap( 'edit_published_blogrolls' );
		}

		$editor_role = get_role( 'editor' );
		if ( $editor_role ) {
			$editor_role->add_cap( 'edit_blogrolls' );
			$editor_role->add_cap( 'edit_others_blogrolls' );
			$editor_role->add_cap( 'publish_blogrolls' );
			$editor_role->add_cap( 'read_private_blogrolls' );
			$editor_role->add_cap( 'delete_blogrolls' );
			$editor_role->add_cap( 'delete_private_blogrolls' );
			$editor_role->add_cap( 'delete_published_blogrolls' );
			$editor_role->add_cap( 'delete_others_blogrolls' );
			$editor_role->add_cap( 'edit_private_blogrolls' );
			$editor_role->add_cap( 'edit_published_blogrolls' );
		}
	}

	/**
	 * Add meta boxes for additional information
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'blogroll_meta',
			__( 'Blogroll Details', 'feed-to-blogroll' ),
			array( $this, 'meta_box_callback' ),
			'blogroll',
			'side',
			'high'
		);
	}

	/**
	 * Meta box callback function
	 *
	 * @param WP_Post $post Post object
	 */
	public function meta_box_callback( $post ) {
		// Add nonce for security
		wp_nonce_field( 'blogroll_meta_box', 'blogroll_meta_box_nonce' );

		$feed_id = get_field( 'feed_id', $post->ID );
		$last_sync = get_field( 'last_sync', $post->ID );
		$sync_status = get_field( 'sync_status', $post->ID ) ?: 'active';

		?>
		<div class="blogroll-meta-box">
			<p>
				<label for="feed_id"><?php esc_html_e( 'Feedbin Feed ID:', 'feed-to-blogroll' ); ?></label>
				<input type="number" id="feed_id" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>" readonly />
			</p>
			
			<p>
				<label for="last_sync"><?php esc_html_e( 'Last Sync:', 'feed-to-blogroll' ); ?></label>
				<input type="text" id="last_sync" name="last_sync" value="<?php echo esc_attr( $last_sync ); ?>" readonly />
			</p>
			
			<p>
				<label for="sync_status"><?php esc_html_e( 'Sync Status:', 'feed-to-blogroll' ); ?></label>
				<select id="sync_status" name="sync_status">
					<option value="active" <?php selected( $sync_status, 'active' ); ?>><?php esc_html_e( 'Active', 'feed-to-blogroll' ); ?></option>
					<option value="inactive" <?php selected( $sync_status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'feed-to-blogroll' ); ?></option>
					<option value="error" <?php selected( $sync_status, 'error' ); ?>><?php esc_html_e( 'Error', 'feed-to-blogroll' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Save meta box data
	 *
	 * @param int $post_id Post ID
	 */
	public function save_meta_box_data( $post_id ) {
		// Check if nonce is valid
		if ( ! isset( $_POST['blogroll_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['blogroll_meta_box_nonce'], 'blogroll_meta_box' ) ) {
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

		// Save sync status if changed
		if ( isset( $_POST['sync_status'] ) ) {
			$sync_status = sanitize_text_field( $_POST['sync_status'] );
			$allowed_statuses = array( 'active', 'inactive', 'error' );
			if ( in_array( $sync_status, $allowed_statuses, true ) ) {
				update_field( 'sync_status', $sync_status, $post_id );
			}
		}
	}

	/**
	 * Force registration of post type (for activation)
	 */
	public function force_registration() {
		$this->create_post_type();
		$this->create_taxonomies();
		flush_rewrite_rules();
	}

	/**
	 * Get blogroll statistics
	 *
	 * @return array Statistics
	 */
	public function get_blogroll_stats() {
		$stats = array(
			'total_blogs'      => wp_count_posts( 'blogroll' )->publish,
			'total_categories' => wp_count_terms( 'blogroll_category' ),
			'total_tags'       => wp_count_terms( 'blogroll_tag' ),
			'draft_blogs'      => wp_count_posts( 'blogroll' )->draft,
			'last_sync'        => get_option( 'feed_to_blogroll_options' )['last_sync'] ?? '',
		);

		return $stats;
	}
}
