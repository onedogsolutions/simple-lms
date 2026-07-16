<?php
/**
 * Frontend HTML for the LMS Outline module.
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

if (!$post || !in_array($post->post_type, array('slms_course', 'slms_lesson'), true)) {
    if (\FLBuilderModel::is_builder_active()) {
        echo '<div class="slms-outline-placeholder">' . esc_html__('LMS Outline will appear here.', 'simple-lms-bridge') . '</div>';
    }
    return;
}

// Find the course ID.
$course_id = $post->ID;
if ('slms_lesson' === $post->post_type) {
    // Find the parent course via the M2M relationship table.
    $courses   = \SimpleLMS\Relationships::get_courses_for_lesson( $post->ID );
    $course_id = ! empty( $courses ) ? (int) $courses[0]->id : 0;
}

if (!$course_id) {
    return;
}

$lesson_ids = get_post_meta($course_id, '_simple_lms_order', true);

if (!is_array($lesson_ids) || empty($lesson_ids)) {
    return;
}

$user_id = get_current_user_id();
$course_progress = $user_id ? \SimpleLMS\Progress::get_course_progress($user_id, $course_id) : array();


?>
<div class="slms-course-outline">
    <header class="slms-outline-header">
        <h3 class="slms-outline-title">
            <?php echo get_the_title($course_id); ?>
        </h3>
        <?php
        $total_lessons = count($lesson_ids);
        $completed_lessons = count($course_progress);
        $percent = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;
        ?>
        <div class="slms-progress-bar-container">
            <div class="slms-progress-bar-fill" style="width: <?php echo esc_attr($percent); ?>%;"></div>
            <span class="slms-progress-label"><?php echo esc_html($percent); ?>% <?php esc_html_e('Complete', 'simple-lms-bridge'); ?></span>
        </div>
    </header>

    <ul class="slms-lesson-list">
        <?php foreach ($lesson_ids as $index => $lesson_id): ?>
            <?php
            $lesson = get_post($lesson_id);
            if (!$lesson) {
                continue;
            }

            $is_current = ($lesson_id === $post->ID);
            $is_completed = isset($course_progress[$lesson_id]);
            $classes = array('slms-lesson-item');
            if ($is_current) {
                $classes[] = 'is-current';
            }
            if ($is_completed) {
                $classes[] = 'is-completed';
            }
            ?>
            <li class="<?php echo implode(' ', $classes); ?>">
                <a href="<?php echo get_permalink($lesson_id); ?>">
                    <span class="slms-lesson-number"><?php echo ($index + 1); ?></span>
                    <span class="slms-status-icon">
                        <?php if ($is_completed): ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-marker"></span>
                        <?php endif; ?>
                    </span>
                    <span class="slms-lesson-label">
                        <?php echo get_the_title($lesson_id); ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>