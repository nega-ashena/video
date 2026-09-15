<?php
/**
 * قالب پیش‌فرض صفحه تکی ویدیو (سبک، بدون CSS/JS اینلاین).
 *
 * @package NegaheVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video_url = get_post_meta( get_the_ID(), \Negahe\Videos\Video_Meta::KEY, true );
$poster    = get_the_post_thumbnail_url( get_the_ID(), 'large' );

get_header();
?>
<main class="negv-single">
	<article <?php post_class( 'negv-single__article' ); ?>>
		<header class="negv-single__header">
			<h1 class="negv-single__title"><?php the_title(); ?></h1>
		</header>

		<?php if ( $video_url ) : ?>
			<figure class="negv-single__player">
				<?php
				$duration = get_post_meta( get_the_ID(), \Negahe\Videos\Video_Meta::DURATION_KEY, true );
				if ( $duration ) :
					?>
					<span class="negv-single__duration" aria-hidden="true"><?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
				<video
					class="negv-single__video"
					controls
					playsinline
					preload="metadata"
					<?php echo $poster ? 'poster="' . esc_url( $poster ) . '"' : ''; ?>
				>
					<source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4" />
					<?php esc_html_e( 'Your browser does not support the video tag.', 'negahe-videos' ); ?>
				</video>
			</figure>
		<?php endif; ?>

		<?php if ( ! empty( get_the_content() ) ) : ?>
			<div class="negv-single__content">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<?php
		$post_id = get_the_ID();
		?>
		<div class="negv-single__actions">
			<?php
			echo Negahe\Videos\Video_Actions::render_download( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Negahe\Videos\Video_Actions::render_like( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Negahe\Videos\Video_Actions::render_share( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>

		<?php
		if ( comments_open( $post_id ) || get_comments_number( $post_id ) ) {
			comments_template();
		}
		?>
	</article>
</main>
<?php
get_footer();
