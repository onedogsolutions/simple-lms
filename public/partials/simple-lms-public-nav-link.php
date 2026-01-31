<div class="wpc-nav <?php echo esc_attr($class); ?>">
  <a href="<?php echo esc_url($post_url); ?>">
    <?php echo html_entity_decode($prepend); ?><?php echo wp_kses($post_title, array('strong' => array(), 'em' => array(), 'b' => array(), 'i' => array())); ?><?php echo html_entity_decode($append); ?>
  </a>
</div>
