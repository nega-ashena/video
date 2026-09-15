<?php
/**
 * ماژول مدیریت لیست ویدیوها در داشبورد.
 *
 * @package NegaheVideos
 */

namespace Negahe\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ستون‌ها و فیلترهای لیست ویدیوها.
 */
class Admin {

	/**
	 * ثبت هوک‌ها.
	 */
	public function __construct() {
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'category_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
	}

	/**
	 * ستون‌های لیست.
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public function columns( $columns ) {
		return array(
			'cb'             => $columns['cb'],
			'negv_thumb'     => __( 'Thumbnail', 'negahe-videos' ),
			'title'          => $columns['title'],
			'negv_video_url' => __( 'Video Link', 'negahe-videos' ),
			'negv_category'  => __( 'Category', 'negahe-videos' ),
			'date'           => $columns['date'],
		);
	}

	/**
	 * محتوای ستون‌های سفارشی.
	 *
	 * @param string $column  نام ستون.
	 * @param int    $post_id شناسه پست.
	 */
	public function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'negv_thumb':
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail( $post_id, array( 60, 60 ) );
				} else {
					echo '&mdash;';
				}
				break;

			case 'negv_video_url':
				$url = get_post_meta( $post_id, Video_Meta::KEY, true );
				if ( $url ) {
					echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>';
				} else {
					echo '&mdash;';
				}
				break;

			case 'negv_category':
				$terms = get_the_terms( $post_id, Taxonomy::TAXONOMY );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$names = array();
					foreach ( $terms as $term ) {
						$names[] = '<a href="' . esc_url( get_edit_term_link( $term->term_id, Taxonomy::TAXONOMY ) ) . '">' . esc_html( $term->name ) . '</a>';
					}
					echo implode( ', ', $names ); // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo '&mdash;';
				}
				break;
		}
	}

	/**
	 * فیلتر دسته‌بندی در لیست.
	 */
	public function category_filter() {
		global $typenow;

		if ( Post_Type::POST_TYPE !== $typenow ) {
			return;
		}

		$selected = isset( $_GET[ Taxonomy::TAXONOMY ] ) ? sanitize_text_field( wp_unslash( $_GET[ Taxonomy::TAXONOMY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		wp_dropdown_categories(
			array(
				'show_option_all' => __( 'All Categories', 'negahe-videos' ),
				'taxonomy'        => Taxonomy::TAXONOMY,
				'name'            => Taxonomy::TAXONOMY,
				'orderby'         => 'name',
				'hierarchical'    => true,
				'hide_empty'      => false,
				'value_field'     => 'slug',
				'selected'        => $selected,
			)
		);
	}

	/**
	 * اعمال فیلتر دسته‌بندی روی کوئری.
	 *
	 * @param \WP_Query $query کوئری.
	 */
	public function filter_query( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow ) {
			return;
		}

		if ( Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$taxonomy_slug = isset( $_GET[ Taxonomy::TAXONOMY ] ) ? sanitize_text_field( wp_unslash( $_GET[ Taxonomy::TAXONOMY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $taxonomy_slug ) ) {
			return;
		}

		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $taxonomy_slug,
				),
			)
		);
	}
}
