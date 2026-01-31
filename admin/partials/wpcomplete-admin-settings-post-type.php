  
  <select name="<?php echo esc_attr($this->plugin_name . '_post_type'); ?>[]" id="<?php echo esc_attr($this->plugin_name . '_post_type'); ?>"<?php if ($disabled) echo " disabled" ?> multiple>
    <option value="post"<?php if ( in_array( 'post', $selected_types ) ) echo ' selected="selected"'; ?>>Post Types</option>
    <option value="page"<?php if ( in_array( 'page', $selected_types ) ) echo ' selected="selected"'; ?>>Page Types</option>
    <?php foreach ( get_post_types( array( '_builtin' => false ) ) as $post_type ) { ?>
    <option value="<?php echo esc_attr($post_type); ?>"<?php if ( in_array( $post_type, $selected_types ) ) echo ' selected="selected"'; ?>><?php echo ucwords(str_replace("_", " ", $post_type)); ?> Types</option>
    <?php } ?>
  </select>
  <?php if ($disabled) echo '<span class="profeature"></span>' ?>
