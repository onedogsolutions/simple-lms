
<input type="text" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="<?php echo __( esc_attr( $text ), $this->plugin_name ); ?>"<?php echo (!empty($class)) ? ' class="' . esc_attr($class) . '"' : ''; ?><?php if ($disabled) echo " disabled" ?> style="width: 100%;"><?php if ($disabled) echo ' <span class="profeature"></span>' ?>

<?php if ( !empty( $last_error ) && isset( $last_error['message'] ) ) : ?>
  <strong>Last webhook error at: </strong> <?php echo $last_error['error_at']; ?>
  <pre><?php var_dump($last_error['message']); ?></pre>
<?php endif; ?>
