<?php
/**
 * ماژول شورت‌کد گرید ویدیوها.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * خروجی گرید ویدیوها با شورت‌کد [video_gallery].
 */
class Video_Shortcode {

	/**
	 * تعداد ویدیو در هر صفحه.
	 */
	const PER_PAGE = 12;

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'video_gallery', array( $this, 'render_gallery' ) );
		add_shortcode( 'video_player', array( $this, 'render_player' ) );
		add_shortcode( 'video_like', array( $this, 'render_like' ) );
		add_shortcode( 'video_share', array( $this, 'render_share' ) );
		add_shortcode( 'video_download', array( $this, 'render_download' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'clear_grid_cache' ) );
		add_action( 'deleted_post', array( $this, 'clear_grid_cache' ) );
		add_action( 'trashed_post', array( $this, 'clear_grid_cache' ) );
		add_action( 'edited_' . Taxonomy::TAXONOMY, array( $this, 'clear_grid_cache' ) );
		add_action( 'created_' . Taxonomy::TAXONOMY, array( $this, 'clear_grid_cache' ) );
		add_action( 'delete_' . Taxonomy::TAXONOMY, array( $this, 'clear_grid_cache' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * اعتبارسنجی شناسه ویدیو؛ بدون id، پست جاری (قالب تکی) استفاده می‌شود.
	 *
	 * @param int $id شناسه پست.
	 * @return int
	 */
	private function get_video_id( $id ) {
		$id = absint( $id );

		if ( ! $id ) {
			$id = get_queried_object_id();
		}

		if ( ! $id || Post_Type::POST_TYPE !== get_post_type( $id ) ) {
			return 0;
		}

		// در پیش‌نمایش المنتور، پست جاری می‌تواند منتشرنشده باشد.
		if ( 'publish' !== get_post_status( $id ) && $id !== get_queried_object_id() ) {
			return 0;
		}

		return $id;
	}

	/**
	 * شورت‌کد پخش‌کننده تک‌ویدیو.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function render_player( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'video_player' );
		$id   = $this->get_video_id( $atts['id'] );

		if ( ! $id ) {
			return '';
		}

		$url      = get_post_meta( $id, Video_Meta::KEY, true );
		$poster   = get_the_post_thumbnail_url( $id, 'large' );
		$duration = get_post_meta( $id, Video_Meta::DURATION_KEY, true );

		if ( ! $url ) {
			return '';
		}

		$this->register_styles();
		wp_enqueue_style( 'negv-single-video' );

		ob_start();
		?>
		<figure class="negv-single__player">
			<?php if ( $duration ) : ?>
				<span class="negv-single__duration" aria-hidden="true"><?php echo esc_html( $duration ); ?></span>
			<?php endif; ?>
			<video
				class="negv-single__video"
				controls
				playsinline
				preload="metadata"
				<?php echo $poster ? 'poster="' . esc_url( $poster ) . '"' : ''; ?>
			>
				<source src="<?php echo esc_url( $url ); ?>" type="video/mp4" />
				<?php esc_html_e( 'Your browser does not support the video tag.', 'negahe-videos' ); ?>
			</video>
		</figure>
		<?php
		return ob_get_clean();
	}

	/**
	 * شورت‌کد دکمه لایک.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function render_like( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'video_like' );
		$id   = $this->get_video_id( $atts['id'] );

		if ( ! $id ) {
			return '';
		}

		$this->register_styles();
		wp_enqueue_style( 'negv-single-video' );

		return Video_Actions::render_like( $id );
	}

	/**
	 * شورت‌کد دکمه اشتراک‌گذاری.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function render_share( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'video_share' );
		$id   = $this->get_video_id( $atts['id'] );

		if ( ! $id ) {
			return '';
		}

		$this->register_styles();
		wp_enqueue_style( 'negv-single-video' );

		return Video_Actions::render_share( $id );
	}

	/**
	 * شورت‌کد دکمه دانلود.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function render_download( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'video_download' );
		$id   = $this->get_video_id( $atts['id'] );

		if ( ! $id ) {
			return '';
		}

		$this->register_styles();
		wp_enqueue_style( 'negv-single-video' );

		return Video_Actions::render_download( $id );
	}

	/**
	 * ثبت استایل‌ها تا در هر نقطه قابل enqueue باشند.
	 */
	private function register_styles() {
		wp_register_style(
			'negv-video-grid',
			plugin_dir_url( NEGV_FILE ) . 'assets/css/video-grid.css',
			array(),
			NEGV_VERSION
		);
		wp_register_style(
			'negv-single-video',
			plugin_dir_url( NEGV_FILE ) . 'assets/css/single-video.css',
			array(),
			NEGV_VERSION
		);
		wp_register_script(
			'negv-video-grid',
			plugin_dir_url( NEGV_FILE ) . 'assets/js/video-grid.js',
			array(),
			NEGV_VERSION,
			array( 'strategy' => 'defer' )
		);
	}

	/**
	 * بارگذاری استایل گرید فقط وقتی شورت‌کد در محتوا باشد.
	 */
	public function enqueue_assets() {
		$this->register_styles();

		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();

		if ( ! $post ) {
			return;
		}

		$has_grid    = has_shortcode( $post->post_content, 'video_gallery' );
		$has_player  = has_shortcode( $post->post_content, 'video_player' );
		$has_buttons = has_shortcode( $post->post_content, 'video_like' )
			|| has_shortcode( $post->post_content, 'video_share' )
			|| has_shortcode( $post->post_content, 'video_download' );

		if ( $has_grid ) {
			wp_enqueue_style( 'negv-video-grid' );
		}

		if ( $has_player || $has_buttons ) {
			wp_enqueue_style( 'negv-single-video' );
		}
	}

	/**
	 * پاکسازی کش گرید.
	 */
	public function clear_grid_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_negv_grid_%' OR option_name LIKE '_transient_timeout_negv_grid_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * ثبت مسیر REST برای لود بیشتر.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'negv/v1',
			'/grid/load-more',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_load_more' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'category' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_title',
					),
					'per_page' => array(
						'default'           => self::PER_PAGE,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * پاسخ درخواست لود بیشتر (AJAX).
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function handle_load_more( $request ) {
		$page     = max( 1, $request->get_param( 'page' ) );
		$category = $request->get_param( 'category' );
		$per_page = max( 1, min( 50, $request->get_param( 'per_page' ) ) );

		$query_args = array(
			'post_type'              => Post_Type::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			// غیرفعال کردن فیلترهای pre_get_posts (جلوگیری از تداخل قالب/افزونه‌ها).
			'suppress_filters'       => true,
		);

		if ( $category ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$videos = new \WP_Query( $query_args );
		$html   = '';

		if ( $videos->have_posts() ) {
			while ( $videos->have_posts() ) {
				$videos->the_post();
				$html .= $this->render_card( get_the_ID(), true );
			}
		}

		wp_reset_postdata();

		return rest_ensure_response(
			array(
				'html'      => $html,
				'has_more'  => $page < $videos->max_num_pages,
				'page'      => $page,
				'total'     => $videos->found_posts,
				'max_pages' => $videos->max_num_pages,
			)
		);
	}

	/**
	 * رندر یک کارت ویدیو.
	 *
	 * @param int  $post_id شناسه پست.
	 * @param bool $has_tabs آیا تب‌ها فعال است.
	 * @return string
	 */
	private function render_card( $post_id, $has_tabs = false ) {
		$poster = get_the_post_thumbnail_url( $post_id, 'medium_large' );
		$term_slugs = array();

		if ( $has_tabs ) {
			$post_terms = get_the_terms( $post_id, Taxonomy::TAXONOMY );
			if ( $post_terms && ! is_wp_error( $post_terms ) ) {
				foreach ( $post_terms as $pt ) {
					$term_slugs[] = $pt->slug;
				}
			}
		}

		$cat_attr = $term_slugs ? implode( ' ', array_map( 'sanitize_title', $term_slugs ) ) : '';
		$duration = get_post_meta( $post_id, Video_Meta::DURATION_KEY, true );
		$title    = get_the_title( $post_id );
		$url      = get_permalink( $post_id );

		ob_start();
		?>
		<a
			class="negv-video-card"
			href="<?php echo esc_url( $url ); ?>"
			aria-label="<?php echo esc_attr( $title ); ?>"
			<?php echo $has_tabs ? 'data-category="' . esc_attr( $cat_attr ) . '"' : ''; ?>
		>
			<span class="negv-video-card__thumb">
				<?php
				if ( $poster ) {
					echo get_the_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						$post_id,
						'medium_large',
						array(
							'loading'       => 'lazy',
							'decoding'      => 'async',
							'fetchpriority' => 'low',
							'sizes'         => '(max-width: 600px) 100vw, (max-width: 1024px) 50vw, 25vw',
						)
					);
				} else {
					echo '<span class="negv-video-card__placeholder" aria-hidden="true"></span>';
				}
				?>
				<span class="negv-video-card__play" aria-hidden="true"></span>
				<?php if ( $duration ) : ?>
					<span class="negv-video-card__duration" aria-hidden="true"><?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
			</span>
			<span class="negv-video-card__title"><?php echo esc_html( $title ); ?></span>
		</a>
		<?php
		return ob_get_clean();
	}

	/**
	 * رندر گرید ویدیوها.
	 *
	 * @param array $atts ویژگی‌های شورت‌کد.
	 * @return string
	 */
	public function render_gallery( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'     => self::PER_PAGE,
				'category'  => '',
				'orderby'   => 'date',
				'order'     => 'DESC',
				'columns'   => '',
				'class'     => '',
				'tabs'      => 'true',
				'all_label' => '',
			),
			$atts,
			'video_gallery'
		);

		$per_page = self::PER_PAGE;

		$allowed_orderby = array( 'date', 'title', 'modified', 'rand', 'comment_count', 'menu_order' );
		$orderby         = sanitize_key( $atts['orderby'] );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		$query_args = array(
			'post_type'              => Post_Type::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'orderby'                => $orderby,
			'order'                  => 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			// غیرفعال کردن فیلترهای pre_get_posts (جلوگیری از تداخل قالب/افزونه‌ها).
			'suppress_filters'       => true,
		);

		if ( $atts['category'] ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['category'] ),
				),
			);
		}

		$show_tabs = 'false' !== strtolower( (string) $atts['tabs'] ) && '0' !== (string) $atts['tabs'];

		$cache_key = 'negv_grid_' . md5( wp_json_encode( $query_args ) . '|' . $atts['columns'] . '|' . $atts['class'] . '|' . ( $show_tabs ? '1' : '0' ) . '|' . $atts['all_label'] );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			$this->register_styles();
			wp_enqueue_style( 'negv-video-grid' );
			wp_enqueue_script( 'negv-video-grid' );
			$this->localize_grid_script();
			return $cached;
		}

		$videos = new \WP_Query( $query_args );

		if ( ! $videos->have_posts() ) {
			wp_reset_postdata();

			return '';
		}

		$this->register_styles();
		wp_enqueue_style( 'negv-video-grid' );
		wp_enqueue_script( 'negv-video-grid' );
		$this->localize_grid_script();

		$columns = intval( $atts['columns'] );
		$classes = array( 'negv-video-grid' );

		if ( $columns >= 1 && $columns <= 6 ) {
			$classes[] = 'negv-video-grid--cols-' . $columns;
		}

		if ( $atts['class'] ) {
			$classes[] = sanitize_html_class( $atts['class'] );
		}

		$terms    = array();
		$has_tabs = false;
		if ( $show_tabs ) {
			$terms = get_terms(
				array(
					'taxonomy'   => Taxonomy::TAXONOMY,
					'hide_empty' => true,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$has_tabs = true;
			} else {
				$terms = array();
			}
		}

		$all_label = $atts['all_label'] ? $atts['all_label'] : __( 'All', 'negahe-videos' );

		ob_start();
		?>
		<div class="negv-video-grid-wrap<?php echo $has_tabs ? ' has-tabs' : ''; ?>"
			data-page="1"
			data-max-pages="<?php echo esc_attr( $videos->max_num_pages ); ?>"
			data-per-page="<?php echo esc_attr( $per_page ); ?>"
			data-category="<?php echo esc_attr( $atts['category'] ); ?>"
		>
			<?php if ( $has_tabs ) : ?>
				<div class="negv-video-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Video categories', 'negahe-videos' ); ?>">
					<button type="button" class="negv-video-tab is-active" data-filter="*" role="tab" aria-selected="true"><?php echo esc_html( $all_label ); ?></button>
					<?php foreach ( $terms as $term ) : ?>
						<button type="button" class="negv-video-tab" data-filter="<?php echo esc_attr( $term->slug ); ?>" role="tab" aria-selected="false"><?php echo esc_html( $term->name ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
				<?php
				$index = 0;
				while ( $videos->have_posts() ) {
					$videos->the_post();
					echo $this->render_card( get_the_ID(), $has_tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$index++;
				}
				?>
			</div>
			<?php if ( $has_tabs ) : ?>
				<p class="negv-video-empty" hidden><?php esc_html_e( 'No videos in this category.', 'negahe-videos' ); ?></p>
			<?php endif; ?>
			<?php if ( $videos->max_num_pages > 1 ) : ?>
				<div class="negv-load-more-wrap">
					<button type="button" class="negv-load-more-btn" aria-label="<?php esc_attr_e( 'Load more videos', 'negahe-videos' ); ?>">
						<?php esc_html_e( 'Load More', 'negahe-videos' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();

		$html = ob_get_clean();

		set_transient( $cache_key, $html, 10 * MINUTE_IN_SECONDS );

		return $html;
	}

	/**
	 * ارسال اطلاعات به اسکریپت گرید.
	 */
	private function localize_grid_script() {
		static $localized = false;
		if ( $localized ) {
			return;
		}
		$localized = true;

		wp_localize_script(
			'negv-video-grid',
			'negvGrid',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'negv/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'perPage'   => self::PER_PAGE,
				'i18n'      => array(
					'loading'  => __( 'Loading...', 'negahe-videos' ),
					'loadMore' => __( 'Load More', 'negahe-videos' ),
					'noMore'   => __( 'No more videos', 'negahe-videos' ),
					'error'    => __( 'Failed to load videos.', 'negahe-videos' ),
					'noVideos' => __( 'No videos in this category.', 'negahe-videos' ),
				),
			)
		);
	}

	/**
	 * Alias برای سازگاری با نسخه‌های قبل (متد قدیمی render).
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function render( $atts ) {
		return $this->render_gallery( $atts );
	}
}
