<?php
/**
 * ماژول پست‌تایپ «ویدیو».
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پست‌تایپ ویدیو.
 */
class Post_Type {

	/**
	 * نام پست‌تایپ.
	 */
	const POST_TYPE = 'video';

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * ثبت پست‌تایپ.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Videos', 'negahe-videos' ),
					'singular_name'      => __( 'Video', 'negahe-videos' ),
					'add_new'            => __( 'Add New Video', 'negahe-videos' ),
					'add_new_item'       => __( 'Add New Video', 'negahe-videos' ),
					'edit_item'          => __( 'Edit Video', 'negahe-videos' ),
					'new_item'           => __( 'New Video', 'negahe-videos' ),
					'view_item'          => __( 'View Video', 'negahe-videos' ),
					'search_items'       => __( 'Search Videos', 'negahe-videos' ),
					'not_found'          => __( 'No videos found', 'negahe-videos' ),
					'not_found_in_trash' => __( 'No videos found in trash', 'negahe-videos' ),
					'menu_name'          => __( 'Videos', 'negahe-videos' ),
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_position' => 5,
				'menu_icon'     => 'dashicons-video-alt2',
				'hierarchical'  => false,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'comments' ),
				'has_archive'   => false,
				'rewrite'       => array(
					'slug'       => 'video',
					'with_front' => false,
				),
			)
		);
	}
}
