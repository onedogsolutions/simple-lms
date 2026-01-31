
<div class="wpc-content wpc-content-page<?php if (isset($atts['async'])) echo ' wpc-content-loading'; ?> wpc-content-page-<?php echo esc_attr($completion_status); ?> wpc-content-page-<?php echo esc_attr($post_id); ?>-<?php echo esc_attr($completion_status); ?>" data-type="page" data-unique-id="<?php echo esc_attr($post_id); ?>"<?php if ($should_hide) { echo ' style="display: none;"'; } ?>>
<?php echo do_shortcode($content); ?>
</div>
