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

/**
 * @var object $settings
 * @var string $id
 * @var object $module
 */

$post = get_post();

if (!$post || 'slms_lesson' !== $post->post_type) {
    if (\FLBuilderModel::is_builder_active()) {
        echo '<div class="slms-content-placeholder">' . esc_html__('LMS Lesson Content will appear here.', 'simple-lms-bridge') . '</div>';
    }
    return;
}


?>
<div class="slms-lesson-content">
    <header class="slms-lesson-header">
        <h1 class="slms-lesson-title">
            <?php echo get_the_title(); ?>
        </h1>
    </header>

    <?php
    $lesson_type = get_post_meta($post->ID, '_slms_lesson_type', true);
    $video_id = get_post_meta($post->ID, '_lms_presto_video', true);

    if ('video' === $lesson_type && $video_id) :
        ?>
        <div class="slms-lesson-video-container">
            <?php echo do_shortcode('[presto_player id=' . absint($video_id) . ']'); ?>
        </div>
    <?php endif; ?>

    <div class="slms-lesson-body">
        <?php the_content(); ?>
    </div>

    <?php
    $quiz_form_id = get_post_meta($post->ID, '_lms_gravity_form', true);
    if ('quiz' === $lesson_type && $quiz_form_id) :
        ?>
        <div class="slms-lesson-quiz-container">
            <?php echo do_shortcode('[gravityform id="' . absint($quiz_form_id) . '" title="true" description="false" ajax="true"]'); ?>
        </div>
    <?php endif; ?>
</div>