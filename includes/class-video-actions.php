<?php
/**
 * ماژول دکمه‌های لایک، اشتراک‌گذاری و دانلود.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * لایک (فقط برای کاربر لاگین‌شده از طریق REST)، اشتراک‌گذاری و دانلود.
 */
class Video_Actions {

	/**
	 * کلید متادیتای لایک‌ها.
	 */
	const LIKE_META_KEY = '_negv_likes';

	/**
	 * کلید کش تعداد لایک برای کوئری سریع.
	 */
	const LIKE_COUNT_KEY = '_negv_like_count';

	/**
	 * حداکثر عمل لایک/آنلایک در بازه زمانی.
	 */
	const RATE_MAX_ACTIONS = 10;

	/**
	 * بازه زمانی شمارش (ثانیه).
	 */
	const RATE_WINDOW = 60;

	/**
	 * مدت قفل موقت پس از عبور از حد مجاز (ثانیه).
	 */
	const RATE_BAN = 300;

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * ثبت مسیر REST برای لایک و دانلود.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'negv/v1',
			'/video/(?P<id>\d+)/like',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'toggle_like' ),
				'permission_callback' => array( $this, 'check_login' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'negv/v1',
			'/video/(?P<id>\d+)/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download_video' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * فقط کاربران لاگین‌کرده با nonce معتبر می‌توانند لایک کنند.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return bool|\WP_Error
	 */
	public function check_login( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'negv_not_logged_in', __( 'You must be logged in.', 'negahe-videos' ), array( 'status' => 401 ) );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'negv_invalid_nonce', __( 'Security check failed.', 'negahe-videos' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * لایک/آنلایک ویدیو.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function toggle_like( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );

		if ( ! $post_id || Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return new \WP_Error( 'negv_invalid_video', __( 'Invalid video.', 'negahe-videos' ), array( 'status' => 404 ) );
		}

		if ( 'publish' !== get_post_status( $post_id ) ) {
			return new \WP_Error( 'negv_video_unpublished', __( 'Video is not published.', 'negahe-videos' ), array( 'status' => 403 ) );
		}

		$rate = $this->check_rate_limit( get_current_user_id() );

		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$user_id = get_current_user_id();
		$likes   = get_post_meta( $post_id, self::LIKE_META_KEY, true );

		if ( ! is_array( $likes ) ) {
			$likes = array();
		}

		$likes = array_map( 'absint', $likes );
		$key   = array_search( $user_id, $likes, true );

		if ( false !== $key ) {
			unset( $likes[ $key ] );
			$liked = false;
		} else {
			$likes[] = $user_id;
			$liked   = true;
		}

		update_post_meta( $post_id, self::LIKE_META_KEY, $likes );
		update_post_meta( $post_id, self::LIKE_COUNT_KEY, count( $likes ) );

		return rest_ensure_response(
			array(
				'liked' => $liked,
				'count' => count( $likes ),
			)
		);
	}

	/**
	 * محدودیت نرخ لایک: در صورت عبور از حد مجاز، کاربر را موقتاً قفل می‌کند.
	 * از قفل اتمیک برای جلوگیری از استغله در شرایط مسابقه استفاده می‌شود.
	 *
	 * @param int $user_id شناسه کاربر.
	 * @return true|\WP_Error
	 */
	private function check_rate_limit( $user_id ) {
		$ban_key = 'negv_like_ban_' . $user_id;

		if ( get_transient( $ban_key ) ) {
			return new \WP_Error(
				'negv_rate_limited',
				sprintf(
					/* translators: %d: مدت قفل به دقیقه. */
					__( 'Too many attempts. Try again in %d minutes.', 'negahe-videos' ),
					self::RATE_BAN / MINUTE_IN_SECONDS
				),
				array( 'status' => 429 )
			);
		}

		$lock_key = 'negv_like_lock_' . $user_id;
		$lock_acquired = false;

		// تلاش برای گرفتن قفل با set_transient که اتمیک است
		for ( $i = 0; $i < 3; $i++ ) {
			$lock_acquired = set_transient( $lock_key, 1, 2 );
			if ( $lock_acquired ) {
				break;
			}
			usleep( 50000 ); // 50ms
		}

		if ( ! $lock_acquired ) {
			return new \WP_Error(
				'negv_rate_limited',
				__( 'Rate limit check failed. Please try again.', 'negahe-videos' ),
				array( 'status' => 429 )
			);
		}

		$window_key = 'negv_like_window_' . $user_id;
		$now        = time();
		$hits       = get_transient( $window_key );

		if ( ! is_array( $hits ) ) {
			$hits = array();
		}

		$hits = array_values(
			array_filter(
				$hits,
				function ( $time ) use ( $now ) {
					return ( $now - (int) $time ) < self::RATE_WINDOW;
				}
			)
		);

		$hits[] = $now;

		if ( count( $hits ) > self::RATE_MAX_ACTIONS ) {
			set_transient( $ban_key, 1, self::RATE_BAN );
			delete_transient( $window_key );
			delete_transient( $lock_key );

			return new \WP_Error(
				'negv_rate_limited',
				sprintf(
					/* translators: %d: مدت قفل به دقیقه. */
					__( 'Too many attempts. Try again in %d minutes.', 'negahe-videos' ),
					self::RATE_BAN / MINUTE_IN_SECONDS
				),
				array( 'status' => 429 )
			);
		}

		set_transient( $window_key, $hits, self::RATE_WINDOW );
		delete_transient( $lock_key );

		return true;
	}

	/**
	 * تعداد لایک‌ها.
	 *
	 * @param int $post_id شناسه پست.
	 * @return int
	 */
	public static function get_like_count( $post_id ) {
		$cached = get_post_meta( $post_id, self::LIKE_COUNT_KEY, true );

		if ( '' !== $cached && is_numeric( $cached ) ) {
			return (int) $cached;
		}

		$likes = get_post_meta( $post_id, self::LIKE_META_KEY, true );
		$count = is_array( $likes ) ? count( array_filter( array_map( 'absint', $likes ) ) ) : 0;

		if ( $count ) {
			update_post_meta( $post_id, self::LIKE_COUNT_KEY, $count );
		}

		return $count;
	}

	/**
	 * آیا کاربر فعلی لایک کرده است؟
	 *
	 * @param int $post_id شناسه پست.
	 * @return bool
	 */
	public static function has_liked( $post_id ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$likes = get_post_meta( $post_id, self::LIKE_META_KEY, true );

		return is_array( $likes ) && in_array( $user_id, array_map( 'absint', $likes ), true );
	}

	/**
	 * دانلود ویدیو با پروکسی برای پشتیبانی از هاست‌های خارجی.
	 * برای فایل‌های بزرگ، به URL اصلی هدایت می‌شود تا از اتلاف حافظه جلوگیری شود.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function download_video( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );

		if ( ! $post_id || Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return new \WP_Error( 'negv_invalid_video', __( 'Invalid video.', 'negahe-videos' ), array( 'status' => 404 ) );
		}

		if ( 'publish' !== get_post_status( $post_id ) ) {
			return new \WP_Error( 'negv_video_unpublished', __( 'Video is not published.', 'negahe-videos' ), array( 'status' => 403 ) );
		}

		$url = get_post_meta( $post_id, Video_Meta::KEY, true );

		if ( ! $url ) {
			return new \WP_Error( 'negv_no_video_url', __( 'Video URL not set.', 'negahe-videos' ), array( 'status' => 404 ) );
		}

		$title    = get_the_title( $post_id );
		$filename = sanitize_file_name( $title ) . '.mp4';

		$request_args = array(
			'timeout'     => 10,
			'redirection' => 5,
			'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
		);

		$head_response = wp_remote_head( $url, $request_args );

		$content_length = 0;
		if ( ! is_wp_error( $head_response ) ) {
			$head_headers = wp_remote_retrieve_headers( $head_response );
			if ( isset( $head_headers['content-length'] ) ) {
				$content_length = (int) $head_headers['content-length'];
			}
		}

		$max_proxy_size = 100 * 1024 * 1024; // 100MB

		if ( $content_length > $max_proxy_size ) {
			return $this->redirect_to_source( $url );
		}

		$get_args = $request_args;
		$get_args['timeout'] = 60;
		$get_args['stream']  = true;

		$response = wp_remote_get( $url, $get_args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			// اگر پروکسی شکست خورد، به URL اصلی هدایت کن
			if ( $this->should_fallback_to_redirect( $error_message ) ) {
				return $this->redirect_to_source( $url );
			}
			return new \WP_Error( 'negv_download_failed', sprintf( __( 'Failed to fetch video from source: %s', 'negahe-videos' ), $error_message ), array( 'status' => 502 ) );
		}

		$headers = wp_remote_retrieve_headers( $response );
		$content_type = isset( $headers['content-type'] ) ? $headers['content-type'] : 'video/mp4';
		if ( ! $content_length && isset( $headers['content-length'] ) ) {
			$content_length = (int) $headers['content-length'];
		}

		$download_response = new \WP_REST_Response( '' );
		$download_response->header( 'Content-Type', $content_type );
		$download_response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
		if ( $content_length ) {
			$download_response->header( 'Content-Length', $content_length );
		}
		$download_response->header( 'Accept-Ranges', 'bytes' );
		$download_response->header( 'Cache-Control', 'no-cache, must-revalidate' );
		$download_response->header( 'Expires', '0' );

		$body = wp_remote_retrieve_body( $response );
		$download_response->set_data( $body );

		return $download_response;
	}

	/**
	 * بررسی اینکه آیا باید به URL اصلی هدایت شویم.
	 *
	 * @param string $error_message پیام خطا.
	 * @return bool
	 */
	private function should_fallback_to_redirect( $error_message ) {
		$fallback_errors = array(
			'cURL error 60',  // SSL certificate problem
			'cURL error 7',   // Failed to connect
			'cURL error 28',  // Timeout
			'cURL error 35',  // SSL connect error
			'cURL error 51',  // SSL peer certificate
			'cURL error 56',  // SSL read error
		);

		foreach ( $fallback_errors as $fallback_error ) {
			if ( stripos( $error_message, $fallback_error ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * هدایت به URL منبع.
	 *
	 * @param string $url URL ویدیو.
	 * @return \WP_REST_Response
	 */
	private function redirect_to_source( $url ) {
		$redirect_response = new \WP_REST_Response( '' );
		$redirect_response->set_status( 302 );
		$redirect_response->header( 'Location', $url );
		$redirect_response->header( 'Cache-Control', 'no-cache, must-revalidate' );
		$redirect_response->header( 'Expires', '0' );
		return $redirect_response;
	}

	/**
	 * خروجی دکمه دانلود.
	 *
	 * @param int $post_id شناسه پست.
	 * @return string
	 */
	public static function render_download( $post_id ) {
		$url = get_post_meta( $post_id, Video_Meta::KEY, true );

		if ( ! $url ) {
			return '';
		}

		$download_url = esc_url_raw( rest_url( 'negv/v1/video/' . $post_id . '/download' ) );

		ob_start();
		?>
		<a
			class="negv-action negv-action--link"
			href="<?php echo $download_url; ?>"
			target="_blank"
			rel="noopener noreferrer"
		>
			<span class="negv-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
			<?php esc_html_e( 'Download', 'negahe-videos' ); ?>
		</a>
		<?php
		return ob_get_clean();
	}

	/**
	 * خروجی دکمه لایک (فقط برای کاربر لاگین‌شده فعال).
	 *
	 * @param int $post_id شناسه پست.
	 * @return string
	 */
	public static function render_like( $post_id ) {
		$like_count = self::get_like_count( $post_id );
		$has_liked  = self::has_liked( $post_id );
		$is_logged  = is_user_logged_in();

		$like_empty_url = plugin_dir_url( NEGV_FILE ) . 'assets/img/like-empty.svg';
		$like_full_url  = plugin_dir_url( NEGV_FILE ) . 'assets/img/like-filled.svg';

		ob_start();
		?>
		<button
			type="button"
			class="negv-action negv-action--like<?php echo $has_liked ? ' is-liked' : ''; ?>"
			data-post-id="<?php echo esc_attr( $post_id ); ?>"
			data-locked-text="<?php echo esc_attr__( 'کمی صبر کنید', 'negahe-videos' ); ?>"
			aria-pressed="<?php echo $has_liked ? 'true' : 'false'; ?>"
			<?php echo $is_logged ? '' : 'disabled'; ?>
		>
			<span class="negv-action__icon">
				<img class="negv-like-empty" src="<?php echo esc_url( $like_empty_url ); ?>" alt="" width="18" height="18" aria-hidden="true">
				<img class="negv-like-full" src="<?php echo esc_url( $like_full_url ); ?>" alt="" width="18" height="18" aria-hidden="true">
			</span>
			<span class="negv-action__label"><?php esc_html_e( 'Like', 'negahe-videos' ); ?></span>
			<span class="negv-action__count"><?php echo esc_html( number_format_i18n( $like_count ) ); ?></span>
		</button>
		<?php
		if ( ! $is_logged ) {
			echo '<a class="negv-action__login-note" href="' . esc_url( wp_login_url( get_permalink( $post_id ) ) ) . '">' . esc_html__( 'برای ثبت لایک وارد شوید', 'negahe-videos' ) . '</a>';
		}

		return ob_get_clean();
	}

	/**
	 * خروجی دکمه اشتراک‌گذاری.
	 *
	 * @param int $post_id شناسه پست.
	 * @return string
	 */
	public static function render_share( $post_id ) {
		ob_start();
		?>
		<button
			type="button"
			class="negv-action negv-action--share"
			data-title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"
			data-url="<?php echo esc_url( get_permalink( $post_id ) ); ?>"
		>
			<span class="negv-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg></span>
			<?php esc_html_e( 'Share', 'negahe-videos' ); ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * بارگذاری اسکریپت و استایل در صفحه تکی ویدیو یا صفحاتی که شورت‌کد دکمه دارند.
	 */
	public function enqueue_assets() {
		$needed = is_singular( Post_Type::POST_TYPE );

		if ( ! $needed && is_singular() ) {
			$post = get_post();

			if ( $post && (
				has_shortcode( $post->post_content, 'video_like' )
				|| has_shortcode( $post->post_content, 'video_share' )
				|| has_shortcode( $post->post_content, 'video_download' )
			) ) {
				$needed = true;
			}
		}

		if ( ! $needed ) {
			return;
		}

		if ( ! wp_style_is( 'negv-single-video', 'registered' ) ) {
			wp_register_style(
				'negv-single-video',
				plugin_dir_url( NEGV_FILE ) . 'assets/css/single-video.css',
				array(),
				NEGV_VERSION
			);
		}
		wp_enqueue_style( 'negv-single-video' );
		wp_enqueue_script(
			'negv-video-actions',
			plugin_dir_url( NEGV_FILE ) . 'assets/js/video-actions.js',
			array(),
			NEGV_VERSION,
			array( 'strategy' => 'defer' )
		);

		wp_localize_script(
			'negv-video-actions',
			'negvActions',
			array(
				'restUrl' => esc_url_raw( rest_url( 'negv/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}