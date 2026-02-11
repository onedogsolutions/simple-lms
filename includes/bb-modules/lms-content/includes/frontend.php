<?php
/**
 * Frontend HTML for the LMS Content module.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

$post = get_post();

if (!$post || 'lms_lesson' !== $post->post_type) {
    if (\FLBuilderModel::is_builder_active()) {
        echo '<div class="slms-content-placeholder">' . esc_html__('LMS Lesson Content will appear here.', 'simple-lms-bridge') . '</div>';
    }
    return;
}

?>
<div class="slms-lesson-content">
    <h1 class="slms-lesson-title">
        <?php echo get_the_title(); ?>
    </h1>

    <div class="slms-lesson-body">
        <?php the_content(); ?>
    </div>
</div>