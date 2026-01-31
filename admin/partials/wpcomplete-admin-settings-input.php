
<input type="text" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="<?php echo __( esc_attr($text), $this->plugin_name ); ?>"<?php echo (!empty($class)) ? ' class="' . esc_attr( $class ) . '"' : ''; ?><?php if ($disabled) echo " disabled" ?>><?php if ($disabled) echo ' <span class="profeature"></span>' ?>

