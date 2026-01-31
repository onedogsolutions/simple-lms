
<div class="wpc-content wpc-content-button<?php if (isset($atts['async'])) echo ' wpc-content-loading'; ?> wpc-content-button-<?php echo esc_attr($completion_status); ?> wpc-content-button-<?php echo esc_attr($this->get_button_class( $unique_button_id )); ?>-<?php echo esc_attr($completion_status); ?>" data-type="button" data-unique-id="<?php echo esc_attr($unique_button_id); ?>"<?php if ($should_hide) { echo ' style="display: none;"'; } ?>>
<?php echo do_shortcode($content); ?>
</div>
