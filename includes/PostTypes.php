<?php
/**
 * Register custom post types and taxonomies.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * PostTypes class.
 */
class PostTypes {

	/**
	 * Register custom post types.
	 */
	public function register_post_types() {
		// Register Campaign post type
		$campaign_labels = array(
			'name'                  => _x( 'Campaigns', 'Post Type General Name', 'fundraiser-pro' ),
			'singular_name'         => _x( 'Campaign', 'Post Type Singular Name', 'fundraiser-pro' ),
			'menu_name'             => __( 'Campaigns', 'fundraiser-pro' ),
			'name_admin_bar'        => __( 'Campaign', 'fundraiser-pro' ),
			'archives'              => __( 'Campaign Archives', 'fundraiser-pro' ),
			'attributes'            => __( 'Campaign Attributes', 'fundraiser-pro' ),
			'parent_item_colon'     => __( 'Parent Campaign:', 'fundraiser-pro' ),
			'all_items'             => __( 'All Campaigns', 'fundraiser-pro' ),
			'add_new_item'          => __( 'Add New Campaign', 'fundraiser-pro' ),
			'add_new'               => __( 'Add New', 'fundraiser-pro' ),
			'new_item'              => __( 'New Campaign', 'fundraiser-pro' ),
			'edit_item'             => __( 'Edit Campaign', 'fundraiser-pro' ),
			'update_item'           => __( 'Update Campaign', 'fundraiser-pro' ),
			'view_item'             => __( 'View Campaign', 'fundraiser-pro' ),
			'view_items'            => __( 'View Campaigns', 'fundraiser-pro' ),
			'search_items'          => __( 'Search Campaign', 'fundraiser-pro' ),
			'not_found'             => __( 'Not found', 'fundraiser-pro' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'fundraiser-pro' ),
			'featured_image'        => __( 'Campaign Image', 'fundraiser-pro' ),
			'set_featured_image'    => __( 'Set campaign image', 'fundraiser-pro' ),
			'remove_featured_image' => __( 'Remove campaign image', 'fundraiser-pro' ),
			'use_featured_image'    => __( 'Use as campaign image', 'fundraiser-pro' ),
			'insert_into_item'      => __( 'Insert into campaign', 'fundraiser-pro' ),
			'uploaded_to_this_item' => __( 'Uploaded to this campaign', 'fundraiser-pro' ),
			'items_list'            => __( 'Campaigns list', 'fundraiser-pro' ),
			'items_list_navigation' => __( 'Campaigns list navigation', 'fundraiser-pro' ),
			'filter_items_list'     => __( 'Filter campaigns list', 'fundraiser-pro' ),
		);

		$campaign_args = array(
			'label'               => __( 'Campaign', 'fundraiser-pro' ),
			'description'         => __( 'Fundraising campaigns', 'fundraiser-pro' ),
			'labels'              => $campaign_labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions', 'custom-fields' ),
			'taxonomies'          => array( 'campaign_category', 'campaign_tag' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'fundraiser-pro',
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-heart',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => 'campaigns',
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => 'campaigns',
			'rewrite'             => array( 'slug' => 'campaign' ),
		);

		register_post_type( 'fundraiser_campaign', $campaign_args );

		// Register Raffle post type
		$raffle_labels = array(
			'name'                  => _x( 'Raffles', 'Post Type General Name', 'fundraiser-pro' ),
			'singular_name'         => _x( 'Raffle', 'Post Type Singular Name', 'fundraiser-pro' ),
			'menu_name'             => __( 'Raffles', 'fundraiser-pro' ),
			'name_admin_bar'        => __( 'Raffle', 'fundraiser-pro' ),
			'archives'              => __( 'Raffle Archives', 'fundraiser-pro' ),
			'attributes'            => __( 'Raffle Attributes', 'fundraiser-pro' ),
			'all_items'             => __( 'All Raffles', 'fundraiser-pro' ),
			'add_new_item'          => __( 'Add New Raffle', 'fundraiser-pro' ),
			'add_new'               => __( 'Add New', 'fundraiser-pro' ),
			'new_item'              => __( 'New Raffle', 'fundraiser-pro' ),
			'edit_item'             => __( 'Edit Raffle', 'fundraiser-pro' ),
			'update_item'           => __( 'Update Raffle', 'fundraiser-pro' ),
			'view_item'             => __( 'View Raffle', 'fundraiser-pro' ),
			'view_items'            => __( 'View Raffles', 'fundraiser-pro' ),
			'search_items'          => __( 'Search Raffle', 'fundraiser-pro' ),
		);

		$raffle_args = array(
			'label'               => __( 'Raffle', 'fundraiser-pro' ),
			'description'         => __( 'Fundraising raffles', 'fundraiser-pro' ),
			'labels'              => $raffle_labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'author', 'revisions' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'fundraiser-pro',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => 'raffles',
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => 'raffles',
			'rewrite'             => array( 'slug' => 'raffle' ),
		);

		register_post_type( 'fundraiser_raffle', $raffle_args );

		// Register Cash Transaction post type
		$cash_labels = array(
			'name'                  => _x( 'Cash Transactions', 'Post Type General Name', 'fundraiser-pro' ),
			'singular_name'         => _x( 'Cash Transaction', 'Post Type Singular Name', 'fundraiser-pro' ),
			'menu_name'             => __( 'Cash Transactions', 'fundraiser-pro' ),
			'all_items'             => __( 'All Transactions', 'fundraiser-pro' ),
			'add_new_item'          => __( 'Add New Transaction', 'fundraiser-pro' ),
			'add_new'               => __( 'Add New', 'fundraiser-pro' ),
			'new_item'              => __( 'New Transaction', 'fundraiser-pro' ),
			'edit_item'             => __( 'Edit Transaction', 'fundraiser-pro' ),
			'view_item'             => __( 'View Transaction', 'fundraiser-pro' ),
		);

		$cash_args = array(
			'label'               => __( 'Cash Transaction', 'fundraiser-pro' ),
			'description'         => __( 'Cash payment transactions', 'fundraiser-pro' ),
			'labels'              => $cash_labels,
			'supports'            => array( 'title', 'author', 'revisions' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'fundraiser-pro',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'show_in_rest'        => false,
		);

		register_post_type( 'fundraiser_cash', $cash_args );

		// Register custom post statuses for campaigns
		register_post_status( 'fp_active', array(
			'label'                     => _x( 'Active', 'post status', 'fundraiser-pro' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: Number of active campaigns */
			'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'fundraiser-pro' ),
		) );

		register_post_status( 'fp_paused', array(
			'label'                     => _x( 'Paused', 'post status', 'fundraiser-pro' ),
			'public'                    => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: Number of paused campaigns */
			'label_count'               => _n_noop( 'Paused <span class="count">(%s)</span>', 'Paused <span class="count">(%s)</span>', 'fundraiser-pro' ),
		) );

		register_post_status( 'fp_completed', array(
			'label'                     => _x( 'Completed', 'post status', 'fundraiser-pro' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: Number of completed campaigns */
			'label_count'               => _n_noop( 'Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>', 'fundraiser-pro' ),
		) );
	}

	/**
	 * Register custom taxonomies.
	 */
	public function register_taxonomies() {
		// Register Campaign Category taxonomy
		$category_labels = array(
			'name'              => _x( 'Campaign Categories', 'taxonomy general name', 'fundraiser-pro' ),
			'singular_name'     => _x( 'Campaign Category', 'taxonomy singular name', 'fundraiser-pro' ),
			'search_items'      => __( 'Search Categories', 'fundraiser-pro' ),
			'all_items'         => __( 'All Categories', 'fundraiser-pro' ),
			'parent_item'       => __( 'Parent Category', 'fundraiser-pro' ),
			'parent_item_colon' => __( 'Parent Category:', 'fundraiser-pro' ),
			'edit_item'         => __( 'Edit Category', 'fundraiser-pro' ),
			'update_item'       => __( 'Update Category', 'fundraiser-pro' ),
			'add_new_item'      => __( 'Add New Category', 'fundraiser-pro' ),
			'new_item_name'     => __( 'New Category Name', 'fundraiser-pro' ),
			'menu_name'         => __( 'Categories', 'fundraiser-pro' ),
		);

		register_taxonomy( 'campaign_category', array( 'fundraiser_campaign' ), array(
			'hierarchical'      => true,
			'labels'            => $category_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'campaign-category' ),
			'show_in_rest'      => true,
		) );

		// Register Campaign Tag taxonomy
		$tag_labels = array(
			'name'              => _x( 'Campaign Tags', 'taxonomy general name', 'fundraiser-pro' ),
			'singular_name'     => _x( 'Campaign Tag', 'taxonomy singular name', 'fundraiser-pro' ),
			'search_items'      => __( 'Search Tags', 'fundraiser-pro' ),
			'all_items'         => __( 'All Tags', 'fundraiser-pro' ),
			'edit_item'         => __( 'Edit Tag', 'fundraiser-pro' ),
			'update_item'       => __( 'Update Tag', 'fundraiser-pro' ),
			'add_new_item'      => __( 'Add New Tag', 'fundraiser-pro' ),
			'new_item_name'     => __( 'New Tag Name', 'fundraiser-pro' ),
			'menu_name'         => __( 'Tags', 'fundraiser-pro' ),
		);

		register_taxonomy( 'campaign_tag', array( 'fundraiser_campaign' ), array(
			'hierarchical'      => false,
			'labels'            => $tag_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'campaign-tag' ),
			'show_in_rest'      => true,
		) );
	}
}
