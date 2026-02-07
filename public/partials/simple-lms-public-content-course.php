
<div class="wpc-content wpc-content-course<?php if (isset($atts['async'])) echo ' wpc-content-loading'; ?> wpc-content-course-<?php echo esc_attr($completion_status); ?> wpc-content-course-<?php echo esc_attr( $this->get_course_class( array( 'course' => $course ) ) ); ?>-<?php echo esc_attr($completion_status); ?>" data-type="course" data-unique-id="<?php echo esc_attr($course); ?>"<?php if ($should_hide) { echo ' style="display: none;"'; } ?>>
<?php echo do_shortcode($content); ?>
</div>
