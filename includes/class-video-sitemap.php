<?php
/**
 * ماژول ویدیو سایت مپ برای کشف همه ویدیوها توسط گوگل.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تولید /video-sitemap.xml با تمام ویدیوها.
 */
class Video_Sitemap {

	/**
	 * نام کوئری‌ور.
	 */
	const QUERY_VAR = 'negv_video_sitemap';

	/**
	 * کلید کش سایت‌مپ.
	 */
	const CACHE_KEY = 'negv_video_sitemap_xml';

	/**
	 * مدت کش سایت‌مپ (۶ ساعت).
	 */
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ), 20 );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'clear_cache' ) );
		add_action( 'deleted_post', array( $this, 'clear_cache' ) );
		add_action( 'trashed_post', array( $this, 'clear_cache' ) );
		add_action( 'transition_post_status', array( $this, 'clear_cache_on_transition' ), 10, 3 );
	}

	/**
	 * تعریف رایت‌رول سایت مپ.
	 */
	public function add_rewrite_rule() {
		add_rewrite_tag( self::QUERY_VAR, '([0-9]+)' );
		add_rewrite_rule( '^video-sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * بررسی درخواست سایت مپ و خروجی XML.
	 */
	public function maybe_render() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			header( 'Content-Type: application/xml; charset=' . get_bloginfo( 'charset' ) );
			header( 'Cache-Control: public, max-age=3600' );
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		header( 'Content-Type: application/xml; charset=' . get_bloginfo( 'charset' ) );
		header( 'Cache-Control: public, max-age=3600' );

		ob_start();
		echo '<?xml version="1.0" encoding="' . esc_attr( get_bloginfo( 'charset' ) ) . '"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		$this->output_videos();

		echo '</urlset>';
		$xml = ob_get_clean();

		set_transient( self::CACHE_KEY, $xml, self::CACHE_TTL );

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * پاکسازی کش سایت‌مپ.
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * پاکسازی کش هنگام تغییر وضعیت پست ویدیو.
	 *
	 * @param string   $new_status وضعیت جدید.
	 * @param string   $old_status وضعیت قبلی.
	 * @param \WP_Post $post آبجکت پست.
	 */
	public function clear_cache_on_transition( $new_status, $old_status, $post ) {
		if ( ! $post || Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			$this->clear_cache();
		}
	}

	/**
	 * خروجی ویدیوها در قالب سایت مپ.
	 */
	private function output_videos() {
		$videos = new \WP_Query(
			array(
				'post_type'      => Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		while ( $videos->have_posts() ) {
			$videos->the_post();

			$post_id = get_the_ID();
			$url     = get_post_meta( $post_id, Video_Meta::KEY, true );
			$thumb   = get_the_post_thumbnail_url( $post_id, 'medium_large' );

			if ( ! $url ) {
				continue;
			}

			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( get_permalink() ) . "</loc>\n";
			echo "\t\t<video:video>\n";
			if ( $thumb ) {
				echo "\t\t\t<video:thumbnail_loc>" . esc_url( $thumb ) . "</video:thumbnail_loc>\n";
			}
			echo "\t\t\t<video:title>" . $this->esc_xml( get_the_title() ) . "</video:title>\n";
			echo "\t\t\t<video:description>" . $this->esc_xml( $this->get_description() ) . "</video:description>\n";
			echo "\t\t\t<video:content_loc>" . esc_url( $url ) . "</video:content_loc>\n";
			echo "\t\t\t<video:publication_date>" . $this->esc_xml( get_the_date( 'c' ) ) . "</video:publication_date>\n";
			echo "\t\t</video:video>\n";
			echo "\t</url>\n";
		}

		wp_reset_postdata();
	}

	/**
	 * توضیح ویدیو برای سایت مپ.
	 *
	 * @return string
	 */
	private function get_description() {
		$description = wp_strip_all_tags( get_the_excerpt() );

		if ( $description ) {
			return $description;
		}

		return get_bloginfo( 'description' );
	}

	/**
	 * فرار کاراکتر برای XML با سازگاری با نسخه‌های قدیمی وردپرس.
	 *
	 * @param string $text متن.
	 * @return string
	 */
	private function esc_xml( $text ) {
		if ( function_exists( 'esc_xml' ) ) {
			return esc_xml( $text );
		}

		return esc_html( $text );
	}
}
