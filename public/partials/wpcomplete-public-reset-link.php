<div class="wpc-reset-container">
  <a href="<?php echo esc_url($reset_url); ?>" class="wpc-reset-link <?php echo esc_attr($this->get_course_class($atts)); ?> <?php echo esc_attr($classes); ?>" data-nonce="<?php echo esc_attr($reset_nonce); ?>" data-course="<?php echo esc_attr($course); ?>" data-success-text="<?php echo esc_attr($success_text); ?>" data-failure-text="<?php echo esc_attr($failure_text); ?>" data-no-change-text="<?php echo esc_attr($no_change_text); ?>"<?php if ( !empty( $confirm_message ) ) : ?> onclick="return (confirm('<?php echo esc_js(addslashes($confirm_message)); ?>'));"<?php endif; ?>>
    <span class="wpc-inactive"><?php echo wp_kses($text, array('strong' => array(), 'em' => array(), 'b' => array(), 'i' => array())); ?></span>
    <span class="wpc-active"><?php echo get_option($this->plugin_name . '_reset_active_text', 'Resetting...'); ?></span>
  </a>
  <div class="wpc-reset-message"></div>
</div>
