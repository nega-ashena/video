<?php
/**
 * ماژول دسته‌بندی ویدیوها.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * دسته‌بندی ویدیوها.
 */
class Taxonomy {

	/**
	 * نام تاکسونومی.
	 */
	const TAXONOMY = 'video_category';

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * ثبت تاکسونومی.
	 */
	public function register() {
		register_taxonomy(
			self::TAXONOMY,
			array( Post_Type::POST_TYPE ),
			array(
				'labels'       => array(
					'name'          => __( 'Video Categories', 'negahe-videos' ),
					'singular_name' => __( 'Video Category', 'negahe-videos' ),
					'search_items'  => __( 'Search Categories', 'negahe-videos' ),
					'all_items'     => __( 'All Categories', 'negahe-videos' ),
					'edit_item'     => __( 'Edit Category', 'negahe-videos' ),
					'add_new_item'  => __( 'Add New Category', 'negahe-videos' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'hierarchical' => true,
				'rewrite'      => array(
					'slug'       => 'video-category',
					'with_front' => false,
				),
			)
		);
	}
}
