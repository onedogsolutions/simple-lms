<?php
/**
 * Frontend HTML for the LMS Course CTA module.
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

// Resolve the course: explicit setting first, else the current post.
$course_id = ! empty( $settings->course_id ) ? absint( $settings->course_id ) : Access::resolve_course_id();

if ( ! $course_id || 'slms_course' !== get_post_type( $course_id ) ) {
	if ( \FLBuilderModel::is_builder_active() ) {
		echo '<div class="slms-cta-placeholder">' . esc_html__( 'LMS Course CTA will appear here.', 'simple-lms-bridge' ) . '</div>';
	}
	return;
}

$dashboard_url = ! empty( $settings->dashboard_url ) ? esc_url( $settings->dashboard_url ) : '';
$cta           = Access::get_cta( $course_id, 0, array( 'dashboard_url' => $dashboard_url ) );
$align         = isset( $settings->align ) ? $settings->align : 'left';

?>
<div class="slms-course-cta slms-align-<?php echo esc_attr( $align ); ?>">
	<a class="slms-cta-button <?php echo esc_attr( $cta['classes'] ); ?>"
		data-state="<?php echo esc_attr( $cta['state'] ); ?>"
		href="<?php echo esc_url( $cta['url'] ); ?>">
		<?php echo esc_html( $cta['label'] ); ?>
	</a>
</div>
