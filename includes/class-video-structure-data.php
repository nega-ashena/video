<?php
/**
 * ماژول اسکیما VideoObject برای سئوی گوگل.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * خروجی JSON-LD در صفحه تکی هر ویدیو.
 */
class Video_Structure_Data {

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ) );
	}

	/**
	 * انتشار اسکیما در head صفحه تکی ویدیو.
	 */
	public function output() {
		if ( ! is_singular( Post_Type::POST_TYPE ) ) {
			return;
		}

		$post  = get_queried_object();
		$url   = get_post_meta( $post->ID, Video_Meta::KEY, true );
		$thumb = get_the_post_thumbnail_url( $post, 'medium_large' );

		if ( ! $url ) {
			return;
		}

		$data = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'name'         => get_the_title( $post ),
			'description'  => $this->get_description( $post ),
			'uploadDate'   => get_the_date( 'c', $post ),
			'contentUrl'   => esc_url( $url ),
			'embedUrl'     => get_permalink( $post ),
		);

		if ( $thumb ) {
			$data['thumbnailUrl'] = array( esc_url( $thumb ) );
		}

		$duration = get_post_meta( $post->ID, Video_Meta::DURATION_KEY, true );
		if ( $duration ) {
			$data['duration'] = $this->format_duration( $duration );
		}

		$like_count = (int) get_post_meta( $post->ID, Video_Actions::LIKE_COUNT_KEY, true );
		if ( $like_count ) {
			$data['interactionStatistic'] = array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => array( '@type' => 'LikeAction' ),
				'userInteractionCount' => $like_count,
			);
		}

		$comment_count = get_comments_number( $post->ID );
		if ( $comment_count ) {
			$data['commentCount'] = (int) $comment_count;
		}

		/**
		 * فیلتر برای توسعه‌پذیری اسکیما.
		 *
		 * @param array    $data اسکیما.
		 * @param \WP_Post $post پست ویدیو.
		 */
		$data = apply_filters( 'negv_video_schema', $data, $post );

		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * توضیح ویدیو از چکیده؛ در صورت نبود، از توضیح سایت.
	 *
	 * @param \WP_Post $post آبجکت پست.
	 * @return string
	 */
	private function get_description( $post ) {
		$description = wp_strip_all_tags( get_the_excerpt( $post ) );

		if ( $description ) {
			return $description;
		}

		return get_bloginfo( 'description' );
	}

	/**
	 * Convert HH:MM:SS or MM:SS to ISO 8601 duration.
	 *
	 * @param string $duration Time in HH:MM:SS or MM:SS format.
	 * @return string
	 */
	private function format_duration( $duration ) {
		$parts = explode( ':', $duration );

		if ( 3 === count( $parts ) ) {
			return sprintf( 'PT%dH%dM%dS', (int) $parts[0], (int) $parts[1], (int) $parts[2] );
		}

		if ( 2 === count( $parts ) ) {
			return sprintf( 'PT%dM%dS', (int) $parts[0], (int) $parts[1] );
		}

		return '';
	}
}
