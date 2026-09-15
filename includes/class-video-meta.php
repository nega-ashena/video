<?php
/**
 * ماژول فیلد لینک فایل ویدیو.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * متادیتای لینک فایل ویدیو و باکس متا.
 */
class Video_Meta {

	/**
	 * کلید متادیتا.
	 */
	const KEY = 'negv_video_url';

	/**
	 * کلید متادیتا مدت زمان.
	 */
	const DURATION_KEY = 'negv_video_duration';

	/**
	 * نام غیرپس فیلد.
	 */
	const NONCE = 'negv_video_url_nonce';

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ) );
	}

	/**
	 * ثبت متادیتا برای دسترسی REST.
	 */
	public function register_post_meta() {
		register_post_meta(
			Post_Type::POST_TYPE,
			self::KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'auth_callback' ),
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_post_meta(
			Post_Type::POST_TYPE,
			self::DURATION_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'auth_callback' ),
				'sanitize_callback' => array( $this, 'sanitize_duration' ),
			)
		);
	}

	/**
	 * اعتبارسنجی مدت زمان 00:00 یا 00:00:00.
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	public function sanitize_duration( $value ) {
		$value = sanitize_text_field( $value );
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^\d{1,2}:\d{2}(:\d{2})?$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * اجازه ویرایش متادیتا.
	 *
	 * @return bool
	 */
	public function auth_callback() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * افزودن باکس متا در ویرایشگر.
	 */
	public function add_meta_box() {
		add_meta_box(
			'negv_video_url_box',
			__( 'Video File Link', 'negahe-videos' ),
			array( $this, 'render' ),
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * رندر باکس متا.
	 *
	 * @param \WP_Post $post آبجکت پست.
	 */
	public function render( $post ) {
		$value    = get_post_meta( $post->ID, self::KEY, true );
		$duration = get_post_meta( $post->ID, self::DURATION_KEY, true );
		wp_nonce_field( 'negv_save_video_url', self::NONCE );
		?>
		<p>
			<label for="negv_video_url_field">
				<?php esc_html_e( 'Enter the direct video file URL (e.g. https://dl.negaheashenapub.com/videos/example.mp4)', 'negahe-videos' ); ?>
			</label>
		</p>
		<p>
			<input
				type="url"
				id="negv_video_url_field"
				class="regular-text"
				name="<?php echo esc_attr( self::KEY ); ?>"
				value="<?php echo esc_url( $value ); ?>"
				dir="ltr"
			/>
		</p>
		<p>
			<label for="negv_video_duration_field">
				<?php esc_html_e( 'Duration (e.g. 02:15 or 01:02:30)', 'negahe-videos' ); ?>
			</label>
		</p>
		<p>
			<input
				type="text"
				id="negv_video_duration_field"
				class="small-text"
				name="<?php echo esc_attr( self::DURATION_KEY ); ?>"
				value="<?php echo esc_attr( $duration ); ?>"
				placeholder="02:15"
				pattern="\d{1,2}:\d{2}(:\d{2})?"
				dir="ltr"
			/>
			<span class="description"><?php esc_html_e( 'Shown as badge on grid card.', 'negahe-videos' ); ?></span>
		</p>
		<?php
	}

	/**
	 * ذخیره لینک ویدیو.
	 *
	 * @param int $post_id شناسه پست.
	 */
	public function save( $post_id ) {
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! wp_verify_nonce( $nonce, 'negv_save_video_url' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST[ self::KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$url = esc_url_raw( wp_unslash( $_POST[ self::KEY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification

			if ( $url ) {
				update_post_meta( $post_id, self::KEY, $url );
			} else {
				delete_post_meta( $post_id, self::KEY );
			}
		}

		if ( isset( $_POST[ self::DURATION_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$duration = $this->sanitize_duration( wp_unslash( $_POST[ self::DURATION_KEY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification

			if ( $duration ) {
				update_post_meta( $post_id, self::DURATION_KEY, $duration );
			} else {
				delete_post_meta( $post_id, self::DURATION_KEY );
			}
		}
	}
}
