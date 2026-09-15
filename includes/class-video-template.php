<?php
/**
 * ماژول صفحه اختصاصی خودکار برای هر ویدیو.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * قالب سبک صفحه تکی ویدیو (فقط وقتی قالب/المنتور قالبی ندارد).
 */
class Video_Template {

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_filter( 'template_include', array( $this, 'load_template' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * بارگذاری قالب پیش‌فرض ویدیو؛ اگر قالبی (تم یا المنتور) وجود داشته باشد تداخلی ایجاد نمی‌کند.
	 *
	 * @param string $template مسیر قالب فعلی.
	 * @return string
	 */
	public function load_template( $template ) {
		if ( ! is_singular( Post_Type::POST_TYPE ) ) {
			return $template;
		}

		if ( locate_template( array( 'single-video.php' ) ) ) {
			return $template;
		}

		return NEGV_DIR . 'templates/single-video.php';
	}

	/**
	 * استایل قالب پیش‌فرض فقط در صفحه تکی ویدیو.
	 */
	public function enqueue_assets() {
		if ( ! is_singular( Post_Type::POST_TYPE ) ) {
			return;
		}

		wp_enqueue_style(
			'negv-single-video',
			plugin_dir_url( NEGV_FILE ) . 'assets/css/single-video.css',
			array(),
			NEGV_VERSION
		);
	}
}
