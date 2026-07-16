<?php
/**
 * Frontend HTML for the LMS My Courses module.
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

$user_id        = get_current_user_id();
$show_thumbnail = (!isset($settings->show_thumbnail) || 'no' !== $settings->show_thumbnail);
$empty_text     = !empty($settings->empty_text) ? $settings->empty_text : __('You are not enrolled in any courses yet.', 'simple-lms-bridge');

if (!$user_id) {
    echo '<div class="slms-my-courses slms-my-courses-empty">' . esc_html__('Please log in to view your courses.', 'simple-lms-bridge') . '</div>';
    return;
}

$courses = Access::get_enrolled_courses_with_progress($user_id);

if (empty($courses)) {
    echo '<div class="slms-my-courses slms-my-courses-empty">' . esc_html($empty_text) . '</div>';
    return;
}
?>
<div class="slms-my-courses">
    <?php foreach ($courses as $course) : ?>
        <?php
        $is_complete = ('completed' === $course['state']);
        $btn_label   = $is_complete
            ? __('Review', 'simple-lms-bridge')
            : ($course['completed'] > 0 ? __('Continue', 'simple-lms-bridge') : __('Start', 'simple-lms-bridge'));
        ?>
        <article class="slms-mc-item<?php echo $is_complete ? ' is-complete' : ''; ?>">
            <?php if ($show_thumbnail) : ?>
                <a class="slms-mc-thumb" href="<?php echo esc_url($course['permalink']); ?>">
                    <?php if (!empty($course['thumbnail'])) : ?>
                        <img src="<?php echo esc_url($course['thumbnail']); ?>" alt="<?php echo esc_attr($course['title']); ?>" />
                    <?php else : ?>
                        <span class="slms-mc-thumb-placeholder"></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <div class="slms-mc-body">
                <h3 class="slms-mc-title">
                    <a href="<?php echo esc_url($course['permalink']); ?>"><?php echo esc_html($course['title']); ?></a>
                </h3>

                <div class="slms-mc-progress">
                    <div class="slms-progress-bar-container">
                        <div class="slms-progress-bar-fill" style="width: <?php echo esc_attr($course['percent']); ?>%;"></div>
                    </div>
                    <span class="slms-progress-label">
                        <?php
                        printf(
                            /* translators: 1: completed lessons, 2: total lessons, 3: percent */
                            esc_html__('%1$d of %2$d lessons · %3$d%% complete', 'simple-lms-bridge'),
                            (int) $course['completed'],
                            (int) $course['total'],
                            (int) $course['percent']
                        );
                        ?>
                    </span>
                </div>
            </div>

            <div class="slms-mc-action">
                <a class="slms-cta-button" href="<?php echo esc_url($course['continue_url']); ?>">
                    <?php echo esc_html($btn_label); ?>
                </a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
