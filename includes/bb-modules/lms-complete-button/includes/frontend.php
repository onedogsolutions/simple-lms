<?php
/**
 * Frontend HTML for the LMS Complete Button module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @var object $settings
 * @var string $id
 * @var object $module
 */

$post = get_post();

if (!$post || 'slms_lesson' !== $post->post_type) {
    if (\FLBuilderModel::is_builder_active()) {
        echo '<div class="slms-complete-placeholder">' . esc_html__('LMS Complete Button will appear here.', 'simple-lms-bridge') . '</div>';
    }
    return;
}

// Find the course ID via the M2M relationship table.
$courses   = \SimpleLMS\Relationships::get_courses_for_lesson( $post->ID );
$course_id = ! empty( $courses ) ? (int) $courses[0]->id : 0;

if (!$course_id) {
    return;
}

$user_id = get_current_user_id();
if (!$user_id) {
    return;
}

$progress = get_user_meta($user_id, '_lms_progress', true);
$is_completed = isset($progress[$course_id][$post->ID]);

?>
<div class="slms-complete-button-container">
    <button type="button"
        class="slms-complete-toggle button <?php echo $is_completed ? 'is-completed button-primary' : 'button-secondary'; ?>"
        data-course-id="<?php echo esc_attr($course_id); ?>" data-lesson-id="<?php echo esc_attr($post->ID); ?>"
        data-user-id="<?php echo esc_attr($user_id); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
        data-rest-url="<?php echo esc_url(rest_url('simple-lms/v1/progress')); ?>">
        <span class="slms-label-incomplete">
            <?php esc_html_e('Mark as Complete', 'simple-lms-bridge'); ?>
        </span>
        <span class="slms-label-complete">
            <?php esc_html_e('Completed', 'simple-lms-bridge'); ?>
        </span>
    </button>
</div>