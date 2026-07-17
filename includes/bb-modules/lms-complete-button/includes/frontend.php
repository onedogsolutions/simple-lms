<?php
/**
 * Frontend HTML for the LMS Complete Button module.
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

$post = get_post();

if ( ! $post || 'slms_lesson' !== $post->post_type ) {
	if ( \FLBuilderModel::is_builder_active() ) {
		echo '<div class="slms-complete-placeholder">' . esc_html__( 'LMS Complete Button will appear here.', 'simple-lms-bridge' ) . '</div>';
	}
	return;
}

// Find the course ID via the M2M relationship table.
$courses   = \SimpleLMS\Relationships::get_courses_for_lesson( $post->ID );
$course_id = ! empty( $courses ) ? (int) $courses[0]->id : 0;

if ( ! $course_id ) {
	return;
}

$user_id = get_current_user_id();
if ( ! $user_id ) {
	return;
}

$lesson_type = get_post_meta( $post->ID, '_slms_lesson_type', true );

// Quiz lessons complete automatically on form submission — never show the
// manual toggle for them.
if ( 'quiz' === $lesson_type ) {
	echo '<div class="slms-complete-button-container slms-quiz-notice">'
		. esc_html__( 'This lesson is completed automatically when you submit the quiz.', 'simple-lms-bridge' )
		. '</div>';
	return;
}

$progress     = get_user_meta( $user_id, '_lms_progress', true );
$is_completed = isset( $progress[ $course_id ][ $post->ID ] );

// Video gating: require N% watched before the button can be clicked.
$video_gate_pct = (int) get_post_meta( $post->ID, '_lms_video_gate_pct', true );
$video_id       = (int) get_post_meta( $post->ID, '_lms_presto_video', true );
$gate_active    = ( 'video' === $lesson_type && $video_gate_pct > 0 && $video_id && ! $is_completed );

$button_classes   = array( 'slms-complete-toggle', 'button' );
$button_classes[] = $is_completed ? 'is-completed button-primary' : 'button-secondary';
if ( $gate_active ) {
	$button_classes[] = 'is-gated';
}
?>
<div class="slms-complete-button-container">
	<button type="button"
		class="<?php echo esc_attr( implode( ' ', $button_classes ) ); ?>"
		data-course-id="<?php echo esc_attr( $course_id ); ?>" data-lesson-id="<?php echo esc_attr( $post->ID ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
		data-rest-url="<?php echo esc_url( rest_url( 'simple-lms/v1/me/progress' ) ); ?>"
		<?php if ( $gate_active ) : ?>
		data-video-gate="<?php echo esc_attr( $video_gate_pct ); ?>"
		data-video-id="<?php echo esc_attr( $video_id ); ?>"
		disabled
		<?php endif; ?>
		>
		<span class="slms-label-incomplete">
			<?php esc_html_e( 'Mark as Complete', 'simple-lms-bridge' ); ?>
		</span>
		<span class="slms-label-complete">
			<?php esc_html_e( 'Completed', 'simple-lms-bridge' ); ?>
		</span>
	</button>
	<?php if ( $gate_active ) : ?>
	<p class="slms-gate-notice">
		<?php
		printf(
			/* translators: %d: percent watched required */
			esc_html__( 'Watch at least %d%% of the video to unlock completion.', 'simple-lms-bridge' ),
			$video_gate_pct
		);
		?>
	</p>
	<?php endif; ?>
</div>