<?php
/**
 * Plugin Name:       Negahe Videos
 * Description:       پست‌تایپ «ویدیو» با فیلد لینک فایل و دسته‌بندی برای نمایش با المنتور.
 * Version:           1.4.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Negahe Ashenapub
 * Text Domain:       negahe-videos
 * Domain Path:       /languages
 *
 * @package NegaheVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NEGV_VERSION', '1.3.0' );
define( 'NEGV_FILE', __FILE__ );
define( 'NEGV_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEGV_BASENAME', plugin_basename( __FILE__ ) );

require_once NEGV_DIR . 'includes/class-negahe-videos.php';

register_activation_hook( __FILE__, array( 'Negahe\\Videos\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Negahe\\Videos\\Plugin', 'deactivate' ) );

Negahe\Videos\Plugin::init();
