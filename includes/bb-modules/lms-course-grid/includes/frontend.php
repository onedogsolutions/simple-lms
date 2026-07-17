<?php
/**
 * Frontend HTML for the LMS Course Grid module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var object $settings
 * @var string $id
 * @var object $module
 */

$columns             = isset( $settings->columns ) ? absint( $settings->columns ) : 3;
$columns             = $columns > 0 ? $columns : 3;
$number              = isset( $settings->number ) ? intval( $settings->number ) : 12;
$show_thumbnail      = ( ! isset( $settings->show_thumbnail ) || 'no' !== $settings->show_thumbnail );
$show_excerpt        = ( ! isset( $settings->show_excerpt ) || 'no' !== $settings->show_excerpt );
$show_price          = ( ! isset( $settings->show_price ) || 'no' !== $settings->show_price );
$show_enrolled_badge = ( ! isset( $settings->show_enrolled_badge ) || 'no' !== $settings->show_enrolled_badge );
$show_progress       = ( ! isset( $settings->show_progress ) || 'no' !== $settings->show_progress );
$dashboard_url       = ! empty( $settings->dashboard_url ) ? esc_url( $settings->dashboard_url ) : '';

$query_args = array(
	'post_type'      => 'slms_course',
	'post_status'    => 'publish',
	'posts_per_page' => $number,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
);

if ( ! empty( $settings->category ) ) {
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => 'slms_course_cat',
			'field'    => 'slug',
			'terms'    => sanitize_title( $settings->category ),
		),
	);
}

$courses = new \WP_Query( $query_args );

if ( ! $courses->have_posts() ) {
	if ( \FLBuilderModel::is_builder_active() ) {
		echo '<div class="slms-grid-placeholder">' . esc_html__( 'No courses found. Course cards will appear here.', 'simple-lms-bridge' ) . '</div>';
	}
	wp_reset_postdata();
	return;
}

$user_id = get_current_user_id();
?>
<div class="slms-course-grid slms-cols-<?php echo esc_attr( $columns ); ?>">
	<?php
	while ( $courses->have_posts() ) :
		$courses->the_post();
		$course_id = get_the_ID();

		$is_enrolled = $user_id ? Access::is_enrolled( $user_id, $course_id ) : false;
		$cta         = Access::get_cta( $course_id, $user_id, array( 'dashboard_url' => $dashboard_url ) );
		$price       = (float) get_post_meta( $course_id, '_slms_course_price', true );
		?>
		<article class="slms-course-card" data-state="<?php echo esc_attr( $cta['state'] ); ?>">
			<?php if ( $show_thumbnail ) : ?>
				<a class="slms-card-thumb" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'medium_large' ); ?>
					<?php else : ?>
						<span class="slms-card-thumb-placeholder"></span>
					<?php endif; ?>
					<?php if ( $show_enrolled_badge && $is_enrolled ) : ?>
						<span class="slms-enrolled-badge"><?php esc_html_e( 'Enrolled', 'simple-lms-bridge' ); ?></span>
					<?php endif; ?>
				</a>
			<?php endif; ?>

			<div class="slms-card-body">
				<h3 class="slms-card-title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>

				<?php if ( $show_excerpt && has_excerpt() ) : ?>
					<p class="slms-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
				<?php endif; ?>

				<?php
				if ( $show_progress && $is_enrolled ) :
					$stats = Access::get_progress_stats( $user_id, $course_id );
					if ( $stats['total'] > 0 ) :
						?>
						<div class="slms-card-progress">
							<div class="slms-progress-bar-container">
								<div class="slms-progress-bar-fill" style="width: <?php echo esc_attr( $stats['percent'] ); ?>%;"></div>
							</div>
							<span class="slms-progress-label"><?php echo esc_html( $stats['percent'] ); ?>% <?php esc_html_e( 'Complete', 'simple-lms-bridge' ); ?></span>
						</div>
						<?php
					endif;
				endif;
				?>

				<div class="slms-card-footer">
					<?php if ( $show_price && ! $is_enrolled ) : ?>
						<span class="slms-card-price">
							<?php echo $price > 0 ? esc_html( Access::format_price( $price ) ) : esc_html__( 'Free', 'simple-lms-bridge' ); ?>
						</span>
					<?php endif; ?>
					<a class="slms-cta-button <?php echo esc_attr( $cta['classes'] ); ?>"
						data-state="<?php echo esc_attr( $cta['state'] ); ?>"
						href="<?php echo esc_url( $cta['url'] ); ?>">
						<?php echo esc_html( $cta['label'] ); ?>
					</a>
				</div>
			</div>
		</article>
		<?php
	endwhile;
	wp_reset_postdata();
	?>
</div>
