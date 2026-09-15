<?php
/**
 * کلاس اصلی پلاگین؛ بارگذاری ماژول‌ها.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس اصلی پلاگین.
 */
final class Plugin {

	/**
	 * آپلودکننده کلاس‌های ماژول.
	 *
	 * @param string $class نام کامل کلاس.
	 */
	public static function autoload( $class ) {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$class_name = str_replace( $prefix, '', $class );
		$file       = NEGV_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * راه‌اندازی پلاگین.
	 */
	public static function init() {
		self::register_autoloader();
		self::load_modules();
	}

	/**
	 * ثبت اتولودر.
	 */
	private static function register_autoloader() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * نمونه‌سازی ماژول‌ها.
	 */
	private static function load_modules() {
		new Post_Type();
		new Taxonomy();
		new Video_Meta();
		new Admin();
		new Video_Template();
		new Video_Structure_Data();
		new Video_Sitemap();
		new Video_Shortcode();
		new Video_Actions();
	}

	/**
	 * اجرای اکتیو شدن پلاگین؛ ثبت قوانین پیش از فلاش.
	 */
	public static function activate() {
		( new Post_Type() )->register();
		( new Taxonomy() )->register();
		( new Video_Sitemap() )->add_rewrite_rule();
		flush_rewrite_rules();
	}

	/**
	 * اجرای دی‌اکتیو شدن پلاگین.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
