  
  </td>
</table>
<table style="margin-top: -20px;">
  <tbody>
    <tr>
      <td>
    <div class="simple-lms-checkbox-container">
      <input type="hidden" name="<?php echo $name; ?>" value="false">
      <label>
        <input type="checkbox" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="true" <?php checked( 'true', $is_enabled ); ?>> 
        <?php echo __($text, $this->plugin_name); ?>
      </label>
    </div>
