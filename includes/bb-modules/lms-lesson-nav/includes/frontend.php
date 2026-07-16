<?php
/**
 * Frontend HTML for the LMS Lesson Nav module.
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
        echo '<div class="slms-nav-placeholder">' . esc_html__('LMS Lesson Nav will appear here.', 'simple-lms-bridge') . '</div>';
    }
    return;
}

$course_id = Access::resolve_course_id($post);
if (!$course_id) {
    return;
}

$lesson_ids = Access::get_lesson_ids($course_id);
if (empty($lesson_ids)) {
    return;
}

$index = array_search((int) $post->ID, $lesson_ids, true);
if (false === $index) {
    return;
}

$prev_id   = $index > 0 ? $lesson_ids[$index - 1] : 0;
$next_id   = ($index < count($lesson_ids) - 1) ? $lesson_ids[$index + 1] : 0;
$user_id   = get_current_user_id();
$show_back = (!isset($settings->show_back) || 'no' !== $settings->show_back);
$back_label = !empty($settings->back_label) ? $settings->back_label : __('Back to Course', 'simple-lms-bridge');

/**
 * Render a single prev/next link, honoring drip/guard locks.
 *
 * @param int    $lesson_id Target lesson ID.
 * @param int    $course_id Course ID.
 * @param int    $user_id   User ID.
 * @param string $dir       'prev' or 'next'.
 * @param string $label     Directional label.
 */
$render_link = function ($lesson_id, $course_id, $user_id, $dir, $label) {
    if (!$lesson_id) {
        echo '<span class="slms-nav-link slms-nav-' . esc_attr($dir) . ' is-empty"></span>';
        return;
    }

    $title  = get_the_title($lesson_id);
    $locked = $user_id ? !Access::can_view($user_id, $lesson_id, $course_id) : true;

    if ($locked) {
        $unlock_ts = Access::get_unlock_timestamp($user_id, $lesson_id, $course_id);
        $tooltip   = $unlock_ts
            ? sprintf(
                /* translators: %s: unlock date */
                __('Unlocks %s', 'simple-lms-bridge'),
                date_i18n(get_option('date_format'), $unlock_ts)
            )
            : __('Locked', 'simple-lms-bridge');
        ?>
        <span class="slms-nav-link slms-nav-<?php echo esc_attr($dir); ?> is-locked" title="<?php echo esc_attr($tooltip); ?>" aria-disabled="true">
            <span class="slms-nav-dir"><?php echo esc_html($label); ?></span>
            <span class="slms-nav-title">
                <span class="dashicons dashicons-lock"></span>
                <?php echo esc_html($title); ?>
            </span>
            <?php if ($unlock_ts) : ?>
                <span class="slms-nav-unlock"><?php echo esc_html($tooltip); ?></span>
            <?php endif; ?>
        </span>
        <?php
        return;
    }
    ?>
    <a class="slms-nav-link slms-nav-<?php echo esc_attr($dir); ?>" href="<?php echo esc_url(get_permalink($lesson_id)); ?>">
        <span class="slms-nav-dir"><?php echo esc_html($label); ?></span>
        <span class="slms-nav-title"><?php echo esc_html($title); ?></span>
    </a>
    <?php
};
?>
<nav class="slms-lesson-nav">
    <?php $render_link($prev_id, $course_id, $user_id, 'prev', __('Previous', 'simple-lms-bridge')); ?>

    <?php if ($show_back) : ?>
        <a class="slms-nav-back" href="<?php echo esc_url(get_permalink($course_id)); ?>">
            <span class="dashicons dashicons-menu-alt"></span>
            <?php echo esc_html($back_label); ?>
        </a>
    <?php endif; ?>

    <?php $render_link($next_id, $course_id, $user_id, 'next', __('Next', 'simple-lms-bridge')); ?>
</nav>
