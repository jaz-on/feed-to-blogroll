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
			'filter_items_list'     => _x( 'Filter blogs list', 'Screen reader text for the filter links', 'feed-to-blogroll' ),
			'items_list_navigation' => _x( 'Blogs list navigation', 'Screen reader text for the pagination', 'feed-to-blogroll' ),
			'items_list'            => _x( 'Blogs list', 'Screen reader text for the items list', 'feed-to-blogroll' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // Don't show in main menu, we have our own
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'blogroll' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-rss',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'       => true,
			'rest_base'          => 'blogroll',
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
	 * Create ACF fields for the blogroll
	 */
	public function create_acf_fields() {
		if ( function_exists( 'acf_add_local_field_group' ) ) {
			acf_add_local_field_group(
				array(
					'key'                   => 'group_blogroll_fields',
					'title'                  => __( 'Blog Information', 'feed-to-blogroll' ),
					'fields'                 => array(
						array(
							'key'               => 'field_blogroll_site_url',
							'label'              => __( 'Site URL', 'feed-to-blogroll' ),
							'name'               => 'site_url',
							'type'               => 'url',
							'instructions'       => __( 'The main URL of the blog', 'feed-to-blogroll' ),
							'required'           => 1,
							'default_value'      => '',
							'placeholder'        => 'https://example.com',
						),
						array(
							'key'               => 'field_blogroll_rss_url',
							'label'              => __( 'RSS Feed URL', 'feed-to-blogroll' ),
							'name'               => 'rss_url',
							'type'               => 'url',
							'instructions'       => __( 'The RSS feed URL of the blog', 'feed-to-blogroll' ),
							'required'           => 1,
							'default_value'      => '',
							'placeholder'        => 'https://example.com/feed/',
						),
						array(
							'key'               => 'field_blogroll_author',
							'label'              => __( 'Author', 'feed-to-blogroll' ),
							'name'               => 'author',
							'type'               => 'text',
							'instructions'       => __( 'The main author of the blog', 'feed-to-blogroll' ),
							'required'           => 0,
							'default_value'      => '',
							'placeholder'        => __( 'Author name', 'feed-to-blogroll' ),
						),
						array(
							'key'               => 'field_blogroll_feedbin_id',
							'label'              => __( 'Feedbin ID', 'feed-to-blogroll' ),
							'name'               => 'feedbin_id',
							'type'               => 'number',
							'instructions'       => __( 'The unique identifier in Feedbin', 'feed-to-blogroll' ),
							'required'           => 0,
							'readonly'           => 1,
						),
						array(
							'key'               => 'field_blogroll_last_sync',
							'label'              => __( 'Last Synchronization', 'feed-to-blogroll' ),
							'name'               => 'last_sync',
							'type'               => 'date_time_picker',
							'instructions'       => __( 'Date and time of the last synchronization', 'feed-to-blogroll' ),
							'required'           => 0,
							'readonly'           => 1,
							'display_format'     => 'd/m/Y H:i',
							'return_format'      => 'Y-m-d H:i:s',
						),
						array(
							'key'               => 'field_blogroll_sync_status',
							'label'              => __( 'Sync Status', 'feed-to-blogroll' ),
							'name'               => 'sync_status',
							'type'               => 'select',
							'instructions'       => __( 'Synchronization status with Feedbin', 'feed-to-blogroll' ),
							'required'           => 0,
							'choices'            => array(
								'active'    => __( 'Active', 'feed-to-blogroll' ),
								'inactive'  => __( 'Inactive', 'feed-to-blogroll' ),
								'error'     => __( 'Error', 'feed-to-blogroll' ),
							),
							'default_value'      => 'active',
							'return_format'      => 'value',
						),
					),
					'location'                => array(
						array(
							array(
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'blogroll',
							),
						),
					),
					'menu_order'               => 0,
					'position'                 => 'normal',
					'style'                    => 'default',
					'label_placement'          => 'top',
					'instruction_placement'    => 'label',
					'hide_on_screen'           => '',
					'active'                   => true,
					'description'              => '',
					'show_in_rest'             => 0,
				)
			);
		}
	}

	/**
	 * Set custom columns for the blogroll admin list
	 *
	 * @param array $columns Admin columns.
	 * @return array Modified columns.
	 */
	public function set_custom_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( 'title' === $key ) {
				$new_columns['site_url']     = __( 'Site URL', 'feed-to-blogroll' );
				$new_columns['rss_url']      = __( 'RSS Feed', 'feed-to-blogroll' );
				$new_columns['author']       = __( 'Author', 'feed-to-blogroll' );
				$new_columns['last_sync']    = __( 'Last Sync', 'feed-to-blogroll' );
				$new_columns['sync_status']  = __( 'Status', 'feed-to-blogroll' );
			}
		}

		return $new_columns;
	}

	/**
	 * Display custom column content
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function custom_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'site_url':
				$site_url = get_field( 'site_url', $post_id );
				if ( $site_url ) {
					printf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $site_url ),
						esc_html( $site_url )
					);
				}
				break;

			case 'rss_url':
				$rss_url = get_field( 'rss_url', $post_id );
				if ( $rss_url ) {
					printf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $rss_url ),
						esc_html( $rss_url )
					);
				}
				break;

			case 'author':
				$author = get_field( 'author', $post_id );
				if ( $author ) {
					echo esc_html( $author );
				}
				break;

			case 'last_sync':
				$last_sync = get_field( 'last_sync', $post_id );
				if ( $last_sync ) {
					echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $last_sync ) ) );
				} else {
					echo esc_html__( 'Never', 'feed-to-blogroll' );
				}
				break;

			case 'sync_status':
				$sync_status = get_field( 'sync_status', $post_id );
				$status_labels = array(
					'active'   => __( 'Active', 'feed-to-blogroll' ),
					'inactive' => __( 'Inactive', 'feed-to-blogroll' ),
					'error'    => __( 'Error', 'feed-to-blogroll' ),
				);

				if ( isset( $status_labels[ $sync_status ] ) ) {
					$status_class = 'active' === $sync_status ? 'status-active' : 'status-inactive';
					printf(
						'<span class="sync-status %s">%s</span>',
						esc_attr( $status_class ),
						esc_html( $status_labels[ $sync_status ] )
					);
				}
				break;
		}
	}

	/**
	 * Set sortable columns
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function set_sortable_columns( $columns ) {
		$columns['last_sync']   = 'last_sync';
		$columns['sync_status'] = 'sync_status';
		$columns['author']      = 'author';

		return $columns;
	}

	/**
	 * Force re-registration of post type
	 * This method can be called to ensure the CPT is properly registered
	 */
	public function force_registration() {
		// Unregister if exists
		if ( post_type_exists( 'blogroll' ) ) {
			unregister_post_type( 'blogroll' );
		}

		// Re-register
		$this->create_post_type();
		$this->create_taxonomies();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Clear cache
		wp_cache_flush();
	}
}
